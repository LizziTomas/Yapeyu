<x-app-layout>
    <div class="row justify-content-center">
        <div class="col-lg-7 col-md-9">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h1 class="h4 mb-0 fw-semibold">Registrar Descarte de Mercadería</h1>
                    <p class="text-muted small mb-0">Descuenta stock sin generar una venta (rotura, merma, vencimiento)</p>
                </div>
                <a href="{{ route('movimientos-stock.index') }}" class="btn btn-outline-secondary btn-sm">
                    Volver al Historial
                </a>
            </div>

            <div class="alert alert-warning small py-2 px-3" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                El descarte reduce el inventario y <strong>no genera ningún ingreso de dinero</strong>. Queda registrado
                de forma permanente en el historial de movimientos.
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('movimientos-stock.descartar.store') }}">
                        @csrf

                        <!-- Producto -->
                        <div class="mb-3">
                            <label for="producto_id" class="form-label">Producto <span class="text-danger">*</span></label>
                            <select name="producto_id"
                                    id="producto_id"
                                    class="form-select form-select-sm @error('producto_id') is-invalid @enderror"
                                    required
                                    autofocus>
                                <option value="">-- Seleccionar producto con stock --</option>
                                @foreach ($productos as $producto)
                                    <option value="{{ $producto->id }}"
                                        {{ old('producto_id') == $producto->id ? 'selected' : '' }}>
                                        {{ $producto->nombre }} (Stock actual: {{ $producto->stock }})
                                    </option>
                                @endforeach
                            </select>
                            @error('producto_id')
                                <div class="invalid-feedback small">{{ $message }}</div>
                            @enderror
                            <div class="form-text small text-muted">
                                Solo se muestran productos activos con stock disponible.
                            </div>
                        </div>

                        <!-- Cantidad -->
                        <div class="mb-3">
                            <label for="cantidad" class="form-label">Cantidad a descartar <span class="text-danger">*</span></label>
                            <input type="number"
                                   name="cantidad"
                                   id="cantidad"
                                   step="1"
                                   min="1"
                                   class="form-control form-control-sm @error('cantidad') is-invalid @enderror"
                                   value="{{ old('cantidad') }}"
                                   placeholder="Ej. 2"
                                   required>
                            @error('cantidad')
                                <div class="invalid-feedback small">{{ $message }}</div>
                            @enderror
                            <div class="form-text small text-muted">
                                No puede superar el stock disponible del producto.
                            </div>
                        </div>

                        <!-- Motivo -->
                        <div class="mb-4">
                            <label for="motivo" class="form-label">Motivo del descarte <span class="text-danger">*</span></label>
                            <textarea name="motivo"
                                      id="motivo"
                                      rows="3"
                                      minlength="5"
                                      maxlength="255"
                                      class="form-control form-control-sm @error('motivo') is-invalid @enderror"
                                      placeholder="Ej. Se cayó al piso y se rompió / Producto vencido"
                                      required>{{ old('motivo') }}</textarea>
                            @error('motivo')
                                <div class="invalid-feedback small">{{ $message }}</div>
                            @enderror
                            <div class="form-text small text-muted">
                                Obligatorio (mínimo 5 caracteres) para la trazabilidad de la merma.
                            </div>
                        </div>

                        <!-- Botones de Acción -->
                        <div class="d-flex justify-content-end gap-2 pt-2 border-top">
                            <a href="{{ route('movimientos-stock.index') }}" class="btn btn-outline-secondary btn-sm">
                                Cancelar
                            </a>
                            <button type="submit" class="btn btn-danger btn-sm px-3">
                                <i class="bi bi-trash3 me-1"></i> Confirmar Descarte
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
