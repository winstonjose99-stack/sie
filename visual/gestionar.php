<?php
require_once __DIR__ . '/../config/conexion.php';
verificarSesionAdmin('iniciar_sesion.html');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión y Modificación de Registros</title>
    <link rel="stylesheet" href="../css/estilos.css">
</head>
<body>

    <header class="header-fijo">
        <div class="logo">
            <img src="logo.png" alt="Logo">
        </div>
        <h2>GESTIÓN DE REGISTROS</h2>
        <nav>
            <ul>
                <li><a href="../api/dashboard.php">Panel Principal</a></li>
                <li><a href="../api/logout.php">Cerrar Sesión</a></li>
            </ul>
        </nav>
    </header>

    <div class="container">
        <h2>Panel de Gestión, Edición y Eliminación</h2>
        <p>Seleccione el módulo que desea administrar para consultar, actualizar o depurar los registros almacenados:</p>

        <div class="grid-gestion">
            <a href="editar_institucion.php" class="card-gestion">
                <div class="icon-box">🏫</div>
                <h3>Instituciones</h3>
                <p>Consultar, editar o eliminar sedes y planteles educativos registrados.</p>
            </a>

            <a href="profesores.php" class="card-gestion">
                <div class="icon-box">👨‍🏫</div>
                <h3>Profesores</h3>
                <p>Actualizar datos personales, correo y plantel asignado a los docentes.</p>
            </a>

            <a href="materias.php" class="card-gestion">
                <div class="icon-box">📚</div>
                <h3>Materias</h3>
                <p>Administrar el catálogo de asignaturas y su asignación docente.</p>
            </a>

            <a href="secciones.php" class="card-gestion">
                <div class="icon-box">🏷️</div>
                <h3>Secciones</h3>
                <p>Gestionar y administrar grupos, años o secciones escolares por período.</p>
            </a>

            <a href="representantes.php" class="card-gestion">
                <div class="icon-box">👨‍👩‍👧</div>
                <h3>Representantes</h3>
                <p>Gestionar tutores legales, números de teléfono WhatsApp y direcciones.</p>
            </a>

            <a href="estudiantes.php" class="card-gestion">
                <div class="icon-box">👨‍🎓</div>
                <h3>Estudiantes</h3>
                <p>Administrar la matrícula estudiantil, cambios de sección y representantes.</p>
            </a>
        </div>
    </div>

</body>
</html>
