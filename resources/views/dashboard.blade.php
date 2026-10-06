<x-app-layout>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-2 mb-4">
        <div>
            <h1 class="h4 mb-1 fw-semibold">Dashboard</h1>
            <p class="text-body-secondary small mb-0">Resumen operativo y comercial de {{ configuracion()->nombre_local }}</p>
        </div>
        <span class="text-body-secondary small">
            <i class="bi bi-calendar3 me-1"></i>
            {{ now('America/Argentina/Buenos_Aires')->translatedFormat('d/m/Y') }}
        </span>
    </div>

    <!-- Fila 1: Indicadores Clave (KPIs) -->
    <div class="row g-3 mb-4">
        <!-- Ventas del Día -->
        <div class="col-6 col-xl-3">
            <div class="card card-panel h-100">
                <div class="card-body p-3 p-lg-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <span class="small fw-medium text-body-secondary">Ventas de hoy</span>
                        <span class="icon-tile"><i class="bi bi-currency-dollar"></i></span>
                    </div>
                    <div class="kpi-value tabular">$ {{ monto($totalVendidoHoy) }}</div>
                    <div class="small text-body-secondary border-top pt-2 mt-3">
                        {{ $cantidadVentasHoy }} {{ $cantidadVentasHoy === 1 ? 'venta registrada' : 'ventas registradas' }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Ventas del Mes -->
        <div class="col-6 col-xl-3">
            <div class="card card-panel h-100">
                <div class="card-body p-3 p-lg-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <span class="small fw-medium text-body-secondary">Ventas del mes</span>
                        <span class="icon-tile"><i class="bi bi-graph-up-arrow"></i></span>
                    </div>
                    <div class="kpi-value tabular">$ {{ monto($totalVendidoMes) }}</div>
                    <div class="small text-body-secondary border-top pt-2 mt-3">
                        {{ $cantidadVentasMes }} {{ $cantidadVentasMes === 1 ? 'venta acumulada' : 'ventas acumuladas' }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Stock en Inventario -->
        <div class="col-6 col-xl-3">
            <div class="card card-panel h-100">
                <div class="card-body p-3 p-lg-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <span class="small fw-medium text-body-secondary">Stock total</span>
                        <span class="icon-tile"><i class="bi bi-box-seam"></i></span>
                    </div>
                    <div class="kpi-value tabular">
                        {{ number_format($totalUnidadesStock, 0, ',', '.') }} <span class="fs-6 fw-normal text-body-secondary">unidades</span>
                    </div>
                    <div class="small text-body-secondary border-top pt-2 mt-3 d-flex justify-content-between flex-wrap gap-1">
                        <span>{{ number_format($totalProductosActivos, 0, ',', '.') }} productos activos</span>
                        @if (Auth::user()->hasRole('admin'))
                            <span class="tabular">Costo: $ {{ monto($totalValorCosto) }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Alertas de Stock Bajo -->
        <div class="col-6 col-xl-3">
            <div class="card card-panel h-100 {{ $cantidadStockBajo > 0 ? 'card-alerta' : '' }}">
                <div class="card-body p-3 p-lg-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <span class="small fw-medium text-body-secondary">Stock bajo</span>
                        <span class="icon-tile {{ $cantidadStockBajo > 0 ? 'bg-danger-subtle text-danger' : '' }}"><i class="bi bi-exclamation-triangle"></i></span>
                    </div>
                    <div class="kpi-value tabular {{ $cantidadStockBajo > 0 ? 'text-danger' : '' }}">
                        {{ $cantidadStockBajo }} <span class="fs-6 fw-normal text-body-secondary">productos</span>
                    </div>
                    <div class="border-top pt-2 mt-3">
                        @if ($cantidadStockBajo > 0)
                            <span class="badge bg-danger-subtle text-danger-emphasis">Atención requerida</span>
                        @else
                            <span class="badge bg-success-subtle text-success-emphasis">Inventario óptimo</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Fila 2: Medios de Pago y Estado de Caja -->
    <div class="row g-3 mb-4">
        <!-- Medios de Pago del Día -->
        <div class="col-lg">
            <div class="card card-panel h-100">
                <div class="card-header d-flex align-items-center gap-2">
                    <span class="icon-tile icon-tile-sm"><i class="bi bi-wallet2"></i></span>
                    <h2 class="h6 mb-0 fw-semibold">Medios de pago de hoy</h2>
                </div>
                <div class="card-body p-4">
                    @php
                        // Cada medio con su monto y color, para mostrarlos en un mismo bucle
                        $medios = [
                            ['Efectivo', $efectivoHoy, 'success', 'bi-cash'],
                            ['Transferencia', $transferenciaHoy, 'primary', 'bi-bank'],
                            ['Tarjeta', $tarjetaHoy, 'info', 'bi-credit-card'],
                        ];
                    @endphp

                    @foreach ($medios as [$nombre, $monto, $color, $icono])
                        @php $porcentaje = $totalVendidoHoy > 0 ? round($monto / $totalVendidoHoy * 100) : 0; @endphp
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="d-flex align-items-center gap-2">
                                    <i class="bi {{ $icono }} text-{{ $color }}"></i> {{ $nombre }}
                                    <span class="small text-body-secondary tabular">{{ $porcentaje }}%</span>
                                </span>
                                <span class="fw-semibold tabular">$ {{ monto($monto) }}</span>
                            </div>
                            <div class="progress" role="progressbar" aria-label="{{ $nombre }}" aria-valuenow="{{ $porcentaje }}" aria-valuemin="0" aria-valuemax="100" style="height: 6px">
                                <div class="progress-bar bg-{{ $color }}" style="width: {{ $porcentaje }}%"></div>
                            </div>
                        </div>
                    @endforeach

                    <div class="d-flex justify-content-between align-items-center bg-body-tertiary rounded-3 px-3 py-2 mt-4">
                        <span class="small text-body-secondary">Total cobrado hoy</span>
                        <strong class="fs-5 tabular">$ {{ monto($totalVendidoHoy) }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estado de Caja -->
        @can('ver cajas')
            <div class="col-lg">
                <div class="card card-panel h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <span class="icon-tile icon-tile-sm"><i class="bi bi-cash-stack"></i></span>
                            <h2 class="h6 mb-0 fw-semibold">Estado de caja</h2>
                        </div>
                        @if ($cajaAbierta)
                            <span class="badge rounded-pill bg-success-subtle text-success-emphasis"><i class="bi bi-circle-fill me-1" style="font-size: 0.5rem"></i>Abierta</span>
                        @else
                            <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis">Sin Caja Abierta</span>
                        @endif
                    </div>
                    <div class="card-body p-4">
                        @if ($cajaAbierta)
                            <!-- Dato principal: el dinero que debería haber en la caja -->
                            <div class="caja-destacado rounded-3 p-3 mb-3">
                                <span class="small text-body-secondary d-block">Dinero físico esperado</span>
                                <span class="fs-3 fw-semibold tabular">$ {{ monto($cajaAbierta->dineroEsperado()) }}</span>
                            </div>
                            <div class="row g-3 mb-3 small">
                                <div class="col-4">
                                    <span class="text-body-secondary d-block">Apertura</span>
                                    <strong class="tabular">{{ $cajaAbierta->fecha_apertura->format('H:i') }} hs</strong>
                                </div>
                                <div class="col-4">
                                    <span class="text-body-secondary d-block">Monto inicial</span>
                                    <strong class="tabular">$ {{ monto($cajaAbierta->monto_inicial) }}</strong>
                                </div>
                                <div class="col-4">
                                    <span class="text-body-secondary d-block">Cobros en efectivo</span>
                                    <strong class="tabular">$ {{ monto($cajaAbierta->totalVentasEfectivo()) }}</strong>
                                </div>
                            </div>
                            <p class="small text-body-secondary mb-3">
                                <i class="bi bi-info-circle me-1"></i>
                                Solo los cobros en <strong>efectivo</strong> suman dinero físico a la caja. Los cobros por transferencia y tarjeta se acreditan electrónicamente.
                            </p>
                            <div class="d-flex gap-2">
                                <a href="{{ route('cajas.show', $cajaAbierta) }}" class="btn btn-outline-secondary btn-sm flex-fill">
                                    <i class="bi bi-eye me-1"></i> Ver Mi Caja
                                </a>
                                @can('crear ventas')
                                    <a href="{{ route('ventas.create') }}" class="btn btn-primary btn-sm flex-fill">
                                        <i class="bi bi-cart-plus me-1"></i> Nueva Venta
                                    </a>
                                @endcan
                            </div>
                        @else
                            <div class="text-center py-3">
                                <span class="icon-tile mb-3" style="width: 3rem; height: 3rem"><i class="bi bi-safe fs-5"></i></span>
                                <p class="text-body-secondary small mb-3">
                                    No tienes ninguna caja abierta en este momento. Abre una nueva caja para comenzar a registrar ventas.
                                </p>
                                @can('abrir cajas')
                                    <a href="{{ route('cajas.create') }}" class="btn btn-primary btn-sm">
                                        <i class="bi bi-plus-circle me-1"></i> Abrir Caja
                                    </a>
                                @endcan
                            </div>
                        @endif

                        <!-- Visión administrativa si hay otras cajas abiertas -->
                        @if (Auth::user()->hasRole('admin') && $cajasAbiertasNegocio->isNotEmpty() && (!$cajaAbierta || $cajasAbiertasNegocio->count() > 1))
                            <hr class="my-4">
                            <span class="small fw-semibold d-block mb-2">Otras cajas abiertas en el negocio</span>
                            <div class="list-group list-group-flush small">
                                @foreach ($cajasAbiertasNegocio as $otraCaja)
                                    @if (!$cajaAbierta || $otraCaja->id !== $cajaAbierta->id)
                                        <div class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>{{ $otraCaja->user->name }}</strong>
                                                <span class="text-body-secondary d-block small">Abierta desde las {{ $otraCaja->fecha_apertura->format('H:i') }} hs</span>
                                            </div>
                                            <div class="text-end">
                                                <span class="fw-semibold tabular">$ {{ monto($otraCaja->dineroEsperado()) }}</span>
                                                <a href="{{ route('cajas.show', $otraCaja) }}" class="btn btn-outline-secondary btn-sm py-0 px-2 ms-2">Ver</a>
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endcan
    </div>

    <!-- Fila 3: Productos con Stock Bajo -->
    <div class="card card-panel">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <span class="icon-tile icon-tile-sm {{ $cantidadStockBajo > 0 ? 'bg-danger-subtle text-danger' : '' }}"><i class="bi bi-box-arrow-in-down"></i></span>
                <h2 class="h6 mb-0 fw-semibold">Productos con stock bajo</h2>
            </div>
            @can('crear ingresos stock')
                <a href="{{ route('movimientos-stock.create') }}" class="btn btn-outline-secondary btn-sm">
                    Ingresar Stock
                </a>
            @endcan
        </div>
        <div class="card-body p-0">
            @if ($productosStockBajoLista->isNotEmpty())
                <div class="table-responsive border-0 rounded-0 bg-transparent">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr class="small">
                                <th scope="col" class="ps-4 fw-medium text-body-secondary bg-body-tertiary">Producto</th>
                                <th scope="col" class="text-center fw-medium text-body-secondary bg-body-tertiary" style="width: 110px;">Stock</th>
                                <th scope="col" class="text-center fw-medium text-body-secondary bg-body-tertiary" style="width: 110px;">Mínimo</th>
                                @can('crear ingresos stock')
                                    <th scope="col" class="text-end pe-4 bg-body-tertiary" style="width: 120px;"><span class="visually-hidden">Acción</span></th>
                                @endcan
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($productosStockBajoLista as $prodCritico)
                                <tr>
                                    <td class="ps-4 py-3">{{ $prodCritico->nombre }}</td>
                                    <td class="text-center">
                                        <span class="badge rounded-pill bg-danger-subtle text-danger-emphasis tabular">{{ $prodCritico->stock }}</span>
                                    </td>
                                    <td class="text-center text-body-secondary tabular">{{ $prodCritico->stock_minimo }}</td>
                                    @can('crear ingresos stock')
                                        <td class="text-end pe-4">
                                            <a href="{{ route('movimientos-stock.create', ['producto_id' => $prodCritico->id]) }}"
                                               class="btn btn-outline-success btn-sm text-nowrap"
                                               title="Registrar ingreso de mercadería">
                                                <i class="bi bi-plus-lg"></i> Reponer
                                            </a>
                                        </td>
                                    @endcan
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-4 text-center text-body-secondary small">
                    <i class="bi bi-check-circle text-success fs-4 d-block mb-1"></i>
                    No hay productos en nivel crítico de stock actualmente.
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
