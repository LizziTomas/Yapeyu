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
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .auth-container {
            width: 100%;
            max-width: 420px;
            padding: 1.5rem;
        }
        .auth-card {
            background-color: #ffffff;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            padding: 1.75rem;
        }
        .form-label {
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="text-center mb-3">
            <a href="/" class="text-decoration-none text-dark fs-4 fw-bold">
                Casa Yacobone
            </a>
        </div>

        <div class="auth-card shadow-sm">
            @isset($slot)
                {{ $slot }}
            @else
                @yield('content')
            @endisset
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
