<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductoRequest;
use App\Http\Requests\UpdateProductoRequest;
use App\Models\Producto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductoController extends Controller
{
    /**
     * Muestra el listado de productos activos, buscador y totales de inventario.
     */
    public function index(Request $request): View
    {
        $search = $request->input('search');

        $query = Producto::activos();

        if ($search) {
            $query->where('nombre', 'like', "%{$search}%");
        }

        $productos = $query->orderBy('nombre', 'asc')
            ->paginate(15)
            ->withQueryString();

        // Totales de inventario calculados únicamente sobre productos activos
        $totales = [
            'total_productos' => Producto::activos()->count(),
            'total_unidades' => (int) Producto::activos()->sum('stock'),
            'total_valor_costo' => (float) (Producto::activos()->selectRaw('SUM(stock * precio_costo) as total')->value('total') ?? 0),
            'total_valor_venta' => (float) (Producto::activos()->selectRaw('SUM(stock * precio_venta) as total')->value('total') ?? 0),
        ];

        return view('productos.index', compact('productos', 'totales', 'search'));
    }

    /**
     * Muestra el formulario para crear un nuevo producto.
     */
    public function create(): View
    {
        return view('productos.create');
    }

    /**
     * Almacena un nuevo producto activo en la base de datos.
     */
    public function store(StoreProductoRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['activo'] = true;

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
     * Actualiza un producto existente en la base de datos.
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
