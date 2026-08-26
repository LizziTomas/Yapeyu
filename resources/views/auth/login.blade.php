<x-guest-layout>
    <h1 class="h4 text-center mb-3 fw-semibold">Iniciar sesión</h1>

    @if (session('status'))
        <div class="alert alert-success py-2 px-3 mb-3 small" role="alert">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email -->
        <div class="mb-3">
            <label for="email" class="form-label">Correo electrónico</label>
            <input type="email" 
                   class="form-control form-control-sm @error('email') is-invalid @enderror" 
                   id="email" 
                   name="email" 
                   value="{{ old('email') }}" 
                   required 
                   autofocus 
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
                   autocomplete="current-password">
            @error('password')
                <div class="invalid-feedback small">{{ $message }}</div>
            @enderror
        </div>

        <!-- Recordarme -->
        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="remember" name="remember">
            <label class="form-check-label small" for="remember">Recordarme en este equipo</label>
        </div>

        <!-- Botón de acción -->
        <div class="d-grid mb-3">
            <button type="submit" class="btn btn-primary btn-sm py-2">
                Ingresar
            </button>
        </div>

        <!-- Enlace a registro -->
        <div class="text-center">
            <span class="small text-muted">¿No tienes cuenta?</span>
            <a href="{{ route('register') }}" class="small text-decoration-none">Registrarse</a>
        </div>
    </form>
</x-guest-layout>
