<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Casa Yacobone') }}</title>

    <!-- Bootstrap 5 CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        body {
            background-color: #f8f9fa;
            color: #212529;
            font-size: 0.95rem;
        }
        .navbar-brand {
            font-weight: 600;
            letter-spacing: -0.5px;
        }
        .table-responsive {
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            background-color: #ffffff;
        }
        .form-label {
            font-weight: 500;
            margin-bottom: 0.3rem;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm py-2">
        <div class="container">
            <a class="navbar-brand" href="{{ route('dashboard') }}">
                Casa Yacobone
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Alternar navegación">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarMain">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('productos.*') ? 'active' : '' }}" href="{{ route('productos.index') }}">
                            Productos
                        </a>
                    </li>
                    @can('ver cajas')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('cajas.*') ? 'active' : '' }}" href="{{ route('cajas.index') }}">
                                Cajas
                            </a>
                        </li>
                    @endcan
                    @if (Auth::check() && Auth::user()->hasRole('admin'))
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('usuarios.*') ? 'active' : '' }}" href="{{ route('usuarios.index') }}">
                                Usuarios
                            </a>
                        </li>
                    @endif
                </ul>

                <ul class="navbar-nav ms-auto align-items-lg-center">
                    @auth
                        <li class="nav-item me-lg-3 text-white-50 small mb-2 mb-lg-0">
                            <span class="text-white fw-semibold">{{ Auth::user()->name }}</span>
                            <span class="badge {{ Auth::user()->hasRole('admin') ? 'bg-primary' : 'bg-secondary' }} ms-1">
                                {{ ucfirst(Auth::user()->getRoleNames()->first() ?? 'Usuario') }}
                            </span>
                        </li>
                        <li class="nav-item">
                            <form method="POST" action="{{ route('logout') }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-outline-light btn-sm">
                                    Cerrar sesión
                                </button>
                            </form>
                        </li>
                    @else
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('login') }}">Iniciar sesión</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('register') }}">Registrarse</a>
                        </li>
                    @endauth
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Container -->
    <main class="container py-4">
        {{-- Mensajes Flash --}}
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show py-2 px-3 mb-3 small" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close py-2 px-3" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show py-2 px-3 mb-3 small" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close py-2 px-3" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        @endif

        @isset($slot)
            {{ $slot }}
        @else
            @yield('content')
        @endisset
    </main>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
