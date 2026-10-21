<x-app-layout>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
        <h1 class="h4 mb-0 fw-semibold">Gestión de Productos</h1>
        <div class="d-flex gap-2">
            @can('crear ingresos stock')
                <a href="{{ route('movimientos-stock.create') }}" class="btn btn-success btn-sm">
                    <i class="bi bi-box-arrow-in-down me-1"></i> Ingreso de Stock
                </a>
            @endcan
            @can('ver movimientos stock')
                <a href="{{ route('movimientos-stock.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-clock-history me-1"></i> Historial Stock
                </a>
            @endcan
            @can('crear productos')
                <a href="{{ route('productos.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i> Nuevo Producto
                </a>
            @endcan
        </div>
    </div>

    @if (Auth::user()->isAdmin())
    <!-- Resumen de Totales del Inventario -->
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3">
                    <span class="text-muted small d-block mb-1">Productos Activos</span>
                    <span class="fs-5 fw-bold text-dark">{{ number_format($totales['total_productos'], 0, ',', '.') }}</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3">
                    <span class="text-muted small d-block mb-1">Unidades en Stock</span>
                    <span class="fs-5 fw-bold text-primary">{{ number_format($totales['total_unidades'], 0, ',', '.') }}</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3">
                    <span class="text-muted small d-block mb-1">Valor Total a Costo</span>
                    <span class="fs-5 fw-bold text-secondary">$ {{ monto($totales['total_valor_costo']) }}</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3">
                    <span class="text-muted small d-block mb-1">Valor Potencial Venta</span>
                    <span class="fs-5 fw-bold text-success">$ {{ monto($totales['total_valor_venta']) }}</span>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Buscador de Productos -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('productos.index') }}" class="row g-2 align-items-end">
                <div class="col-sm-8 col-md-5 col-lg-4">
                    <label for="search" class="form-label small text-muted">Buscar</label>
                    <input type="text"
                           id="search"
                           name="search"
                           class="form-control form-control-sm"
                           placeholder="Buscar por nombre de producto..."
                           value="{{ request('search') }}">
                </div>
                <div class="col-sm-4 col-md-3 col-lg-2">
                    <label for="estado" class="form-label small text-muted">Estado</label>
                    <select id="estado" name="estado" class="form-select form-select-sm">
                        <option value="activos" @selected($estado === 'activos')>Activos</option>
                        <option value="inactivos" @selected($estado === 'inactivos')>Inactivos</option>
                        <option value="todos" @selected($estado === 'todos')>Todos</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-secondary btn-sm">
                        <i class="bi bi-search me-1"></i> Filtrar
                    </button>
                    @if (request()->filled('search') || ($estado !== 'activos'))
                        <a href="{{ route('productos.index') }}" class="btn btn-outline-secondary btn-sm ms-1">
                            Limpiar
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla de Productos -->
    <div class="table-responsive shadow-sm">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col" class="ps-3">Nombre</th>
                    @if (Auth::user()->isAdmin())
                        <th scope="col" class="text-end" style="width: 120px;">P. Costo</th>
                    @endif
                    <th scope="col" class="text-end" style="width: 120px;">P. Venta</th>
                    <th scope="col" class="text-center" style="width: 100px;">Stock</th>
                    <th scope="col" class="text-center" style="width: 100px;">Stock Mín.</th>
                    @if (Auth::user()->isAdmin())
                        <th scope="col" class="text-end" style="width: 130px;">Valor a Costo</th>
                        <th scope="col" class="text-end" style="width: 130px;">Valor Potencial</th>
                    @endif
                    @if (Auth::user()->hasRole('admin'))
                        <th scope="col" class="text-end pe-3" style="width: 150px;">Acciones</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($productos as $producto)
                    <tr>
                        <td class="ps-3 fw-medium">
                            {{ $producto->nombre }}
                            @if ($producto->isStockBajo())
                                <span class="badge bg-warning text-dark ms-1 small" title="Stock en nivel mínimo o inferior">Stock bajo</span>
                            @endif
                        </td>
                        @if (Auth::user()->isAdmin())
                            <td class="text-end text-muted">$ {{ monto($producto->precio_costo) }}</td>
                        @endif
                        <td class="text-end fw-medium">$ {{ monto($producto->precio_venta) }}</td>
                        <td class="text-center">
                            <span class="fw-semibold {{ $producto->isStockBajo() ? 'text-danger' : 'text-dark' }}">
                                {{ $producto->stock }}
                            </span>
                        </td>
                        <td class="text-center text-muted">{{ $producto->stock_minimo }}</td>
                        @if (Auth::user()->isAdmin())
                            <td class="text-end text-muted small">$ {{ monto($producto->valor_stock_costo) }}</td>
                            <td class="text-end text-success small fw-medium">$ {{ monto($producto->valor_potencial_venta) }}</td>
                        @endif
                        @if (Auth::user()->hasRole('admin'))
                            <td class="text-end pe-3">
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="{{ route('productos.edit', $producto) }}" 
                                       class="btn btn-outline-primary btn-sm py-0 px-2" 
                                       title="Editar producto">
                                        Editar
                                    </a>
                                    <form method="POST" 
                                          action="{{ route('productos.destroy', $producto) }}" 
                                          class="d-inline"
                                          onsubmit="return confirm('¿Está seguro de desactivar el producto {{ $producto->nombre }}? Ya no aparecerá en el catálogo ni estará disponible para ventas.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" 
                                                class="btn btn-outline-danger btn-sm py-0 px-2 ms-1" 
                                                title="Desactivar producto">
                                            Desactivar
                                        </button>
                                    </form>
                                </div>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ Auth::user()->hasRole('admin') ? 8 : 4 }}" class="text-center text-muted py-4">
                            <i class="bi bi-box-seam fs-4 d-block mb-1"></i>
                            No se encontraron productos con los filtros seleccionados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Paginación -->
    @if ($productos->hasPages())
        <div class="mt-3 d-flex justify-content-center">
            {{ $productos->links('pagination::bootstrap-5') }}
        </div>
    @endif
</x-app-layout>
