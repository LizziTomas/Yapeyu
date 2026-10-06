<x-app-layout>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-4">
        <div>
            <h1 class="h4 mb-1 fw-semibold">Dashboard</h1>
            <p class="text-muted small mb-0">Resumen operativo y comercial de Casa Yacobone</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-light text-dark border px-3 py-2">
                <i class="bi bi-calendar3 me-1 text-primary"></i>
                {{ now('America/Argentina/Buenos_Aires')->translatedFormat('d/m/Y') }}
            </span>
            <span class="badge {{ Auth::user()->hasRole('admin') ? 'bg-primary' : 'bg-secondary' }} px-3 py-2">
                <i class="bi bi-person-badge me-1"></i>
                {{ ucfirst(Auth::user()->getRoleNames()->first() ?? 'Usuario') }}: {{ Auth::user()->name }}
            </span>
        </div>
    </div>

    <!-- Fila 1: Indicadores Clave (KPIs) -->
    <div class="row g-3 mb-4">
        <!-- Ventas del Día -->
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-medium text-uppercase">Ventas de Hoy</span>
                        <div class="rounded-circle bg-primary-subtle p-2 text-primary">
                            <i class="bi bi-currency-dollar fs-5"></i>
                        </div>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold text-dark mb-1">
                            $ {{ number_format($totalVendidoHoy, 0, ',', '.') }}
                        </div>
                        <span class="text-muted small">
                            <i class="bi bi-bag-check me-1"></i>{{ $cantidadVentasHoy }} {{ $cantidadVentasHoy === 1 ? 'venta registrada' : 'ventas registradas' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ventas del Mes -->
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-medium text-uppercase">Ventas del Mes</span>
                        <div class="rounded-circle bg-success-subtle p-2 text-success">
                            <i class="bi bi-graph-up-arrow fs-5"></i>
                        </div>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold text-dark mb-1">
                            $ {{ number_format($totalVendidoMes, 0, ',', '.') }}
                        </div>
                        <span class="text-muted small">
                            <i class="bi bi-receipt me-1"></i>{{ $cantidadVentasMes }} {{ $cantidadVentasMes === 1 ? 'venta acumulada' : 'ventas acumuladas' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stock en Inventario -->
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-medium text-uppercase">Stock Total</span>
                        <div class="rounded-circle bg-info-subtle p-2 text-info">
                            <i class="bi bi-box-seam fs-5"></i>
                        </div>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold text-dark mb-1">
                            {{ number_format($totalUnidadesStock, 0, ',', '.') }} <span class="fs-6 fw-normal text-muted">unidades</span>
                        </div>
                        <span class="text-muted small">
                            {{ number_format($totalProductosActivos, 0, ',', '.') }} productos activos
                            @if (Auth::user()->hasRole('admin'))
                                · Costo: $ {{ number_format($totalValorCosto, 0, ',', '.') }}
                            @endif
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alertas de Stock Bajo -->
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-medium text-uppercase">Stock Bajo</span>
                        <div class="rounded-circle {{ $cantidadStockBajo > 0 ? 'bg-danger-subtle text-danger' : 'bg-light text-muted' }} p-2">
                            <i class="bi bi-exclamation-triangle fs-5"></i>
                        </div>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold {{ $cantidadStockBajo > 0 ? 'text-danger' : 'text-dark' }} mb-1">
                            {{ $cantidadStockBajo }}
                        </div>
                        <span class="text-muted small">
                            @if ($cantidadStockBajo > 0)
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Atención requerida</span>
                            @else
                                <span class="badge bg-success-subtle text-success border border-success-subtle">Inventario óptimo</span>
                            @endif
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Fila 2: Dos Columnas de Detalle -->
    <div class="row g-3">
        <!-- Columna Izquierda: Medios de Pago y Alertas de Stock -->
        <div class="col-lg-6">
            <!-- Medios de Pago del Día -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-bottom py-3">
                    <h2 class="h6 mb-0 fw-semibold">
                        <i class="bi bi-wallet2 me-2 text-primary"></i>Medios de Pago de Hoy
                    </h2>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-4 text-center border-end">
                            <span class="text-muted small d-block mb-1">Efectivo</span>
                            <span class="fs-5 fw-bold text-success">$ {{ number_format($efectivoHoy, 0, ',', '.') }}</span>
                        </div>
                        <div class="col-4 text-center border-end">
                            <span class="text-muted small d-block mb-1">Transferencia</span>
                            <span class="fs-5 fw-bold text-primary">$ {{ number_format($transferenciaHoy, 0, ',', '.') }}</span>
                        </div>
                        <div class="col-4 text-center">
                            <span class="text-muted small d-block mb-1">Tarjeta</span>
                            <span class="fs-5 fw-bold text-info">$ {{ number_format($tarjetaHoy, 0, ',', '.') }}</span>
                        </div>
                    </div>
                    <div class="alert alert-light border py-2 px-3 mb-0 small text-muted d-flex justify-content-between">
                        <span>Total cobrado hoy:</span>
                        <strong class="text-dark">$ {{ number_format($totalVendidoHoy, 0, ',', '.') }}</strong>
                    </div>
                </div>
            </div>

            <!-- Productos con Stock Bajo -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h2 class="h6 mb-0 fw-semibold">
                        <i class="bi bi-box-arrow-in-down me-2 text-danger"></i>Productos con Stock Bajo
                    </h2>
                    @can('crear ingresos stock')
                        <a href="{{ route('movimientos-stock.create') }}" class="btn btn-outline-primary btn-sm py-0 px-2">
                            Ingresar Stock
                        </a>
                    @endcan
                </div>
                <div class="card-body p-0">
                    @if ($productosStockBajoLista->isNotEmpty())
                        <div class="table-responsive">
                            <table class="table table-hover table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col" class="ps-3">Producto</th>
                                        <th scope="col" class="text-center" style="width: 90px;">Stock</th>
                                        <th scope="col" class="text-center" style="width: 100px;">Mínimo</th>
                                        @can('crear ingresos stock')
                                            <th scope="col" class="text-end pe-3" style="width: 90px;">Acción</th>
                                        @endcan
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($productosStockBajoLista as $prodCritico)
                                        <tr>
                                            <td class="ps-3 fw-medium text-dark">
                                                {{ $prodCritico->nombre }}
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-danger">{{ $prodCritico->stock }}</span>
                                            </td>
                                            <td class="text-center text-muted small">
                                                {{ $prodCritico->stock_minimo }}
                                            </td>
                                            @can('crear ingresos stock')
                                                <td class="text-end pe-3">
                                                    <a href="{{ route('movimientos-stock.create', ['producto_id' => $prodCritico->id]) }}" 
                                                       class="btn btn-outline-success btn-sm py-0 px-2"
                                                       title="Registrar ingreso de mercadería">
                                                        + Reponer
                                                    </a>
                                                </td>
                                            @endcan
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="p-4 text-center text-muted small">
                            <i class="bi bi-check-circle text-success fs-4 d-block mb-1"></i>
                            No hay productos en nivel crítico de stock actualmente.
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Columna Derecha: Estado de Caja y Accesos Rápidos -->
        <div class="col-lg-6">
            <!-- Estado de Caja -->
            @can('ver cajas')
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                        <h2 class="h6 mb-0 fw-semibold">
                            <i class="bi bi-cash-stack me-2 text-success"></i>Estado de Caja
                        </h2>
                        @if ($cajaAbierta)
                            <span class="badge bg-success">Abierta</span>
                        @else
                            <span class="badge bg-secondary">Sin Caja Abierta</span>
                        @endif
                    </div>
                    <div class="card-body p-4">
                        @if ($cajaAbierta)
                            <div class="p-3 bg-light rounded mb-3">
                                <div class="row g-2 mb-2">
                                    <div class="col-sm-6">
                                        <span class="text-muted small d-block">Hora de apertura:</span>
                                        <strong>{{ $cajaAbierta->fecha_apertura->format('H:i') }} hs</strong>
                                    </div>
                                    <div class="col-sm-6">
                                        <span class="text-muted small d-block">Monto inicial:</span>
                                        <strong>$ {{ number_format($cajaAbierta->monto_inicial, 0, ',', '.') }}</strong>
                                    </div>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-sm-6">
                                        <span class="text-muted small d-block">Cobros en efectivo:</span>
                                        <strong class="text-success">$ {{ number_format($cajaAbierta->totalVentasEfectivo(), 0, ',', '.') }}</strong>
                                    </div>
                                    <div class="col-sm-6">
                                        <span class="text-muted small d-block">Dinero físico esperado:</span>
                                        <span class="fs-5 fw-bold text-primary">$ {{ number_format($cajaAbierta->dineroEsperado(), 0, ',', '.') }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="alert alert-info py-2 px-3 mb-3 small" role="alert">
                                <i class="bi bi-info-circle me-1"></i>
                                Solo los cobros en <strong>efectivo</strong> suman dinero físico a la caja. Los cobros por transferencia y tarjeta se acreditan electrónicamente.
                            </div>
                            <div class="d-flex gap-2">
                                <a href="{{ route('cajas.show', $cajaAbierta) }}" class="btn btn-outline-primary btn-sm flex-fill">
                                    <i class="bi bi-eye me-1"></i> Ver Mi Caja
                                </a>
                                @can('crear ventas')
                                    <a href="{{ route('ventas.create') }}" class="btn btn-primary btn-sm flex-fill">
                                        <i class="bi bi-cart-plus me-1"></i> Nueva Venta
                                    </a>
                                @endcan
                            </div>
                        @else
                            <p class="text-muted small mb-3">
                                No tienes ninguna caja abierta en este momento. Abre una nueva caja para comenzar a registrar ventas.
                            </p>
                            @can('abrir cajas')
                                <a href="{{ route('cajas.create') }}" class="btn btn-primary btn-sm">
                                    <i class="bi bi-plus-circle me-1"></i> Abrir Caja
                                </a>
                            @endcan
                        @endif

                        <!-- Visión administrativa si hay otras cajas abiertas -->
                        @if (Auth::user()->hasRole('admin') && $cajasAbiertasNegocio->isNotEmpty() && (!$cajaAbierta || $cajasAbiertasNegocio->count() > 1))
                            <hr class="my-3">
                            <span class="text-muted small fw-semibold d-block mb-2 text-uppercase">Otras Cajas Abiertas en el Negocio</span>
                            <div class="list-group list-group-flush small">
                                @foreach ($cajasAbiertasNegocio as $otraCaja)
                                    @if (!$cajaAbierta || $otraCaja->id !== $cajaAbierta->id)
                                        <div class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>{{ $otraCaja->user->name }}</strong>
                                                <span class="text-muted d-block small">Abierta desde las {{ $otraCaja->fecha_apertura->format('H:i') }} hs</span>
                                            </div>
                                            <div class="text-end">
                                                <span class="fw-bold text-primary">$ {{ number_format($otraCaja->dineroEsperado(), 0, ',', '.') }}</span>
                                                <a href="{{ route('cajas.show', $otraCaja) }}" class="btn btn-outline-secondary btn-sm py-0 px-2 ms-2">Ver</a>
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endcan

            <!-- Accesos Rápidos del Negocio -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-bottom py-3">
                    <h2 class="h6 mb-0 fw-semibold">
                        <i class="bi bi-lightning-charge me-2 text-warning"></i>Accesos Rápidos
                    </h2>
                </div>
                <div class="card-body p-3">
                    <div class="row g-2">
                        <div class="col-6">
                            <a href="{{ route('productos.index') }}" class="btn btn-outline-secondary btn-sm w-100 py-2 text-start">
                                <i class="bi bi-box-seam me-1 text-primary"></i> Catálogo de Productos
                            </a>
                        </div>
                        @can('crear ingresos stock')
                            <div class="col-6">
                                <a href="{{ route('movimientos-stock.create') }}" class="btn btn-outline-secondary btn-sm w-100 py-2 text-start">
                                    <i class="bi bi-box-arrow-in-down me-1 text-success"></i> Ingreso de Mercadería
                                </a>
                            </div>
                        @endcan
                        @can('ver ventas')
                            <div class="col-6">
                                <a href="{{ route('ventas.index') }}" class="btn btn-outline-secondary btn-sm w-100 py-2 text-start">
                                    <i class="bi bi-receipt me-1 text-info"></i> Historial de Ventas
                                </a>
                            </div>
                        @endcan
                        @can('ver movimientos stock')
                            <div class="col-6">
                                <a href="{{ route('movimientos-stock.index') }}" class="btn btn-outline-secondary btn-sm w-100 py-2 text-start">
                                    <i class="bi bi-clock-history me-1 text-secondary"></i> Historial de Stock
                                </a>
                            </div>
                        @endcan
                        @if (Auth::user()->hasRole('admin'))
                            <div class="col-12">
                                <a href="{{ route('usuarios.index') }}" class="btn btn-outline-secondary btn-sm w-100 py-2 text-start">
                                    <i class="bi bi-people me-1 text-dark"></i> Gestión de Usuarios
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
