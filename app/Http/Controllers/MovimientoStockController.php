<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDescarteStockRequest;
use App\Http\Requests\StoreIngresoStockRequest;
use App\Models\MovimientoStock;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MovimientoStockController extends Controller
{
    /**
     * Muestra el historial de movimientos de stock con filtros y paginación.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', MovimientoStock::class);

        $query = MovimientoStock::with(['producto', 'user']);

        if ($request->filled('producto_id')) {
            $query->where('producto_id', $request->producto_id);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        if ($request->filled('fecha')) {
            $query->whereDate('created_at', $request->fecha);
        }

        // Búsqueda libre por observación o nombre del producto
        if ($request->filled('search')) {
            $busqueda = $request->search;

            $query->where(function ($sub) use ($busqueda) {
                $sub->where('observacion', 'like', "%{$busqueda}%")
                    ->orWhereHas('producto', function ($producto) use ($busqueda) {
                        $producto->where('nombre', 'like', "%{$busqueda}%");
                    });
            });
        }

        $movimientos = $query->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(15)
            ->withQueryString();

        $productos = Producto::orderBy('nombre')->get();
        $usuarios = User::orderBy('name')->get();
        $tipos = MovimientoStock::etiquetas();

        return view('movimientos_stock.index', compact('movimientos', 'productos', 'usuarios', 'tipos'));
    }

    /**
     * Muestra el formulario para registrar un nuevo ingreso de mercadería.
     */
    public function create(Request $request): View
    {
        Gate::authorize('create', MovimientoStock::class);

        // Solo se permiten seleccionar productos que estén activos
        $productos = Producto::activos()->orderBy('nombre')->get();
        $productoSeleccionadoId = $request->query('producto_id');

        return view('movimientos_stock.create', compact('productos', 'productoSeleccionadoId'));
    }

    /**
     * Registra un nuevo ingreso de mercadería y actualiza el stock del producto de forma atómica.
     */
    public function store(StoreIngresoStockRequest $request): RedirectResponse
    {
        Gate::authorize('create', MovimientoStock::class);

        /** @var User $user */
        $user = Auth::user();

        $movimiento = DB::transaction(function () use ($request, $user) {
            $producto = Producto::where('id', $request->validated('producto_id'))
                ->lockForUpdate()
                ->first();

            if (! $producto) {
                throw ValidationException::withMessages([
                    'producto_id' => 'El producto seleccionado no existe.',
                ]);
            }

            if (! $producto->activo) {
                throw ValidationException::withMessages([
                    'producto_id' => 'No se puede ingresar mercadería para un producto inactivo.',
                ]);
            }

            $cantidad = (int) $request->validated('cantidad');
            $stockAnterior = (int) $producto->stock;
            $stockPosterior = $stockAnterior + $cantidad;

            // Actualizar stock del producto
            $producto->update([
                'stock' => $stockPosterior,
            ]);

            // Crear el registro inmutable del movimiento de stock
            return MovimientoStock::create([
                'producto_id' => $producto->id,
                'user_id' => $user->id,
                'tipo' => MovimientoStock::TIPO_INGRESO,
                'cantidad' => $cantidad,
                'stock_anterior' => $stockAnterior,
                'stock_posterior' => $stockPosterior,
                'observacion' => $request->validated('observacion'),
            ]);
        });

        return redirect()->route('movimientos-stock.show', $movimiento)
            ->with('success', 'Ingreso de mercadería registrado exitosamente.');
    }

    /**
     * Muestra el detalle histórico de un movimiento de stock específico.
     */
    public function show(MovimientoStock $movimientoStock): View
    {
        Gate::authorize('view', $movimientoStock);

        $movimientoStock->load(['producto', 'user']);

        return view('movimientos_stock.show', [
            'movimiento' => $movimientoStock,
        ]);
    }

    /**
     * Muestra el formulario para registrar un descarte de mercadería (merma sin venta).
     */
    public function descartarForm(): View
    {
        Gate::authorize('descartar', MovimientoStock::class);

        // Solo se pueden descartar productos activos con stock disponible
        $productos = Producto::activos()
            ->where('stock', '>', 0)
            ->orderBy('nombre')
            ->get();

        return view('movimientos_stock.descartar', compact('productos'));
    }

    /**
     * Registra un descarte de mercadería y descuenta el stock de forma atómica.
     * No genera ninguna venta ni movimiento de dinero.
     */
    public function descartarStore(StoreDescarteStockRequest $request): RedirectResponse
    {
        Gate::authorize('descartar', MovimientoStock::class);

        /** @var User $user */
        $user = Auth::user();

        $movimiento = DB::transaction(function () use ($request, $user) {
            $producto = Producto::whereKey($request->validated('producto_id'))
                ->lockForUpdate()
                ->first();

            if (! $producto) {
                throw ValidationException::withMessages([
                    'producto_id' => 'El producto seleccionado no existe.',
                ]);
            }

            if (! $producto->activo) {
                throw ValidationException::withMessages([
                    'producto_id' => 'No se puede descartar mercadería de un producto inactivo.',
                ]);
            }

            $cantidad = (int) $request->validated('cantidad');

            if ($producto->stock < $cantidad) {
                throw ValidationException::withMessages([
                    'cantidad' => "No hay stock suficiente para descartar (Disponible: {$producto->stock}, Solicitado: {$cantidad}).",
                ]);
            }

            $stockAnterior = (int) $producto->stock;
            $stockPosterior = $stockAnterior - $cantidad;

            // Actualizar stock del producto
            $producto->update([
                'stock' => $stockPosterior,
            ]);

            // Crear el registro inmutable del descarte
            return MovimientoStock::create([
                'producto_id' => $producto->id,
                'user_id' => $user->id,
                'tipo' => MovimientoStock::TIPO_DESCARTE,
                'cantidad' => $cantidad,
                'stock_anterior' => $stockAnterior,
                'stock_posterior' => $stockPosterior,
                'observacion' => $request->validated('motivo'),
            ]);
        });

        return redirect()->route('movimientos-stock.show', $movimiento)
            ->with('success', 'Descarte de mercadería registrado exitosamente.');
    }
}
