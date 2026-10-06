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
                <div>
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
            </div>

            <div class="card-footer bg-transparent d-flex justify-content-end p-3">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-check-lg me-1"></i> Guardar cambios
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
