<x-app-layout>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
        <div>
            <a href="{{ route('movimientos-stock.por-producto') }}" class="btn btn-outline-secondary btn-sm mb-2">
                <i class="bi bi-arrow-left me-1"></i> Volver al Resumen
            </a>
            <h1 class="h4 mb-1 fw-semibold">Historial de {{ $producto->nombre }}</h1>
            <p class="text-muted small mb-0">
                Stock actual: <span class="fw-semibold">{{ number_format($producto->stock, 0, ',', '.') }}</span> unidades
            </p>
        </div>
        @if ($producto->activo)
            <span class="badge rounded-pill bg-success-subtle text-success-emphasis align-self-md-end">Activo</span>
        @else
            <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis align-self-md-end">Inactivo</span>
        @endif
    </div>

    <!-- Filtro por rango de fechas -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('movimientos-stock.producto', $producto) }}" class="row g-2 align-items-end">
                <div class="col-sm-6 col-md-3">
                    <label for="fecha_desde" class="form-label small text-muted">Desde</label>
                    <input type="date"
                           name="fecha_desde"
                           id="fecha_desde"
                           class="form-control form-control-sm"
                           value="{{ request('fecha_desde') }}">
                </div>

                <div class="col-sm-6 col-md-3">
                    <label for="fecha_hasta" class="form-label small text-muted">Hasta</label>
                    <input type="date"
                           name="fecha_hasta"
                           id="fecha_hasta"
                           class="form-control form-control-sm"
                           value="{{ request('fecha_hasta') }}">
                </div>

                <div class="col-sm-6 col-md-auto d-flex gap-1">
                    <button type="submit" class="btn btn-secondary btn-sm flex-fill">
                        <i class="bi bi-search me-1"></i> Filtrar
                    </button>
                    @if (request()->hasAny(['fecha_desde', 'fecha_hasta']))
                        <a href="{{ route('movimientos-stock.producto', $producto) }}" class="btn btn-outline-secondary btn-sm">
                            Limpiar
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Resumen del período: el "stock entre 2 fechas" -->
    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <span class="text-muted small d-block mb-1">Stock al inicio</span>
                    <span class="fs-4 fw-semibold text-secondary">{{ number_format($stockInicial, 0, ',', '.') }}</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <span class="text-muted small d-block mb-1">Entradas</span>
                    <span class="fs-4 fw-semibold text-success">+{{ number_format($entradas, 0, ',', '.') }}</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <span class="text-muted small d-block mb-1">Salidas</span>
                    <span class="fs-4 fw-semibold text-danger">−{{ number_format($salidas, 0, ',', '.') }}</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <span class="text-muted small d-block mb-1">Stock al final</span>
                    <span class="fs-4 fw-bold text-primary">{{ number_format($stockFinal, 0, ',', '.') }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Línea de tiempo de movimientos -->
    <div class="table-responsive shadow-sm">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col" class="ps-3" style="width: 140px;">Fecha y Hora</th>
                    <th scope="col" class="text-center" style="width: 110px;">Tipo</th>
                    <th scope="col" class="text-end" style="width: 110px;">Cantidad</th>
                    <th scope="col" class="text-center" style="width: 130px;">Stock Resultante</th>
                    <th scope="col" style="width: 150px;">Usuario</th>
                    <th scope="col">Observación</th>
                    <th scope="col" class="text-end pe-3" style="width: 90px;">Acción</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($movimientos as $movimiento)
                    <tr>
                        <td class="ps-3 text-muted small">
                            {{ $movimiento->created_at->setTimezone('America/Argentina/Buenos_Aires')->format('d/m/Y H:i') }}
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
                        <td class="text-muted small">
                            {{ $movimiento->observacion ?: '—' }}
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
                            Este producto no tiene movimientos de stock en el período seleccionado.
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
