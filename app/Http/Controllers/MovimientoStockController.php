<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDescarteStockRequest;
use App\Http\Requests\StoreIngresoStockRequest;
use App\Models\MovimientoStock;
use App\Models\Producto;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

    /**
     * Muestra el resumen agrupado por producto con el stock entre dos fechas.
     *
     * Es la vista "Resumen por producto": una fila por producto con la cantidad
     * de movimientos, las entradas, las salidas y el stock al inicio y al final
     * del período elegido. No modifica el stock ni la lógica de ventas.
     */
    public function porProducto(Request $request): View
    {
        Gate::authorize('viewAny', MovimientoStock::class);

        [$desde, $hasta] = $this->rangoDeFechas($request);
        $busqueda = $request->input('search');

        $query = Producto::query()->orderBy('nombre');

        if ($busqueda) {
            // Con una búsqueda directa se muestra el producto aunque no tenga movimientos
            $query->where('nombre', 'like', "%{$busqueda}%");
        } else {
            // Sin búsqueda se listan solo los productos con movimientos en el período
            $query->whereHas('movimientosStock', function ($movimientos) use ($desde, $hasta) {
                $this->aplicarRango($movimientos, $desde, $hasta);
            });
        }

        $productos = $query
            ->withCount(['movimientosStock as total_movimientos' => function ($movimientos) use ($desde, $hasta) {
                $this->aplicarRango($movimientos, $desde, $hasta);
            }])
            ->withSum(['movimientosStock as entradas' => function ($movimientos) use ($desde, $hasta) {
                $this->aplicarRango($movimientos, $desde, $hasta)
                    ->whereIn('tipo', [MovimientoStock::TIPO_INGRESO, MovimientoStock::TIPO_ANULACION]);
            }], 'cantidad')
            ->withSum(['movimientosStock as salidas' => function ($movimientos) use ($desde, $hasta) {
                $this->aplicarRango($movimientos, $desde, $hasta)
                    ->whereIn('tipo', [MovimientoStock::TIPO_VENTA, MovimientoStock::TIPO_DESCARTE]);
            }], 'cantidad')
            ->withMax(['movimientosStock as ultimo_ingreso' => function ($movimientos) {
                $movimientos->where('tipo', MovimientoStock::TIPO_INGRESO);
            }], 'created_at')
            ->paginate(15)
            ->withQueryString();

        // Movimientos posteriores a la fecha de corte: permiten reconstruir el stock
        // que tenía el producto al final del período sin tocar el stock actual.
        $posteriores = $this->movimientosPosteriores($productos->pluck('id'), $hasta);

        $productos->getCollection()->each(function (Producto $producto) use ($posteriores) {
            $entradas = (int) ($producto->entradas ?? 0);
            $salidas = (int) ($producto->salidas ?? 0);
            $stockFinal = (int) $producto->stock - (int) ($posteriores[$producto->id] ?? 0);

            $producto->setAttribute('entradas_periodo', $entradas);
            $producto->setAttribute('salidas_periodo', $salidas);
            $producto->setAttribute('stock_final_periodo', $stockFinal);
            $producto->setAttribute('stock_inicial_periodo', $stockFinal - ($entradas - $salidas));
            $producto->setAttribute('ultimo_ingreso_at', $producto->ultimo_ingreso ? Carbon::parse($producto->ultimo_ingreso) : null);
        });

        return view('movimientos_stock.por-producto', compact('productos'));
    }

    /**
     * Muestra el historial completo de un producto: la línea de tiempo de todos
     * sus movimientos y el stock al inicio y al final del período elegido.
     */
    public function historialProducto(Request $request, Producto $producto): View
    {
        Gate::authorize('viewAny', MovimientoStock::class);

        [$desde, $hasta] = $this->rangoDeFechas($request);

        $movimientos = $this->movimientosDelProducto($producto, $desde, $hasta)
            ->with('user')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $entradas = (int) $this->movimientosDelProducto($producto, $desde, $hasta)
            ->whereIn('tipo', [MovimientoStock::TIPO_INGRESO, MovimientoStock::TIPO_ANULACION])
            ->sum('cantidad');

        $salidas = (int) $this->movimientosDelProducto($producto, $desde, $hasta)
            ->whereIn('tipo', [MovimientoStock::TIPO_VENTA, MovimientoStock::TIPO_DESCARTE])
            ->sum('cantidad');

        $posteriores = $this->movimientosPosteriores(collect([$producto->id]), $hasta);
        $stockFinal = (int) $producto->stock - (int) ($posteriores[$producto->id] ?? 0);

        return view('movimientos_stock.producto', [
            'producto' => $producto,
            'movimientos' => $movimientos,
            'entradas' => $entradas,
            'salidas' => $salidas,
            'stockInicial' => $stockFinal - ($entradas - $salidas),
            'stockFinal' => $stockFinal,
        ]);
    }

    /**
     * Convierte los filtros de fecha (hora argentina) a los límites en UTC que
     * usa la base de datos. Devuelve [desde, hasta]; null cuando no se filtra.
     *
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function rangoDeFechas(Request $request): array
    {
        $zonaHoraria = 'America/Argentina/Buenos_Aires';

        $desde = $request->filled('fecha_desde')
            ? Carbon::parse($request->fecha_desde, $zonaHoraria)->startOfDay()->setTimezone('UTC')
            : null;

        $hasta = $request->filled('fecha_hasta')
            ? Carbon::parse($request->fecha_hasta, $zonaHoraria)->endOfDay()->setTimezone('UTC')
            : null;

        return [$desde, $hasta];
    }

    /**
     * Aplica el rango de fechas sobre una consulta de movimientos de stock
     * y devuelve la misma consulta para poder seguir encadenando métodos.
     */
    private function aplicarRango($query, ?Carbon $desde, ?Carbon $hasta)
    {
        if ($desde) {
            $query->where('created_at', '>=', $desde);
        }

        if ($hasta) {
            $query->where('created_at', '<=', $hasta);
        }

        return $query;
    }

    /**
     * Consulta base de los movimientos de un producto dentro de un rango de fechas.
     */
    private function movimientosDelProducto(Producto $producto, ?Carbon $desde, ?Carbon $hasta)
    {
        $query = $producto->movimientosStock();

        $this->aplicarRango($query, $desde, $hasta);

        return $query;
    }

    /**
     * Suma de stock neta de los movimientos posteriores a la fecha de corte.
     * Los ingresos y las anulaciones suman; las ventas y los descartes restan.
     *
     * @param  Collection<int, int>  $productoIds
     * @return array<int, int>
     */
    private function movimientosPosteriores(Collection $productoIds, ?Carbon $hasta): array
    {
        if (! $hasta || $productoIds->isEmpty()) {
            return [];
        }

        return MovimientoStock::whereIn('producto_id', $productoIds)
            ->where('created_at', '>', $hasta)
            ->selectRaw(
                'producto_id, SUM(CASE WHEN tipo IN (?, ?) THEN cantidad ELSE -cantidad END) as delta',
                [MovimientoStock::TIPO_INGRESO, MovimientoStock::TIPO_ANULACION]
            )
            ->groupBy('producto_id')
            ->pluck('delta', 'producto_id')
            ->map(fn ($delta) => (int) $delta)
            ->all();
    }
}
