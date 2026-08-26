<x-app-layout>
    <div class="row justify-content-center">
        <div class="col-lg-7 col-md-9">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h1 class="h4 mb-0 fw-semibold">Editar Producto</h1>
                <a href="{{ route('productos.index') }}" class="btn btn-outline-secondary btn-sm">
                    Volver a la lista
                </a>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('productos.update', $producto) }}">
                        @csrf
                        @method('PUT')

                        <!-- Nombre del Producto -->
                        <div class="mb-3">
                            <label for="nombre" class="form-label">Nombre del producto</label>
                            <input type="text" 
                                   class="form-control form-control-sm @error('nombre') is-invalid @enderror" 
                                   id="nombre" 
                                   name="nombre" 
                                   value="{{ old('nombre', $producto->nombre) }}" 
                                   required 
                                   autofocus>
                            @error('nombre')
                                <div class="invalid-feedback small">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Precios -->
                        <div class="row g-2 mb-3">
                            <div class="col-sm-6">
                                <label for="precio_costo" class="form-label">Precio de costo ($)</label>
                                <input type="number" 
                                       step="0.01" 
                                       min="0"
                                       class="form-control form-control-sm @error('precio_costo') is-invalid @enderror" 
                                       id="precio_costo" 
                                       name="precio_costo" 
                                       value="{{ old('precio_costo', $producto->precio_costo) }}" 
                                       required>
                                @error('precio_costo')
                                    <div class="invalid-feedback small">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-sm-6">
                                <label for="precio_venta" class="form-label">Precio de venta ($)</label>
                                <input type="number" 
                                       step="0.01" 
                                       min="0"
                                       class="form-control form-control-sm @error('precio_venta') is-invalid @enderror" 
                                       id="precio_venta" 
                                       name="precio_venta" 
                                       value="{{ old('precio_venta', $producto->precio_venta) }}" 
                                       required>
                                @error('precio_venta')
                                    <div class="invalid-feedback small">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <!-- Stocks -->
                        <div class="row g-2 mb-4">
                            <div class="col-sm-6">
                                <label for="stock" class="form-label">Stock actual</label>
                                <input type="number" 
                                       step="1" 
                                       min="0"
                                       class="form-control form-control-sm @error('stock') is-invalid @enderror" 
                                       id="stock" 
                                       name="stock" 
                                       value="{{ old('stock', $producto->stock) }}" 
                                       required>
                                @error('stock')
                                    <div class="invalid-feedback small">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-sm-6">
                                <label for="stock_minimo" class="form-label">Stock mínimo de alerta</label>
                                <input type="number" 
                                       step="1" 
                                       min="0"
                                       class="form-control form-control-sm @error('stock_minimo') is-invalid @enderror" 
                                       id="stock_minimo" 
                                       name="stock_minimo" 
                                       value="{{ old('stock_minimo', $producto->stock_minimo) }}" 
                                       required>
                                @error('stock_minimo')
                                    <div class="invalid-feedback small">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <!-- Botones de Acción -->
                        <div class="d-flex justify-content-end gap-2 pt-2 border-top">
                            <a href="{{ route('productos.index') }}" class="btn btn-outline-secondary btn-sm">
                                Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary btn-sm px-3">
                                Actualizar Producto
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
