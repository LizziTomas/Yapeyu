<x-app-layout>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-1 fw-semibold">Gestión de Ventas</h1>
            <p class="text-muted small mb-0">Registro y consulta de operaciones comerciales de Casa Yacobone</p>
        </div>
        @can('crear ventas')
            <a href="{{ route('ventas.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-cart-plus me-1"></i> Nueva Venta
            </a>
        @endcan
    </div>

    {{-- Filtros de búsqueda --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('ventas.index') }}" class="row g-2 align-items-center">
                @if (Auth::user()->isAdmin())
                    <div class="col-md-3">
                        <label for="user_id" class="visually-hidden">Vendedor</label>
                        <select name="user_id" id="user_id" class="form-select form-select-sm">
                            <option value="">-- Todos los vendedores --</option>
                            @foreach ($usuarios as $usr)
                                <option value="{{ $usr->id }}" {{ request('user_id') == $usr->id ? 'selected' : '' }}>
                                    {{ $usr->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="col-md-3">
                    <label for="medio_pago" class="visually-hidden">Medio de Pago</label>
                    <select name="medio_pago" id="medio_pago" class="form-select form-select-sm">
                        <option value="">-- Todos los medios de pago --</option>
                        <option value="efectivo" {{ request('medio_pago') === 'efectivo' ? 'selected' : '' }}>Efectivo</option>
                        <option value="transferencia" {{ request('medio_pago') === 'transferencia' ? 'selected' : '' }}>Transferencia</option>
                        <option value="tarjeta" {{ request('medio_pago') === 'tarjeta' ? 'selected' : '' }}>Tarjeta</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="fecha" class="visually-hidden">Fecha</label>
                    <input type="date" name="fecha" id="fecha" class="form-control form-control-sm" value="{{ request('fecha') }}" placeholder="Fecha">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-filter me-1"></i> Filtrar
                    </button>
                    @if (request()->hasAny(['user_id', 'medio_pago', 'fecha']))
                        <a href="{{ route('ventas.index') }}" class="btn btn-outline-secondary btn-sm">Limpiar</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- Tabla de Ventas --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 border-bottom">
            <h2 class="h6 mb-0 fw-semibold text-secondary">
                {{ Auth::user()->isAdmin() ? 'Listado General de Ventas' : 'Mis Ventas Realizadas' }}
            </h2>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col" style="width: 70px;">ID</th>
                        <th scope="col">Fecha y Hora</th>
                        @if (Auth::user()->isAdmin())
                            <th scope="col">Vendedor</th>
                        @endif
                        <th scope="col">Caja</th>
                        <th scope="col">Medio de Pago</th>
                        <th scope="col" class="text-end">Total</th>
                        <th scope="col" class="text-center">Estado</th>
                        <th scope="col" class="text-end" style="width: 120px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($ventas as $venta)
                        <tr>
                            <td class="fw-semibold text-muted">#{{ $venta->id }}</td>
                            <td>
                                <div>{{ $venta->created_at->format('d/m/Y') }}</div>
                                <small class="text-muted">{{ $venta->created_at->format('H:i') }} hs</small>
                            </td>
                            @if (Auth::user()->isAdmin())
                                <td>
                                    <span class="fw-medium">{{ $venta->user->name ?? 'Usuario Eliminado' }}</span>
                                    <small class="text-muted d-block">{{ $venta->user->email ?? '-' }}</small>
                                </td>
                            @endif
                            <td>
                                <a href="{{ route('cajas.show', $venta->caja_id) }}" class="text-decoration-none badge bg-light text-dark border">
                                    Caja #{{ $venta->caja_id }}
                                </a>
                            </td>
                            <td>
                                @if ($venta->medio_pago === 'efectivo')
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                        <i class="bi bi-cash me-1"></i>Efectivo
                                    </span>
                                @elseif ($venta->medio_pago === 'transferencia')
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                        <i class="bi bi-arrow-left-right me-1"></i>Transferencia
                                    </span>
                                @elseif ($venta->medio_pago === 'tarjeta')
                                    <span class="badge bg-info-subtle text-info border border-info-subtle">
                                        <i class="bi bi-credit-card me-1"></i>Tarjeta
                                    </span>
                                @else
                                    <span class="badge bg-secondary">{{ ucfirst($venta->medio_pago) }}</span>
                                @endif
                            </td>
                            <td class="text-end fw-semibold fs-6">
                                $ {{ number_format($venta->total, 2, ',', '.') }}
                            </td>
                            <td class="text-center">
                                <span class="badge bg-success">Completada</span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('ventas.show', $venta) }}" class="btn btn-outline-primary btn-sm" title="Ver Detalle">
                                    <i class="bi bi-eye"></i> Detalle
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ Auth::user()->isAdmin() ? 8 : 7 }}" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-3 d-block mb-1"></i>
                                No se encontraron registros de ventas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($ventas->hasPages())
            <div class="card-footer bg-white py-3">
                {{ $ventas->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
