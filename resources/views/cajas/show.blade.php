<x-app-layout>
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <a href="{{ route('cajas.index') }}" class="btn btn-outline-secondary btn-sm mb-2">
                        <i class="bi bi-arrow-left me-1"></i> Volver a Cajas
                    </a>
                    <h1 class="h4 mb-1 fw-semibold">Detalle de Caja #{{ $caja->id }}</h1>
                    <p class="text-muted small mb-0">Información general y estado de la sesión de caja</p>
                </div>
                <div>
                    @if ($caja->estaAbierta())
                        <span class="badge bg-success fs-6">Abierta</span>
                    @else
                        <span class="badge bg-secondary fs-6">Cerrada</span>
                    @endif
                </div>
            </div>

            {{-- Ficha de Datos de la Caja --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h2 class="h6 mb-0 fw-semibold text-secondary">Datos de la Sesión</h2>
                </div>
                <div class="card-body p-4">
                    <div class="row g-4">
                        <div class="col-sm-6">
                            <span class="text-muted small d-block mb-1">Usuario Titular</span>
                            <span class="fw-medium fs-6">{{ $caja->user->name ?? 'Usuario Eliminado' }}</span>
                            <small class="text-muted d-block">{{ $caja->user->email ?? '' }}</small>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted small d-block mb-1">Estado</span>
                            @if ($caja->estaAbierta())
                                <span class="badge bg-success">Abierta</span>
                            @else
                                <span class="badge bg-secondary">Cerrada</span>
                            @endif
                        </div>

                        <div class="col-sm-6">
                            <span class="text-muted small d-block mb-1">Fecha y Hora de Apertura</span>
                            <span class="fw-medium">{{ $caja->fecha_apertura->format('d/m/Y H:i') }} hs</span>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted small d-block mb-1">Fecha y Hora de Cierre</span>
                            @if ($caja->fecha_cierre)
                                <span class="fw-medium">{{ $caja->fecha_cierre->format('d/m/Y H:i') }} hs</span>
                            @else
                                <span class="text-muted fst-italic">Sesión en curso</span>
                            @endif
                        </div>

                        <hr class="my-2 text-muted">

                        {{-- Desglose Financiero de la Caja --}}
                        <div class="col-12">
                            <h3 class="h6 fw-semibold text-secondary mb-3">Desglose de Ingresos y Ventas</h3>
                            <div class="row g-3">
                                <div class="col-sm-6 col-md-4">
                                    <span class="text-muted small d-block mb-1">Monto Inicial en Efectivo</span>
                                    <span class="fs-5 fw-semibold text-dark">$ {{ number_format($caja->monto_inicial, 2, ',', '.') }}</span>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <span class="text-muted small d-block mb-1">Ventas en Efectivo</span>
                                    <span class="fs-5 fw-semibold text-success">+ $ {{ number_format($caja->totalVentasEfectivo(), 2, ',', '.') }}</span>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <span class="text-muted small d-block mb-1">Ventas por Transferencia</span>
                                    <span class="fs-5 fw-semibold text-primary">$ {{ number_format($caja->totalVentasTransferencia(), 2, ',', '.') }}</span>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <span class="text-muted small d-block mb-1">Ventas con Tarjeta</span>
                                    <span class="fs-5 fw-semibold text-info">$ {{ number_format($caja->totalVentasTarjeta(), 2, ',', '.') }}</span>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <span class="text-muted small d-block mb-1">Total Vendido (Todos los medios)</span>
                                    <span class="fs-5 fw-bold text-dark">$ {{ number_format($caja->totalVendido(), 2, ',', '.') }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 mt-3">
                            <div class="p-3 rounded-3 bg-primary-subtle border border-primary-subtle">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="fw-bold fs-6 text-primary d-block">Dinero Físico Esperado en Caja:</span>
                                        <small class="text-muted">Monto Inicial ($ {{ number_format($caja->monto_inicial, 2, ',', '.') }}) + Ventas en Efectivo ($ {{ number_format($caja->totalVentasEfectivo(), 2, ',', '.') }})</small>
                                    </div>
                                    <div class="text-end">
                                        <span class="fs-4 fw-bold text-primary">$ {{ number_format($caja->dineroEsperado(), 2, ',', '.') }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if ($caja->estaCerrada())
                            <div class="col-sm-6 col-md-6 mt-3">
                                <span class="text-muted small d-block mb-1">Monto Real Físico Contado</span>
                                <span class="fs-5 fw-semibold text-dark">$ {{ number_format($caja->monto_real, 2, ',', '.') }}</span>
                            </div>

                            <div class="col-12 mt-2">
                                <div class="p-3 rounded-3 {{ $caja->diferencia == 0 ? 'bg-success-subtle border border-success' : ($caja->diferencia > 0 ? 'bg-info-subtle border border-info' : 'bg-danger-subtle border border-danger') }}">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="fw-medium d-block">Resultado del Arqueo / Cierre:</span>
                                            <small class="text-muted">Diferencia calculada respecto al dinero físico esperado</small>
                                        </div>
                                        <div class="text-end">
                                            @if ($caja->diferencia == 0)
                                                <span class="fs-5 fw-bold text-success">$ 0,00 (Exacto)</span>
                                            @elseif ($caja->diferencia > 0)
                                                <span class="fs-5 fw-bold text-info">+$ {{ number_format($caja->diferencia, 2, ',', '.') }} (Sobrante)</span>
                                            @else
                                                <span class="fs-5 fw-bold text-danger">-$ {{ number_format(abs($caja->diferencia), 2, ',', '.') }} (Faltante)</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Formulario de Cierre si la caja está abierta y pertenece al usuario actual --}}
            @if ($caja->estaAbierta())
                @if (Auth::id() === $caja->user_id)
                    <div class="card border-warning shadow-sm">
                        <div class="card-header bg-warning-subtle py-3 border-bottom border-warning-subtle">
                            <h2 class="h6 mb-0 fw-semibold text-dark">
                                <i class="bi bi-lock me-1"></i> Arqueo y Cierre de Caja
                            </h2>
                        </div>
                        <div class="card-body p-4">
                            <p class="text-muted small mb-3">
                                Realiza el recuento físico del dinero en efectivo y registra el monto total para cerrar la caja.
                            </p>

                            <form method="POST" action="{{ route('cajas.cerrar', $caja) }}" id="formCerrarCaja" onsubmit="return confirm('¿Estás seguro de que deseas cerrar esta caja? Esta acción no se puede deshacer.');">
                                @csrf

                                <div class="mb-3">
                                    <label for="monto_real" class="form-label">
                                        Monto Real Físico en Efectivo <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" 
                                               step="0.01" 
                                               min="0" 
                                               name="monto_real" 
                                               id="monto_real" 
                                               class="form-control @error('monto_real') is-invalid @enderror" 
                                               value="{{ old('monto_real') }}" 
                                               placeholder="0.00" 
                                               required>
                                        @error('monto_real')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="form-text text-muted">
                                        Monto total contado físicamente al finalizar el turno.
                                    </div>
                                </div>

                                {{-- Indicador en tiempo real de diferencia (informativo vía JavaScript) --}}
                                <div id="contenedorDiferencia" class="alert alert-light border py-2 px-3 mb-3 d-none">
                                    <div class="d-flex justify-content-between align-items-center small">
                                        <span>Diferencia estimada:</span>
                                        <span id="textoDiferencia" class="fw-bold"></span>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end gap-2 mt-4">
                                    <button type="submit" class="btn btn-danger">
                                        <i class="bi bi-lock-fill me-1"></i> Confirmar Cierre de Caja
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    {{-- Script interactivo para previsualizar diferencia en el cliente --}}
                    <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            const montoEsperado = {{ (float) $caja->dineroEsperado() }};
                            const inputReal = document.getElementById('monto_real');
                            const contenedor = document.getElementById('contenedorDiferencia');
                            const textoDiferencia = document.getElementById('textoDiferencia');

                            function actualizarDiferencia() {
                                const valor = parseFloat(inputReal.value);
                                if (isNaN(valor)) {
                                    contenedor.classList.add('d-none');
                                    return;
                                }

                                const diff = Math.round((valor - montoEsperado) * 100) / 100;
                                contenedor.classList.remove('d-none');

                                if (diff === 0) {
                                    textoDiferencia.className = 'fw-bold text-success';
                                    textoDiferencia.textContent = '$ 0,00 (Exacto)';
                                } else if (diff > 0) {
                                    textoDiferencia.className = 'fw-bold text-info';
                                    textoDiferencia.textContent = '+$ ' + diff.toFixed(2) + ' (Sobrante)';
                                } else {
                                    textoDiferencia.className = 'fw-bold text-danger';
                                    textoDiferencia.textContent = '-$ ' + Math.abs(diff).toFixed(2) + ' (Faltante)';
                                }
                            }

                            inputReal.addEventListener('input', actualizarDiferencia);
                            if (inputReal.value) {
                                actualizarDiferencia();
                            }
                        });
                    </script>
                @else
                    {{-- Aviso para administradores u otros usuarios cuando la caja abierta pertenece a otro usuario --}}
                    <div class="alert alert-info border-info-subtle shadow-sm py-3 px-4 mb-0" role="alert">
                        <i class="bi bi-info-circle me-2"></i>
                        Esta caja se encuentra actualmente abierta y solo puede ser cerrada por su titular (<strong>{{ $caja->user->name ?? 'Usuario' }}</strong>).
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-app-layout>
