<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ configuracion()->nombre_local }}</title>

    <script>
        // Aplica el tema guardado (claro u oscuro) antes de dibujar la página para evitar parpadeos.
        try { document.documentElement.dataset.bsTheme = localStorage.getItem('tema') || 'light'; } catch (e) {}
    </script>

    <!-- Bootstrap 5 CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <!-- Tipografía -->
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* Colores propios del sistema, con su versión para modo oscuro */
        :root {
            --app-bg: #e9edef;
            --app-line: #d9dfe2;
            --app-head-bg: #f7f9fa;
            --app-tile: #1f2a30;
            --app-tile-text: #ffffff;
            --app-ok-bg: #f1f7f4;
            --app-ok-line: #d7e9df;
            --sb-bg: #dde3e6;
            --sb-text: #34414a;
            --sb-strong: #1f2a30;
            --sb-hover: rgba(31, 42, 48, 0.06);
            --sb-active: #ffffff;
            --sb-border: #ccd4d8;
        }
        [data-bs-theme="dark"] {
            --bs-body-bg: #1c2327;
            --bs-tertiary-bg: #242d32;
            --bs-border-color: #33404a;
            --app-bg: #141a1d;
            --app-line: #2c373e;
            --app-head-bg: transparent;
            --app-tile: #273239;
            --app-tile-text: #d5dde1;
            --app-ok-bg: rgba(25, 135, 84, 0.12);
            --app-ok-line: rgba(25, 135, 84, 0.35);
            --sb-bg: #1f2a30;
            --sb-text: #c9d3d8;
            --sb-strong: #ffffff;
            --sb-hover: rgba(255, 255, 255, 0.06);
            --sb-active: rgba(224, 165, 38, 0.14);
            --sb-border: #2c3a42;
        }

        body {
            background-color: var(--app-bg);
            font-family: 'IBM Plex Sans', system-ui, sans-serif;
            font-size: 0.95rem;
        }
        .table-responsive {
            border: 1px solid var(--bs-border-color);
            border-radius: 0.375rem;
            background-color: var(--bs-body-bg);
        }
        .form-label {
            font-weight: 500;
            margin-bottom: 0.3rem;
        }
        .card {
            --bs-card-border-color: var(--app-line);
        }

        /* Modo oscuro: adapta las clases de color fijo que usan las pantallas existentes */
        [data-bs-theme="dark"] .bg-white { background-color: var(--bs-body-bg) !important; }
        [data-bs-theme="dark"] .bg-light { background-color: var(--bs-tertiary-bg) !important; }
        [data-bs-theme="dark"] .text-dark { color: var(--bs-body-color) !important; }
        [data-bs-theme="dark"] .table-light {
            --bs-table-bg: var(--bs-tertiary-bg);
            --bs-table-color: var(--bs-body-color);
            --bs-table-border-color: var(--bs-border-color);
        }

        /* Barra lateral */
        .sidebar {
            --bs-offcanvas-width: 248px;
            --sb-accent: #e0a526;
        }
        .sidebar-inner {
            background-color: var(--sb-bg);
            color: var(--sb-text);
            border-right: 1px solid var(--sb-border);
        }
        .sidebar-brand {
            color: var(--sb-strong);
            font-weight: 600;
            font-size: 1.05rem;
            text-decoration: none;
        }
        .sidebar-brand .bi {
            color: var(--sb-accent);
        }
        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.55rem 0.85rem;
            border-radius: 0.4rem;
            color: var(--sb-text);
            text-decoration: none;
        }
        .sidebar-link:hover {
            background-color: var(--sb-hover);
            color: var(--sb-strong);
        }
        .sidebar-link.active {
            background-color: var(--sb-active);
            color: var(--sb-strong);
            font-weight: 500;
            box-shadow: inset 3px 0 0 var(--sb-accent);
        }
        .sidebar-link.active .bi {
            color: var(--sb-accent);
        }
        .sidebar-head {
            border-bottom: 1px solid var(--sb-border);
        }
        .sidebar-footer {
            border-top: 1px solid var(--sb-border);
        }
        @media (min-width: 992px) {
            .sidebar {
                position: sticky;
                top: 0;
                width: 248px;
                height: 100vh;
                flex-shrink: 0;
            }
        }

        /* Usuario en la barra lateral: burbuja con inicial y rol */
        .sidebar-avatar {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            width: 2.25rem;
            height: 2.25rem;
            border-radius: 50%;
            background-color: var(--sb-accent);
            color: #1f2a30;
            font-weight: 600;
        }
        .sidebar-role {
            display: inline-block;
            padding: 0.05rem 0.5rem;
            border-radius: 999px;
            background-color: var(--sb-active);
            color: var(--sb-strong);
            font-size: 0.7rem;
            font-weight: 500;
        }

        /* Tarjetas del dashboard */
        .card-panel {
            --bs-card-border-color: var(--app-line);
            --bs-card-border-radius: 0.75rem;
            --bs-card-inner-border-radius: calc(0.75rem - 1px);
            --bs-card-cap-bg: var(--app-head-bg);
            --bs-card-cap-padding-y: 0.9rem;
            --bs-card-cap-padding-x: 1.25rem;
            box-shadow: 0 1px 2px rgba(31, 42, 48, 0.04), 0 4px 12px rgba(31, 42, 48, 0.04);
            overflow: hidden;
        }
        .card-panel .card-header {
            border-bottom-color: var(--app-line);
        }
        .card-alerta {
            box-shadow: inset 3px 0 0 var(--bs-danger), 0 1px 2px rgba(31, 42, 48, 0.04);
        }
        .icon-tile {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            width: 2.25rem;
            height: 2.25rem;
            border-radius: 0.6rem;
            background-color: var(--app-tile);
            color: var(--app-tile-text);
        }
        .icon-tile-sm {
            width: 1.85rem;
            height: 1.85rem;
            border-radius: 0.5rem;
            font-size: 0.9rem;
        }
        .kpi-value {
            font-size: clamp(1.25rem, 4vw, 1.65rem);
            font-weight: 600;
            line-height: 1.2;
        }
        .caja-destacado {
            background-color: var(--app-ok-bg);
            border: 1px solid var(--app-ok-line);
        }
        .tabular {
            font-variant-numeric: tabular-nums;
        }
    </style>
</head>
<body>
    <div class="d-lg-flex">
        <!-- Barra superior solo en celulares -->
        <header class="d-flex d-lg-none align-items-center gap-2 bg-body border-bottom px-3 py-2 sticky-top">
            <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar" aria-label="Abrir menú">
                <i class="bi bi-list fs-5"></i>
            </button>
            <span class="fw-semibold">{{ configuracion()->nombre_local }}</span>
        </header>

        <!-- Barra lateral -->
        <aside id="sidebar" class="sidebar offcanvas-lg offcanvas-start" tabindex="-1" aria-label="Menú principal">
            <div class="sidebar-inner d-flex flex-column h-100 w-100">
                <div class="sidebar-head d-flex align-items-center justify-content-between px-3 py-3 mb-2">
                    <a class="sidebar-brand d-flex align-items-center gap-2" href="{{ route('dashboard') }}">
                        <i class="bi bi-shop fs-4"></i> {{ configuracion()->nombre_local }}
                    </a>
                    <button type="button" class="btn-close d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Cerrar menú"></button>
                </div>

                <nav class="flex-grow-1 overflow-auto px-2">
                    <ul class="list-unstyled d-flex flex-column gap-1 mb-0">
                        <li>
                            <a class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li>
                            <a class="sidebar-link {{ request()->routeIs('productos.*') ? 'active' : '' }}" href="{{ route('productos.index') }}">
                                <i class="bi bi-box-seam"></i> Productos
                            </a>
                        </li>
                        @can('ver movimientos stock')
                            <li>
                                <a class="sidebar-link {{ request()->routeIs('movimientos-stock.*') ? 'active' : '' }}" href="{{ route('movimientos-stock.index') }}">
                                    <i class="bi bi-arrow-left-right"></i> Movimientos Stock
                                </a>
                            </li>
                        @endcan
                        @can('ver cajas')
                            <li>
                                <a class="sidebar-link {{ request()->routeIs('cajas.*') ? 'active' : '' }}" href="{{ route('cajas.index') }}">
                                    <i class="bi bi-cash-stack"></i> Cajas
                                </a>
                            </li>
                        @endcan
                        @can('ver ventas')
                            <li>
                                <a class="sidebar-link {{ request()->routeIs('ventas.*') ? 'active' : '' }}" href="{{ route('ventas.index') }}">
                                    <i class="bi bi-receipt"></i> Ventas
                                </a>
                            </li>
                        @endcan
                        @if (Auth::check() && Auth::user()->hasRole('admin'))
                            <li>
                                <a class="sidebar-link {{ request()->routeIs('usuarios.*') ? 'active' : '' }}" href="{{ route('usuarios.index') }}">
                                    <i class="bi bi-people"></i> Usuarios
                                </a>
                            </li>
                        @endif
                    </ul>
                </nav>

                @auth
                    <!-- Usuario y panel de configuración -->
                    <div class="sidebar-footer d-flex align-items-center gap-2 px-3 py-3">
                        <span class="sidebar-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(Auth::user()->name, 0, 1)) }}</span>
                        <div class="flex-grow-1 text-truncate" style="min-width: 0">
                            <div class="fw-semibold small text-truncate" style="color: var(--sb-strong)">{{ Auth::user()->name }}</div>
                            <span class="sidebar-role">{{ ucfirst(Auth::user()->getRoleNames()->first() ?? 'Usuario') }}</span>
                        </div>
                        <div class="dropup">
                            <button class="btn btn-sm sidebar-link px-2" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Configuración">
                                <i class="bi bi-gear fs-5"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow">
                                <li><h6 class="dropdown-header">Apariencia</h6></li>
                                <li>
                                    <button type="button" class="dropdown-item d-flex align-items-center gap-2" data-tema="light">
                                        <i class="bi bi-sun"></i> Claro
                                    </button>
                                </li>
                                <li>
                                    <button type="button" class="dropdown-item d-flex align-items-center gap-2" data-tema="dark">
                                        <i class="bi bi-moon"></i> Oscuro
                                    </button>
                                </li>
                                @if (Auth::user()->hasRole('admin'))
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('configuracion.edit') }}">
                                            <i class="bi bi-sliders"></i> Configuración del local
                                        </a>
                                    </li>
                                @endif
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="dropdown-item d-flex align-items-center gap-2">
                                            <i class="bi bi-box-arrow-right"></i> Cerrar sesión
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </div>
                @endauth
            </div>
        </aside>

        <script>
            // Botones del panel de configuración para cambiar entre modo claro y oscuro.
            document.querySelectorAll('[data-tema]').forEach(function (btn) {
                btn.classList.toggle('active', btn.dataset.tema === document.documentElement.dataset.bsTheme);
                btn.addEventListener('click', function () {
                    document.documentElement.dataset.bsTheme = btn.dataset.tema;
                    try { localStorage.setItem('tema', btn.dataset.tema); } catch (e) {}
                    document.querySelectorAll('[data-tema]').forEach(function (otro) {
                        otro.classList.toggle('active', otro === btn);
                    });
                });
            });
        </script>

        <!-- Contenido principal -->
        <main class="flex-grow-1 px-3 px-lg-4 py-4" style="min-width: 0">
            <div class="mx-auto" style="max-width: 1280px">
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
            </div>
        </main>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
