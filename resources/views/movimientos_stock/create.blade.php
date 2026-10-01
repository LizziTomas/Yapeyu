<x-app-layout>
    <div class="row justify-content-center">
        <div class="col-lg-7 col-md-9">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h1 class="h4 mb-0 fw-semibold">Registrar Ingreso de Mercadería</h1>
                    <p class="text-muted small mb-0">Aumenta el stock disponible y genera el registro en el historial</p>
                </div>
                <a href="{{ route('movimientos-stock.index') }}" class="btn btn-outline-secondary btn-sm">
                    Volver al Historial
                </a>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('movimientos-stock.store') }}">
                        @csrf

                        <!-- Producto -->
                        <div class="mb-3">
                            <label for="producto_id" class="form-label">Producto <span class="text-danger">*</span></label>
                            <select name="producto_id" 
                                    id="producto_id" 
                                    class="form-select form-select-sm @error('producto_id') is-invalid @enderror" 
                                    required 
                                    autofocus>
                                <option value="">-- Seleccionar producto activo --</option>
                                @foreach ($productos as $producto)
                                    <option value="{{ $producto->id }}" 
                                        {{ old('producto_id', $productoSeleccionadoId) == $producto->id ? 'selected' : '' }}>
                                        {{ $producto->nombre }} (Stock actual: {{ $producto->stock }})
                                    </option>
                                @endforeach
                            </select>
                            @error('producto_id')
                                <div class="invalid-feedback small">{{ $message }}</div>
                            @enderror
                            <div class="form-text small text-muted">
                                Solo se muestran productos actualmente activos en el catálogo.
                            </div>
                        </div>

                        <!-- Cantidad -->
                        <div class="mb-3">
                            <label for="cantidad" class="form-label">Cantidad a ingresar <span class="text-danger">*</span></label>
                            <input type="number" 
                                   name="cantidad" 
                                   id="cantidad" 
                                   step="1" 
                                   min="1" 
                                   class="form-control form-control-sm @error('cantidad') is-invalid @enderror" 
                                   value="{{ old('cantidad') }}" 
                                   placeholder="Ej. 25" 
                                   required>
                            @error('cantidad')
                                <div class="invalid-feedback small">{{ $message }}</div>
                            @enderror
                            <div class="form-text small text-muted">
                                Ingrese un número entero mayor a 0.
                            </div>
                        </div>

                        <!-- Observación -->
                        <div class="mb-4">
                            <label for="observacion" class="form-label">Observación <span class="text-muted small">(opcional)</span></label>
                            <textarea name="observacion" 
                                      id="observacion" 
                                      rows="2" 
                                      class="form-control form-control-sm @error('observacion') is-invalid @enderror" 
                                      placeholder="Ej. Llegada de lote #402 / Proveedor Yacobone">{{ old('observacion') }}</textarea>
                            @error('observacion')
                                <div class="invalid-feedback small">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Botones de Acción -->
                        <div class="d-flex justify-content-end gap-2 pt-2 border-top">
                            <a href="{{ route('movimientos-stock.index') }}" class="btn btn-outline-secondary btn-sm">
                                Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary btn-sm px-3">
                                <i class="bi bi-box-arrow-in-down me-1"></i> Confirmar Ingreso
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
