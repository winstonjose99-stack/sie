<?php
require_once __DIR__ . '/config/conexion.php';
iniciarSesionSegura();

// Si el administrador ya inició sesión, redirigimos a su panel de control
if (isset($_SESSION['id'])) {
    header("Location: api/dashboard.php");
    exit();
}

// Si un docente ya inició sesión, redirigimos al panel docente
if (isset($_SESSION['id_docente'])) {
    header("Location: visual/panel_profesor.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Asistencias Escolares - Portal de Acceso</title>
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .welcome-box {
            max-width: 480px;
            width: 100%;
            margin-bottom: 0;
        }
        .welcome-box h1 {
            margin: 12px 0 10px 0;
            font-size: 1.85rem;
        }
        .welcome-box p {
            margin-bottom: 26px;
            font-size: 0.96rem;
        }
        .btn-ingresar {
            margin-bottom: 12px;
        }
        .btn-secundario {
            background: linear-gradient(135deg, var(--success) 0%, var(--success-hover) 100%);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.22);
        }
        .btn-secundario:hover {
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.32);
        }
    </style>
</head>
<body>

    <div class="welcome-box">
        <span class="hero-badge">Plataforma Escolar SIE</span>
        <h1>Sistema de Asistencias</h1>
        <p>Plataforma de gestión gerencial, control estudiantil por secciones y notificaciones automatizadas vía WhatsApp.</p>

        <a href="visual/iniciar_sesion.html" class="btn-ingresar">Acceso Administración</a>
        <a href="visual/login_profesor.php" class="btn-ingresar btn-secundario">Acceso Profesores</a>

        <div class="auth-footer">
            <span>¿Desea conocer más sobre la plataforma? <a href="visual/inicio.html">Ir a la portada principal</a></span>
        </div>
    </div>

</body>
</html>
