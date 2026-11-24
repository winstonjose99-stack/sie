<?php
require_once __DIR__ . '/../config/conexion.php';
verificarSesionDocente('login_profesor.php');

$id_seccion = intval($_GET['id_seccion'] ?? 0);
$id_materia = intval($_GET['id_materia'] ?? 0);
$id_docente = (int) $_SESSION['id_docente'];

if ($id_seccion <= 0 || $id_materia <= 0) {
    header("Location: panel_profesor.php");
    exit();
}

// Verificar propiedad de la sección/materia en el período activo
$stmtSec = $conn->prepare("
    SELECT s.id_seccion, s.nombre_seccion, s.id_periodo, m.id_materia, m.nombre_materia
    FROM secciones s
    INNER JOIN materias m ON s.id_materia = m.id_materia
    INNER JOIN periodos_academicos p ON s.id_periodo = p.id_periodo
    WHERE s.id_seccion = ? AND s.id_materia = ? AND s.id_docente = ? AND p.activo = 1
    LIMIT 1
");
$stmtSec->bind_param("iii", $id_seccion, $id_materia, $id_docente);
$stmtSec->execute();
$seccionInfo = $stmtSec->get_result()->fetch_assoc();
$stmtSec->close();

if (!$seccionInfo) {
    alertaYRedirigir('No tiene permiso para cargar notas en esta sección.', 'panel_profesor.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_estudiante  = intval($_POST['id_estudiante'] ?? 0);
    $lapso          = trim($_POST['lapso'] ?? '1er Lapso');
    $evaluacion     = trim($_POST['evaluacion'] ?? '');
    $calificacion   = floatval($_POST['calificacion'] ?? -1);
    $observacion    = trim($_POST['observacion'] ?? '');
    $fecha_registro = trim($_POST['fecha_registro'] ?? date('Y-m-d'));

    if ($id_estudiante <= 0 || $evaluacion === '' || $calificacion < 0 || $calificacion > 20) {
        alertaYRedirigir('Verifique los datos: debe seleccionar un estudiante, indicar la evaluación y una calificación entre 0 y 20.');
    }

    // Validar que el estudiante pertenece a esta sección y obtener su representante
    $stmtEstInfo = $conn->prepare("
        SELECT id_estudiante, nombre, apellido, id_representante
        FROM estudiantes
        WHERE id_estudiante = ? AND id_seccion = ?
        LIMIT 1
    ");
    $stmtEstInfo->bind_param("ii", $id_estudiante, $id_seccion);
    $stmtEstInfo->execute();
    $estInfo = $stmtEstInfo->get_result()->fetch_assoc();
    $stmtEstInfo->close();

    if (!$estInfo) {
        alertaYRedirigir('El estudiante seleccionado no pertenece a esta sección.');
    }

    $idPeriodo = (int) ($seccionInfo['id_periodo'] ?? obtenerPeriodoActivoId($conn) ?? 0);
    $obsVal    = $observacion !== '' ? $observacion : null;

    $stmtIns = $conn->prepare("
        INSERT INTO notas_academicas (id_estudiante, id_materia, id_docente, id_periodo, lapso, evaluacion, calificacion, observacion, fecha_registro)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmtIns->bind_param("iiiissdss", $id_estudiante, $id_materia, $id_docente, $idPeriodo, $lapso, $evaluacion, $calificacion, $obsVal, $fecha_registro);

    if ($stmtIns->execute()) {
        $stmtIns->close();

        // Notificar inmediatamente al representante en su Portal
        $idRep = (int) ($estInfo['id_representante'] ?? 0);
        if ($idRep > 0) {
            $nomEst = trim($estInfo['nombre'] . ' ' . $estInfo['apellido']);
            $nomMat = $seccionInfo['nombre_materia'];
            $califTxt = number_format($calificacion, 2);
            $tituloNotif = "Nueva nota académica — {$nomMat}";
            $msgNotif = "Se registró {$califTxt} / 20 para {$nomEst} en «{$evaluacion}» ({$lapso})." . ($obsVal ? " Observación: {$obsVal}." : "");
            registrarNotificacionRepresentante($conn, $idRep, $id_estudiante, 'nota', $tituloNotif, $msgNotif);
        }

        $conn->close();
        alertaYRedirigir(
            'Nota académica registrada y notificada al Portal del Representante.',
            "registrar_notas.php?id_seccion={$id_seccion}&id_materia={$id_materia}"
        );
    }

    $stmtIns->close();
    alertaYRedirigir('Error al guardar la calificación.');
}

// Listar estudiantes de la sección
$stmtEsts = $conn->prepare("SELECT id_estudiante, nombre, apellido, cedula FROM estudiantes WHERE id_seccion = ? ORDER BY apellido, nombre");
$stmtEsts->bind_param("i", $id_seccion);
$stmtEsts->execute();
$resEsts = $stmtEsts->get_result();
$estudiantes = [];
if ($resEsts) {
    while ($e = $resEsts->fetch_assoc()) {
        $estudiantes[] = $e;
    }
}
$stmtEsts->close();

// Listar notas ya cargadas en esta sección y materia
$stmtNotas = $conn->prepare("
    SELECT n.*, e.nombre AS est_nombre, e.apellido AS est_apellido
    FROM notas_academicas n
    INNER JOIN estudiantes e ON n.id_estudiante = e.id_estudiante
    WHERE e.id_seccion = ? AND n.id_materia = ?
    ORDER BY n.fecha_registro DESC, n.id_nota DESC
    LIMIT 60
");
$stmtNotas->bind_param("ii", $id_seccion, $id_materia);
$stmtNotas->execute();
$resNotas = $stmtNotas->get_result();
$notasCargadas = [];
if ($resNotas) {
    while ($nt = $resNotas->fetch_assoc()) {
        $notasCargadas[] = $nt;
    }
}
$stmtNotas->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cargar Notas Académicas - Portal Docente</title>
    <link rel="stylesheet" href="../css/estilos.css">
</head>
<body>

    <header class="header-fijo">
        <div class="logo">
            <img src="logo.png" alt="Logo SIE">
        </div>
        <h2>CARGA DE NOTAS ACADÉMICAS</h2>
        <nav>
            <ul>
                <li><a href="panel_profesor.php">← Volver al Panel</a></li>
                <li><a href="registrar_inasistencia.php?id_seccion=<?php echo $id_seccion; ?>&id_materia=<?php echo $id_materia; ?>">Tomar Asistencia</a></li>
                <li><a href="../api/logout.php">Cerrar Sesión</a></li>
            </ul>
        </nav>
    </header>

    <div class="dashboard-container">
        <div class="welcome-card">
            <h2>Registro de Notas — Sección <?php echo htmlspecialchars($seccionInfo['nombre_seccion']); ?></h2>
            <p>Materia: <strong><?php echo htmlspecialchars($seccionInfo['nombre_materia']); ?></strong>. Cada calificación registrada se publica automáticamente en el Portal del Representante.</p>
        </div>

        <div class="container" style="margin-top: 20px; margin-bottom: 32px;">
            <h2>Registrar Nueva Calificación</h2>
            <form method="POST" action="registrar_notas.php?id_seccion=<?php echo $id_seccion; ?>&id_materia=<?php echo $id_materia; ?>" style="max-width: 720px; margin: 16px auto;">
                <div>
                    <label for="id_estudiante">Estudiante:</label>
                    <select id="id_estudiante" name="id_estudiante" required>
                        <option value="">-- Seleccione un estudiante --</option>
                        <?php foreach ($estudiantes as $e) { ?>
                            <option value="<?php echo (int) $e['id_estudiante']; ?>">
                                <?php echo htmlspecialchars($e['apellido'] . ', ' . $e['nombre'] . ' (C.I. ' . $e['cedula'] . ')'); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>

                <div>
                    <label for="lapso">Lapso:</label>
                    <select id="lapso" name="lapso" required>
                        <option value="1er Lapso">1er Lapso</option>
                        <option value="2do Lapso">2do Lapso</option>
                        <option value="3er Lapso">3er Lapso</option>
                    </select>
                </div>

                <div>
                    <label for="evaluacion">Nombre de la Evaluación:</label>
                    <input type="text" id="evaluacion" name="evaluacion" placeholder="Ej. Examen Escrito Unidad 1, Taller Práctico..." required>
                </div>

                <div>
                    <label for="calificacion">Calificación (0 a 20 puntos):</label>
                    <input type="number" id="calificacion" name="calificacion" min="0" max="20" step="0.5" placeholder="Ej. 18.5" required>
                </div>

                <div>
                    <label for="fecha_registro">Fecha de Evaluación:</label>
                    <input type="date" id="fecha_registro" name="fecha_registro" value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div>
                    <label for="observacion">Observación (opcional):</label>
                    <input type="text" id="observacion" name="observacion" placeholder="Comentario para el representante...">
                </div>

                <button type="submit">Guardar Nota y Notificar al Representante</button>
            </form>
        </div>

        <div class="container" style="margin-top: 0;">
            <h2>Notas Registradas en esta Sección</h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Estudiante</th>
                            <th>Lapso</th>
                            <th>Evaluación</th>
                            <th>Calificación</th>
                            <th>Estado</th>
                            <th>Observación</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($notasCargadas)) { ?>
                            <?php foreach ($notasCargadas as $nt) {
                                $calif = (float) $nt['calificacion'];
                                $aprobado = ($calif >= 10.0);
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($nt['fecha_registro']); ?></td>
                                <td><strong><?php echo htmlspecialchars(trim($nt['est_nombre'] . ' ' . $nt['est_apellido'])); ?></strong></td>
                                <td><?php echo htmlspecialchars($nt['lapso']); ?></td>
                                <td><?php echo htmlspecialchars($nt['evaluacion']); ?></td>
                                <td><strong><?php echo number_format($calif, 2); ?> / 20</strong></td>
                                <td>
                                    <span class="badge <?php echo $aprobado ? 'badge-presente' : 'badge-ausente'; ?>">
                                        <?php echo $aprobado ? 'Aprobado' : 'En Recuperación'; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($nt['observacion'] ?: 'Sin observaciones'); ?></td>
                            </tr>
                            <?php } ?>
                        <?php } else { ?>
                            <tr><td colspan="7">No hay calificaciones registradas todavía para esta sección.</td></tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</body>
</html>
<?php $conn->close(); ?>
