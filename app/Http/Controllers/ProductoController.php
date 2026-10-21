<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductoRequest;
use App\Http\Requests\UpdateProductoRequest;
use App\Models\Producto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProductoController extends Controller
{
    /**
     * Muestra el listado de productos activos, buscador y totales de inventario.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Producto::class);

        $search = $request->input('search');
        $estado = $request->input('estado', 'activos');

        $query = Producto::query();

        // Filtro por estado: activos (por defecto), inactivos o todos
        if ($estado === 'inactivos') {
            $query->where('activo', false);
        } elseif ($estado !== 'todos') {
            $query->activos();
        }

        if ($search) {
            $query->where('nombre', 'like', "%{$search}%");
        }

        $productos = $query->orderBy('nombre', 'asc')
            ->paginate(15)
            ->withQueryString();

        // Totales de inventario: información administrativa, reservada a los administradores.
        // El vendedor solo consulta el nombre, el precio y el stock disponible de cada producto.
        $totales = [];

        if (Auth::user()->isAdmin()) {
            $totales = [
                'total_productos' => Producto::activos()->count(),
                'total_unidades' => (int) Producto::activos()->sum('stock'),
                'total_valor_costo' => (float) (Producto::activos()->selectRaw('SUM(stock * precio_costo) as total')->value('total') ?? 0),
                'total_valor_venta' => (float) (Producto::activos()->selectRaw('SUM(stock * precio_venta) as total')->value('total') ?? 0),
            ];
        }

        return view('productos.index', compact('productos', 'totales', 'search', 'estado'));
    }

    /**
     * Muestra el formulario para crear un nuevo producto.
     */
    public function create(): View
    {
        return view('productos.create');
    }

    /**
     * Almacena un nuevo producto activo en la base de datos con stock inicial en 0.
     */
    public function store(StoreProductoRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['activo'] = true;
        $data['stock'] = 0;

        Producto::create($data);

        return redirect()->route('productos.index')->with('success', 'Producto creado exitosamente.');
    }

    /**
     * Muestra el formulario para editar un producto.
     */
    public function edit(Producto $producto): View
    {
        return view('productos.edit', compact('producto'));
    }

    /**
     * Actualiza un producto existente en la base de datos sin alterar el stock.
     */
    public function update(UpdateProductoRequest $request, Producto $producto): RedirectResponse
    {
        $producto->update($request->validated());

        return redirect()->route('productos.index')->with('success', 'Producto actualizado exitosamente.');
    }

    /**
     * Desactiva lógicamente un producto (activo = false).
     * No se elimina físicamente el registro de la base de datos.
     */
    public function destroy(Producto $producto): RedirectResponse
    {
        $producto->update(['activo' => false]);

        return redirect()->route('productos.index')->with('success', 'Producto desactivado exitosamente.');
    }
}
