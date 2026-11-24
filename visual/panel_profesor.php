<?php
require_once __DIR__ . '/../config/conexion.php';
verificarSesionDocente('login_profesor.php');

$id_docente = (int) $_SESSION['id_docente'];

// 1. Consultar las secciones asignadas a este profesor en el período académico activo
$stmt = $conn->prepare("
    SELECT s.id_seccion, s.nombre_seccion, m.id_materia, m.nombre_materia, i.nombre AS nombre_institucion,
           (SELECT COUNT(*) FROM estudiantes e WHERE e.id_seccion = s.id_seccion) AS total_estudiantes
    FROM secciones s
    INNER JOIN materias m ON s.id_materia = m.id_materia
    LEFT JOIN instituciones i ON s.id_institucion = i.id_institucion
    INNER JOIN periodos_academicos p ON s.id_periodo = p.id_periodo
    WHERE s.id_docente = ? AND p.activo = 1
    ORDER BY s.nombre_seccion
");
$stmt->bind_param("i", $id_docente);
$stmt->execute();
$res_sec = $stmt->get_result();
$secciones_docente = [];
$total_alumnos_docente = 0;
if ($res_sec) {
    while ($row = $res_sec->fetch_assoc()) {
        $secciones_docente[] = $row;
        $total_alumnos_docente += (int) $row['total_estudiantes'];
    }
}
$stmt->close();

// 2. Consultar las asistencias guardadas por el profesor agrupadas por Fecha + Sección + Materia
$stmt_guardadas = $conn->prepare("
    SELECT i.fecha,
           s.id_seccion, s.nombre_seccion,
           m.id_materia, m.nombre_materia,
           (SELECT COUNT(*) FROM estudiantes e2 WHERE e2.id_seccion = s.id_seccion) AS total_matricula,
           SUM(CASE WHEN i.estado = 'Ausente' THEN 1 ELSE 0 END) AS total_ausentes,
           SUM(CASE WHEN i.estado = 'Justificado' THEN 1 ELSE 0 END) AS total_justificados
    FROM inasistencias i
    INNER JOIN estudiantes e ON i.id_estudiante = e.id_estudiante
    INNER JOIN secciones s ON e.id_seccion = s.id_seccion
    INNER JOIN materias m ON i.id_materia = m.id_materia
    WHERE i.id_docente = ? OR s.id_docente = ?
    GROUP BY i.fecha, s.id_seccion, s.nombre_seccion, m.id_materia, m.nombre_materia
    ORDER BY i.fecha DESC, s.nombre_seccion ASC
    LIMIT 30
");
$stmt_guardadas->bind_param("ii", $id_docente, $id_docente);
$stmt_guardadas->execute();
$res_guardadas = $stmt_guardadas->get_result();
$sesiones_guardadas = [];
$total_faltas_registradas = 0;
if ($res_guardadas) {
    while ($g = $res_guardadas->fetch_assoc()) {
        $sesiones_guardadas[] = $g;
        $total_faltas_registradas += (int) $g['total_ausentes'] + (int) $g['total_justificados'];
    }
}
$stmt_guardadas->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Portal del Docente - Control de Asistencias</title>
    <link rel="stylesheet" href="../css/estilos.css">
</head>
<body>

    <header class="header-fijo">
        <div class="logo">
            <img src="logo.png" alt="Logo">
        </div>
        <nav>
            <ul>
                <li><span><?php echo htmlspecialchars($_SESSION['nombre_docente'] ?? 'Profesor'); ?></span></li>
                <li><a href="asistencias_guardadas.php">Asistencias Guardadas</a></li>
                <li><a href="historial_inasistencias.php">Historial General</a></li>
                <li><a href="../api/webhook_zapier.php">Webhook Zapier</a></li>
                <li><a href="../api/logout.php">Cerrar Sesión</a></li>
            </ul>
        </nav>
    </header>

    <div class="dashboard-container">
        <div class="welcome-card">
            <h2>Portal Docente de Control de Asistencia</h2>
            <p>Gestione la asistencia diaria de sus secciones asignadas y consulte o edite sus listas de asistencia guardadas.</p>
        </div>

        <!-- KPIs del Docente -->
        <div class="stats-grid">
            <div class="stat-card">
                <div>
                    <p class="stat-value"><?php echo count($secciones_docente); ?></p>
                    <p class="stat-label">Secciones Activas</p>
                </div>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-value"><?php echo $total_alumnos_docente; ?></p>
                    <p class="stat-label">Estudiantes a Cargo</p>
                </div>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-value"><?php echo count($sesiones_guardadas); ?></p>
                    <p class="stat-label">Clases Guardadas</p>
                </div>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-value"><?php echo $total_faltas_registradas; ?></p>
                    <p class="stat-label">Inasistencias Registradas</p>
                </div>
            </div>
        </div>

        <!-- Apartado 1: Secciones Asignadas -->
        <div class="container" style="margin-top: 24px; margin-bottom: 32px;">
            <h2>Mis Secciones Asignadas (Período Activo)</h2>
            <p>Seleccione una sección para pasar lista hoy o consultar sus asistencias guardadas.</p>

            <table>
                <thead>
                    <tr>
                        <th>Sección</th>
                        <th>Materia</th>
                        <th>Institución</th>
                        <th>Matrícula</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (!empty($secciones_docente)) {
                        foreach ($secciones_docente as $sec) {
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($sec['nombre_seccion']); ?></strong></td>
                        <td><?php echo htmlspecialchars($sec['nombre_materia']); ?></td>
                        <td><?php echo htmlspecialchars($sec['nombre_institucion'] ?? 'N/A'); ?></td>
                        <td><?php echo (int) $sec['total_estudiantes']; ?> alumnos</td>
                        <td>
                            <div class="acciones-container">
                                <a href="registrar_inasistencia.php?id_seccion=<?php echo (int) $sec['id_seccion']; ?>&id_materia=<?php echo (int) $sec['id_materia']; ?>" class="btn-asistencia">
                                    Tomar Asistencia
                                </a>
                                <a href="registrar_notas.php?id_seccion=<?php echo (int) $sec['id_seccion']; ?>&id_materia=<?php echo (int) $sec['id_materia']; ?>" class="btn-editar">
                                    Gestionar Notas (100%)
                                </a>
                                <a href="asistencias_guardadas.php?id_seccion=<?php echo (int) $sec['id_seccion']; ?>&id_materia=<?php echo (int) $sec['id_materia']; ?>" class="btn-ver">
                                    Asistencias Guardadas
                                </a>
                                <a href="../api/generar_pdf.php?tipo=estudiantes_seccion&id_seccion=<?php echo (int) $sec['id_seccion']; ?>" class="btn-pdf">
                                    Nómina PDF
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php
                        }
                    } else {
                        echo "<tr><td colspan='5'>No tienes secciones asignadas para el período académico actual.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!-- Apartado 2: Asistencias Guardadas por Clase/Fecha -->
        <div class="container" style="margin-top: 0;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 10px;">
                <div style="text-align: left;">
                    <h2 style="margin: 0; text-align: left;">Mis Asistencias Guardadas</h2>
                    <p style="margin: 4px 0 0 0;">Resumen de las listas de asistencia tomadas por fecha y sección.</p>
                </div>
                <a href="asistencias_guardadas.php" class="btn-ver" style="padding: 10px 18px; border-radius: 999px;">
                    Ver Nómina Detallada →
                </a>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Fecha de Clase</th>
                        <th>Sección</th>
                        <th>Materia</th>
                        <th>Presentes</th>
                        <th>Ausentes</th>
                        <th>Justificados</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (!empty($sesiones_guardadas)) {
                        foreach ($sesiones_guardadas as $sg) {
                            $mat  = (int) $sg['total_matricula'];
                            $aus  = (int) $sg['total_ausentes'];
                            $jus  = (int) $sg['total_justificados'];
                            $pres = max(0, $mat - $aus - $jus);
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($sg['fecha']); ?></strong></td>
                        <td><?php echo htmlspecialchars($sg['nombre_seccion']); ?></td>
                        <td><?php echo htmlspecialchars($sg['nombre_materia']); ?></td>
                        <td><span class="badge badge-presente"><?php echo $pres; ?> Presente(s)</span></td>
                        <td><span class="badge badge-ausente"><?php echo $aus; ?> Ausente(s)</span></td>
                        <td><span class="badge badge-justificado"><?php echo $jus; ?> Justificado(s)</span></td>
                        <td>
                            <div class="acciones-container">
                                <a href="asistencias_guardadas.php?id_seccion=<?php echo (int) $sg['id_seccion']; ?>&id_materia=<?php echo (int) $sg['id_materia']; ?>&fecha=<?php echo urlencode($sg['fecha']); ?>" class="btn-ver">
                                    Ver Lista
                                </a>
                                <a href="registrar_inasistencia.php?id_seccion=<?php echo (int) $sg['id_seccion']; ?>&id_materia=<?php echo (int) $sg['id_materia']; ?>&fecha=<?php echo urlencode($sg['fecha']); ?>" class="btn-editar">
                                    Editar
                                </a>
                                <a href="../api/generar_pdf.php?tipo=asistencia_clase&id_seccion=<?php echo (int) $sg['id_seccion']; ?>&id_materia=<?php echo (int) $sg['id_materia']; ?>&fecha=<?php echo urlencode($sg['fecha']); ?>" class="btn-pdf">
                                    PDF
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php
                        }
                    } else {
                        echo "<tr><td colspan='7'>Aún no hay listas de asistencia guardadas. Seleccione una sección arriba y pulse «Tomar Asistencia».</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>
<?php $conn->close(); ?>
