<x-app-layout>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">Historial de Movimientos de Stock</h1>
            <p class="text-muted small mb-0">Registro y trazabilidad de ingresos de mercadería</p>
        </div>
        @can('crear ingresos stock')
            <a href="{{ route('movimientos-stock.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-box-arrow-in-down me-1"></i> Registrar Ingreso
            </a>
        @endcan
    </div>

    <!-- Filtros Simples de Búsqueda -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('movimientos-stock.index') }}" class="row g-2 align-items-end">
                <div class="col-sm-6 col-md-4">
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

                <div class="col-sm-6 col-md-3">
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

                <div class="col-sm-6 col-md-3">
                    <label for="fecha" class="form-label small text-muted">Fecha</label>
                    <input type="date" 
                           name="fecha" 
                           id="fecha" 
                           class="form-control form-control-sm" 
                           value="{{ request('fecha') }}">
                </div>

                <div class="col-sm-6 col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-secondary btn-sm flex-fill">
                        Filtrar
                    </button>
                    @if (request()->hasAny(['producto_id', 'user_id', 'fecha']))
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
                            {{ $movimiento->created_at->format('d/m/Y H:i') }}
                        </td>
                        <td class="fw-medium">
                            {{ $movimiento->producto->nombre ?? 'Producto no disponible' }}
                        </td>
                        <td class="text-center">
                            <span class="badge bg-success-subtle text-success border border-success-subtle text-uppercase small">
                                {{ $movimiento->tipo }}
                            </span>
                        </td>
                        <td class="text-end fw-semibold text-success">
                            +{{ number_format($movimiento->cantidad, 0, ',', '.') }}
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
                            No se encontraron movimientos de stock registrados.
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
