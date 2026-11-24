<?php
require_once __DIR__ . '/../config/conexion.php';
verificarSesionRepresentante('login_representante.php');

$id_representante = (int) $_SESSION['id_representante'];
$filtro_est       = intval($_GET['id_estudiante'] ?? 0);

// Acciones POST: marcar notificaciones como leídas
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = trim($_POST['accion'] ?? '');
    if ($accion === 'marcar_leida') {
        $idNotif = intval($_POST['id_notificacion'] ?? 0);
        if ($idNotif > 0) {
            $stmtMark = $conn->prepare("UPDATE notificaciones_representante SET leida = 1 WHERE id_notificacion = ? AND id_representante = ?");
            if ($stmtMark) {
                $stmtMark->bind_param("ii", $idNotif, $id_representante);
                $stmtMark->execute();
                $stmtMark->close();
            }
        }
    } elseif ($accion === 'marcar_todas') {
        $stmtAll = $conn->prepare("UPDATE notificaciones_representante SET leida = 1 WHERE id_representante = ? AND leida = 0");
        if ($stmtAll) {
            $stmtAll->bind_param("i", $id_representante);
            $stmtAll->execute();
            $stmtAll->close();
        }
    }
    $redir = 'panel_representante.php' . ($filtro_est > 0 ? '?id_estudiante=' . $filtro_est : '');
    header("Location: " . $redir);
    exit();
}

// 1. Datos del representante
$stmtRep = $conn->prepare("SELECT id_representante, nombre, apellido, cedula, telefono FROM representantes WHERE id_representante = ? LIMIT 1");
$stmtRep->bind_param("i", $id_representante);
$stmtRep->execute();
$repInfo = $stmtRep->get_result()->fetch_assoc();
$stmtRep->close();

$nombreRep = $repInfo ? trim($repInfo['nombre'] . ' ' . $repInfo['apellido']) : ($_SESSION['nombre_representante'] ?? 'Representante');

// 2. Estudiantes representados
$stmtEst = $conn->prepare("
    SELECT e.id_estudiante, e.nombre, e.apellido, e.cedula, e.fecha_nacimiento, e.sexo,
           s.nombre_seccion, m.nombre_materia, i.nombre AS nombre_institucion
    FROM estudiantes e
    LEFT JOIN secciones s ON e.id_seccion = s.id_seccion
    LEFT JOIN materias m ON s.id_materia = m.id_materia
    LEFT JOIN instituciones i ON s.id_institucion = i.id_institucion
    WHERE e.id_representante = ?
    ORDER BY e.apellido ASC, e.nombre ASC
");
$stmtEst->bind_param("i", $id_representante);
$stmtEst->execute();
$resEst = $stmtEst->get_result();
$estudiantes = [];
$estIdsValidos = [];
if ($resEst) {
    while ($row = $resEst->fetch_assoc()) {
        $estudiantes[] = $row;
        $estIdsValidos[] = (int) $row['id_estudiante'];
    }
}
$stmtEst->close();

if ($filtro_est > 0 && !in_array($filtro_est, $estIdsValidos, true)) {
    $filtro_est = 0;
}

// 3. Notificaciones del representante
if ($filtro_est > 0) {
    $stmtNotif = $conn->prepare("
        SELECT n.*, e.nombre AS est_nombre, e.apellido AS est_apellido
        FROM notificaciones_representante n
        LEFT JOIN estudiantes e ON n.id_estudiante = e.id_estudiante
        WHERE n.id_representante = ? AND n.id_estudiante = ?
        ORDER BY n.leida ASC, n.fecha_creacion DESC, n.id_notificacion DESC
        LIMIT 50
    ");
    $stmtNotif->bind_param("ii", $id_representante, $filtro_est);
} else {
    $stmtNotif = $conn->prepare("
        SELECT n.*, e.nombre AS est_nombre, e.apellido AS est_apellido
        FROM notificaciones_representante n
        LEFT JOIN estudiantes e ON n.id_estudiante = e.id_estudiante
        WHERE n.id_representante = ?
        ORDER BY n.leida ASC, n.fecha_creacion DESC, n.id_notificacion DESC
        LIMIT 50
    ");
    $stmtNotif->bind_param("i", $id_representante);
}
$stmtNotif->execute();
$resNotif = $stmtNotif->get_result();
$notificaciones = [];
$sinLeer = 0;
if ($resNotif) {
    while ($n = $resNotif->fetch_assoc()) {
        $notificaciones[] = $n;
        if ((int) $n['leida'] === 0) {
            $sinLeer++;
        }
    }
}
$stmtNotif->close();

// 4. Historial de asistencias e inasistencias
if ($filtro_est > 0) {
    $stmtAsis = $conn->prepare("
        SELECT i.fecha, i.estado, i.motivo,
               e.nombre AS est_nombre, e.apellido AS est_apellido,
               s.nombre_seccion, m.nombre_materia,
               d.nombre AS doc_nombre, d.apellido AS doc_apellido
        FROM inasistencias i
        INNER JOIN estudiantes e ON i.id_estudiante = e.id_estudiante
        LEFT JOIN secciones s ON e.id_seccion = s.id_seccion
        LEFT JOIN materias m ON i.id_materia = m.id_materia
        LEFT JOIN docentes d ON i.id_docente = d.id_docente
        WHERE e.id_representante = ? AND e.id_estudiante = ?
        ORDER BY i.fecha DESC, i.id_inasistencia DESC
        LIMIT 60
    ");
    $stmtAsis->bind_param("ii", $id_representante, $filtro_est);
} else {
    $stmtAsis = $conn->prepare("
        SELECT i.fecha, i.estado, i.motivo,
               e.nombre AS est_nombre, e.apellido AS est_apellido,
               s.nombre_seccion, m.nombre_materia,
               d.nombre AS doc_nombre, d.apellido AS doc_apellido
        FROM inasistencias i
        INNER JOIN estudiantes e ON i.id_estudiante = e.id_estudiante
        LEFT JOIN secciones s ON e.id_seccion = s.id_seccion
        LEFT JOIN materias m ON i.id_materia = m.id_materia
        LEFT JOIN docentes d ON i.id_docente = d.id_docente
        WHERE e.id_representante = ?
        ORDER BY i.fecha DESC, i.id_inasistencia DESC
        LIMIT 60
    ");
    $stmtAsis->bind_param("i", $id_representante);
}
$stmtAsis->execute();
$resAsis = $stmtAsis->get_result();
$asistencias = [];
$totalPresentes = 0;
$totalFaltas = 0;
if ($resAsis) {
    while ($a = $resAsis->fetch_assoc()) {
        $asistencias[] = $a;
        if ($a['estado'] === 'Presente') {
            $totalPresentes++;
        } else {
            $totalFaltas++;
        }
    }
}
$stmtAsis->close();

// 5. Notas académicas (con ponderación % y resumen acumulado por materia/lapso)
if ($filtro_est > 0) {
    $stmtNotas = $conn->prepare("
        SELECT n.id_nota, n.id_estudiante, n.lapso, n.evaluacion, n.ponderacion, n.calificacion, n.observacion, n.fecha_registro,
               e.nombre AS est_nombre, e.apellido AS est_apellido,
               m.nombre_materia,
               d.nombre AS doc_nombre, d.apellido AS doc_apellido
        FROM notas_academicas n
        INNER JOIN estudiantes e ON n.id_estudiante = e.id_estudiante
        LEFT JOIN materias m ON n.id_materia = m.id_materia
        LEFT JOIN docentes d ON n.id_docente = d.id_docente
        WHERE e.id_representante = ? AND e.id_estudiante = ?
        ORDER BY n.fecha_registro DESC, n.id_nota DESC
        LIMIT 80
    ");
    $stmtNotas->bind_param("ii", $id_representante, $filtro_est);
} else {
    $stmtNotas = $conn->prepare("
        SELECT n.id_nota, n.id_estudiante, n.lapso, n.evaluacion, n.ponderacion, n.calificacion, n.observacion, n.fecha_registro,
               e.nombre AS est_nombre, e.apellido AS est_apellido,
               m.nombre_materia,
               d.nombre AS doc_nombre, d.apellido AS doc_apellido
        FROM notas_academicas n
        INNER JOIN estudiantes e ON n.id_estudiante = e.id_estudiante
        LEFT JOIN materias m ON n.id_materia = m.id_materia
        LEFT JOIN docentes d ON n.id_docente = d.id_docente
        WHERE e.id_representante = ?
        ORDER BY n.fecha_registro DESC, n.id_nota DESC
        LIMIT 80
    ");
    $stmtNotas->bind_param("i", $id_representante);
}
$stmtNotas->execute();
$resNotas = $stmtNotas->get_result();
$notas = [];
$resumenAcumulado = [];
$sumaNotas = 0.0;
if ($resNotas) {
    while ($nt = $resNotas->fetch_assoc()) {
        $pond = (float) ($nt['ponderacion'] ?? 0);
        $calif = (float) $nt['calificacion'];
        $aporte = $pond > 0 ? ($calif * $pond) / 100.0 : 0.0;
        $nt['aporte_ponderado'] = $aporte;
        $notas[] = $nt;
        $sumaNotas += $calif;

        $clave = $nt['id_estudiante'] . '|' . ($nt['nombre_materia'] ?? 'Materia') . '|' . ($nt['lapso'] ?? '1er Lapso');
        if (!isset($resumenAcumulado[$clave])) {
            $resumenAcumulado[$clave] = [
                'estudiante' => trim($nt['est_nombre'] . ' ' . $nt['est_apellido']),
                'materia'    => $nt['nombre_materia'] ?? 'N/A',
                'lapso'      => $nt['lapso'] ?? '1er Lapso',
                'pct'        => 0.0,
                'acumulado'  => 0.0,
                'cantidad'   => 0,
            ];
        }
        $resumenAcumulado[$clave]['pct'] += $pond;
        $resumenAcumulado[$clave]['acumulado'] += $aporte;
        $resumenAcumulado[$clave]['cantidad']++;
    }
}
$stmtNotas->close();

$promedioGeneral = count($notas) > 0 ? number_format($sumaNotas / count($notas), 2) : 'N/A';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal de Representantes - Sistema de Asistencias</title>
    <link rel="stylesheet" href="../css/estilos.css">
    <style>
        .filtro-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 18px 0 24px 0;
        }
        .pill-btn {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 999px;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #1e293b;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .pill-btn:hover,
        .pill-btn.activo {
            background: #1e3a8a;
            color: #ffffff;
            border-color: #1e3a8a;
        }
        .notif-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 14px;
        }
        .notif-item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            padding: 16px 18px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            text-align: left;
        }
        .notif-item.no-leida {
            background: #f0f7ff;
            border-color: #93c5fd;
            border-left: 4px solid #1d4ed8;
        }
        .notif-meta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
        }
        .notif-titulo {
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 4px 0;
            font-size: 0.98rem;
        }
        .notif-texto {
            margin: 0;
            color: #334155;
            font-size: 0.92rem;
            line-height: 1.45;
        }
        .notif-fecha {
            font-size: 0.8rem;
            color: #64748b;
        }
        .badge-nota {
            background: #dbeafe;
            color: #1e40af;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
        }
        .badge-nueva {
            background: #1d4ed8;
            color: #ffffff;
            padding: 3px 9px;
            border-radius: 999px;
            font-size: 0.74rem;
            font-weight: 700;
        }
        .btn-mini {
            padding: 6px 12px;
            font-size: 0.82rem;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #1e293b;
            cursor: pointer;
            font-weight: 600;
            white-space: nowrap;
        }
        .btn-mini:hover {
            background: #f1f5f9;
        }
        .nota-valor {
            font-weight: 700;
            font-size: 1rem;
            color: #0f172a;
        }
    </style>
</head>
<body>

    <header class="header-fijo">
        <div class="logo">
            <img src="logo.png" alt="Logo SIE">
        </div>
        <h2>PORTAL DE REPRESENTANTES</h2>
        <nav>
            <ul>
                <li><span><?php echo htmlspecialchars($nombreRep); ?></span></li>
                <li><a href="#notificaciones">Notificaciones<?php echo $sinLeer > 0 ? " ({$sinLeer})" : ""; ?></a></li>
                <li><a href="#asistencias">Asistencias</a></li>
                <li><a href="#notas">Notas</a></li>
                <li><a href="../api/logout.php">Cerrar Sesión</a></li>
            </ul>
        </nav>
    </header>

    <div class="dashboard-container">
        <div class="welcome-card">
            <h2>Bienvenido/a, <?php echo htmlspecialchars($nombreRep); ?></h2>
            <p>
                Cédula: <strong><?php echo htmlspecialchars($repInfo['cedula'] ?? 'N/A'); ?></strong> |
                Teléfono registrado: <strong><?php echo htmlspecialchars($repInfo['telefono'] ?? 'N/A'); ?></strong>.
                Desde este portal recibe todas las notificaciones de asistencia, inasistencias y calificaciones de sus representados.
            </p>
        </div>

        <!-- KPIs del Representante -->
        <div class="stats-grid">
            <div class="stat-card">
                <div>
                    <p class="stat-value"><?php echo count($estudiantes); ?></p>
                    <p class="stat-label">Estudiantes Representados</p>
                </div>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-value"><?php echo $sinLeer; ?></p>
                    <p class="stat-label">Notificaciones Sin Leer</p>
                </div>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-value"><?php echo $totalPresentes; ?> / <?php echo $totalFaltas; ?></p>
                    <p class="stat-label">Asistencias / Inasistencias</p>
                </div>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-value"><?php echo $promedioGeneral; ?></p>
                    <p class="stat-label">Promedio Académico (/20)</p>
                </div>
            </div>
        </div>

        <!-- Filtro por Estudiante -->
        <?php if (count($estudiantes) > 1) { ?>
        <div class="filtro-pills">
            <a href="panel_representante.php" class="pill-btn <?php echo $filtro_est === 0 ? 'activo' : ''; ?>">
                Todos mis Representados (<?php echo count($estudiantes); ?>)
            </a>
            <?php foreach ($estudiantes as $est) { ?>
                <a href="panel_representante.php?id_estudiante=<?php echo (int) $est['id_estudiante']; ?>"
                   class="pill-btn <?php echo $filtro_est === (int) $est['id_estudiante'] ? 'activo' : ''; ?>">
                    <?php echo htmlspecialchars(trim($est['nombre'] . ' ' . $est['apellido'])); ?>
                    (Sección <?php echo htmlspecialchars($est['nombre_seccion'] ?? 'N/A'); ?>)
                </a>
            <?php } ?>
        </div>
        <?php } ?>

        <!-- Sección 1: Bandeja de Notificaciones -->
        <div id="notificaciones" class="container" style="margin-top: 16px; margin-bottom: 32px;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                <div style="text-align: left;">
                    <h2 style="margin: 0; text-align: left;">Bandeja de Notificaciones</h2>
                    <p style="margin: 4px 0 0 0;">Avisos automáticos sobre asistencia a clases, inasistencias y nuevas notas registradas por los docentes.</p>
                </div>
                <?php if ($sinLeer > 0) { ?>
                <form method="POST" action="panel_representante.php<?php echo $filtro_est > 0 ? '?id_estudiante=' . $filtro_est : ''; ?>" style="margin: 0; padding: 0; box-shadow: none; background: transparent; max-width: none;">
                    <input type="hidden" name="accion" value="marcar_todas">
                    <button type="submit" class="btn-mini">Marcar todas como leídas (<?php echo $sinLeer; ?>)</button>
                </form>
                <?php } ?>
            </div>

            <div class="notif-list">
                <?php if (!empty($notificaciones)) { ?>
                    <?php foreach ($notificaciones as $n) {
                        $esNoLeida = ((int) $n['leida'] === 0);
                        $tipo = $n['tipo'] ?? 'asistencia';
                        if ($tipo === 'ausencia') {
                            $badgeClass = 'badge badge-ausente';
                            $badgeTexto = 'Inasistencia';
                        } elseif ($tipo === 'justificado') {
                            $badgeClass = 'badge badge-justificado';
                            $badgeTexto = 'Justificado';
                        } elseif ($tipo === 'nota') {
                            $badgeClass = 'badge-nota';
                            $badgeTexto = 'Nota Académica';
                        } else {
                            $badgeClass = 'badge badge-presente';
                            $badgeTexto = 'Asistencia';
                        }
                    ?>
                    <div class="notif-item <?php echo $esNoLeida ? 'no-leida' : ''; ?>">
                        <div>
                            <div class="notif-meta">
                                <?php if ($esNoLeida) { ?>
                                    <span class="badge-nueva">Nueva</span>
                                <?php } ?>
                                <span class="<?php echo $badgeClass; ?>"><?php echo $badgeTexto; ?></span>
                                <span class="notif-fecha"><?php echo htmlspecialchars($n['fecha_creacion']); ?></span>
                            </div>
                            <p class="notif-titulo"><?php echo htmlspecialchars($n['titulo']); ?></p>
                            <p class="notif-texto"><?php echo htmlspecialchars($n['mensaje']); ?></p>
                        </div>
                        <?php if ($esNoLeida) { ?>
                        <form method="POST" action="panel_representante.php<?php echo $filtro_est > 0 ? '?id_estudiante=' . $filtro_est : ''; ?>" style="margin: 0; padding: 0; box-shadow: none; background: transparent; max-width: none;">
                            <input type="hidden" name="accion" value="marcar_leida">
                            <input type="hidden" name="id_notificacion" value="<?php echo (int) $n['id_notificacion']; ?>">
                            <button type="submit" class="btn-mini">Marcar leída</button>
                        </form>
                        <?php } ?>
                    </div>
                    <?php } ?>
                <?php } else { ?>
                    <p style="text-align: center; color: #64748b; padding: 18px 0;">No tiene notificaciones registradas hasta el momento.</p>
                <?php } ?>
            </div>
        </div>

        <!-- Sección 2: Historial de Asistencia e Inasistencias -->
        <div id="asistencias" class="container" style="margin-top: 0; margin-bottom: 32px;">
            <h2>Control de Asistencias e Inasistencias</h2>
            <p>Detalle de la asistencia diaria reportada por los docentes para sus representados.</p>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Estudiante</th>
                            <th>Sección</th>
                            <th>Materia</th>
                            <th>Estado</th>
                            <th>Observación / Motivo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($asistencias)) { ?>
                            <?php foreach ($asistencias as $a) {
                                $estado = $a['estado'] ?? 'Presente';
                                if ($estado === 'Ausente') {
                                    $bCls = 'badge badge-ausente';
                                } elseif ($estado === 'Justificado') {
                                    $bCls = 'badge badge-justificado';
                                } else {
                                    $bCls = 'badge badge-presente';
                                }
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($a['fecha']); ?></strong></td>
                                <td><?php echo htmlspecialchars(trim($a['est_nombre'] . ' ' . $a['est_apellido'])); ?></td>
                                <td><?php echo htmlspecialchars($a['nombre_seccion'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($a['nombre_materia'] ?? 'N/A'); ?></td>
                                <td><span class="<?php echo $bCls; ?>"><?php echo htmlspecialchars($estado); ?></span></td>
                                <td><?php echo htmlspecialchars($a['motivo'] ?: ($estado === 'Ausente' ? 'Inasistencia sin justificar' : 'Asistió a clase')); ?></td>
                            </tr>
                            <?php } ?>
                        <?php } else { ?>
                            <tr><td colspan="6">Aún no hay registros de asistencia para mostrar.</td></tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Sección 3: Boletín de Notas Académicas Ponderadas -->
        <div id="notas" class="container" style="margin-top: 0; margin-bottom: 32px;">
            <h2>Boletín de Notas Académicas (Ponderación 100%)</h2>
            <p>Resumen acumulado por lapso y desglose de cada actividad evaluada en escala de 0 a 20 puntos.</p>

            <?php if (!empty($resumenAcumulado)) { ?>
            <div class="table-responsive" style="margin-bottom: 24px;">
                <table>
                    <thead>
                        <tr>
                            <th>Estudiante</th>
                            <th>Materia</th>
                            <th>Lapso</th>
                            <th>Evaluaciones</th>
                            <th>% Evaluado</th>
                            <th>Nota Acumulada (/ 20)</th>
                            <th>Estado del Lapso</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resumenAcumulado as $res) {
                            $acum = (float) $res['acumulado'];
                            $pct  = (float) $res['pct'];
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($res['estudiante']); ?></strong></td>
                            <td><?php echo htmlspecialchars($res['materia']); ?></td>
                            <td><?php echo htmlspecialchars($res['lapso']); ?></td>
                            <td><?php echo (int) $res['cantidad']; ?> actividad(es)</td>
                            <td><strong><?php echo number_format($pct, 0); ?>% / 100%</strong></td>
                            <td><span class="nota-valor"><?php echo number_format($acum, 2); ?> / 20</span></td>
                            <td>
                                <?php if ($acum >= 10.0) { ?>
                                    <span class="badge badge-presente">Aprobado</span>
                                <?php } elseif ($pct < 100.0) { ?>
                                    <span class="badge badge-justificado">En Curso</span>
                                <?php } else { ?>
                                    <span class="badge badge-ausente">En Recuperación</span>
                                <?php } ?>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <?php } ?>

            <h3 style="text-align: left; margin-bottom: 10px;">Desglose por Actividad</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Estudiante</th>
                            <th>Materia</th>
                            <th>Lapso</th>
                            <th>Actividad</th>
                            <th>Ponderación</th>
                            <th>Calificación</th>
                            <th>Aporte</th>
                            <th>Observación</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($notas)) { ?>
                            <?php foreach ($notas as $nt) {
                                $calif  = (float) $nt['calificacion'];
                                $pond   = (float) ($nt['ponderacion'] ?? 0);
                                $aporte = (float) ($nt['aporte_ponderado'] ?? 0);
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars(trim($nt['est_nombre'] . ' ' . $nt['est_apellido'])); ?></strong></td>
                                <td><?php echo htmlspecialchars($nt['nombre_materia'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($nt['lapso']); ?></td>
                                <td><?php echo htmlspecialchars($nt['evaluacion']); ?></td>
                                <td><span class="badge-nota"><?php echo $pond > 0 ? number_format($pond, 0) . '%' : '—'; ?></span></td>
                                <td><span class="nota-valor"><?php echo number_format($calif, 2); ?> / 20</span></td>
                                <td><strong><?php echo $pond > 0 ? number_format($aporte, 2) . ' pts' : '—'; ?></strong></td>
                                <td><?php echo htmlspecialchars($nt['observacion'] ?: 'Sin observaciones'); ?></td>
                                <td><?php echo htmlspecialchars($nt['fecha_registro']); ?></td>
                            </tr>
                            <?php } ?>
                        <?php } else { ?>
                            <tr><td colspan="9">Aún no hay calificaciones cargadas para sus representados.</td></tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</body>
</html>
<?php $conn->close(); ?>
