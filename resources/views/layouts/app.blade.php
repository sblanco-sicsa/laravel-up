<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Panel de Sincronización')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Font Awesome & Bootstrap Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

    <style>
        body {
            overflow-x: hidden;
            background-color: #f4f6f9;
            font-size: 0.875rem;
        }

        .sidebar {
            background-color: #0f1c47;
            min-height: 100vh;
            padding-top: 1rem;
            position: fixed;
            width: 230px;
            z-index: 1000;
        }

        .sidebar .nav-link {
            color: #ffffff;
            margin-bottom: 0.5rem;
        }

        .sidebar .nav-link.active,
        .sidebar .nav-link:hover {
            background-color: #3b4cca;
            font-weight: bold;
            border-radius: 0.375rem;
        }

        .content-wrapper {
            margin-left: 230px;
            padding: 2rem;
        }

        .navbar-brand {
            font-weight: bold;
            color: white;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease-in-out;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .content-wrapper {
                margin-left: 0;
                padding: 1rem;
            }

            .sidebar-toggler {
                display: inline-block;
            }
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-dark bg-dark px-3">
        <button class="btn btn-outline-light d-md-none sidebar-toggler me-2" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <a class="navbar-brand" href="#">Desarrollo para Sincronización</a>
    </nav>

    <div class="sidebar d-flex flex-column" id="sidebar">
        <h5 class="text-white text-center mb-4">
            <i class="bi bi-ui-checks-grid me-2"></i> Panel
        </h5>
        <ul class="nav flex-column px-3">
            <li class="nav-item">
                <a href="{{ route('sync-errors.index') }}"
                    class="nav-link {{ request()->routeIs('sync-errors.index') ? 'active' : '' }}">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> Errores de Sincronización
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('sync-history.index') }}"
                    class="nav-link {{ request()->routeIs('sync-history.index') ? 'active' : '' }}">
                    <i class="bi bi-clock-history me-2"></i> Historial de Sincronizaciones
                </a>
            </li>
        </ul>
    </div>

    <div class="content-wrapper">
        @yield('content')
    </div>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
        }
    </script>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    @stack('scripts')
</body>

</html>