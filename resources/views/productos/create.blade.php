<x-app-layout>
    <div class="row justify-content-center">
        <div class="col-lg-7 col-md-9">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h1 class="h4 mb-0 fw-semibold">Nuevo Producto</h1>
                <a href="{{ route('productos.index') }}" class="btn btn-outline-secondary btn-sm">
                    Volver a la lista
                </a>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('productos.store') }}">
                        @csrf

                        <!-- Nombre del Producto -->
                        <div class="mb-3">
                            <label for="nombre" class="form-label">Nombre del producto <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control form-control-sm @error('nombre') is-invalid @enderror" 
                                   id="nombre" 
                                   name="nombre" 
                                   value="{{ old('nombre') }}" 
                                   placeholder="Ej. Bombacha de campo"
                                   required 
                                   autofocus>
                            @error('nombre')
                                <div class="invalid-feedback small">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Precios -->
                        <div class="row g-2 mb-3">
                            <div class="col-sm-6">
                                <label for="precio_costo" class="form-label">Precio de costo ($) <span class="text-danger">*</span></label>
                                <input type="number" 
                                       step="0.01" 
                                       min="0"
                                       class="form-control form-control-sm @error('precio_costo') is-invalid @enderror" 
                                       id="precio_costo" 
                                       name="precio_costo" 
                                       value="{{ old('precio_costo', '0.00') }}" 
                                       required>
                                @error('precio_costo')
                                    <div class="invalid-feedback small">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-sm-6">
                                <label for="precio_venta" class="form-label">Precio de venta ($) <span class="text-danger">*</span></label>
                                <input type="number" 
                                       step="0.01" 
                                       min="0"
                                       class="form-control form-control-sm @error('precio_venta') is-invalid @enderror" 
                                       id="precio_venta" 
                                       name="precio_venta" 
                                       value="{{ old('precio_venta', '0.00') }}" 
                                       required>
                                @error('precio_venta')
                                    <div class="invalid-feedback small">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <!-- Stock Mínimo y Nota de Stock Inicial -->
                        <div class="row g-2 mb-4">
                            <div class="col-sm-6">
                                <label for="stock_minimo" class="form-label">Stock mínimo de alerta <span class="text-danger">*</span></label>
                                <input type="number" 
                                       step="1" 
                                       min="0"
                                       class="form-control form-control-sm @error('stock_minimo') is-invalid @enderror" 
                                       id="stock_minimo" 
                                       name="stock_minimo" 
                                       value="{{ old('stock_minimo', '0') }}" 
                                       required>
                                @error('stock_minimo')
                                    <div class="invalid-feedback small">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-sm-6 d-flex align-items-center">
                                <div class="alert alert-light border py-2 px-3 mb-0 small text-muted w-100">
                                    <i class="bi bi-info-circle me-1 text-primary"></i>
                                    El stock inicial comenzará automáticamente en <strong>0</strong>. Podrás aumentarlo registrando un ingreso de mercadería.
                                </div>
                            </div>
                        </div>

                        <!-- Botones de Acción -->
                        <div class="d-flex justify-content-end gap-2 pt-2 border-top">
                            <a href="{{ route('productos.index') }}" class="btn btn-outline-secondary btn-sm">
                                Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary btn-sm px-3">
                                Guardar Producto
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
