{{-- Modal de confirmación para anular una venta (requiere permiso 'anular ventas') --}}
@can('anular', $venta)
    <div class="modal fade" id="modalAnularVenta{{ $venta->id }}" tabindex="-1"
         aria-labelledby="modalAnularVentaLabel{{ $venta->id }}" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('ventas.anular', $venta) }}">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title h6 fw-semibold" id="modalAnularVentaLabel{{ $venta->id }}">
                            <i class="bi bi-x-octagon text-danger me-1"></i> Anular Venta #{{ $venta->id }}
                        </h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>

                    <div class="modal-body">
                        <div class="alert alert-warning small py-2 px-3" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>
                            Esta acción <strong>no se puede deshacer</strong>. La venta quedará registrada como
                            <strong>anulada</strong> y el stock de los productos volverá al inventario.
                        </div>

                        <ul class="list-unstyled small mb-3">
                            <li class="mb-1">
                                <i class="bi bi-currency-dollar me-1 text-muted"></i>
                                Total: <strong>$ {{ monto($venta->total) }}</strong>
                            </li>
                            <li>
                                <i class="bi bi-credit-card me-1 text-muted"></i>
                                Medio de pago: <strong>{{ ucfirst($venta->medio_pago) }}</strong>
                            </li>
                        </ul>

                        @if ($venta->caja && $venta->caja->estaCerrada())
                            <div class="alert alert-danger small py-2 px-3" role="alert">
                                <i class="bi bi-info-circle me-1"></i>
                                La caja #{{ $venta->caja_id }} de esta venta ya está cerrada. El arqueo se
                                recalculará automáticamente y el monto real contado no se modificará.
                            </div>
                        @endif

                        <div class="mb-0">
                            <label for="motivoAnulacion{{ $venta->id }}" class="form-label">
                                Motivo de la anulación <span class="text-danger">*</span>
                            </label>
                            <textarea name="motivo"
                                      id="motivoAnulacion{{ $venta->id }}"
                                      rows="3"
                                      minlength="5"
                                      maxlength="255"
                                      class="form-control form-control-sm @error('motivo') is-invalid @enderror"
                                      placeholder="Ej. Cobro duplicado / Producto devuelto por el cliente"
                                      required>{{ old('motivo') }}</textarea>
                            @error('motivo')
                                <div class="invalid-feedback small">{{ $message }}</div>
                            @enderror
                            <div class="form-text small text-muted">
                                Mínimo 5 caracteres. Queda registrado en la auditoría de la venta.
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">
                            Cancelar
                        </button>
                        <button type="submit" class="btn btn-danger btn-sm px-3">
                            <i class="bi bi-x-circle me-1"></i> Confirmar Anulación
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endcan
