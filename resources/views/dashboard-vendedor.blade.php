<x-app-layout>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-2 mb-4">
        <div>
            <h1 class="h4 mb-1 fw-semibold">Hola, {{ Auth::user()->name }}</h1>
            <p class="text-body-secondary small mb-0">Accesos rápidos para tu jornada de ventas</p>
        </div>
        <span class="text-body-secondary small">
            <i class="bi bi-calendar3 me-1"></i>
            {{ now('America/Argentina/Buenos_Aires')->translatedFormat('d/m/Y') }}
        </span>
    </div>

    @if (! $cajaAbierta)
        <div class="alert alert-warning d-flex align-items-center gap-2 py-2" role="alert">
            <i class="bi bi-exclamation-triangle"></i>
            <span class="small mb-0">No tenés una caja abierta. Abrila para poder registrar ventas.</span>
        </div>
    @endif

    <div class="row g-3">
        <!-- Acceso rápido: Nueva venta -->
        <div class="col-12 col-md-4">
            <div class="card card-panel h-100">
                <div class="card-body p-4 d-flex flex-column">
                    <span class="icon-tile mb-3"><i class="bi bi-cart-plus"></i></span>
                    <h2 class="h6 fw-semibold mb-1">Nueva venta</h2>
                    <p class="text-body-secondary small flex-grow-1">
                        Registrá una venta y el sistema descuenta el stock y suma el cobro a tu caja.
                    </p>
                    <a href="{{ route('ventas.create') }}" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-lg me-1"></i> Nueva venta
                    </a>
                </div>
            </div>
        </div>

        <!-- Acceso rápido: Mis ventas -->
        <div class="col-12 col-md-4">
            <div class="card card-panel h-100">
                <div class="card-body p-4 d-flex flex-column">
                    <span class="icon-tile mb-3"><i class="bi bi-receipt"></i></span>
                    <h2 class="h6 fw-semibold mb-1">Mis ventas</h2>
                    <p class="text-body-secondary small flex-grow-1">
                        Consultá tus ventas completadas y anuladas, y el detalle de cada comprobante.
                    </p>
                    <a href="{{ route('ventas.index') }}" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-list-ul me-1"></i> Ver mis ventas
                    </a>
                </div>
            </div>
        </div>

        <!-- Acceso rápido: Mi caja -->
        <div class="col-12 col-md-4">
            <div class="card card-panel h-100">
                <div class="card-body p-4 d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <span class="icon-tile"><i class="bi bi-cash-stack"></i></span>
                        @if ($cajaAbierta)
                            <span class="badge rounded-pill bg-success-subtle text-success-emphasis">
                                <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem"></i>Abierta
                            </span>
                        @else
                            <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis">Sin Caja Abierta</span>
                        @endif
                    </div>
                    <h2 class="h6 fw-semibold mb-1">Mi caja</h2>
                    <p class="text-body-secondary small flex-grow-1">
                        @if ($cajaAbierta)
                            Tu caja está abierta desde las
                            {{ $cajaAbierta->fecha_apertura->setTimezone('America/Argentina/Buenos_Aires')->format('H:i') }} hs.
                        @else
                            Abrí tu caja para comenzar a registrar ventas del día.
                        @endif
                    </p>
                    @if ($cajaAbierta)
                        <a href="{{ route('cajas.show', $cajaAbierta) }}" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-eye me-1"></i> Ver mi caja
                        </a>
                    @else
                        <a href="{{ route('cajas.create') }}" class="btn btn-success btn-sm">
                            <i class="bi bi-plus-circle me-1"></i> Abrir Caja
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
