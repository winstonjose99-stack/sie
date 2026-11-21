<?php
require_once __DIR__ . '/../config/conexion.php';
iniciarSesionSegura();

$esAdmin   = isset($_SESSION['id']);
$esDocente = isset($_SESSION['id_docente']);

if (!$esAdmin && !$esDocente) {
    header("Location: iniciar_sesion.html");
    exit();
}

$fecha_filtro   = trim($_GET['fecha'] ?? '');
$estado_filtro  = trim($_GET['estado'] ?? '');
$id_seccion_f   = intval($_GET['id_seccion'] ?? 0);

// Listado de secciones para filtro
if ($esAdmin) {
    $secciones = $conn->query("
        SELECT s.id_seccion, s.nombre_seccion, m.nombre_materia
        FROM secciones s
        INNER JOIN materias m ON s.id_materia = m.id_materia
        ORDER BY s.nombre_seccion
    ");
} else {
    $stmt_s = $conn->prepare("
        SELECT s.id_seccion, s.nombre_seccion, m.nombre_materia
        FROM secciones s
        INNER JOIN materias m ON s.id_materia = m.id_materia
        WHERE s.id_docente = ?
        ORDER BY s.nombre_seccion
    ");
    $id_doc = (int) $_SESSION['id_docente'];
    $stmt_s->bind_param("i", $id_doc);
    $stmt_s->execute();
    $secciones = $stmt_s->get_result();
}

// Construir consulta segura con filtros opcionales
$sql = "
    SELECT i.id_inasistencia, i.fecha, i.estado, i.motivo,
           e.nombre AS est_nombre, e.apellido AS est_apellido, e.cedula AS est_cedula,
           s.nombre_seccion,
           m.nombre_materia,
           d.nombre AS doc_nombre, d.apellido AS doc_apellido
    FROM inasistencias i
    INNER JOIN estudiantes e ON i.id_estudiante = e.id_estudiante
    INNER JOIN materias m ON i.id_materia = m.id_materia
    INNER JOIN docentes d ON i.id_docente = d.id_docente
    LEFT JOIN secciones s ON e.id_seccion = s.id_seccion
    WHERE i.estado IN ('Ausente', 'Justificado')
";

$params = [];
$types  = '';

if (!$esAdmin && $esDocente) {
    $sql .= " AND i.id_docente = ?";
    $types .= 'i';
    $params[] = (int) $_SESSION['id_docente'];
}

if ($fecha_filtro !== '') {
    $sql .= " AND i.fecha = ?";
    $types .= 's';
    $params[] = $fecha_filtro;
}

if (in_array($estado_filtro, ['Ausente', 'Justificado'], true)) {
    $sql .= " AND i.estado = ?";
    $types .= 's';
    $params[] = $estado_filtro;
}

if ($id_seccion_f > 0) {
    $sql .= " AND e.id_seccion = ?";
    $types .= 'i';
    $params[] = $id_seccion_f;
}

$sql .= " ORDER BY i.fecha DESC, i.id_inasistencia DESC";

$stmt = $conn->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$resultado = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de Inasistencias</title>
    <link rel="stylesheet" href="../css/estilos.css">
</head>
<body>

    <header class="header-fijo">
        <div class="logo">
            <img src="logo.png" alt="Logo">
        </div>
        <nav>
            <ul>
                <?php if ($esAdmin) { ?>
                    <li><a href="../api/dashboard.php">Inicio</a></li>
                <?php } else { ?>
                    <li><a href="panel_profesor.php">Panel Docente</a></li>
                <?php } ?>
                <li><a href="../api/logout.php">Cerrar Sesión</a></li>
            </ul>
        </nav>
    </header>

    <div class="container">
        <h2>Historial y Reporte de Inasistencias</h2>

        <form method="GET" class="filtros">
            <div>
                <label for="fecha">Fecha:</label>
                <input type="date" id="fecha" name="fecha" value="<?php echo htmlspecialchars($fecha_filtro); ?>">
            </div>

            <div>
                <label for="id_seccion">Sección:</label>
                <select id="id_seccion" name="id_seccion">
                    <option value="0">Todas</option>
                    <?php
                    if ($secciones && $secciones->num_rows > 0) {
                        while ($sec = $secciones->fetch_assoc()) {
                            $sel = ((int) $sec['id_seccion'] === $id_seccion_f) ? 'selected' : '';
                            echo "<option value='" . (int) $sec['id_seccion'] . "' $sel>" . htmlspecialchars($sec['nombre_seccion'] . ' - ' . $sec['nombre_materia']) . "</option>";
                        }
                    }
                    ?>
                </select>
            </div>

            <div>
                <label for="estado">Estado:</label>
                <select id="estado" name="estado">
                    <option value="">Todos</option>
                    <option value="Ausente" <?php echo $estado_filtro === 'Ausente' ? 'selected' : ''; ?>>Ausente</option>
                    <option value="Justificado" <?php echo $estado_filtro === 'Justificado' ? 'selected' : ''; ?>>Justificado</option>
                </select>
            </div>

            <button type="submit">Filtrar</button>
            <a href="historial_inasistencias.php" class="btn-limpiar">Limpiar</a>
            <a href="../api/generar_pdf.php?tipo=historial_inasistencias&fecha=<?php echo urlencode($fecha_filtro); ?>&id_seccion=<?php echo (int) $id_seccion_f; ?>&estado=<?php echo urlencode($estado_filtro); ?>" class="btn-pdf">
                Exportar PDF
            </a>
        </form>

        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Estudiante</th>
                    <th>Cédula</th>
                    <th>Sección</th>
                    <th>Materia</th>
                    <th>Docente</th>
                    <th>Estado</th>
                    <th>Motivo</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($resultado && $resultado->num_rows > 0) {
                    while ($row = $resultado->fetch_assoc()) {
                        $badgeClass = ($row['estado'] === 'Justificado') ? 'badge-justificado' : 'badge-ausente';
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['fecha']); ?></td>
                    <td><?php echo htmlspecialchars($row['est_nombre'] . ' ' . $row['est_apellido']); ?></td>
                    <td><?php echo htmlspecialchars($row['est_cedula']); ?></td>
                    <td><?php echo htmlspecialchars($row['nombre_seccion'] ?? 'Sin asignar'); ?></td>
                    <td><?php echo htmlspecialchars($row['nombre_materia']); ?></td>
                    <td><?php echo htmlspecialchars(trim($row['doc_nombre'] . ' ' . $row['doc_apellido'])); ?></td>
                    <td><span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($row['estado']); ?></span></td>
                    <td><?php echo htmlspecialchars($row['motivo'] ?? '—'); ?></td>
                </tr>
                <?php
                    }
                } else {
                    echo "<tr><td colspan='8'>No se encontraron inasistencias con los filtros seleccionados.</td></tr>";
                }
                $stmt->close();
                ?>
            </tbody>
        </table>
    </div>

</body>
</html>
<?php $conn->close(); ?>
