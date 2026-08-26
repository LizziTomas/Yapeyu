<x-app-layout>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
        <h1 class="h4 mb-0 fw-semibold">Gestión de Productos</h1>
        @if (Auth::user()->isAdmin())
            <a href="{{ route('productos.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i> Nuevo Producto
            </a>
        @endif
    </div>

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
                    <span class="fs-5 fw-bold text-secondary">$ {{ number_format($totales['total_valor_costo'], 2, ',', '.') }}</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3">
                    <span class="text-muted small d-block mb-1">Valor Potencial Venta</span>
                    <span class="fs-5 fw-bold text-success">$ {{ number_format($totales['total_valor_venta'], 2, ',', '.') }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Buscador de Productos -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('productos.index') }}" class="row g-2 align-items-center">
                <div class="col-sm-8 col-md-6 col-lg-4">
                    <input type="text" 
                           name="search" 
                           class="form-control form-control-sm" 
                           placeholder="Buscar por nombre de producto..." 
                           value="{{ request('search') }}">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-secondary btn-sm">
                        Buscar
                    </button>
                    @if(request('search'))
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
                    <th scope="col" class="text-end" style="width: 120px;">P. Costo</th>
                    <th scope="col" class="text-end" style="width: 120px;">P. Venta</th>
                    <th scope="col" class="text-center" style="width: 100px;">Stock</th>
                    <th scope="col" class="text-center" style="width: 100px;">Stock Mín.</th>
                    <th scope="col" class="text-end" style="width: 130px;">Valor a Costo</th>
                    <th scope="col" class="text-end" style="width: 130px;">Valor Potencial</th>
                    @if (Auth::user()->isAdmin())
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
                        <td class="text-end text-muted">$ {{ number_format($producto->precio_costo, 2, ',', '.') }}</td>
                        <td class="text-end fw-medium">$ {{ number_format($producto->precio_venta, 2, ',', '.') }}</td>
                        <td class="text-center">
                            <span class="fw-semibold {{ $producto->isStockBajo() ? 'text-danger' : 'text-dark' }}">
                                {{ $producto->stock }}
                            </span>
                        </td>
                        <td class="text-center text-muted">{{ $producto->stock_minimo }}</td>
                        <td class="text-end text-muted small">$ {{ number_format($producto->valor_stock_costo, 2, ',', '.') }}</td>
                        <td class="text-end text-success small fw-medium">$ {{ number_format($producto->valor_potencial_venta, 2, ',', '.') }}</td>
                        @if (Auth::user()->isAdmin())
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
                        <td colspan="{{ Auth::user()->isAdmin() ? 8 : 7 }}" class="text-center text-muted py-4">
                            No se encontraron productos activos registrados.
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
