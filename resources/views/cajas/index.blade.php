<x-app-layout>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-1 fw-semibold">Gestión de Cajas</h1>
            <p class="text-muted small mb-0">Control de apertura, arqueo y cierre de sesiones de caja</p>
        </div>
        @if (!$cajaAbierta && Auth::user()->can('abrir cajas'))
            <a href="{{ route('cajas.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-cash-stack me-1"></i> Abrir Nueva Caja
            </a>
        @endif
    </div>

    {{-- Estado de la caja actual del usuario autenticado --}}
    @if ($cajaAbierta)
        <div class="card border-success shadow-sm mb-4">
            <div class="card-body p-3 p-md-4">
                <div class="row align-items-center gy-3">
                    <div class="col-md-8">
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge bg-success me-2">Caja Abierta</span>
                            <span class="text-muted small">Iniciada el {{ $cajaAbierta->fecha_apertura->format('d/m/Y \a \l\a\s H:i') }} hs</span>
                        </div>
                        <div class="row g-3 mt-1">
                            <div class="col-sm-6 col-lg-3">
                                <span class="text-muted small d-block">Monto Inicial</span>
                                <span class="fs-6 fw-semibold text-dark">$ {{ number_format($cajaAbierta->monto_inicial, 2, ',', '.') }}</span>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <span class="text-muted small d-block">Ventas Efectivo</span>
                                <span class="fs-6 fw-semibold text-success">+ $ {{ number_format($cajaAbierta->totalVentasEfectivo(), 2, ',', '.') }}</span>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <span class="text-muted small d-block">Total Vendido</span>
                                <span class="fs-6 fw-semibold text-dark">$ {{ number_format($cajaAbierta->totalVendido(), 2, ',', '.') }}</span>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <span class="text-muted small d-block">Dinero Esperado</span>
                                <span class="fs-5 fw-bold text-primary">$ {{ number_format($cajaAbierta->dineroEsperado(), 2, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <a href="{{ route('cajas.show', $cajaAbierta) }}" class="btn btn-outline-success">
                            <i class="bi bi-eye me-1"></i> Ver / Realizar Cierre
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Filtros para Administrador --}}
    @if (Auth::user()->isAdmin())
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-3">
                <form method="GET" action="{{ route('cajas.index') }}" class="row g-2 align-items-center">
                    <div class="col-md-4">
                        <label for="user_id" class="visually-hidden">Usuario</label>
                        <select name="user_id" id="user_id" class="form-select form-select-sm">
                            <option value="">-- Todos los usuarios --</option>
                            @foreach ($usuarios as $usr)
                                <option value="{{ $usr->id }}" {{ request('user_id') == $usr->id ? 'selected' : '' }}>
                                    {{ $usr->name }} ({{ $usr->email }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="estado" class="visually-hidden">Estado</label>
                        <select name="estado" id="estado" class="form-select form-select-sm">
                            <option value="">-- Todos los estados --</option>
                            <option value="abierta" {{ request('estado') === 'abierta' ? 'selected' : '' }}>Abiertas</option>
                            <option value="cerrada" {{ request('estado') === 'cerrada' ? 'selected' : '' }}>Cerradas</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-filter me-1"></i> Filtrar
                        </button>
                        @if (request()->hasAny(['user_id', 'estado']))
                            <a href="{{ route('cajas.index') }}" class="btn btn-outline-secondary btn-sm">Limpiar</a>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Listado Histórico de Cajas --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 border-bottom">
            <h2 class="h6 mb-0 fw-semibold text-secondary">
                {{ Auth::user()->isAdmin() ? 'Historial General de Cajas' : 'Mi Historial de Cajas' }}
            </h2>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col" style="width: 70px;">ID</th>
                        @if (Auth::user()->isAdmin())
                            <th scope="col">Usuario</th>
                        @endif
                        <th scope="col">Apertura</th>
                        <th scope="col">Cierre</th>
                        <th scope="col" class="text-end">Monto Inicial</th>
                        <th scope="col" class="text-end">Monto Esperado</th>
                        <th scope="col" class="text-end">Monto Real</th>
                        <th scope="col" class="text-end">Diferencia</th>
                        <th scope="col" class="text-center">Estado</th>
                        <th scope="col" class="text-end" style="width: 120px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($cajas as $caja)
                        <tr>
                            <td class="fw-semibold text-muted">#{{ $caja->id }}</td>
                            @if (Auth::user()->isAdmin())
                                <td>
                                    <span class="fw-medium">{{ $caja->user->name ?? 'Usuario Eliminado' }}</span>
                                    <small class="text-muted d-block">{{ $caja->user->email ?? '-' }}</small>
                                </td>
                            @endif
                            <td>
                                <div>{{ $caja->fecha_apertura->format('d/m/Y') }}</div>
                                <small class="text-muted">{{ $caja->fecha_apertura->format('H:i') }} hs</small>
                            </td>
                            <td>
                                @if ($caja->fecha_cierre)
                                    <div>{{ $caja->fecha_cierre->format('d/m/Y') }}</div>
                                    <small class="text-muted">{{ $caja->fecha_cierre->format('H:i') }} hs</small>
                                @else
                                    <span class="text-muted fst-italic">En curso</span>
                                @endif
                            </td>
                            <td class="text-end">$ {{ number_format($caja->monto_inicial, 2, ',', '.') }}</td>
                            <td class="text-end">$ {{ number_format($caja->dineroEsperado(), 2, ',', '.') }}</td>
                            <td class="text-end">
                                @if ($caja->monto_real !== null)
                                    $ {{ number_format($caja->monto_real, 2, ',', '.') }}
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if ($caja->diferencia !== null)
                                    @if ($caja->diferencia == 0)
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                                            $ 0,00 (Exacto)
                                        </span>
                                    @elseif ($caja->diferencia > 0)
                                        <span class="badge bg-info-subtle text-info border border-info-subtle">
                                            +$ {{ number_format($caja->diferencia, 2, ',', '.') }} (Sobrante)
                                        </span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                            -$ {{ number_format(abs($caja->diferencia), 2, ',', '.') }} (Faltante)
                                        </span>
                                    @endif
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if ($caja->estaAbierta())
                                    <span class="badge bg-success">Abierta</span>
                                @else
                                    <span class="badge bg-secondary">Cerrada</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('cajas.show', $caja) }}" class="btn btn-outline-primary btn-sm" title="Ver Detalle">
                                    <i class="bi bi-eye"></i> Detalle
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ Auth::user()->isAdmin() ? 10 : 9 }}" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-3 d-block mb-1"></i>
                                No se encontraron registros de caja.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($cajas->hasPages())
            <div class="card-footer bg-white py-3">
                {{ $cajas->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
