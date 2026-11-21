<?php
require_once __DIR__ . '/../config/conexion.php';
verificarSesionAdmin('iniciar_sesion.html');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nuevo_periodo_nombre = trim($_POST['nombre_periodo'] ?? '');

    if ($nuevo_periodo_nombre !== '') {
        $conn->begin_transaction();

        try {
            // 1. Desactivar períodos actuales
            $conn->query("UPDATE periodos_academicos SET activo = 0");

            // 2. Insertar y activar el nuevo período
            $stmt_nuevo = $conn->prepare("INSERT INTO periodos_academicos (nombre_periodo, activo) VALUES (?, 1)");
            $stmt_nuevo->bind_param("s", $nuevo_periodo_nombre);
            if (!$stmt_nuevo->execute()) {
                throw new RuntimeException('No se pudo crear el nuevo período.');
            }
            $id_nuevo_periodo = (int) $stmt_nuevo->insert_id;
            $stmt_nuevo->close();

            // 3. Migrar secciones y estudiantes al nuevo período activo
            $stmt_sec = $conn->prepare("UPDATE secciones SET id_periodo = ?");
            $stmt_sec->bind_param("i", $id_nuevo_periodo);
            if (!$stmt_sec->execute()) {
                throw new RuntimeException('No se pudieron migrar las secciones.');
            }
            $stmt_sec->close();

            $stmt_est = $conn->prepare("UPDATE estudiantes SET id_periodo = ?");
            $stmt_est->bind_param("i", $id_nuevo_periodo);
            if (!$stmt_est->execute()) {
                throw new RuntimeException('No se pudieron migrar los estudiantes.');
            }
            $stmt_est->close();

            $conn->commit();
            $conn->close();
            alertaYRedirigir('¡Nuevo período académico aperturado! Secciones y estudiantes promovidos exitosamente.', 'admin_periodos.php');
        } catch (Throwable $e) {
            $conn->rollback();
            alertaYRedirigir('Error al aperturar el nuevo período: ' . $e->getMessage());
        }
    } else {
        alertaYRedirigir('Debe indicar el nombre del nuevo período.');
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Aperturar Nuevo Período Académico</title>
    <link rel="stylesheet" href="../css/estilos.css">
    <style>
        .container { max-width: 520px; }
        .container form { box-shadow: none; border: none; padding: 10px 0; }
        .btn-volver { margin-top: 16px; background: transparent; color: var(--text-muted); }
        .btn-volver:hover { background: #f1f5f9; color: var(--text-main); }
    </style>
</head>
<body>

    <header class="header-fijo">
        <div class="logo">
            <img src="logo.png" alt="Logo">
        </div>
        <nav>
            <ul>
                <li><a href="../api/dashboard.php">Inicio</a></li>
                <li><a href="admin_periodos.php">Período Activo</a></li>
                <li><a href="../api/logout.php">Cerrar Sesión</a></li>
            </ul>
        </nav>
    </header>

    <div class="container">
        <h2>Iniciar Nuevo Período Escolar</h2>
        <p>Al crear un nuevo período, se desactivará automáticamente el año escolar anterior y <strong>todas las secciones y estudiantes registrados se asociarán al nuevo período</strong> conservando toda su información.</p>

        <form action="" method="POST">
            <label for="nombre_periodo"><strong>Nombre del Nuevo Período:</strong></label>
            <input type="text" id="nombre_periodo" name="nombre_periodo" placeholder="Ej: 2026-2027" required>

            <button type="submit" class="btn-activar">Aperturar y Promover Matrícula</button>
        </form>

        <a href="admin_periodos.php" class="btn-volver">← Cancelar y volver al panel</a>
    </div>
</body>
</html>
<?php $conn->close(); ?>
