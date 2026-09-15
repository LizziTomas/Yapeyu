<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVentaRequest;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class VentaController extends Controller
{
    /**
     * Muestra el listado de ventas registradas.
     * Vendedor: visualiza únicamente sus propias ventas.
     * Administrador: visualiza todas las ventas y puede filtrar.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Venta::class);

        /** @var User $user */
        $user = Auth::user();

        if ($user->isAdmin()) {
            $query = Venta::with(['user', 'caja']);

            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            if ($request->filled('medio_pago')) {
                $query->where('medio_pago', $request->medio_pago);
            }

            if ($request->filled('fecha')) {
                $query->whereDate('created_at', $request->fecha);
            }

            $ventas = $query->orderBy('created_at', 'desc')
                ->paginate(15)
                ->withQueryString();

            $usuarios = User::orderBy('name')->get();
        } else {
            $query = $user->ventas()->with('caja');

            if ($request->filled('medio_pago')) {
                $query->where('medio_pago', $request->medio_pago);
            }

            if ($request->filled('fecha')) {
                $query->whereDate('created_at', $request->fecha);
            }

            $ventas = $query->orderBy('created_at', 'desc')
                ->paginate(15)
                ->withQueryString();

            $usuarios = collect();
        }

        return view('ventas.index', compact('ventas', 'usuarios'));
    }

    /**
     * Muestra el formulario para registrar una nueva venta.
     * Requiere que el usuario autenticado tenga una caja abierta.
     */
    public function create(): View|RedirectResponse
    {
        Gate::authorize('create', Venta::class);

        /** @var User $user */
        $user = Auth::user();
        $caja = $user->cajaAbierta();

        if (! $caja) {
            return redirect()->route('cajas.index')
                ->with('error', 'Debes abrir una caja antes de poder realizar una venta.');
        }

        $productos = Producto::activos()
            ->where('stock', '>', 0)
            ->orderBy('nombre')
            ->get();

        return view('ventas.create', compact('productos', 'caja'));
    }

    /**
     * Almacena una nueva venta en la base de datos dentro de una transacción.
     * Verifica caja abierta, valida stock y toma precios/costos actuales de la base de datos.
     */
    public function store(StoreVentaRequest $request): RedirectResponse
    {
        Gate::authorize('create', Venta::class);

        /** @var User $user */
        $user = Auth::user();
        $caja = $user->cajaAbierta();

        if (! $caja) {
            return redirect()->route('cajas.index')
                ->with('error', 'Debes abrir una caja antes de poder registrar una venta.');
        }

        $venta = DB::transaction(function () use ($request, $user, $caja) {
            // 1. Consolidar productos repetidos en el payload
            $itemsConsolidados = [];
            foreach ($request->validated('productos') as $item) {
                $productoId = (int) $item['id'];
                $cantidad = (int) $item['cantidad'];
                if ($cantidad <= 0) {
                    continue;
                }
                $itemsConsolidados[$productoId] = ($itemsConsolidados[$productoId] ?? 0) + $cantidad;
            }

            if (empty($itemsConsolidados)) {
                throw ValidationException::withMessages([
                    'productos' => 'Debes incluir al menos un producto con cantidad válida.',
                ]);
            }

            $detallesData = [];
            $totalVenta = 0.0;

            // 2. Procesar cada producto con bloqueo y verificación estricta de stock y valores
            foreach ($itemsConsolidados as $productoId => $cantidad) {
                $producto = Producto::where('id', $productoId)->lockForUpdate()->first();

                if (! $producto) {
                    throw ValidationException::withMessages([
                        'productos' => "El producto con ID {$productoId} no existe.",
                    ]);
                }

                if (! $producto->activo) {
                    throw ValidationException::withMessages([
                        'productos' => "El producto '{$producto->nombre}' se encuentra inactivo y no puede venderse.",
                    ]);
                }

                if ($producto->stock < $cantidad) {
                    throw ValidationException::withMessages([
                        'productos' => "El producto '{$producto->nombre}' no tiene stock suficiente (Disponible: {$producto->stock}, Solicitado: {$cantidad}).",
                    ]);
                }

                // Precios y costos reales obtenidos directamente desde la base de datos
                $precioUnitario = (float) $producto->precio_venta;
                $costoUnitario = (float) $producto->precio_costo;
                $subtotal = round($precioUnitario * $cantidad, 2);

                $detallesData[] = [
                    'producto_id' => $producto->id,
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precioUnitario,
                    'costo_unitario' => $costoUnitario,
                    'subtotal' => $subtotal,
                ];

                $totalVenta += $subtotal;

                // 3. Descontar stock automáticamente
                $producto->decrement('stock', $cantidad);
            }

            // 4. Crear registro de venta de cabecera
            $ventaCreada = Venta::create([
                'caja_id' => $caja->id,
                'user_id' => $user->id,
                'total' => round($totalVenta, 2),
                'medio_pago' => $request->validated('medio_pago'),
                'estado' => 'completada',
            ]);

            // 5. Crear los renglones y detalles de la venta
            foreach ($detallesData as $detalle) {
                $ventaCreada->detalles()->create($detalle);
            }

            return $ventaCreada;
        });

        return redirect()->route('ventas.show', $venta)
            ->with('success', "Venta #{$venta->id} registrada exitosamente.");
    }

    /**
     * Muestra la información y el detalle completo de una venta.
     */
    public function show(Venta $venta): View
    {
        Gate::authorize('view', $venta);

        $venta->load(['user', 'caja', 'detalles.producto']);

        return view('ventas.show', compact('venta'));
    }
}
