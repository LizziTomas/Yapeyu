<x-guest-layout>
    <h1 class="h4 text-center mb-3 fw-semibold">Crear cuenta</h1>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <!-- Nombre -->
        <div class="mb-3">
            <label for="name" class="form-label">Nombre completo</label>
            <input type="text" 
                   class="form-control form-control-sm @error('name') is-invalid @enderror" 
                   id="name" 
                   name="name" 
                   value="{{ old('name') }}" 
                   required 
                   autofocus 
                   autocomplete="name">
            @error('name')
                <div class="invalid-feedback small">{{ $message }}</div>
            @enderror
        </div>

        <!-- Correo Electrónico -->
        <div class="mb-3">
            <label for="email" class="form-label">Correo electrónico</label>
            <input type="email" 
                   class="form-control form-control-sm @error('email') is-invalid @enderror" 
                   id="email" 
                   name="email" 
                   value="{{ old('email') }}" 
                   required 
                   autocomplete="username">
            @error('email')
                <div class="invalid-feedback small">{{ $message }}</div>
            @enderror
        </div>

        <!-- Contraseña -->
        <div class="mb-3">
            <label for="password" class="form-label">Contraseña</label>
            <input type="password" 
                   class="form-control form-control-sm @error('password') is-invalid @enderror" 
                   id="password" 
                   name="password" 
                   required 
                   autocomplete="new-password">
            @error('password')
                <div class="invalid-feedback small">{{ $message }}</div>
            @enderror
        </div>

        <!-- Confirmar Contraseña -->
        <div class="mb-3">
            <label for="password_confirmation" class="form-label">Confirmar contraseña</label>
            <input type="password" 
                   class="form-control form-control-sm @error('password_confirmation') is-invalid @enderror" 
                   id="password_confirmation" 
                   name="password_confirmation" 
                   required 
                   autocomplete="new-password">
            @error('password_confirmation')
                <div class="invalid-feedback small">{{ $message }}</div>
            @enderror
        </div>

        <!-- Botón de acción -->
        <div class="d-grid mb-3">
            <button type="submit" class="btn btn-primary btn-sm py-2">
                Registrarse
            </button>
        </div>

        <!-- Enlace a login -->
        <div class="text-center">
            <span class="small text-muted">¿Ya tienes una cuenta?</span>
            <a href="{{ route('login') }}" class="small text-decoration-none">Iniciar sesión</a>
        </div>
    </form>
</x-guest-layout>
