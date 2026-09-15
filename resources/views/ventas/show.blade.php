<x-app-layout>
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <a href="{{ route('ventas.index') }}" class="btn btn-outline-secondary btn-sm mb-2">
                        <i class="bi bi-arrow-left me-1"></i> Volver a Ventas
                    </a>
                    <h1 class="h4 mb-1 fw-semibold">Detalle de Venta #{{ $venta->id }}</h1>
                    <p class="text-muted small mb-0">Comprobante de operación comercial registrada en Casa Yacobone</p>
                </div>
                <div class="d-flex gap-2">
                    <span class="badge bg-success fs-6">
                        <i class="bi bi-check-circle me-1"></i> Completada
                    </span>
                    @can('crear ventas')
                        <a href="{{ route('ventas.create') }}" class="btn btn-primary btn-sm">
                            <i class="bi bi-cart-plus me-1"></i> Nueva Venta
                        </a>
                    @endcan
                </div>
            </div>

            {{-- Ficha de Cabecera de la Venta --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h2 class="h6 mb-0 fw-semibold text-secondary">Información General de la Operación</h2>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-sm-6 col-md-3">
                            <span class="text-muted small d-block mb-1">Vendedor</span>
                            <span class="fw-medium">{{ $venta->user->name ?? 'Usuario Eliminado' }}</span>
                            <small class="text-muted d-block">{{ $venta->user->email ?? '' }}</small>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <span class="text-muted small d-block mb-1">Fecha y Hora</span>
                            <span class="fw-medium">{{ $venta->created_at->format('d/m/Y') }}</span>
                            <small class="text-muted d-block">{{ $venta->created_at->format('H:i') }} hs</small>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <span class="text-muted small d-block mb-1">Caja Asociada</span>
                            <a href="{{ route('cajas.show', $venta->caja_id) }}" class="text-decoration-none badge bg-light text-dark border p-2">
                                <i class="bi bi-cash-stack me-1"></i> Caja #{{ $venta->caja_id }}
                            </a>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <span class="text-muted small d-block mb-1">Medio de Pago</span>
                            @if ($venta->medio_pago === 'efectivo')
                                <span class="badge bg-success-subtle text-success border border-success-subtle p-2">
                                    <i class="bi bi-cash me-1"></i> Efectivo
                                </span>
                            @elseif ($venta->medio_pago === 'transferencia')
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle p-2">
                                    <i class="bi bi-arrow-left-right me-1"></i> Transferencia
                                </span>
                            @elseif ($venta->medio_pago === 'tarjeta')
                                <span class="badge bg-info-subtle text-info border border-info-subtle p-2">
                                    <i class="bi bi-credit-card me-1"></i> Tarjeta
                                </span>
                            @else
                                <span class="badge bg-secondary p-2">{{ ucfirst($venta->medio_pago) }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Tabla de Renglones Históricos --}}
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 border-bottom">
                    <h2 class="h6 mb-0 fw-semibold text-secondary">Detalle de Productos Vendidos</h2>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col" style="width: 50px;">#</th>
                                <th scope="col">Producto</th>
                                <th scope="col" class="text-center" style="width: 120px;">Cantidad</th>
                                <th scope="col" class="text-end" style="width: 160px;">Precio Unit. Histórico</th>
                                <th scope="col" class="text-end" style="width: 160px;">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($venta->detalles as $index => $detalle)
                                <tr>
                                    <td class="text-muted small">{{ $index + 1 }}</td>
                                    <td>
                                        <span class="fw-medium">{{ $detalle->producto->nombre ?? 'Producto Eliminado' }}</span>
                                    </td>
                                    <td class="text-center fw-semibold">
                                        {{ $detalle->cantidad }}
                                    </td>
                                    <td class="text-end">
                                        $ {{ number_format($detalle->precio_unitario, 2, ',', '.') }}
                                    </td>
                                    <td class="text-end fw-semibold">
                                        $ {{ number_format($detalle->subtotal, 2, ',', '.') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="4" class="text-end fw-bold fs-6">Total de la Venta:</td>
                                <td class="text-end fw-bold fs-5 text-primary">
                                    $ {{ number_format($venta->total, 2, ',', '.') }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
