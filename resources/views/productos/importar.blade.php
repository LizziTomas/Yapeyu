@php($filasConError = session('filas_con_error', []))

<x-app-layout>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">Importar productos</h1>
            <p class="text-muted small mb-0">Cargá varios productos desde un archivo CSV o pegándolos desde Excel</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('productos.importar.plantilla') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-download me-1"></i> Descargar plantilla
            </a>
            <a href="{{ route('productos.index') }}" class="btn btn-outline-secondary btn-sm">
                Volver a la lista
            </a>
        </div>
    </div>

    {{-- Errores generales del archivo (columnas faltantes, archivo vacío, etc.) --}}
    @if (session('errores_importacion'))
        <div class="alert alert-danger py-2 px-3 mb-3 small" role="alert">
            <div class="fw-semibold mb-1">
                <i class="bi bi-exclamation-triangle me-1"></i> No se pudo leer la importación:
            </div>
            <ul class="mb-0 ps-3">
                @foreach (session('errores_importacion') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Errores por fila: el archivo se leyó bien, pero hay datos para corregir --}}
    @if ($filasConError !== [])
        <div class="alert alert-danger py-2 px-3 mb-3 small" role="alert">
            <div class="fw-semibold mb-1">
                <i class="bi bi-exclamation-triangle me-1"></i>
                No se importó nada porque hay filas con errores. Corregilas y volvé a analizar:
                @if (blank(old('texto')))
                    <span class="d-block mt-1">Si usaste un archivo, volvé a seleccionarlo.</span>
                @endif
            </div>
            <div class="table-responsive mt-2">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 70px;">Fila</th>
                            <th style="width: 220px;">Producto</th>
                            <th>Problema</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($filasConError as $fila)
                            <tr>
                                <td class="text-muted">{{ $fila['linea'] }}</td>
                                <td class="fw-medium">{{ $fila['nombre'] !== '' ? $fila['nombre'] : '—' }}</td>
                                <td>
                                    <ul class="mb-0 ps-3">
                                        @foreach ($fila['errores'] as $mensaje)
                                            <li>{{ $mensaje }}</li>
                                        @endforeach
                                    </ul>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('productos.importar.analizar') }}" enctype="multipart/form-data">
                        @csrf

                        <div class="mb-4">
                            <label for="archivo" class="form-label">
                                <i class="bi bi-filetype-csv me-1 text-primary"></i> Opción 1: subir un archivo CSV
                            </label>
                            <input type="file"
                                   id="archivo"
                                   name="archivo"
                                   accept=".csv,.txt,text/csv"
                                   class="form-control form-control-sm @error('archivo') is-invalid @enderror">
                            @error('archivo')
                                <div class="invalid-feedback small">{{ $message }}</div>
                            @enderror
                            <div class="form-text">
                                En Excel: <strong>Archivo → Guardar como → CSV</strong>. Si Excel te ofrece
                                "CSV UTF-8", elegí esa opción.
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-2 text-muted small mb-4">
                            <hr class="flex-grow-1 m-0">
                            <span>o</span>
                            <hr class="flex-grow-1 m-0">
                        </div>

                        <div class="mb-4">
                            <label for="texto" class="form-label">
                                <i class="bi bi-clipboard-data me-1 text-primary"></i> Opción 2: pegar desde Excel
                            </label>
                            <textarea id="texto"
                                      name="texto"
                                      rows="8"
                                      class="form-control form-control-sm font-monospace @error('texto') is-invalid @enderror"
                                      placeholder="Seleccioná las celdas en Excel, copiá con Ctrl+C y pegá acá con Ctrl+V.">{{ old('texto') }}</textarea>
                            @error('texto')
                                <div class="invalid-feedback small">{{ $message }}</div>
                            @enderror
                            <div class="form-text">
                                Podés pegar las celdas tal cual (con tabulaciones) o separadas por punto y coma.
                                La primera fila puede ser el encabezado.
                            </div>
                        </div>

                        @if (old('texto'))
                            <div class="alert alert-light border py-2 px-3 small mb-4">
                                Se conservó el texto que pegaste. Corregilo y volvé a analizar.
                            </div>
                        @endif

                        <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                            <a href="{{ route('productos.index') }}" class="btn btn-outline-secondary btn-sm">
                                Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary btn-sm px-3">
                                <i class="bi bi-search me-1"></i> Analizar datos
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-transparent py-2 px-3">
                    <span class="fw-semibold small"><i class="bi bi-table me-1 text-primary"></i> Formato de la planilla</span>
                </div>
                <div class="card-body p-3">
                    <p class="small text-muted mb-2">Las columnas, en este orden:</p>
                    <ol class="small mb-3 ps-3">
                        <li><code>nombre</code> <span class="text-muted">(obligatorio)</span></li>
                        <li><code>precio_costo</code> <span class="text-muted">(obligatorio)</span></li>
                        <li><code>precio_venta</code> <span class="text-muted">(obligatorio)</span></li>
                        <li><code>stock_minimo</code> <span class="text-muted">(obligatorio)</span></li>
                        <li><code>stock_inicial</code> <span class="text-muted">(opcional, por defecto 0)</span></li>
                    </ol>

                    <p class="small text-muted mb-1">Ejemplo:</p>
                    <pre class="bg-body-tertiary border rounded p-2 small mb-0" style="white-space: pre-wrap;">Bombacha de campo;1200;1800;5;10
Camisa de trabajo;8500;12900;3;0</pre>

                    <div class="form-text mt-2">
                        Los montos aceptan coma decimal ($ 1.234,56) y los stock deben ser números enteros.
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent py-2 px-3">
                    <span class="fw-semibold small"><i class="bi bi-info-circle me-1 text-primary"></i> Antes de confirmar</span>
                </div>
                <div class="card-body p-3 small text-muted">
                    <ul class="mb-0 ps-3 d-flex flex-column gap-2">
                        <li>Se muestra una <strong>previsualización</strong> con lo que se va a crear y actualizar.</li>
                        <li>Si el producto ya existe (mismo nombre), se <strong>actualizan precios y stock mínimo</strong>. El stock no cambia.</li>
                        <li>El <strong>stock inicial</strong> de un producto nuevo se registra como movimiento de ingreso.</li>
                        <li>La importación es <strong>todo o nada</strong>: si una fila tiene errores, no se guarda nada.</li>
                        <li>Máximo <strong>{{ App\Services\ImportacionProductos::MAX_FILAS }} productos</strong> por importación.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
