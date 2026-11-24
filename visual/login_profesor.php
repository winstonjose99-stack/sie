<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Profesores - Control de Asistencias</title>
    <link rel="stylesheet" href="../css/estilos.css">
    <style>
        .auth-badge-docente {
            display: inline-flex;
            align-items: center;
            padding: 5px 14px;
            border-radius: 999px;
            background: var(--primary-soft);
            color: var(--primary);
            border: 1px solid #bfdbfe;
            font-size: 0.76rem;
            font-weight: 800;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 12px;
        }
        .auth-card h2 {
            margin-bottom: 6px;
        }
        .auth-footer span {
            line-height: 1.45;
        }
        @media (max-width: 768px) {
            .auth-card form > div {
                margin-bottom: 14px;
            }
            .auth-card label {
                font-size: 0.84rem;
            }
        }
    </style>
</head>
<body class="portal-docente">
    <header class="header-fijo" id="appHeader">
        <div class="header-brand">
            <a href="inicio.html" class="logo" aria-label="Inicio del sistema">
                <img src="../visual/logo.png" alt="Logo">
            </a>
            <h2 class="header-title">Portal Docente</h2>
        </div>

        <div class="header-mobile-controls">
            <button type="button" class="nav-toggle" id="navToggle" aria-label="Abrir menú de navegación" aria-expanded="false" aria-controls="headerNav">
                <span class="nav-toggle-bar"></span>
                <span class="nav-toggle-bar"></span>
                <span class="nav-toggle-bar"></span>
            </button>
        </div>

        <nav class="header-nav" id="headerNav" aria-label="Navegación acceso docente">
            <ul>
                <li><a href="inicio.html">Inicio</a></li>
                <li><a href="login_representante.php">Portal Representantes</a></li>
                <li><a href="iniciar_sesion.html">Administración</a></li>
            </ul>
        </nav>
    </header>
    <div class="nav-backdrop" id="navBackdrop" aria-hidden="true"></div>

    <main class="auth-wrapper">
        <div class="auth-card">
            <span class="auth-badge-docente">Acceso Institucional</span>
            <h2>Portal Docente</h2>
            <p class="auth-subtitle">Acceda con su cédula y correo institucional para registrar la asistencia de sus secciones.</p>

            <form action="../api/procesar_login_profesor.php" method="POST">
                <div>
                    <label for="cedula">Cédula de Identidad:</label>
                    <input type="text" id="cedula" name="cedula" inputmode="numeric" placeholder="Ingrese su Cédula (solo números)" required>
                </div>

                <div>
                    <label for="correo">Correo Electrónico:</label>
                    <input type="email" id="correo" name="correo" autocomplete="email" placeholder="docente@institucion.edu.ve" required>
                </div>

                <button type="submit">Ingresar al Sistema</button>
            </form>

            <div class="auth-footer">
                <span>¿Es usted representante? <a href="login_representante.php">Acceso Portal Representantes</a></span>
                <span>¿Pertenece al personal directivo? <a href="iniciar_sesion.html">Acceso Administración</a></span>
            </div>
        </div>
    </main>

    <script>
        (function() {
            var toggle = document.getElementById('navToggle');
            var nav = document.getElementById('headerNav');
            var backdrop = document.getElementById('navBackdrop');
            if (!toggle || !nav) return;

            function setMenu(open) {
                nav.classList.toggle('is-open', open);
                if (backdrop) backdrop.classList.toggle('is-open', open);
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            }

            toggle.addEventListener('click', function() {
                setMenu(!nav.classList.contains('is-open'));
            });
            if (backdrop) {
                backdrop.addEventListener('click', function() { setMenu(false); });
            }
            nav.querySelectorAll('a').forEach(function(link) {
                link.addEventListener('click', function() { setMenu(false); });
            });
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') setMenu(false);
            });
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) setMenu(false);
            });
        })();
    </script>
</body>
</html>
