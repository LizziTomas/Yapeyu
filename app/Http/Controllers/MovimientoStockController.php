<?php

namespace App\Http\Controllers;

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

        if ($request->filled('fecha')) {
            $query->whereDate('created_at', $request->fecha);
        }

        $movimientos = $query->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(15)
            ->withQueryString();

        $productos = Producto::orderBy('nombre')->get();
        $usuarios = User::orderBy('name')->get();

        return view('movimientos_stock.index', compact('movimientos', 'productos', 'usuarios'));
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
                'tipo' => 'ingreso',
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
}
