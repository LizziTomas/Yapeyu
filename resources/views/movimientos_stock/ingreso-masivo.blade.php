<x-app-layout>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">Ingreso masivo de stock</h1>
            <p class="text-muted small mb-0">Cargá la cantidad que llegó de varios productos en una sola operación</p>
        </div>
        <a href="{{ route('movimientos-stock.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Volver al historial
        </a>
    </div>

    @if ($productos->isEmpty())
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center text-muted py-5">
                <i class="bi bi-box-seam fs-2 d-block mb-2"></i>
                No hay productos activos para cargar stock.
            </div>
        </div>
    @else
        <form method="POST" action="{{ route('movimientos-stock.ingreso-masivo.store') }}" id="form-ingreso-masivo">
            @csrf

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-3">
                    <div class="row g-2 align-items-end">
                        <div class="col-sm-7 col-md-5">
                            <label for="filtro-productos" class="form-label small text-muted">Buscar producto</label>
                            <input type="search"
                                   id="filtro-productos"
                                   class="form-control form-control-sm"
                                   placeholder="Filtrar la lista por nombre..."
                                   autocomplete="off">
                        </div>
                        <div class="col-sm-5 col-md-7 text-sm-end">
                            <span class="small text-muted" id="resumen-cantidades">Todavía no cargaste cantidades.</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-responsive shadow-sm">
                <div style="max-height: 58vh; overflow-y: auto;">
                    <table class="table table-hover table-sm align-middle mb-0">
                        <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                            <tr>
                                <th scope="col" class="ps-3">Producto</th>
                                <th scope="col" class="text-center" style="width: 110px;">Stock actual</th>
                                <th scope="col" class="text-center" style="width: 110px;">Stock mín.</th>
                                <th scope="col" class="text-end pe-3" style="width: 170px;">Cantidad a ingresar</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($productos as $producto)
                                <tr data-nombre="{{ Illuminate\Support\Str::ascii(mb_strtolower($producto->nombre)) }}">
                                    <td class="ps-3 fw-medium">
                                        {{ $producto->nombre }}
                                        @if ($producto->isStockBajo())
                                            <span class="badge bg-warning text-dark ms-1 small">Stock bajo</span>
                                        @endif
                                    </td>
                                    <td class="text-center {{ $producto->isStockBajo() ? 'text-danger fw-semibold' : 'text-muted' }}">
                                        {{ number_format($producto->stock, 0, ',', '.') }}
                                    </td>
                                    <td class="text-center text-muted">{{ number_format($producto->stock_minimo, 0, ',', '.') }}</td>
                                    <td class="text-end pe-3">
                                        <input type="number"
                                               name="cantidades[{{ $producto->id }}]"
                                               value="{{ old('cantidades.'.$producto->id) }}"
                                               min="0"
                                               max="1000000"
                                               step="1"
                                               inputmode="numeric"
                                               data-cantidad
                                               aria-label="Cantidad a ingresar de {{ $producto->nombre }}"
                                               class="form-control form-control-sm text-end ms-auto @error('cantidades.'.$producto->id) is-invalid @enderror"
                                               style="max-width: 130px"
                                               placeholder="0">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card border-0 shadow-sm mt-3">
                <div class="card-body p-3">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-7">
                            <label for="observacion" class="form-label small text-muted">
                                Observación <span class="fw-normal">(opcional, se guarda en todos los movimientos)</span>
                            </label>
                            <input type="text"
                                   id="observacion"
                                   name="observacion"
                                   value="{{ old('observacion') }}"
                                   maxlength="255"
                                   class="form-control form-control-sm @error('observacion') is-invalid @enderror"
                                   placeholder="Ej. Llegada de lote #402 / Proveedor Yacobone">
                            @error('observacion')
                                <div class="invalid-feedback small">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-5 d-flex justify-content-md-end gap-2">
                            <a href="{{ route('movimientos-stock.index') }}" class="btn btn-outline-secondary btn-sm">
                                Cancelar
                            </a>
                            <button type="submit" class="btn btn-success btn-sm px-3">
                                <i class="bi bi-box-arrow-in-down me-1"></i> Registrar ingresos
                            </button>
                        </div>
                    </div>
                    <div class="form-text mt-2">
                        Solo se registran los productos con una cantidad mayor a 0. Se genera un movimiento de ingreso por producto.
                    </div>
                </div>
            </div>
        </form>
    @endif

    @push('scripts')
        <script>
            (function () {
                var form = document.getElementById('form-ingreso-masivo');
                if (!form) return;

                var inputs = Array.prototype.slice.call(form.querySelectorAll('input[data-cantidad]'));
                var resumen = document.getElementById('resumen-cantidades');

                function normalizar(texto) {
                    return String(texto || '')
                        .toLowerCase()
                        .normalize('NFD')
                        .replace(/[\u0300-\u036f]/g, '');
                }

                function actualizarResumen() {
                    var productos = 0;
                    var unidades = 0;

                    inputs.forEach(function (input) {
                        var cantidad = parseInt(input.value, 10);
                        if (!isNaN(cantidad) && cantidad > 0) {
                            productos++;
                            unidades += cantidad;
                        }
                    });

                    if (productos === 0) {
                        resumen.textContent = 'Todavía no cargaste cantidades.';
                    } else {
                        resumen.textContent = productos + (productos === 1 ? ' producto' : ' productos')
                            + ' · ' + unidades + (unidades === 1 ? ' unidad' : ' unidades');
                        resumen.classList.add('fw-semibold');
                    }

                    if (productos === 0) {
                        resumen.classList.remove('fw-semibold');
                    }
                }

                inputs.forEach(function (input) {
                    input.addEventListener('input', actualizarResumen);
                });

                var filtro = document.getElementById('filtro-productos');
                if (filtro) {
                    filtro.addEventListener('input', function () {
                        var busqueda = normalizar(filtro.value).trim();

                        form.querySelectorAll('tr[data-nombre]').forEach(function (fila) {
                            var coincide = busqueda === '' || fila.dataset.nombre.indexOf(busqueda) !== -1;
                            fila.classList.toggle('d-none', ! coincide);
                        });
                    });
                }

                actualizarResumen();
            })();
        </script>
    @endpush
</x-app-layout>
