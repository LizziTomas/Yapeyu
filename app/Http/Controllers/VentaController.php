<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnularVentaRequest;
use App\Http\Requests\StoreVentaRequest;
use App\Models\MovimientoStock;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Carbon\Carbon;
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

        $zonaHoraria = 'America/Argentina/Buenos_Aires';

        // Filtros comunes a ambos roles (administrador y vendedor)
        $aplicarFiltros = function ($query) use ($request, $zonaHoraria) {
            if ($request->filled('medio_pago')) {
                $query->where('medio_pago', $request->medio_pago);
            }

            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            }

            if ($request->filled('fecha')) {
                $query->whereDate('created_at', $request->fecha);
            }

            // Rango de fechas: el negocio opera en hora argentina y la base guarda UTC
            if ($request->filled('fecha_desde')) {
                $desde = Carbon::parse($request->fecha_desde, $zonaHoraria)->startOfDay()->setTimezone('UTC');
                $query->where('created_at', '>=', $desde);
            }

            if ($request->filled('fecha_hasta')) {
                $hasta = Carbon::parse($request->fecha_hasta, $zonaHoraria)->endOfDay()->setTimezone('UTC');
                $query->where('created_at', '<=', $hasta);
            }

            if ($request->filled('monto_min')) {
                $query->where('total', '>=', (float) $request->monto_min);
            }

            if ($request->filled('monto_max')) {
                $query->where('total', '<=', (float) $request->monto_max);
            }

            return $query;
        };

        if ($user->isAdmin()) {
            $query = Venta::with(['user', 'caja']);

            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            $aplicarFiltros($query);

            $ventas = $query->orderBy('created_at', 'desc')
                ->paginate(15)
                ->withQueryString();

            $usuarios = User::orderBy('name')->get();
        } else {
            $query = $user->ventas()->with('caja');

            $aplicarFiltros($query);

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
            // 1. Consolidar el payload:
            //    - $renglones: un renglón por cada combinación producto + descuento (mismo descuento se suma)
            //    - $itemsConsolidados: cantidad total pedida de cada producto, para controlar el stock
            $renglones = [];
            $itemsConsolidados = [];
            foreach ($request->validated('productos') as $item) {
                $productoId = (int) $item['id'];
                $cantidad = (int) $item['cantidad'];
                $descuento = (int) ($item['descuento'] ?? 0);
                if ($cantidad <= 0) {
                    continue;
                }
                $clave = $productoId.'-'.$descuento;
                $renglones[$clave] = [
                    'producto_id' => $productoId,
                    'descuento' => $descuento,
                    'cantidad' => ($renglones[$clave]['cantidad'] ?? 0) + $cantidad,
                ];
                $itemsConsolidados[$productoId] = ($itemsConsolidados[$productoId] ?? 0) + $cantidad;
            }

            if (empty($itemsConsolidados)) {
                throw ValidationException::withMessages([
                    'productos' => 'Debes incluir al menos un producto con cantidad válida.',
                ]);
            }

            $productosBloqueados = [];
            $detallesData = [];
            $movimientosPendientes = [];
            $totalVenta = 0.0;

            // 2. Procesar cada producto con bloqueo y verificación estricta de stock
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

                $productosBloqueados[$productoId] = $producto;

                // Descontar stock y guardar los datos para el movimiento histórico
                $stockAnterior = (int) $producto->stock;
                $stockPosterior = $stockAnterior - $cantidad;

                $producto->update(['stock' => $stockPosterior]);

                $movimientosPendientes[] = [
                    'producto_id' => $producto->id,
                    'cantidad' => $cantidad,
                    'stock_anterior' => $stockAnterior,
                    'stock_posterior' => $stockPosterior,
                ];
            }

            // 3. Armar los renglones con precios y costos reales de la base y el descuento aplicado.
            //    El subtotal se redondea según la configuración del local (con o sin centavos).
            foreach ($renglones as $renglon) {
                $producto = $productosBloqueados[$renglon['producto_id']];
                $precioUnitario = (float) $producto->precio_venta;
                $subtotal = round(
                    $precioUnitario * $renglon['cantidad'] * (100 - $renglon['descuento']) / 100,
                    configuracion()->decimales()
                );

                $detallesData[] = [
                    'producto_id' => $producto->id,
                    'cantidad' => $renglon['cantidad'],
                    'precio_unitario' => $precioUnitario,
                    'descuento_porcentaje' => $renglon['descuento'],
                    'costo_unitario' => (float) $producto->precio_costo,
                    'subtotal' => $subtotal,
                ];

                $totalVenta += $subtotal;
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

            // 6. Registrar la salida de stock por la venta (trazabilidad completa del inventario)
            foreach ($movimientosPendientes as $movimiento) {
                MovimientoStock::create([
                    'producto_id' => $movimiento['producto_id'],
                    'user_id' => $user->id,
                    'tipo' => MovimientoStock::TIPO_VENTA,
                    'cantidad' => $movimiento['cantidad'],
                    'stock_anterior' => $movimiento['stock_anterior'],
                    'stock_posterior' => $movimiento['stock_posterior'],
                    'observacion' => "Venta #{$ventaCreada->id}",
                ]);
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

    /**
     * Anula una venta completada y estorna el stock al inventario.
     *
     * El proceso se ejecuta dentro de una transacción:
     *  1. Bloquea la venta para evitar anulaciones simultáneas.
     *  2. Devuelve al stock cada producto vendido, registrando el movimiento histórico.
     *  3. Marca la venta como anulada con los datos de auditoría.
     *  4. Si la caja asociada ya estaba cerrada, recalcula su arqueo congelado.
     */
    public function anular(AnularVentaRequest $request, Venta $venta): RedirectResponse
    {
        Gate::authorize('anular', $venta);

        /** @var User $user */
        $user = Auth::user();
        $motivo = $request->validated('motivo');

        DB::transaction(function () use ($venta, $user, $motivo) {
            // 1. Bloqueo de la fila para evitar dobles anulaciones concurrentes
            $ventaBloqueada = Venta::whereKey($venta->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($ventaBloqueada->esAnulada()) {
                throw ValidationException::withMessages([
                    'motivo' => 'Esta venta ya fue anulada previamente.',
                ]);
            }

            // 2. Estorno del stock y registro del movimiento por cada renglón vendido
            foreach ($ventaBloqueada->detalles as $detalle) {
                $producto = Producto::whereKey($detalle->producto_id)
                    ->lockForUpdate()
                    ->first();

                if (! $producto) {
                    continue;
                }

                $stockAnterior = (int) $producto->stock;
                $stockPosterior = $stockAnterior + (int) $detalle->cantidad;

                $producto->update(['stock' => $stockPosterior]);

                MovimientoStock::create([
                    'producto_id' => $producto->id,
                    'user_id' => $user->id,
                    'tipo' => MovimientoStock::TIPO_ANULACION,
                    'cantidad' => $detalle->cantidad,
                    'stock_anterior' => $stockAnterior,
                    'stock_posterior' => $stockPosterior,
                    'observacion' => "Anulación de la venta #{$ventaBloqueada->id}: {$motivo}",
                ]);
            }

            // 3. Marca de anulación con datos de auditoría
            $ventaBloqueada->update([
                'estado' => 'anulada',
                'motivo_anulacion' => $motivo,
                'anulada_por' => $user->id,
                'anulada_at' => now(),
            ]);

            // 4. Si la caja ya estaba cerrada, se recalcula su arqueo congelado.
            //    El monto real contado físicamente no se modifica.
            $caja = $ventaBloqueada->caja;

            if ($caja && $caja->estaCerrada()) {
                $nuevoEsperado = round((float) $caja->monto_inicial + $caja->totalVentasEfectivo(), 2);

                $caja->update([
                    'monto_esperado' => $nuevoEsperado,
                    'diferencia' => round((float) $caja->monto_real - $nuevoEsperado, 2),
                ]);
            }
        });

        return redirect()->route('ventas.show', $venta)
            ->with('success', "Venta #{$venta->id} anulada exitosamente. El stock fue devuelto al inventario.");
    }
}
