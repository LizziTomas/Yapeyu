<x-app-layout>
    <div class="mb-4">
        <h1 class="h4 mb-1 fw-semibold">Dashboard</h1>
        <p class="text-muted small mb-0">Panel de control de Casa Yacobone</p>
    </div>

    <div class="row g-3">
        <div class="col-md-6 col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <h2 class="h6 fw-semibold text-muted text-uppercase mb-3">Información de Sesión</h2>
                    <div class="mb-2">
                        <span class="text-muted small d-block">Usuario</span>
                        <span class="fw-medium">{{ Auth::user()->name }}</span>
                    </div>
                    <div class="mb-2">
                        <span class="text-muted small d-block">Correo electrónico</span>
                        <span>{{ Auth::user()->email }}</span>
                    </div>
                    <div class="mb-3">
                        <span class="text-muted small d-block">Rol asignado</span>
                        <span class="badge {{ Auth::user()->isAdmin() ? 'bg-primary' : 'bg-secondary' }}">
                            {{ ucfirst(Auth::user()->role) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                    <div>
                        <h2 class="h6 fw-semibold text-muted text-uppercase mb-3">Productos y Stock</h2>
                        <p class="text-muted small">
                            {{ Auth::user()->isAdmin() ? 'Administra el catálogo de productos, precios, niveles de stock y valorización del inventario.' : 'Consulta el catálogo de productos activos, precios de venta y disponibilidad de stock en tiempo real.' }}
                        </p>
                    </div>
                    <div>
                        <a href="{{ route('productos.index') }}" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-box-seam me-1"></i> Ir a Productos
                        </a>
                    </div>
                </div>
            </div>
        </div>

        @if (Auth::user()->isAdmin())
            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4 d-flex flex-column justify-content-between">
                        <div>
                            <h2 class="h6 fw-semibold text-muted text-uppercase mb-3">Administración</h2>
                            <p class="text-muted small">
                                Como administrador, tienes acceso a la gestión integral de usuarios del sistema (crear, editar, asignar roles y eliminar).
                            </p>
                        </div>
                        <div>
                            <a href="{{ route('usuarios.index') }}" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-people me-1"></i> Ir a Gestión de Usuarios
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
