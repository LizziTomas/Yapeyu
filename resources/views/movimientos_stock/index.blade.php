<x-app-layout>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">Historial de Movimientos de Stock</h1>
            <p class="text-muted small mb-0">Registro y trazabilidad de ingresos, anulaciones de venta y descartes de mercadería</p>
        </div>
        <div class="d-flex gap-2">
            @can('descartar', App\Models\MovimientoStock::class)
                <a href="{{ route('movimientos-stock.descartar') }}" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-trash3 me-1"></i> Registrar Descarte
                </a>
            @endcan
            @can('crear ingresos stock')
                <a href="{{ route('movimientos-stock.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-box-arrow-in-down me-1"></i> Registrar Ingreso
                </a>
            @endcan
        </div>
    </div>

    {{-- Vistas hermanas del módulo: listado plano y resumen agrupado por producto --}}
    <ul class="nav nav-pills gap-1 mb-3">
        <li class="nav-item">
            <a class="nav-link active py-1 px-3" href="{{ route('movimientos-stock.index') }}">
                <i class="bi bi-list-ul me-1"></i> Lista de movimientos
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link py-1 px-3" href="{{ route('movimientos-stock.por-producto') }}">
                <i class="bi bi-box-seam me-1"></i> Resumen por producto
            </a>
        </li>
    </ul>

    <!-- Filtros Simples de Búsqueda -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('movimientos-stock.index') }}" class="row g-2 align-items-end">
                <div class="col-sm-6 col-md-4">
                    <label for="search" class="form-label small text-muted">Buscar</label>
                    <input type="text"
                           name="search"
                           id="search"
                           class="form-control form-control-sm"
                           placeholder="Observación o producto..."
                           value="{{ request('search') }}">
                </div>

                <div class="col-sm-6 col-md-3">
                    <label for="producto_id" class="form-label small text-muted">Producto</label>
                    <select name="producto_id" id="producto_id" class="form-select form-select-sm">
                        <option value="">Todos los productos</option>
                        @foreach ($productos as $prod)
                            <option value="{{ $prod->id }}" {{ request('producto_id') == $prod->id ? 'selected' : '' }}>
                                {{ $prod->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-sm-6 col-md-2">
                    <label for="tipo" class="form-label small text-muted">Tipo</label>
                    <select name="tipo" id="tipo" class="form-select form-select-sm">
                        <option value="">Todos los tipos</option>
                        @foreach ($tipos as $valor => $etiqueta)
                            <option value="{{ $valor }}" {{ request('tipo') === $valor ? 'selected' : '' }}>
                                {{ $etiqueta }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-sm-6 col-md-2">
                    <label for="user_id" class="form-label small text-muted">Usuario</label>
                    <select name="user_id" id="user_id" class="form-select form-select-sm">
                        <option value="">Todos los usuarios</option>
                        @foreach ($usuarios as $usuario)
                            <option value="{{ $usuario->id }}" {{ request('user_id') == $usuario->id ? 'selected' : '' }}>
                                {{ $usuario->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-sm-6 col-md-2">
                    <label for="fecha" class="form-label small text-muted">Fecha</label>
                    <input type="date" 
                           name="fecha" 
                           id="fecha" 
                           class="form-control form-control-sm" 
                           value="{{ request('fecha') }}">
                </div>

                <div class="col-sm-6 col-md-auto d-flex gap-1">
                    <button type="submit" class="btn btn-secondary btn-sm flex-fill">
                        <i class="bi bi-search me-1"></i> Filtrar
                    </button>
                    @if (request()->hasAny(['search', 'producto_id', 'user_id', 'tipo', 'fecha']))
                        <a href="{{ route('movimientos-stock.index') }}" class="btn btn-outline-secondary btn-sm">
                            Limpiar
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla del Historial -->
    <div class="table-responsive shadow-sm">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col" class="ps-3" style="width: 140px;">Fecha y Hora</th>
                    <th scope="col">Producto</th>
                    <th scope="col" class="text-center" style="width: 110px;">Tipo</th>
                    <th scope="col" class="text-end" style="width: 110px;">Cantidad</th>
                    <th scope="col" class="text-center" style="width: 130px;">Stock Resultante</th>
                    <th scope="col" style="width: 150px;">Usuario</th>
                    <th scope="col" class="text-end pe-3" style="width: 100px;">Acción</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($movimientos as $movimiento)
                    <tr>
                        <td class="ps-3 text-muted small">
                            {{ $movimiento->created_at->setTimezone('America/Argentina/Buenos_Aires')->format('d/m/Y H:i') }}
                        </td>
                        <td class="fw-medium">
                            {{ $movimiento->producto->nombre ?? 'Producto no disponible' }}
                        </td>
                        <td class="text-center">
                            @if ($movimiento->esDescarte())
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle text-uppercase small">
                                    {{ $movimiento->tipoLabel() }}
                                </span>
                            @elseif ($movimiento->esVenta())
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle text-uppercase small">
                                    {{ $movimiento->tipoLabel() }}
                                </span>
                            @elseif ($movimiento->esAnulacion())
                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle text-uppercase small">
                                    {{ $movimiento->tipoLabel() }}
                                </span>
                            @else
                                <span class="badge bg-success-subtle text-success border border-success-subtle text-uppercase small">
                                    {{ $movimiento->tipoLabel() }}
                                </span>
                            @endif
                        </td>
                        <td class="text-end fw-semibold {{ $movimiento->sumaStock() ? 'text-success' : 'text-danger' }}">
                            {{ $movimiento->sumaStock() ? '+' : '−' }}{{ number_format($movimiento->cantidad, 0, ',', '.') }}
                        </td>
                        <td class="text-center fw-medium">
                            {{ number_format($movimiento->stock_posterior, 0, ',', '.') }}
                        </td>
                        <td class="text-muted small">
                            {{ $movimiento->user->name ?? 'Usuario no disponible' }}
                        </td>
                        <td class="text-end pe-3">
                            <a href="{{ route('movimientos-stock.show', $movimiento) }}" 
                               class="btn btn-outline-primary btn-sm py-0 px-2"
                               title="Ver detalle del movimiento">
                                Ver
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            <i class="bi bi-clock-history fs-4 d-block mb-1"></i>
                            No se encontraron movimientos de stock con los filtros seleccionados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Paginación -->
    @if ($movimientos->hasPages())
        <div class="mt-3 d-flex justify-content-center">
            {{ $movimientos->links('pagination::bootstrap-5') }}
        </div>
    @endif
</x-app-layout>
