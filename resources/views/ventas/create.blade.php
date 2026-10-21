<x-app-layout>
    @push('estilos')
        <style>
            /* Lista de sugerencias del buscador de productos */
            .sugerencias {
                position: fixed;
                z-index: 1080;
                max-height: 280px;
                margin: 0;
                padding: 0.35rem;
                overflow-y: auto;
                list-style: none;
                background-color: var(--bs-body-bg);
                border: 1px solid var(--app-line);
                border-radius: 0.6rem;
                box-shadow: 0 8px 24px rgba(31, 42, 48, 0.14);
            }
            .sugerencia {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 0.75rem;
                width: 100%;
                padding: 0.45rem 0.65rem;
                border: 0;
                border-radius: 0.4rem;
                background: transparent;
                color: var(--bs-body-color);
                text-align: left;
            }
            .sugerencia:hover,
            .sugerencia.activa {
                background-color: var(--bs-tertiary-bg);
                box-shadow: inset 3px 0 0 var(--app-accent);
            }

            /* Renglón que pide más de lo que hay en stock */
            .fila-sin-stock {
                --bs-table-bg: var(--bs-danger-bg-subtle);
            }
            .fila-sin-stock > td:first-child {
                box-shadow: inset 3px 0 0 var(--bs-danger);
            }

            /* Campos numéricos sin las flechitas del navegador */
            .sin-flechas { appearance: textfield; -moz-appearance: textfield; }
            .sin-flechas::-webkit-outer-spin-button,
            .sin-flechas::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
            .sin-flechas.is-invalid { background-image: none; padding-right: 0.5rem; }

            /* Opciones de medio de pago */
            .medio-opcion {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                padding: 0.6rem 0.85rem;
                border: 1px solid var(--app-line);
                border-radius: 0.6rem;
                cursor: pointer;
            }
            .medio-opcion:hover {
                background-color: var(--bs-tertiary-bg);
            }
            .btn-check:checked + .medio-opcion {
                border-color: var(--app-accent);
                background-color: var(--bs-tertiary-bg);
                box-shadow: inset 3px 0 0 var(--app-accent);
                font-weight: 500;
            }
            .btn-check:focus-visible + .medio-opcion {
                outline: 2px solid var(--app-accent);
                outline-offset: 2px;
            }

            /* Aviso de problemas al confirmar */
            .aviso-venta {
                padding: 0.9rem 1.1rem;
                border: 1px solid var(--bs-danger-border-subtle);
                border-radius: 0.75rem;
                background-color: var(--bs-danger-bg-subtle);
                color: var(--bs-danger-text-emphasis);
            }
        </style>
    @endpush

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-2 mb-4">
        <div>
            <a href="{{ route('ventas.index') }}" class="btn btn-outline-secondary btn-sm mb-2">
                <i class="bi bi-arrow-left me-1"></i> Volver a Ventas
            </a>
            <h1 class="h4 mb-1 fw-semibold">Nueva Venta</h1>
            <p class="text-body-secondary small mb-0">Elegí los productos, las cantidades y el medio de pago</p>
        </div>
        <span class="badge rounded-pill bg-success-subtle text-success-emphasis align-self-md-end">
            <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem"></i>Caja abierta #{{ $caja->id }}
        </span>
    </div>

    {{-- novalidate: los avisos los muestra el script (y el servidor vuelve a validar todo) --}}
    <form method="POST" action="{{ route('ventas.store') }}" id="formVenta" novalidate>
        @csrf

        {{-- Aviso de problemas: lo completa el servidor si rechazó la venta, o el script al confirmar --}}
        <div id="avisoVenta" class="aviso-venta d-flex gap-3 mb-4 {{ $errors->any() ? '' : 'd-none' }}" role="alert">
            <span class="icon-tile bg-danger text-white"><i class="bi bi-exclamation-triangle"></i></span>
            <div>
                <strong>No se pudo registrar la venta</strong>
                <ul class="mb-0 mt-1 ps-3 small" id="listaAvisos">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>

        <div class="row g-4">
            {{-- Renglones de la venta --}}
            <div class="col-lg-8">
                <div class="card card-panel">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <span class="icon-tile icon-tile-sm"><i class="bi bi-cart3"></i></span>
                            <h2 class="h6 mb-0 fw-semibold">Productos</h2>
                        </div>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnAgregarProducto">
                            <i class="bi bi-plus-lg me-1"></i> Agregar renglón
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive border-0 rounded-0 bg-transparent">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr class="small">
                                        <th scope="col" class="ps-4 fw-medium text-body-secondary bg-body-tertiary" style="min-width: 220px;">Producto</th>
                                        <th scope="col" class="fw-medium text-body-secondary bg-body-tertiary" style="width: 90px;">Cantidad</th>
                                        <th scope="col" class="text-end fw-medium text-body-secondary bg-body-tertiary" style="width: 110px;">Precio unit.</th>
                                        <th scope="col" class="fw-medium text-body-secondary bg-body-tertiary" style="width: 100px;">Descuento</th>
                                        <th scope="col" class="text-end fw-medium text-body-secondary bg-body-tertiary" style="width: 115px;">Subtotal</th>
                                        <th scope="col" class="pe-4 bg-body-tertiary" style="width: 56px;"><span class="visually-hidden">Quitar</span></th>
                                    </tr>
                                </thead>
                                {{-- Los renglones se agregan desde la plantilla de abajo --}}
                                <tbody id="filasProductos"></tbody>
                            </table>
                        </div>
                        <p class="small text-body-secondary px-4 py-3 mb-0 border-top">
                            <i class="bi bi-info-circle me-1"></i>
                            Escribí el nombre del producto y elegilo de la lista. Los productos sin stock no aparecen.
                        </p>
                    </div>
                </div>
            </div>

            {{-- Resumen y cobro --}}
            <div class="col-lg-4">
                <div class="card card-panel sticky-top" style="top: 1.5rem;">
                    <div class="card-header d-flex align-items-center gap-2">
                        <span class="icon-tile icon-tile-sm"><i class="bi bi-wallet2"></i></span>
                        <h2 class="h6 mb-0 fw-semibold">Cobro</h2>
                    </div>
                    <div class="card-body p-4">
                        <span class="form-label d-block">Medio de pago</span>
                        <div class="d-grid gap-2 mb-4">
                            @foreach (['efectivo' => ['Efectivo', 'bi-cash', 'success'], 'transferencia' => ['Transferencia', 'bi-bank', 'primary'], 'tarjeta' => ['Tarjeta de débito o crédito', 'bi-credit-card', 'info']] as $valor => [$texto, $icono, $color])
                                <input type="radio"
                                       class="btn-check"
                                       name="medio_pago"
                                       id="medio_{{ $valor }}"
                                       value="{{ $valor }}"
                                       @checked(old('medio_pago', 'efectivo') === $valor)>
                                <label class="medio-opcion" for="medio_{{ $valor }}">
                                    <i class="bi {{ $icono }} text-{{ $color }}"></i> {{ $texto }}
                                </label>
                            @endforeach
                        </div>

                        <div class="bg-body-tertiary rounded-3 p-3 mb-4">
                            {{-- Descuentos aplicados (solo se muestra si hay alguno) --}}
                            <div class="d-flex justify-content-between small text-body-secondary mb-2 d-none" id="filaDescuentos">
                                <span>Descuentos</span>
                                <span class="tabular" id="totalDescuentos"></span>
                            </div>
                            <span class="small text-body-secondary d-block">Total a cobrar</span>
                            <span class="kpi-value tabular" id="totalVenta">$ {{ monto(0) }}</span>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary" id="btnConfirmarVenta">
                                <i class="bi bi-check-lg me-1"></i> Confirmar venta
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

    {{-- Lista de sugerencias: se ubica debajo del campo Producto que se está usando --}}
    <ul class="sugerencias d-none" id="listaSugerencias" role="listbox"></ul>

    {{-- Plantilla de un renglón: __I__ se reemplaza por el número de renglón --}}
    <template id="plantillaFila">
        <tr class="fila-producto">
            <td class="ps-4">
                <input type="text"
                       class="form-control form-control-sm input-producto"
                       placeholder="Buscar producto..."
                       autocomplete="off"
                       role="combobox"
                       aria-autocomplete="list"
                       aria-controls="listaSugerencias"
                       aria-expanded="false">
                <input type="hidden" name="productos[__I__][id]" class="input-producto-id">
                <div class="small text-danger mt-1 aviso-stock"></div>
            </td>
            <td>
                <input type="number"
                       name="productos[__I__][cantidad]"
                       class="form-control form-control-sm text-center sin-flechas input-cantidad"
                       min="1"
                       value="1"
                       aria-label="Cantidad">
            </td>
            <td class="text-end tabular texto-precio">$ {{ monto(0) }}</td>
            <td>
                <div class="input-group input-group-sm">
                    <input type="number"
                           name="productos[__I__][descuento]"
                           class="form-control text-end sin-flechas input-descuento"
                           min="0"
                           max="100"
                           step="1"
                           value="0"
                           aria-label="Descuento en porcentaje">
                    <span class="input-group-text">%</span>
                </div>
            </td>
            <td class="text-end fw-semibold tabular texto-subtotal">$ {{ monto(0) }}</td>
            <td class="pe-4 text-end">
                <button type="button" class="btn btn-sm btn-outline-secondary btn-eliminar-fila" title="Quitar renglón" aria-label="Quitar renglón">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        </tr>
    </template>

    {{-- Script: renglones, buscador, descuentos, control de stock y totales en tiempo real --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const productos = {{ Js::from($productos->map->only(['id', 'nombre', 'precio_venta', 'stock'])->values()) }};
            const decimales = {{ configuracion()->decimales() }};
            const formulario = document.getElementById('formVenta');
            const contenedorFilas = document.getElementById('filasProductos');
            const plantilla = document.getElementById('plantillaFila');
            const btnAgregarProducto = document.getElementById('btnAgregarProducto');
            const lista = document.getElementById('listaSugerencias');
            const elementoTotal = document.getElementById('totalVenta');
            const filaDescuentos = document.getElementById('filaDescuentos');
            const elementoDescuentos = document.getElementById('totalDescuentos');
            const aviso = document.getElementById('avisoVenta');
            const listaAvisos = document.getElementById('listaAvisos');
            let contadorFilas = 0;
            let campoActivo = null;   // campo Producto que tiene la lista abierta
            let indiceActivo = -1;    // sugerencia resaltada con el teclado

            function redondear(valor) {
                const factor = Math.pow(10, decimales);
                return Math.round(valor * factor) / factor;
            }

            function formatearMoneda(valor) {
                return '$ ' + valor.toLocaleString('es-AR', { minimumFractionDigits: decimales, maximumFractionDigits: decimales });
            }

            // Pasa a minúsculas y quita tildes para que "batería" coincida con "bateria"
            function normalizar(texto) {
                return texto.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
            }

            function agregarFila() {
                contenedorFilas.insertAdjacentHTML('beforeend', plantilla.innerHTML.replaceAll('__I__', contadorFilas));
                contadorFilas++;
                recalcular(false);
                return contenedorFilas.lastElementChild;
            }

            function productoDeFila(fila) {
                const id = Number(fila.querySelector('.input-producto-id').value);
                return productos.find(function (producto) { return producto.id === id; }) || null;
            }

            // --- Lista de sugerencias ---

            function abrirLista(campo) {
                campoActivo = campo;
                const termino = normalizar(campo.value.trim());
                const coincidencias = productos
                    .filter(function (producto) { return normalizar(producto.nombre).includes(termino); })
                    .slice(0, 8);

                lista.replaceChildren();
                indiceActivo = -1;

                if (coincidencias.length === 0) {
                    const vacio = document.createElement('li');
                    vacio.className = 'small text-body-secondary px-2 py-1';
                    vacio.textContent = 'No hay productos que coincidan.';
                    lista.appendChild(vacio);
                }

                coincidencias.forEach(function (producto) {
                    const item = document.createElement('li');
                    const boton = document.createElement('button');
                    boton.type = 'button';
                    boton.className = 'sugerencia';
                    boton.setAttribute('role', 'option');
                    boton.dataset.id = producto.id;

                    const nombre = document.createElement('span');
                    nombre.textContent = producto.nombre;
                    const detalle = document.createElement('span');
                    detalle.className = 'small text-body-secondary text-nowrap tabular';
                    detalle.textContent = 'Stock ' + producto.stock + ' · ' + formatearMoneda(Number(producto.precio_venta));

                    boton.append(nombre, detalle);
                    item.appendChild(boton);
                    lista.appendChild(item);
                });

                // Posición fija debajo del campo (así no la recorta el contenedor de la tabla)
                const rect = campo.getBoundingClientRect();
                lista.style.top = (rect.bottom + 4) + 'px';
                lista.style.left = rect.left + 'px';
                lista.style.width = Math.max(rect.width, 320) + 'px';
                lista.classList.remove('d-none');
                campo.setAttribute('aria-expanded', 'true');
            }

            function cerrarLista() {
                lista.classList.add('d-none');
                if (campoActivo) {
                    campoActivo.setAttribute('aria-expanded', 'false');
                }
                campoActivo = null;
            }

            function resaltar(indice) {
                const botones = lista.querySelectorAll('.sugerencia');
                if (botones.length === 0) {
                    return;
                }
                indiceActivo = (indice + botones.length) % botones.length;
                botones.forEach(function (boton, i) { boton.classList.toggle('activa', i === indiceActivo); });
                botones[indiceActivo].scrollIntoView({ block: 'nearest' });
            }

            function elegirProducto(id) {
                const fila = campoActivo.closest('.fila-producto');
                const producto = productos.find(function (p) { return p.id === Number(id); });
                campoActivo.value = producto.nombre;
                fila.querySelector('.input-producto-id').value = producto.id;
                cerrarLista();
                recalcular(false);
                fila.querySelector('.input-cantidad').select();
            }

            // pointerdown en vez de click: elige antes de que el campo pierda el foco
            lista.addEventListener('pointerdown', function (e) {
                const boton = e.target.closest('.sugerencia');
                if (boton) {
                    e.preventDefault();
                    elegirProducto(boton.dataset.id);
                }
            });

            // --- Avisos y cálculos ---

            function mostrarAviso(problemas) {
                listaAvisos.replaceChildren();
                problemas.forEach(function (texto) {
                    const item = document.createElement('li');
                    item.textContent = texto;
                    listaAvisos.appendChild(item);
                });
                aviso.classList.toggle('d-none', problemas.length === 0);
            }

            /**
             * Recalcula precios, descuentos y totales, y marca en rojo los renglones con problemas.
             * Si marcarProductos es true (al confirmar), también marca los renglones sin producto elegido.
             * Devuelve la lista de problemas encontrados; vacía si la venta se puede confirmar.
             */
            function recalcular(marcarProductos) {
                const filas = contenedorFilas.querySelectorAll('.fila-producto');
                const problemas = [];
                let total = 0;
                let descuentos = 0;

                // Cantidad pedida de cada producto: puede estar repetido en varios renglones
                const pedido = {};
                filas.forEach(function (fila) {
                    const producto = productoDeFila(fila);
                    if (producto) {
                        const cantidad = parseInt(fila.querySelector('.input-cantidad').value) || 0;
                        pedido[producto.id] = (pedido[producto.id] || 0) + cantidad;
                    }
                });

                filas.forEach(function (fila, indice) {
                    const producto = productoDeFila(fila);
                    const inputProducto = fila.querySelector('.input-producto');
                    const inputCantidad = fila.querySelector('.input-cantidad');
                    const inputDescuento = fila.querySelector('.input-descuento');
                    const cantidad = parseInt(inputCantidad.value) || 0;
                    const descuento = inputDescuento.value === '' ? 0 : Number(inputDescuento.value);
                    const renglon = 'Renglón ' + (indice + 1);

                    const sinStock = producto !== null && pedido[producto.id] > producto.stock;
                    const cantidadInvalida = cantidad < 1;
                    const descuentoInvalido = ! Number.isInteger(descuento) || descuento < 0 || descuento > 100;

                    // Renglón en rojo apenas la cantidad supera el stock disponible
                    fila.classList.toggle('fila-sin-stock', sinStock);
                    fila.querySelector('.aviso-stock').textContent = sinStock ? 'Stock disponible: ' + producto.stock : '';
                    inputCantidad.classList.toggle('is-invalid', sinStock || cantidadInvalida);
                    inputDescuento.classList.toggle('is-invalid', descuentoInvalido);
                    if (marcarProductos || producto) {
                        inputProducto.classList.toggle('is-invalid', ! producto);
                    }

                    if (! producto) {
                        problemas.push(renglon + ': elegí un producto de la lista.');
                    }
                    if (sinStock) {
                        problemas.push(producto.nombre + ': pediste ' + pedido[producto.id] + ' y hay ' + producto.stock + ' en stock.');
                    }
                    if (cantidadInvalida) {
                        problemas.push(renglon + ': la cantidad debe ser al menos 1.');
                    }
                    if (descuentoInvalido) {
                        problemas.push(renglon + ': el descuento debe ser un número entero entre 0 y 100.');
                    }

                    const precio = producto ? Number(producto.precio_venta) : 0;
                    let subtotal = 0;
                    if (producto && ! cantidadInvalida && ! descuentoInvalido) {
                        subtotal = redondear(precio * cantidad * (100 - descuento) / 100);
                        total += subtotal;
                        descuentos += precio * cantidad - subtotal;
                    }
                    fila.querySelector('.texto-precio').textContent = formatearMoneda(precio);
                    fila.querySelector('.texto-subtotal').textContent = formatearMoneda(subtotal);
                    fila.querySelector('.btn-eliminar-fila').disabled = filas.length === 1;
                });

                elementoTotal.textContent = formatearMoneda(total);
                elementoDescuentos.textContent = '− ' + formatearMoneda(descuentos);
                filaDescuentos.classList.toggle('d-none', descuentos <= 0);

                // Varios renglones con el mismo producto repiten el aviso de stock: se deja uno solo
                return [...new Set(problemas)];
            }

            function actualizar() {
                const problemas = recalcular(false);
                if (! aviso.classList.contains('d-none')) {
                    mostrarAviso(problemas);
                }
            }

            // --- Eventos ---

            btnAgregarProducto.addEventListener('click', function () {
                agregarFila().querySelector('.input-producto').focus();
            });

            contenedorFilas.addEventListener('input', function (e) {
                if (e.target.classList.contains('input-producto')) {
                    // Si se edita el texto, el producto elegido deja de valer hasta elegir otro
                    e.target.closest('.fila-producto').querySelector('.input-producto-id').value = '';
                    abrirLista(e.target);
                }
                actualizar();
            });

            contenedorFilas.addEventListener('focusin', function (e) {
                if (e.target.classList.contains('input-producto')) {
                    abrirLista(e.target);
                }
            });

            contenedorFilas.addEventListener('focusout', function (e) {
                if (e.target === campoActivo) {
                    cerrarLista();
                }
            });

            // Teclado en el buscador: flechas para moverse, Enter para elegir, Esc para cerrar
            contenedorFilas.addEventListener('keydown', function (e) {
                if (e.target !== campoActivo) {
                    return;
                }
                if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                    e.preventDefault();
                    resaltar(indiceActivo + (e.key === 'ArrowDown' ? 1 : -1));
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    const botones = lista.querySelectorAll('.sugerencia');
                    const elegido = botones[indiceActivo] || (botones.length === 1 ? botones[0] : null);
                    if (elegido) {
                        elegirProducto(elegido.dataset.id);
                    }
                } else if (e.key === 'Escape') {
                    cerrarLista();
                }
            });

            // Si la página se desplaza, la lista se cierra para no quedar fuera de lugar
            window.addEventListener('scroll', function (e) {
                if (e.target !== lista) {
                    cerrarLista();
                }
            }, true);
            window.addEventListener('resize', cerrarLista);

            contenedorFilas.addEventListener('click', function (e) {
                const btnEliminar = e.target.closest('.btn-eliminar-fila');
                if (btnEliminar && ! btnEliminar.disabled) {
                    btnEliminar.closest('.fila-producto').remove();
                    actualizar();
                }
            });

            // Al confirmar: si hay problemas, no se envía y se muestra el aviso
            formulario.addEventListener('submit', function (e) {
                const problemas = recalcular(true);
                if (problemas.length > 0) {
                    e.preventDefault();
                    mostrarAviso(problemas);
                    aviso.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });

            agregarFila().querySelector('.input-producto').focus();
        });
    </script>
</x-app-layout>
