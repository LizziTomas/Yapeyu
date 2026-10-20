<x-app-layout>
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <a href="{{ route('ventas.index') }}" class="btn btn-outline-secondary btn-sm mb-2">
                        <i class="bi bi-arrow-left me-1"></i> Volver a Ventas
                    </a>
                    <h1 class="h4 mb-1 fw-semibold">Nueva Venta</h1>
                    <p class="text-muted small mb-0">Selecciona los productos, cantidades y medio de pago para registrar la venta</p>
                </div>
                <div>
                    <span class="badge bg-success-subtle text-success border border-success-subtle py-2 px-3">
                        <i class="bi bi-cash-stack me-1"></i> Caja Abierta #{{ $caja->id }}
                    </span>
                </div>
            </div>

            <form method="POST" action="{{ route('ventas.store') }}" id="formVenta">
                @csrf

                <div class="row g-4">
                    {{-- Sección de selección de productos --}}
                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                                <h2 class="h6 mb-0 fw-semibold text-secondary">Renglones de la Venta</h2>
                                <button type="button" class="btn btn-outline-primary btn-sm" id="btnAgregarProducto">
                                    <i class="bi bi-plus-circle me-1"></i> Agregar Renglón
                                </button>
                            </div>
                            <div class="card-body p-3 p-md-4">
                                {{-- Buscador: filtra las opciones de los selectores al escribir --}}
                                <div class="mb-3">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                                        <input type="text"
                                               id="buscadorProductos"
                                               class="form-control"
                                               placeholder="Buscar producto por nombre..."
                                               autocomplete="off"
                                               autofocus>
                                        <button type="button" class="btn btn-outline-secondary" id="btnLimpiarBusquedaProductos" title="Limpiar búsqueda">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </div>
                                    <div class="form-text" id="ayudaBuscadorProductos">
                                        Escribí para acotar la lista de cada selector. Los productos con stock 0 no se muestran.
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table align-middle mb-0" id="tablaProductos">
                                        <thead class="table-light">
                                            <tr>
                                                <th scope="col" style="min-width: 250px;">Producto</th>
                                                <th scope="col" style="width: 120px;">Cantidad</th>
                                                <th scope="col" class="text-end" style="width: 130px;">Precio Unit.</th>
                                                <th scope="col" class="text-end" style="width: 130px;">Subtotal</th>
                                                <th scope="col" style="width: 50px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="filasProductos">
                                            {{-- Fila inicial --}}
                                            <tr class="fila-producto" data-indice="0">
                                                <td>
                                                    <select name="productos[0][id]" class="form-select form-select-sm select-producto" required>
                                                        <option value="" disabled selected>-- Seleccionar Producto --</option>
                                                        @foreach ($productos as $prod)
                                                            <option value="{{ $prod->id }}"
                                                                    data-precio="{{ (float) $prod->precio_venta }}"
                                                                    data-stock="{{ $prod->stock }}">
                                                                {{ $prod->nombre }} (Stock: {{ $prod->stock }}) - $ {{ monto($prod->precio_venta) }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="number"
                                                           name="productos[0][cantidad]"
                                                           class="form-control form-control-sm input-cantidad"
                                                           min="1"
                                                           value="1"
                                                           required>
                                                </td>
                                                <td class="text-end fw-medium texto-precio">$ {{ monto(0) }}</td>
                                                <td class="text-end fw-semibold texto-subtotal">$ {{ monto(0) }}</td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-outline-danger btn-sm btn-eliminar-fila" title="Eliminar ítem">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Sección de pago y totales --}}
                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm sticky-top" style="top: 80px;">
                            <div class="card-header bg-white py-3 border-bottom">
                                <h2 class="h6 mb-0 fw-semibold text-secondary">Resumen de Operación</h2>
                            </div>
                            <div class="card-body p-4">
                                {{-- Medio de pago --}}
                                <div class="mb-4">
                                    <label for="medio_pago" class="form-label">
                                        Medio de Pago <span class="text-danger">*</span>
                                    </label>
                                    <select name="medio_pago" id="medio_pago" class="form-select @error('medio_pago') is-invalid @enderror" required>
                                        <option value="efectivo" {{ old('medio_pago') === 'efectivo' ? 'selected' : '' }}>
                                            💵 Efectivo
                                        </option>
                                        <option value="transferencia" {{ old('medio_pago') === 'transferencia' ? 'selected' : '' }}>
                                            📲 Transferencia Bancaria / Virtual
                                        </option>
                                        <option value="tarjeta" {{ old('medio_pago') === 'tarjeta' ? 'selected' : '' }}>
                                            💳 Tarjeta (Débito / Crédito)
                                        </option>
                                    </select>
                                </div>

                                <hr class="my-3">

                                {{-- Total a cobrar --}}
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <span class="fs-6 fw-semibold text-muted">Total a Cobrar:</span>
                                    <span class="fs-4 fw-bold text-primary" id="totalVenta">$ {{ monto(0) }}</span>
                                </div>

                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary btn-lg" id="btnConfirmarVenta">
                                        <i class="bi bi-check-circle me-1"></i> Confirmar Venta
                                    </button>
                                    <a href="{{ route('ventas.index') }}" class="btn btn-outline-secondary">
                                        Cancelar
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Script: buscador que filtra opciones, alta/baja de renglones y totales en tiempo real --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            let contadorFilas = 1;

            const contenedorFilas = document.getElementById('filasProductos');
            const btnAgregar = document.getElementById('btnAgregarProducto');
            const elementoTotal = document.getElementById('totalVenta');
            const buscador = document.getElementById('buscadorProductos');
            const btnLimpiarBusqueda = document.getElementById('btnLimpiarBusquedaProductos');
            const ayuda = document.getElementById('ayudaBuscadorProductos');

            // Plantilla de opciones usada al crear un renglón nuevo
            const opcionesProductosHtml = `
                <option value="" disabled selected>-- Seleccionar Producto --</option>
                @foreach ($productos as $prod)
                    <option value="{{ $prod->id }}"
                            data-precio="{{ (float) $prod->precio_venta }}"
                            data-stock="{{ $prod->stock }}">
                        {{ $prod->nombre }} (Stock: {{ $prod->stock }}) - $ {{ monto($prod->precio_venta) }}
                    </option>
                @endforeach
            `;

            const textoAyudaBase = 'Escribí para acotar la lista de cada selector. Los productos con stock 0 no se muestran.';

            function formatearMoneda(valor) {
                return '$ ' + valor.toLocaleString('es-AR', { minimumFractionDigits: {{ configuracion()->decimales() }}, maximumFractionDigits: {{ configuracion()->decimales() }} });
            }

            /**
             * Filtra las opciones de todos los selectores de producto según el texto buscado.
             * La opción de encabezado y la opción ya elegida siempre se mantienen visibles.
             * Se usa `hidden` para que cerrar y abrir el select no muestre las descartadas.
             */
            function filtrarOpciones() {
                const termino = (buscador.value || '').trim().toLowerCase();
                let algunaCoincidencia = false;

                contenedorFilas.querySelectorAll('.select-producto').forEach(function (select) {
                    const valorSeleccionado = select.value;

                    Array.from(select.options).forEach(function (opcion) {
                        if (opcion.value === '') {
                            opcion.hidden = false;
                            return;
                        }

                        const coincide = termino === '' || opcion.textContent.toLowerCase().includes(termino);
                        opcion.hidden = ! coincide && opcion.value !== valorSeleccionado;

                        if (coincide) {
                            algunaCoincidencia = true;
                        }
                    });
                });

                if (termino === '') {
                    ayuda.textContent = textoAyudaBase;
                } else if (! algunaCoincidencia) {
                    ayuda.innerHTML = '<span class="text-danger">No se encontraron productos que coincidan con la búsqueda.</span>';
                } else {
                    ayuda.textContent = 'Lista filtrada. Abrí cualquier selector para elegir el producto.';
                }
            }

            function recalcularTotales() {
                let granTotal = 0;

                contenedorFilas.querySelectorAll('.fila-producto').forEach(function (fila) {
                    const select = fila.querySelector('.select-producto');
                    const inputCantidad = fila.querySelector('.input-cantidad');
                    const opcionSeleccionada = select.selectedOptions[0];

                    if (opcionSeleccionada && opcionSeleccionada.value) {
                        const precio = parseFloat(opcionSeleccionada.dataset.precio || 0);
                        const stock = parseInt(opcionSeleccionada.dataset.stock || 0);
                        let cantidad = parseInt(inputCantidad.value || 1);

                        if (cantidad < 1) {
                            cantidad = 1;
                            inputCantidad.value = 1;
                        }

                        inputCantidad.max = stock;

                        const subtotal = Math.round((precio * cantidad) * 100) / 100;
                        fila.querySelector('.texto-precio').textContent = formatearMoneda(precio);
                        fila.querySelector('.texto-subtotal').textContent = formatearMoneda(subtotal);
                        granTotal += subtotal;
                    } else {
                        fila.querySelector('.texto-precio').textContent = '$ {{ monto(0) }}';
                        fila.querySelector('.texto-subtotal').textContent = '$ {{ monto(0) }}';
                    }
                });

                elementoTotal.textContent = formatearMoneda(granTotal);
            }

            // Agregar un nuevo renglón
            btnAgregar.addEventListener('click', function () {
                const nuevaFila = document.createElement('tr');
                nuevaFila.className = 'fila-producto';
                nuevaFila.dataset.indice = contadorFilas;
                nuevaFila.innerHTML = `
                    <td>
                        <select name="productos[${contadorFilas}][id]" class="form-select form-select-sm select-producto" required>
                            ${opcionesProductosHtml}
                        </select>
                    </td>
                    <td>
                        <input type="number"
                               name="productos[${contadorFilas}][cantidad]"
                               class="form-control form-control-sm input-cantidad"
                               min="1"
                               value="1"
                               required>
                    </td>
                    <td class="text-end fw-medium texto-precio">$ {{ monto(0) }}</td>
                    <td class="text-end fw-semibold texto-subtotal">$ {{ monto(0) }}</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-outline-danger btn-sm btn-eliminar-fila" title="Eliminar ítem">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                `;

                contenedorFilas.appendChild(nuevaFila);
                contadorFilas++;

                // El renglón nuevo respeta el filtro activo del buscador
                filtrarOpciones();
            });

            // Cambios de producto o cantidad
            contenedorFilas.addEventListener('change', function (e) {
                if (e.target.classList.contains('select-producto') || e.target.classList.contains('input-cantidad')) {
                    recalcularTotales();
                }
            });

            contenedorFilas.addEventListener('input', function (e) {
                if (e.target.classList.contains('input-cantidad')) {
                    recalcularTotales();
                }
            });

            // Eliminar renglón (siempre debe quedar al menos uno)
            contenedorFilas.addEventListener('click', function (e) {
                const btnEliminar = e.target.closest('.btn-eliminar-fila');
                if (! btnEliminar) {
                    return;
                }

                if (contenedorFilas.querySelectorAll('.fila-producto').length > 1) {
                    btnEliminar.closest('.fila-producto').remove();
                    recalcularTotales();
                } else {
                    alert('Debes mantener al menos un renglón en la venta.');
                }
            });

            // --- Buscador ---
            buscador.addEventListener('input', filtrarOpciones);

            btnLimpiarBusqueda.addEventListener('click', function () {
                buscador.value = '';
                filtrarOpciones();
                buscador.focus();
            });

            // Atajo de teclado: "/" enfoca el buscador (salvo que ya se esté escribiendo en otro campo)
            document.addEventListener('keydown', function (e) {
                const activo = document.activeElement;
                const escribiendo = activo && (activo.tagName === 'INPUT' || activo.tagName === 'SELECT' || activo.tagName === 'TEXTAREA');

                if (e.key === '/' && ! escribiendo) {
                    e.preventDefault();
                    buscador.focus();
                }
            });

            recalcularTotales();
        });
    </script>
</x-app-layout>
