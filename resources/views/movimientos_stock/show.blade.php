<x-app-layout>
    <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h1 class="h4 mb-0 fw-semibold">Movimiento de Stock #{{ $movimiento->id }}</h1>
                    <span class="text-muted small">Registrado el {{ $movimiento->created_at->format('d/m/Y \a \l\a\s H:i') }} hs</span>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('movimientos-stock.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left me-1"></i> Volver al Historial
                    </a>
                    @can('crear ingresos stock')
                        <a href="{{ route('movimientos-stock.create') }}" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-lg me-1"></i> Nuevo Ingreso
                        </a>
                    @endcan
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-bottom py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-semibold">Detalle del Movimiento</span>
                        <span class="badge bg-success-subtle text-success border border-success-subtle text-uppercase px-2 py-1">
                            {{ $movimiento->tipo }}
                        </span>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3 mb-4">
                        <div class="col-sm-6">
                            <span class="text-muted small d-block">Producto</span>
                            <span class="fs-6 fw-semibold text-dark">{{ $movimiento->producto->nombre ?? 'Producto no disponible' }}</span>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted small d-block">Usuario que registró el ingreso</span>
                            <span class="fs-6 fw-medium text-dark">{{ $movimiento->user->name ?? 'Usuario no disponible' }}</span>
                        </div>
                    </div>

                    <div class="row g-3 p-3 bg-light rounded mb-4">
                        <div class="col-4 text-center border-end">
                            <span class="text-muted small d-block mb-1">Stock Anterior</span>
                            <span class="fs-5 fw-medium text-secondary">{{ number_format($movimiento->stock_anterior, 0, ',', '.') }}</span>
                        </div>
                        <div class="col-4 text-center border-end">
                            <span class="text-muted small d-block mb-1">Cantidad Ingresada</span>
                            <span class="fs-5 fw-bold text-success">+{{ number_format($movimiento->cantidad, 0, ',', '.') }}</span>
                        </div>
                        <div class="col-4 text-center">
                            <span class="text-muted small d-block mb-1">Stock Resultante</span>
                            <span class="fs-5 fw-bold text-primary">{{ number_format($movimiento->stock_posterior, 0, ',', '.') }}</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <span class="text-muted small d-block mb-1">Observación</span>
                        <div class="p-3 bg-light rounded small text-dark">
                            {{ $movimiento->observacion ?: 'Sin observaciones registradas.' }}
                        </div>
                    </div>

                    <div class="alert alert-info py-2 px-3 mb-0 small" role="alert">
                        <i class="bi bi-info-circle me-1"></i>
                        Este registro es histórico e inmutable para garantizar la trazabilidad del inventario.
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
