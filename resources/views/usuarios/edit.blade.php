<x-app-layout>
    <div class="row justify-content-center">
        <div class="col-lg-7 col-md-9">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h1 class="h4 mb-0 fw-semibold">Editar Usuario</h1>
                <a href="{{ route('usuarios.index') }}" class="btn btn-outline-secondary btn-sm">
                    Volver a la lista
                </a>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('usuarios.update', $user) }}">
                        @csrf
                        @method('PUT')

                        <!-- Nombre -->
                        <div class="mb-3">
                            <label for="name" class="form-label">Nombre completo</label>
                            <input type="text" 
                                   class="form-control form-control-sm @error('name') is-invalid @enderror" 
                                   id="name" 
                                   name="name" 
                                   value="{{ old('name', $user->name) }}" 
                                   required 
                                   autofocus>
                            @error('name')
                                <div class="invalid-feedback small">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Email -->
                        <div class="mb-3">
                            <label for="email" class="form-label">Correo electrónico</label>
                            <input type="email" 
                                   class="form-control form-control-sm @error('email') is-invalid @enderror" 
                                   id="email" 
                                   name="email" 
                                   value="{{ old('email', $user->email) }}" 
                                   required>
                            @error('email')
                                <div class="invalid-feedback small">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Rol -->
                        <div class="mb-3">
                            <label for="role" class="form-label">Rol del usuario</label>
                            <select class="form-select form-select-sm @error('role') is-invalid @enderror" 
                                    id="role" 
                                    name="role" 
                                    required>
                                <option value="vendedor" {{ old('role', $user->getRoleNames()->first()) === 'vendedor' ? 'selected' : '' }}>Vendedor</option>
                                <option value="admin" {{ old('role', $user->getRoleNames()->first()) === 'admin' ? 'selected' : '' }}>Administrador</option>
                            </select>
                            @error('role')
                                <div class="invalid-feedback small">{{ $message }}</div>
                            @enderror
                            @if ($user->id === Auth::id())
                                <div class="form-text small text-muted">
                                    Nota: Estás editando tu propia cuenta. Si cambias tu rol a vendedor perderás los privilegios de administrador.
                                </div>
                            @endif
                        </div>

                        <hr class="my-3 text-muted">

                        <!-- Cambio opcional de contraseña -->
                        <div class="mb-2">
                            <span class="fw-medium small text-muted d-block mb-2">Cambiar contraseña (opcional)</span>
                        </div>

                        <div class="row g-2 mb-4">
                            <div class="col-sm-6">
                                <label for="password" class="form-label">Nueva contraseña</label>
                                <input type="password" 
                                       class="form-control form-control-sm @error('password') is-invalid @enderror" 
                                       id="password" 
                                       name="password"
                                       placeholder="Dejar en blanco para mantener la actual">
                                @error('password')
                                    <div class="invalid-feedback small">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-sm-6">
                                <label for="password_confirmation" class="form-label">Confirmar nueva contraseña</label>
                                <input type="password" 
                                       class="form-control form-control-sm" 
                                       id="password_confirmation" 
                                       name="password_confirmation"
                                       placeholder="Repetir nueva contraseña">
                            </div>
                        </div>

                        <!-- Botones de Acción -->
                        <div class="d-flex justify-content-end gap-2 pt-2 border-top">
                            <a href="{{ route('usuarios.index') }}" class="btn btn-outline-secondary btn-sm">
                                Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary btn-sm px-3">
                                Actualizar Usuario
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
