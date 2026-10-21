<x-app-layout>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">Movimientos por Producto</h1>
            <p class="text-muted small mb-0">Stock entre dos fechas: entradas, salidas y stock al inicio y al final del período</p>
        </div>
    </div>

    {{-- Vistas hermanas del módulo: listado plano y resumen agrupado por producto --}}
    <ul class="nav nav-pills gap-1 mb-3">
        <li class="nav-item">
            <a class="nav-link py-1 px-3" href="{{ route('movimientos-stock.index') }}">
                <i class="bi bi-list-ul me-1"></i> Lista de movimientos
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link active py-1 px-3" href="{{ route('movimientos-stock.por-producto') }}">
                <i class="bi bi-box-seam me-1"></i> Resumen por producto
            </a>
        </li>
    </ul>

    <!-- Filtros: búsqueda por producto y rango de fechas -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('movimientos-stock.por-producto') }}" class="row g-2 align-items-end">
                <div class="col-sm-6 col-md-4">
                    <label for="search" class="form-label small text-muted">Buscar producto</label>
                    <input type="text"
                           name="search"
                           id="search"
                           class="form-control form-control-sm"
                           placeholder="Nombre del producto..."
                           value="{{ request('search') }}">
                </div>

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
                    @if (request()->hasAny(['search', 'fecha_desde', 'fecha_hasta']))
                        <a href="{{ route('movimientos-stock.por-producto') }}" class="btn btn-outline-secondary btn-sm">
                            Limpiar
                        </a>
                    @endif
                </div>
            </form>

            @if (request()->filled('fecha_desde') || request()->filled('fecha_hasta'))
                <p class="small text-muted mb-0 mt-2">
                    <i class="bi bi-calendar-range me-1"></i>
                    Mostrando el período
                    {{ request('fecha_desde') ? \Carbon\Carbon::parse(request('fecha_desde'))->format('d/m/Y') : 'sin fecha de inicio' }}
                    —
                    {{ request('fecha_hasta') ? \Carbon\Carbon::parse(request('fecha_hasta'))->format('d/m/Y') : 'sin fecha de fin' }}.
                </p>
            @endif
        </div>
    </div>

    <!-- Tabla del resumen por producto -->
    <div class="table-responsive shadow-sm">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col" class="ps-3">Producto</th>
                    <th scope="col" class="text-center" style="width: 110px;">Movimientos</th>
                    <th scope="col" class="text-end" style="width: 100px;">Entradas</th>
                    <th scope="col" class="text-end" style="width: 100px;">Salidas</th>
                    <th scope="col" class="text-center" style="width: 120px;">Stock inicial</th>
                    <th scope="col" class="text-center" style="width: 120px;">Stock final</th>
                    <th scope="col" class="text-center" style="width: 120px;">Stock actual</th>
                    <th scope="col" style="width: 150px;">Último ingreso</th>
                    <th scope="col" class="text-end pe-3" style="width: 130px;">Acción</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($productos as $producto)
                    <tr>
                        <td class="ps-3 fw-medium">{{ $producto->nombre }}</td>
                        <td class="text-center text-muted">{{ number_format($producto->total_movimientos, 0, ',', '.') }}</td>
                        <td class="text-end fw-semibold text-success">
                            +{{ number_format($producto->entradas_periodo, 0, ',', '.') }}
                        </td>
                        <td class="text-end fw-semibold text-danger">
                            −{{ number_format($producto->salidas_periodo, 0, ',', '.') }}
                        </td>
                        <td class="text-center text-secondary">
                            {{ number_format($producto->stock_inicial_periodo, 0, ',', '.') }}
                        </td>
                        <td class="text-center fw-semibold text-primary">
                            {{ number_format($producto->stock_final_periodo, 0, ',', '.') }}
                        </td>
                        <td class="text-center fw-bold">
                            {{ number_format($producto->stock, 0, ',', '.') }}
                        </td>
                        <td class="text-muted small">
                            @if ($producto->ultimo_ingreso_at)
                                {{ $producto->ultimo_ingreso_at->copy()->setTimezone('America/Argentina/Buenos_Aires')->format('d/m/Y H:i') }}
                            @else
                                <span class="text-body-tertiary">Sin ingresos</span>
                            @endif
                        </td>
                        <td class="text-end pe-3">
                            <a href="{{ route('movimientos-stock.producto', array_filter(['producto' => $producto, 'fecha_desde' => request('fecha_desde'), 'fecha_hasta' => request('fecha_hasta')])) }}"
                               class="btn btn-outline-primary btn-sm py-0 px-2"
                               title="Ver el historial completo del producto">
                                Ver historial
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">
                            <i class="bi bi-box-seam fs-4 d-block mb-1"></i>
                            No se encontraron productos con movimientos para los filtros seleccionados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Paginación -->
    @if ($productos->hasPages())
        <div class="mt-3 d-flex justify-content-center">
            {{ $productos->links('pagination::bootstrap-5') }}
        </div>
    @endif
</x-app-layout>
