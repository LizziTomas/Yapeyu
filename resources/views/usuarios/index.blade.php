<x-app-layout>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
        <h1 class="h4 mb-0 fw-semibold">Gestión de Usuarios</h1>
        <a href="{{ route('usuarios.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i> Nuevo Usuario
        </a>
    </div>

    <!-- Filtro de Búsqueda -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('usuarios.index') }}" class="row g-2 align-items-center">
                <div class="col-sm-8 col-md-6 col-lg-4">
                    <input type="text" 
                           name="search" 
                           class="form-control form-control-sm" 
                           placeholder="Buscar por nombre, email o rol..." 
                           value="{{ request('search') }}">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-secondary btn-sm">
                        Buscar
                    </button>
                    @if(request('search'))
                        <a href="{{ route('usuarios.index') }}" class="btn btn-outline-secondary btn-sm ms-1">
                            Limpiar
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla de Usuarios -->
    <div class="table-responsive shadow-sm">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col" class="ps-3" style="width: 70px;">ID</th>
                    <th scope="col">Nombre</th>
                    <th scope="col">Correo electrónico</th>
                    <th scope="col" style="width: 140px;">Rol</th>
                    <th scope="col" style="width: 160px;">Registrado</th>
                    <th scope="col" class="text-end pe-3" style="width: 150px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td class="ps-3 text-muted small">#{{ $user->id }}</td>
                        <td class="fw-medium">
                            {{ $user->name }}
                            @if ($user->id === Auth::id())
                                <span class="badge bg-light text-dark border ms-1">Tú</span>
                            @endif
                        </td>
                        <td>{{ $user->email }}</td>
                        <td>
                            @if ($user->isAdmin())
                                <span class="badge bg-primary">Administrador</span>
                            @else
                                <span class="badge bg-secondary">Vendedor</span>
                            @endif
                        </td>
                        <td class="small text-muted">
                            {{ $user->created_at ? $user->created_at->format('d/m/Y H:i') : '-' }}
                        </td>
                        <td class="text-end pe-3">
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="{{ route('usuarios.edit', $user) }}" 
                                   class="btn btn-outline-primary btn-sm py-0 px-2" 
                                   title="Editar usuario">
                                    Editar
                                </a>

                                @if ($user->id !== Auth::id())
                                    <form method="POST" 
                                          action="{{ route('usuarios.destroy', $user) }}" 
                                          class="d-inline"
                                          onsubmit="return confirm('¿Está seguro de eliminar al usuario {{ $user->name }}? Esta acción no se puede deshacer.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" 
                                                class="btn btn-outline-danger btn-sm py-0 px-2 ms-1" 
                                                title="Eliminar usuario">
                                            Eliminar
                                        </button>
                                    </form>
                                @else
                                    <button type="button" 
                                            class="btn btn-outline-secondary btn-sm py-0 px-2 ms-1" 
                                            disabled 
                                            title="No puedes eliminarte a ti mismo">
                                        Eliminar
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            No se encontraron usuarios registrados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Paginación -->
    @if ($users->hasPages())
        <div class="mt-3 d-flex justify-content-center">
            {{ $users->links('pagination::bootstrap-5') }}
        </div>
    @endif
</x-app-layout>
