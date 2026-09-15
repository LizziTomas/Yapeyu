<x-app-layout>
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="mb-4">
                <a href="{{ route('cajas.index') }}" class="btn btn-outline-secondary btn-sm mb-2">
                    <i class="bi bi-arrow-left me-1"></i> Volver a Cajas
                </a>
                <h1 class="h4 mb-1 fw-semibold">Apertura de Caja</h1>
                <p class="text-muted small mb-0">Inicia una nueva jornada de caja especificando el monto inicial en efectivo</p>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('cajas.store') }}">
                        @csrf

                        <div class="mb-4">
                            <label for="monto_inicial" class="form-label">
                                Monto Inicial en Efectivo <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" 
                                       step="0.01" 
                                       min="0" 
                                       name="monto_inicial" 
                                       id="monto_inicial" 
                                       class="form-control @error('monto_inicial') is-invalid @enderror" 
                                       value="{{ old('monto_inicial', '0.00') }}" 
                                       required 
                                       autofocus>
                                @error('monto_inicial')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-text text-muted">
                                Ingresa la cantidad de dinero en efectivo disponible físicamente en la caja al comenzar el turno.
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('cajas.index') }}" class="btn btn-outline-secondary">
                                Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-1"></i> Confirmar Apertura
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
