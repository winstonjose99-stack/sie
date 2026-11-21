<?php
require_once __DIR__ . '/../config/conexion.php';
verificarSesionDocente('login_profesor.php');

$id_docente = (int) $_SESSION['id_docente'];

// 1. Obtener todas las secciones del profesor (para el selector)
$stmt_sec = $conn->prepare("
    SELECT s.id_seccion, s.nombre_seccion, m.id_materia, m.nombre_materia, i.nombre AS nombre_institucion
    FROM secciones s
    INNER JOIN materias m ON s.id_materia = m.id_materia
    LEFT JOIN instituciones i ON s.id_institucion = i.id_institucion
    WHERE s.id_docente = ?
    ORDER BY s.nombre_seccion
");
$stmt_sec->bind_param("i", $id_docente);
$stmt_sec->execute();
$res_sec = $stmt_sec->get_result();
$secciones = [];
if ($res_sec) {
    while ($r = $res_sec->fetch_assoc()) {
        $secciones[] = $r;
    }
}
$stmt_sec->close();

$id_seccion_sel = intval($_GET['id_seccion'] ?? ($secciones[0]['id_seccion'] ?? 0));
$id_materia_sel = intval($_GET['id_materia'] ?? 0);

// Buscar la sección seleccionada en la lista del profesor
$seccion_actual = null;
foreach ($secciones as $sec) {
    if ((int) $sec['id_seccion'] === $id_seccion_sel && ($id_materia_sel === 0 || (int) $sec['id_materia'] === $id_materia_sel)) {
        $seccion_actual = $sec;
        $id_materia_sel = (int) $sec['id_materia'];
        break;
    }
}
if (!$seccion_actual && !empty($secciones)) {
    $seccion_actual = $secciones[0];
    $id_seccion_sel = (int) $seccion_actual['id_seccion'];
    $id_materia_sel = (int) $seccion_actual['id_materia'];
}

// 2. Obtener fechas que tienen registros guardados para esta sección y materia
$fechas_guardadas = [];
if ($seccion_actual) {
    $stmt_fechas = $conn->prepare("
        SELECT DISTINCT i.fecha
        FROM inasistencias i
        INNER JOIN estudiantes e ON i.id_estudiante = e.id_estudiante
        WHERE e.id_seccion = ? AND i.id_materia = ?
        ORDER BY i.fecha DESC
    ");
    $stmt_fechas->bind_param("ii", $id_seccion_sel, $id_materia_sel);
    $stmt_fechas->execute();
    $res_f = $stmt_fechas->get_result();
    if ($res_f) {
        while ($frow = $res_f->fetch_assoc()) {
            $fechas_guardadas[] = $frow['fecha'];
        }
    }
    $stmt_fechas->close();
}

$fecha_sel = trim($_GET['fecha'] ?? ($fechas_guardadas[0] ?? date('Y-m-d')));

// 3. Obtener la nómina completa de estudiantes de la sección y cruzar con inasistencias de la fecha seleccionada
$nomina = [];
$total_presentes    = 0;
$total_ausentes     = 0;
$total_justificados = 0;

if ($seccion_actual) {
    $stmt_nomina = $conn->prepare("
        SELECT e.id_estudiante, e.nombre, e.apellido, e.cedula, e.fecha_nacimiento, e.sexo,
               r.nombre AS rep_nombre, r.apellido AS rep_apellido, r.telefono AS rep_telefono,
               i.id_inasistencia, i.estado, i.motivo
        FROM estudiantes e
        LEFT JOIN representantes r ON e.id_representante = r.id_representante
        LEFT JOIN inasistencias i
               ON i.id_estudiante = e.id_estudiante
              AND i.id_materia = ?
              AND i.fecha = ?
        WHERE e.id_seccion = ?
        ORDER BY e.apellido, e.nombre
    ");
    $stmt_nomina->bind_param("isi", $id_materia_sel, $fecha_sel, $id_seccion_sel);
    $stmt_nomina->execute();
    $res_nom = $stmt_nomina->get_result();
    if ($res_nom) {
        while ($al = $res_nom->fetch_assoc()) {
            if ($al['estado'] === 'Ausente') {
                $total_ausentes++;
            } elseif ($al['estado'] === 'Justificado') {
                $total_justificados++;
            } else {
                $al['estado'] = 'Presente';
                $total_presentes++;
            }
            $nomina[] = $al;
        }
    }
    $stmt_nomina->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asistencias Guardadas - Portal Docente</title>
    <link rel="stylesheet" href="../css/estilos.css">
    <style>
        .fechas-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: center;
            margin: 14px 0 22px 0;
        }
        .fecha-pill {
            padding: 6px 14px;
            border-radius: 999px;
            background: #f1f5f9;
            color: var(--text-main);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            border: 1px solid var(--border-color);
            transition: all 0.2s ease;
        }
        .fecha-pill:hover,
        .fecha-pill.activa {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
        }
    </style>
</head>
<body>

    <header class="header-fijo">
        <div class="logo">
            <img src="logo.png" alt="Logo">
        </div>
        <nav>
            <ul>
                <li><a href="panel_profesor.php">← Volver a Mi Panel</a></li>
                <li><a href="historial_inasistencias.php">Historial General</a></li>
                <li><a href="../api/logout.php">Cerrar Sesión</a></li>
            </ul>
        </nav>
    </header>

    <div class="dashboard-container">
        <div class="container" style="margin-top: 10px;">
            <h2>Asistencias Guardadas por Sección y Fecha</h2>
            <p>Consulte la lista completa de asistencia de cada clase (estudiantes presentes, ausentes y justificados).</p>

            <!-- Selector de Sección y Fecha -->
            <form method="GET" class="filtros">
                <div>
                    <label for="id_seccion">Sección y Materia:</label>
                    <select id="id_seccion" name="id_seccion">
                        <?php foreach ($secciones as $sec) {
                            $sel = ((int) $sec['id_seccion'] === $id_seccion_sel) ? 'selected' : '';
                        ?>
                            <option value="<?php echo (int) $sec['id_seccion']; ?>" <?php echo $sel; ?>>
                                <?php echo htmlspecialchars($sec['nombre_seccion'] . ' — ' . $sec['nombre_materia']); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>

                <div>
                    <label for="fecha">Fecha de la Clase:</label>
                    <input type="date" id="fecha" name="fecha" value="<?php echo htmlspecialchars($fecha_sel); ?>">
                </div>

                <button type="submit">Consultar Asistencia</button>

                <?php if ($seccion_actual) { ?>
                    <a href="registrar_inasistencia.php?id_seccion=<?php echo $id_seccion_sel; ?>&id_materia=<?php echo $id_materia_sel; ?>&fecha=<?php echo urlencode($fecha_sel); ?>" class="btn-editar" style="padding: 11px 18px;">
                        Editar Asistencia
                    </a>
                    <a href="../api/generar_pdf.php?tipo=asistencia_clase&id_seccion=<?php echo $id_seccion_sel; ?>&id_materia=<?php echo $id_materia_sel; ?>&fecha=<?php echo urlencode($fecha_sel); ?>" class="btn-pdf" style="padding: 11px 18px;">
                        Descargar PDF
                    </a>
                <?php } ?>
            </form>

            <?php if (!empty($fechas_guardadas)) { ?>
                <div>
                    <small style="color: var(--text-muted); font-weight: 600;">Fechas con registro guardado en esta sección:</small>
                    <div class="fechas-pills">
                        <?php foreach ($fechas_guardadas as $fg) {
                            $activa = ($fg === $fecha_sel) ? 'activa' : '';
                        ?>
                            <a href="asistencias_guardadas.php?id_seccion=<?php echo $id_seccion_sel; ?>&id_materia=<?php echo $id_materia_sel; ?>&fecha=<?php echo urlencode($fg); ?>" class="fecha-pill <?php echo $activa; ?>">
                                <?php echo htmlspecialchars($fg); ?>
                            </a>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>

            <?php if ($seccion_actual) { ?>
                <!-- Resumen KPI de esa clase -->
                <div class="stats-grid" style="margin-top: 20px;">
                    <div class="stat-card">
                        <div>
                            <p class="stat-value"><?php echo count($nomina); ?></p>
                            <p class="stat-label">Matrícula de la Sección</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div>
                            <p class="stat-value"><?php echo $total_presentes; ?></p>
                            <p class="stat-label">Presentes</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div>
                            <p class="stat-value"><?php echo $total_justificados; ?></p>
                            <p class="stat-label">Justificados</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div>
                            <p class="stat-value"><?php echo $total_ausentes; ?></p>
                            <p class="stat-label">Ausentes</p>
                        </div>
                    </div>
                </div>

                <h3>
                    Nómina de Clase: <?php echo htmlspecialchars($seccion_actual['nombre_seccion'] . ' — ' . $seccion_actual['nombre_materia']); ?>
                    (<?php echo htmlspecialchars($fecha_sel); ?>)
                </h3>

                <table>
                    <thead>
                        <tr>
                            <th>Cédula</th>
                            <th>Estudiante</th>
                            <th>Fecha de Nacimiento</th>
                            <th>Sexo</th>
                            <th>Representante</th>
                            <th>Teléfono</th>
                            <th>Estado Guardado</th>
                            <th>Observación / Motivo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($nomina)) {
                            foreach ($nomina as $al) {
                                if ($al['estado'] === 'Ausente') {
                                    $badgeClass = 'badge-ausente';
                                } elseif ($al['estado'] === 'Justificado') {
                                    $badgeClass = 'badge-justificado';
                                } else {
                                    $badgeClass = 'badge-presente';
                                }
                                $repNombre = trim(($al['rep_nombre'] ?? '') . ' ' . ($al['rep_apellido'] ?? '')) ?: 'Sin asignar';
                                $fechaNac  = (!empty($al['fecha_nacimiento']) && $al['fecha_nacimiento'] !== '0000-00-00') ? $al['fecha_nacimiento'] : 'N/A';
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($al['cedula']); ?></td>
                            <td><strong><?php echo htmlspecialchars($al['nombre'] . ' ' . $al['apellido']); ?></strong></td>
                            <td><?php echo htmlspecialchars($fechaNac); ?></td>
                            <td><?php echo htmlspecialchars(!empty($al['sexo']) ? $al['sexo'] : 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($repNombre); ?></td>
                            <td><?php echo htmlspecialchars($al['rep_telefono'] ?? '—'); ?></td>
                            <td><span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($al['estado']); ?></span></td>
                            <td><?php echo htmlspecialchars($al['motivo'] ?? ($al['estado'] === 'Presente' ? 'Asistió a clase' : 'Inasistencia sin justificar')); ?></td>
                        </tr>
                        <?php }
                        } else {
                            echo "<tr><td colspan='8'>No hay estudiantes inscritos en esta sección.</td></tr>";
                        } ?>
                    </tbody>
                </table>
            <?php } else { ?>
                <p>No tienes secciones asignadas todavía.</p>
            <?php } ?>
        </div>
    </div>

</body>
</html>
<?php $conn->close(); ?>
