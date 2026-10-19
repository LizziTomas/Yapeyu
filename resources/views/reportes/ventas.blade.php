<x-app-layout>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-2 mb-4">
        <div>
            <h1 class="h4 mb-1 fw-semibold">Reporte de Ventas</h1>
            <p class="text-body-secondary small mb-0">Consolidado y detalle de operaciones comerciales de {{ configuracion()->nombre_local }}</p>
        </div>
        <span class="text-body-secondary small">
            <i class="bi bi-calendar3 me-1"></i>
            {{ now('America/Argentina/Buenos_Aires')->translatedFormat('d/m/Y') }}
        </span>
    </div>

    {{-- Alerta de errores de validación (por ejemplo, fecha hasta < fecha desde) --}}
    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show mb-4 small" role="alert">
            <div class="d-flex align-items-center gap-2 mb-1">
                <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                <strong>Por favor, revise los filtros ingresados:</strong>
            </div>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    @endif

    {{-- Filtros del Reporte --}}
    <div class="card card-panel mb-4">
        <div class="card-header py-3">
            <h2 class="h6 mb-0 fw-semibold d-flex align-items-center gap-2">
                <i class="bi bi-funnel text-primary"></i> Filtros de Período y Operaciones
            </h2>
        </div>
        <div class="card-body p-3 p-lg-4">
            <form method="GET" action="{{ route('reportes.ventas') }}" class="row g-3 align-items-end">
                <div class="col-sm-6 col-md-3">
                    <label for="fecha_desde" class="form-label small fw-medium">Fecha Desde</label>
                    <input type="date" 
                           name="fecha_desde" 
                           id="fecha_desde" 
                           class="form-control form-control-sm tabular @error('fecha_desde') is-invalid @enderror" 
                           value="{{ old('fecha_desde', $fechaDesde) }}"
                           required>
                    @error('fecha_desde')
                        <div class="invalid-feedback small">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-sm-6 col-md-3">
                    <label for="fecha_hasta" class="form-label small fw-medium">Fecha Hasta</label>
                    <input type="date" 
                           name="fecha_hasta" 
                           id="fecha_hasta" 
                           class="form-control form-control-sm tabular @error('fecha_hasta') is-invalid @enderror" 
                           value="{{ old('fecha_hasta', $fechaHasta) }}"
                           required>
                    @error('fecha_hasta')
                        <div class="invalid-feedback small">{{ $message }}</div>
                    @enderror
                </div>

                @if (Auth::user()->isAdmin())
                    <div class="col-sm-6 col-md-3">
                        <label for="user_id" class="form-label small fw-medium">Vendedor</label>
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

                <div class="col-sm-6 col-md-{{ Auth::user()->isAdmin() ? '3' : '6' }}">
                    <label for="medio_pago" class="form-label small fw-medium">Medio de Pago</label>
                    <select name="medio_pago" id="medio_pago" class="form-select form-select-sm">
                        <option value="">-- Todos los medios --</option>
                        <option value="efectivo" {{ request('medio_pago') === 'efectivo' ? 'selected' : '' }}>Efectivo</option>
                        <option value="transferencia" {{ request('medio_pago') === 'transferencia' ? 'selected' : '' }}>Transferencia</option>
                        <option value="tarjeta" {{ request('medio_pago') === 'tarjeta' ? 'selected' : '' }}>Tarjeta</option>
                    </select>
                </div>

                <div class="col-12 d-flex justify-content-end gap-2 pt-2 border-top">
                    @if (request()->hasAny(['fecha_desde', 'fecha_hasta', 'user_id', 'medio_pago']))
                        <a href="{{ route('reportes.ventas') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-x-circle me-1"></i> Reestablecer
                        </a>
                    @endif
                    <button type="submit" class="btn btn-primary btn-sm px-3">
                        <i class="bi bi-search me-1"></i> Consultar Reporte
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Resumen del Período (KPIs) --}}
    <div class="row g-3 mb-4">
        <!-- Total Facturado -->
        <div class="col-sm-6 col-xl-3">
            <div class="card card-panel h-100">
                <div class="card-body p-3 p-lg-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <span class="small fw-medium text-body-secondary text-uppercase">Total Facturado</span>
                        <span class="icon-tile"><i class="bi bi-currency-dollar"></i></span>
                    </div>
                    <div class="kpi-value tabular text-primary">$ {{ monto($totalVendido) }}</div>
                    <div class="small text-body-secondary border-top pt-2 mt-3">
                        Facturación bruta del período
                    </div>
                </div>
            </div>
        </div>

        <!-- Cantidad de Ventas -->
        <div class="col-sm-6 col-xl-3">
            <div class="card card-panel h-100">
                <div class="card-body p-3 p-lg-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <span class="small fw-medium text-body-secondary text-uppercase">Ventas Completadas</span>
                        <span class="icon-tile"><i class="bi bi-bag-check"></i></span>
                    </div>
                    <div class="kpi-value tabular">{{ number_format($cantidadVentas, 0, ',', '.') }}</div>
                    <div class="small text-body-secondary border-top pt-2 mt-3">
                        {{ $cantidadVentas === 1 ? '1 operación registrada' : "$cantidadVentas operaciones registradas" }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Ticket Promedio -->
        <div class="col-sm-6 col-xl-3">
            <div class="card card-panel h-100">
                <div class="card-body p-3 p-lg-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <span class="small fw-medium text-body-secondary text-uppercase">Promedio por Venta</span>
                        <span class="icon-tile"><i class="bi bi-calculator"></i></span>
                    </div>
                    @php
                        $ticketPromedio = $cantidadVentas > 0 ? $totalVendido / $cantidadVentas : 0;
                    @endphp
                    <div class="kpi-value tabular">$ {{ monto($ticketPromedio) }}</div>
                    <div class="small text-body-secondary border-top pt-2 mt-3">
                        Promedio por operación
                    </div>
                </div>
            </div>
        </div>

        <!-- Rango Consultado -->
        <div class="col-sm-6 col-xl-3">
            <div class="card card-panel h-100">
                <div class="card-body p-3 p-lg-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <span class="small fw-medium text-body-secondary text-uppercase">Período Consultado</span>
                        <span class="icon-tile"><i class="bi bi-calendar-range"></i></span>
                    </div>
                    <div class="fs-6 fw-semibold text-dark mb-1 tabular">
                        {{ \Carbon\Carbon::parse($fechaDesde)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($fechaHasta)->format('d/m/Y') }}
                    </div>
                    <div class="small text-body-secondary border-top pt-2 mt-3">
                        @if (Auth::user()->isAdmin() && request('user_id'))
                            Vendedor: {{ $usuarios->firstWhere('id', request('user_id'))->name ?? 'Filtrado' }}
                        @elseif (!Auth::user()->isAdmin())
                            Mis ventas realizadas
                        @else
                            Todos los vendedores
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Desglose por Medios de Pago --}}
    <div class="card card-panel mb-4">
        <div class="card-header py-3">
            <h2 class="h6 mb-0 fw-semibold d-flex align-items-center gap-2">
                <i class="bi bi-wallet2 text-success"></i> Desglose por Medio de Pago en el Período
            </h2>
        </div>
        <div class="card-body p-4">
            <div class="row g-4">
                @php
                    $medios = [
                        ['Efectivo', $totalEfectivo, 'success', 'bi-cash'],
                        ['Transferencia', $totalTransferencia, 'primary', 'bi-bank'],
                        ['Tarjeta', $totalTarjeta, 'info', 'bi-credit-card'],
                    ];
                @endphp

                @foreach ($medios as [$nombre, $montoMedio, $color, $icono])
                    @php 
                        $porcentaje = $totalVendido > 0 ? round($montoMedio / $totalVendido * 100, 1) : 0; 
                    @endphp
                    <div class="col-md-4 text-center border-end-md last-no-border">
                        <div class="d-flex justify-content-center align-items-center gap-2 mb-2">
                            <i class="bi {{ $icono }} text-{{ $color }} fs-5"></i>
                            <span class="fw-semibold text-body-secondary">{{ $nombre }}</span>
                        </div>
                        <div class="fs-4 fw-bold tabular mb-1 text-{{ $color }}">
                            $ {{ monto($montoMedio) }}
                        </div>
                        <div class="small text-body-secondary tabular mb-2">
                            {{ $porcentaje }}% del total facturado
                        </div>
                        <div class="progress" role="progressbar" aria-label="{{ $nombre }}" aria-valuenow="{{ $porcentaje }}" aria-valuemin="0" aria-valuemax="100" style="height: 6px">
                            <div class="progress-bar bg-{{ $color }}" style="width: {{ $porcentaje }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Tabla Detallada de Operaciones --}}
    <div class="card card-panel">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h2 class="h6 mb-0 fw-semibold d-flex align-items-center gap-2">
                <i class="bi bi-list-check text-primary"></i> Detalle de Operaciones Completadas
            </h2>
            <span class="badge bg-secondary-subtle text-secondary-emphasis tabular">
                {{ $ventas->total() }} {{ $ventas->total() === 1 ? 'operación' : 'operaciones' }}
            </span>
        </div>
        <div class="card-body p-0">
            @if ($ventas->isNotEmpty())
                <div class="table-responsive border-0 rounded-0 bg-transparent">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr class="small text-body-secondary">
                                <th scope="col" class="ps-4" style="width: 80px;">N° Venta</th>
                                <th scope="col" style="width: 160px;">Fecha y Hora</th>
                                @if (Auth::user()->isAdmin())
                                    <th scope="col">Vendedor</th>
                                @endif
                                <th scope="col" style="width: 100px;">Caja</th>
                                <th scope="col" style="width: 140px;">Medio de Pago</th>
                                <th scope="col" class="text-end" style="width: 130px;">Total</th>
                                <th scope="col" class="text-center" style="width: 110px;">Estado</th>
                                <th scope="col" class="text-end pe-4" style="width: 90px;">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($ventas as $venta)
                                <tr>
                                    <td class="ps-4 fw-semibold text-body-secondary tabular">
                                        #{{ $venta->id }}
                                    </td>
                                    <td>
                                        <div class="tabular fw-medium">{{ $venta->created_at->setTimezone('America/Argentina/Buenos_Aires')->format('d/m/Y') }}</div>
                                        <small class="text-body-secondary tabular">{{ $venta->created_at->setTimezone('America/Argentina/Buenos_Aires')->format('H:i') }} hs</small>
                                    </td>
                                    @if (Auth::user()->isAdmin())
                                        <td>
                                            <span class="fw-medium">{{ $venta->user->name ?? 'Usuario Eliminado' }}</span>
                                            <small class="text-body-secondary d-block">{{ $venta->user->email ?? '-' }}</small>
                                        </td>
                                    @endif
                                    <td>
                                        <a href="{{ route('cajas.show', $venta->caja_id) }}" class="badge text-decoration-none bg-light text-dark border tabular">
                                            Caja #{{ $venta->caja_id }}
                                        </a>
                                    </td>
                                    <td>
                                        @if ($venta->medio_pago === 'efectivo')
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                <i class="bi bi-cash me-1"></i> Efectivo
                                            </span>
                                        @elseif ($venta->medio_pago === 'transferencia')
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                                <i class="bi bi-bank me-1"></i> Transferencia
                                            </span>
                                        @elseif ($venta->medio_pago === 'tarjeta')
                                            <span class="badge bg-info-subtle text-info border border-info-subtle">
                                                <i class="bi bi-credit-card me-1"></i> Tarjeta
                                            </span>
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                                                {{ ucfirst($venta->medio_pago) }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-end fw-bold tabular text-dark">
                                        $ {{ monto($venta->total) }}
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-success-subtle text-success border border-success-subtle text-uppercase small">
                                            {{ $venta->estado }}
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <a href="{{ route('ventas.show', $venta) }}" 
                                           class="btn btn-outline-primary btn-sm py-0 px-2"
                                           title="Ver comprobante de venta">
                                            Ver
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Paginación conservando filtros --}}
                @if ($ventas->hasPages())
                    <div class="p-3 border-top d-flex justify-content-center">
                        {{ $ventas->links('pagination::bootstrap-5') }}
                    </div>
                @endif
            @else
                <div class="text-center py-5">
                    <span class="icon-tile mb-3" style="width: 3.5rem; height: 3.5rem"><i class="bi bi-inbox fs-4"></i></span>
                    <h3 class="h6 fw-semibold text-body-secondary mb-1">Sin operaciones encontradas</h3>
                    <p class="text-body-secondary small mb-3">
                        No se encontraron ventas completadas para el período y filtros seleccionados.
                    </p>
                    <a href="{{ route('reportes.ventas') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-repeat me-1"></i> Restablecer filtros
                    </a>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
