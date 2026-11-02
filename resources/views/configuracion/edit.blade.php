<x-app-layout>
    <div class="mb-4">
        <h1 class="h4 mb-1 fw-semibold">Configuración del local</h1>
        <p class="text-body-secondary small mb-0">Datos generales que se aplican a todo el sistema</p>
    </div>

    <div class="card card-panel" style="max-width: 640px">
        <form method="POST" action="{{ route('configuracion.update') }}">
            @csrf
            @method('PATCH')

            <div class="card-body p-4">
                <!-- Nombre del local -->
                <div class="mb-4">
                    <label for="nombre_local" class="form-label">Nombre del local</label>
                    <input type="text"
                           id="nombre_local"
                           name="nombre_local"
                           class="form-control @error('nombre_local') is-invalid @enderror"
                           value="{{ old('nombre_local', $configuracion->nombre_local) }}"
                           maxlength="100"
                           required>
                    @error('nombre_local')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">Se muestra en el menú, en el inicio de sesión y en los comprobantes.</div>
                </div>

                <!-- Uso de centavos -->
                <div class="mb-4">
                    <span class="form-label d-block">Precios y montos</span>
                    <div class="form-check form-switch">
                        {{-- El campo oculto envía 0 cuando el interruptor está apagado --}}
                        <input type="hidden" name="usa_centavos" value="0">
                        <input type="checkbox"
                               id="usa_centavos"
                               name="usa_centavos"
                               value="1"
                               class="form-check-input"
                               role="switch"
                               @checked(old('usa_centavos', $configuracion->usa_centavos))>
                        <label for="usa_centavos" class="form-check-label">Usar centavos</label>
                    </div>
                    <div class="form-text">
                        Activado: los montos se muestran y cargan con 2 decimales ($ 1.234,50).
                        Desactivado: se usan números enteros ($ 1.235).
                        Los montos ya guardados no se modifican, solo cambia cómo se muestran.
                    </div>
                </div>

                <!-- Avisos de stock bajo -->
                <div class="mt-4 pt-4 border-top">
                    <span class="form-label d-block">Avisos de stock bajo</span>
                    <div class="form-check form-switch mb-3">
                        {{-- El campo oculto envía 0 cuando el interruptor está apagado --}}
                        <input type="hidden" name="avisos_stock" value="0">
                        <input type="checkbox"
                               id="avisos_stock"
                               name="avisos_stock"
                               value="1"
                               class="form-check-input"
                               role="switch"
                               @checked(old('avisos_stock', $configuracion->avisos_stock))>
                        <label for="avisos_stock" class="form-check-label">
                            Enviar avisos por mail a los administradores
                        </label>
                    </div>

                    <div style="max-width: 220px">
                        <label for="resumen_stock_dias" class="form-label small">Resumen general cada</label>
                        <div class="input-group input-group-sm">
                            <input type="number"
                                   id="resumen_stock_dias"
                                   name="resumen_stock_dias"
                                   class="form-control @error('resumen_stock_dias') is-invalid @enderror"
                                   value="{{ old('resumen_stock_dias', $configuracion->resumen_stock_dias) }}"
                                   min="1"
                                   max="30"
                                   required>
                            <span class="input-group-text">días</span>
                        </div>
                        @error('resumen_stock_dias')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-text">
                        Cuando una venta o un descarte deja un producto en su stock mínimo, se avisa en el momento.
                        Además, cada la cantidad de días elegida se envía un resumen con todos los productos que
                        siguen por debajo del mínimo.
                        @if ($configuracion->resumen_stock_ultimo_envio)
                            <br>Último resumen enviado: {{ $configuracion->resumen_stock_ultimo_envio->format('d/m/Y') }}.
                        @endif
                    </div>
                </div>
            </div>

            <div class="card-footer bg-transparent d-flex justify-content-end p-3">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-check-lg me-1"></i> Guardar cambios
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
