<x-app-layout>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">Previsualizar importación</h1>
            <p class="text-muted small mb-0">Revisá lo que se va a guardar. Todavía no se modificó ningún producto.</p>
        </div>
        <a href="{{ route('productos.importar') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Volver y corregir
        </a>
    </div>

    {{-- Resumen --}}
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3">
                    <span class="text-muted small d-block mb-1">Filas leídas</span>
                    <span class="fs-5 fw-bold">{{ number_format($resumen['total'], 0, ',', '.') }}</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3">
                    <span class="text-muted small d-block mb-1">Productos nuevos</span>
                    <span class="fs-5 fw-bold text-success">{{ number_format($resumen['nuevos'], 0, ',', '.') }}</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3">
                    <span class="text-muted small d-block mb-1">Se actualizan</span>
                    <span class="fs-5 fw-bold text-primary">{{ number_format($resumen['actualiza'] + $resumen['inactivos'], 0, ',', '.') }}</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3">
                    <span class="text-muted small d-block mb-1">Unidades iniciales</span>
                    <span class="fs-5 fw-bold text-dark">{{ number_format($resumen['unidades'], 0, ',', '.') }}</span>
                </div>
            </div>
        </div>
    </div>

    @if ($resumen['inactivos'] > 0)
        <div class="alert alert-warning py-2 px-3 mb-3 small" role="alert">
            <i class="bi bi-exclamation-triangle me-1"></i>
            {{ $resumen['inactivos'] }}
            {{ $resumen['inactivos'] === 1 ? 'producto existente está inactivo' : 'productos existentes están inactivos' }}:
            se actualizan sus precios y stock mínimo, pero {{ $resumen['inactivos'] === 1 ? 'sigue' : 'siguen' }} inactivo{{ $resumen['inactivos'] === 1 ? '' : 's' }}.
        </div>
    @endif

    <div class="table-responsive shadow-sm mb-3">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col" class="ps-3" style="width: 60px;">Fila</th>
                    <th scope="col">Producto</th>
                    <th scope="col" class="text-end" style="width: 110px;">P. Costo</th>
                    <th scope="col" class="text-end" style="width: 110px;">P. Venta</th>
                    <th scope="col" class="text-center" style="width: 90px;">Stock mín.</th>
                    <th scope="col" class="text-center" style="width: 100px;">Stock inicial</th>
                    <th scope="col" style="width: 120px;">Estado</th>
                    <th scope="col" style="width: 260px;">Qué va a pasar</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($filas as $fila)
                    <tr>
                        <td class="ps-3 text-muted small">{{ $fila['linea'] }}</td>
                        <td class="fw-medium">{{ $fila['nombre'] }}</td>
                        <td class="text-end text-muted">$ {{ monto($fila['precio_costo']) }}</td>
                        <td class="text-end fw-medium">$ {{ monto($fila['precio_venta']) }}</td>
                        <td class="text-center">{{ number_format($fila['stock_minimo'], 0, ',', '.') }}</td>
                        <td class="text-center">
                            @if ($fila['estado'] === 'nuevo')
                                <span class="fw-semibold">{{ number_format($fila['stock_inicial'], 0, ',', '.') }}</span>
                            @else
                                <span class="text-muted" title="El stock de un producto existente no se modifica">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($fila['estado'] === 'nuevo')
                                <span class="badge bg-success-subtle text-success border border-success-subtle">Nuevo</span>
                            @elseif ($fila['estado'] === 'actualiza')
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Actualiza</span>
                            @else
                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">Inactivo</span>
                            @endif
                        </td>
                        <td class="small text-muted">{{ $fila['nota'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
            <div class="small text-muted">
                <i class="bi bi-shield-check me-1 text-success"></i>
                Se guarda todo junto en una sola operación. Si algo falla, no se modifica nada.
            </div>
            <form method="POST" action="{{ route('productos.importar.confirmar') }}" class="d-flex gap-2">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <a href="{{ route('productos.importar') }}" class="btn btn-outline-secondary btn-sm">
                    Cancelar
                </a>
                <button type="submit" class="btn btn-success btn-sm px-3">
                    <i class="bi bi-check-lg me-1"></i> Confirmar importación
                </button>
            </form>
        </div>
    </div>
</x-app-layout>
