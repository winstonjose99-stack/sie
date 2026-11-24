<?php
require_once __DIR__ . '/../config/conexion.php';
verificarSesionRepresentante('login_representante.php');

$id_representante = (int) $_SESSION['id_representante'];
$filtro_est       = intval($_GET['id_estudiante'] ?? 0);
$fecha_lista      = trim($_GET['fecha'] ?? $_POST['fecha'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_lista)) {
    $fecha_lista = date('Y-m-d');
}

// Acciones POST: marcar notificaciones como leídas o pasar lista desde el Portal de Representantes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = trim($_POST['accion'] ?? '');
    if ($accion === 'pasar_lista_representante') {
        $estadosPost = $_POST['estado'] ?? [];
        $motivosPost = $_POST['motivo_just'] ?? [];
        $obsPost     = $_POST['motivo_extra'] ?? [];

        $stmtEstVal = $conn->prepare("
            SELECT e.id_estudiante, e.nombre, e.apellido, e.id_seccion,
                   s.nombre_seccion, s.id_materia, s.id_docente, m.nombre_materia
            FROM estudiantes e
            INNER JOIN secciones s ON e.id_seccion = s.id_seccion
            LEFT JOIN materias m ON s.id_materia = m.id_materia
            WHERE e.id_estudiante = ? AND e.id_representante = ?
            LIMIT 1
        ");
        $stmtChk = $conn->prepare("
            SELECT id_inasistencia
            FROM inasistencias
            WHERE id_estudiante = ? AND id_materia = ? AND fecha = ?
            LIMIT 1
        ");
        $stmtIns = $conn->prepare("
            INSERT INTO inasistencias (id_estudiante, id_materia, id_docente, fecha, estado, motivo)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmtUpd = $conn->prepare("
            UPDATE inasistencias
            SET estado = ?, motivo = ?, id_docente = ?
            WHERE id_inasistencia = ?
        ");

        $registrados = 0;
        $actualizados = 0;

        foreach ($estadosPost as $idEstRaw => $estadoSel) {
            $idEst = intval($idEstRaw);
            $estadoSel = trim((string) $estadoSel);
            if ($idEst <= 0 || !in_array($estadoSel, ['Presente', 'Ausente', 'Justificado'], true)) {
                continue;
            }

            $stmtEstVal->bind_param("ii", $idEst, $id_representante);
            $stmtEstVal->execute();
            $estData = $stmtEstVal->get_result()->fetch_assoc();
            if (!$estData || empty($estData['id_materia']) || empty($estData['id_docente'])) {
                continue;
            }

            $idMat  = (int) $estData['id_materia'];
            $idDoc  = (int) $estData['id_docente'];
            $nomEst = trim($estData['nombre'] . ' ' . $estData['apellido']);
            $nomSec = $estData['nombre_seccion'] ?? '';
            $nomMat = $estData['nombre_materia'] ?? 'la materia';

            $obsLibre = trim((string) ($obsPost[$idEstRaw] ?? ''));
            if ($estadoSel === 'Presente') {
                $motivoBd = $obsLibre !== '' ? "Asistió — {$obsLibre} (Rep.)" : 'Asistió a clase (Reportado por Representante)';
            } elseif ($estadoSel === 'Justificado') {
                $razon = trim((string) ($motivosPost[$idEstRaw] ?? 'Motivos Personales'));
                if ($razon === '') {
                    $razon = 'Motivos Personales';
                }
                $motivoBd = $obsLibre !== '' ? "{$razon}: {$obsLibre} (Rep.)" : "{$razon} (Reportado por Representante)";
            } else {
                $motivoBd = $obsLibre !== '' ? "{$obsLibre} (Reportado por Representante)" : 'Inasistencia reportada por Representante';
            }
            if (function_exists('mb_substr')) {
                $motivoBd = mb_substr($motivoBd, 0, 148, 'UTF-8');
            } else {
                $motivoBd = substr($motivoBd, 0, 148);
            }

            $stmtChk->bind_param("iis", $idEst, $idMat, $fecha_lista);
            $stmtChk->execute();
            $exRow = $stmtChk->get_result()->fetch_assoc();

            $okOp = false;
            if ($exRow) {
                $idIna = (int) $exRow['id_inasistencia'];
                $stmtUpd->bind_param("ssii", $estadoSel, $motivoBd, $idDoc, $idIna);
                if ($stmtUpd->execute()) {
                    $actualizados++;
                    $okOp = true;
                }
            } else {
                $stmtIns->bind_param("iiisss", $idEst, $idMat, $idDoc, $fecha_lista, $estadoSel, $motivoBd);
                if ($stmtIns->execute()) {
                    $registrados++;
                    $okOp = true;
                }
            }

            if ($okOp) {
                $tipoN = $estadoSel === 'Presente' ? 'asistencia' : ($estadoSel === 'Justificado' ? 'justificado' : 'ausencia');
                $titN  = "Pase de lista registrado — {$nomMat}";
                $msgN  = "Usted reportó a {$nomEst} (Sección {$nomSec}) como {$estadoSel} el {$fecha_lista}. Detalle: {$motivoBd}. Sincronizado con Docente y Administración.";
                registrarNotificacionRepresentante($conn, $id_representante, $idEst, $tipoN, $titN, $msgN);
            }
        }

        $stmtEstVal->close();
        $stmtChk->close();
        $stmtIns->close();
        $stmtUpd->close();
        $conn->close();

        $totalGuard = $registrados + $actualizados;
        $redirLista = 'panel_representante.php?fecha=' . urlencode($fecha_lista) . ($filtro_est > 0 ? '&id_estudiante=' . $filtro_est : '') . '#pasar-lista';
        alertaYRedirigir(
            "Asistencia del {$fecha_lista} guardada ({$totalGuard} registro(s) sincronizados con el Docente y Administración).",
            $redirLista
        );
    } elseif ($accion === 'marcar_leida') {
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
    if (is_object($conn) && method_exists($conn, 'flushSyncCookies')) {
        $conn->flushSyncCookies();
    }
    $redir = 'panel_representante.php?fecha=' . urlencode($fecha_lista) . ($filtro_est > 0 ? '&id_estudiante=' . $filtro_est : '');
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

// 2. Estudiantes representados (incluyendo estado guardado para $fecha_lista)
$stmtEst = $conn->prepare("
    SELECT e.id_estudiante, e.nombre, e.apellido, e.cedula, e.fecha_nacimiento, e.sexo,
           s.id_seccion, s.nombre_seccion, s.id_materia, m.nombre_materia, i.nombre AS nombre_institucion
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

// Estado de asistencia ya registrado para $fecha_lista (para precargar las tarjetas móviles)
$asistenciaFechaMapa = [];
$stmtHoy = $conn->prepare("
    SELECT i.id_estudiante, i.estado, i.motivo
    FROM inasistencias i
    INNER JOIN estudiantes e ON i.id_estudiante = e.id_estudiante
    WHERE e.id_representante = ? AND i.fecha = ?
");
$stmtHoy->bind_param("is", $id_representante, $fecha_lista);
$stmtHoy->execute();
$resHoy = $stmtHoy->get_result();
if ($resHoy) {
    while ($h = $resHoy->fetch_assoc()) {
        $asistenciaFechaMapa[(int) $h['id_estudiante']] = $h;
    }
}
$stmtHoy->close();

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
        /* Pase de lista móvil (optimizado para teléfono) */
        .fecha-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 14px 16px;
            margin: 14px 0 18px 0;
        }
        .fecha-bar label {
            margin: 0;
            font-weight: 700;
            color: #0f172a;
            font-size: 0.95rem;
        }
        .fecha-bar input[type="date"] {
            margin: 0;
            max-width: 210px;
            min-height: 44px;
            font-size: 1rem;
            font-weight: 600;
        }
        .lista-movil-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(290px, 1fr));
            gap: 16px;
        }
        .est-check-card {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 14px;
            padding: 16px;
            text-align: left;
            box-shadow: 0 2px 10px rgba(15, 23, 42, 0.04);
        }
        .est-check-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 12px;
        }
        .est-check-name {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 700;
            color: #0f172a;
        }
        .est-check-sub {
            margin: 3px 0 0 0;
            font-size: 0.84rem;
            color: #64748b;
        }
        .estado-toggle-group {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-bottom: 12px;
        }
        .estado-opt {
            position: relative;
            margin: 0;
        }
        .estado-opt input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }
        .estado-opt span {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 46px;
            padding: 8px 6px;
            border-radius: 10px;
            border: 2px solid #cbd5e1;
            background: #f8fafc;
            color: #334155;
            font-weight: 700;
            font-size: 0.88rem;
            cursor: pointer;
            text-align: center;
            transition: all 0.15s ease;
            user-select: none;
        }
        .estado-opt input[type="radio"][value="Presente"]:checked + span {
            background: #dcfce7;
            border-color: #16a34a;
            color: #166534;
        }
        .estado-opt input[type="radio"][value="Ausente"]:checked + span {
            background: #fee2e2;
            border-color: #dc2626;
            color: #991b1b;
        }
        .estado-opt input[type="radio"][value="Justificado"]:checked + span {
            background: #fef3c7;
            border-color: #d97706;
            color: #92400e;
        }
        .just-box {
            margin-bottom: 10px;
        }
        .just-box select,
        .obs-movil-input {
            min-height: 42px;
            font-size: 0.92rem;
            margin-bottom: 0;
        }
        .btn-guardar-movil {
            width: 100%;
            max-width: 440px;
            min-height: 50px;
            font-size: 1rem;
            font-weight: 700;
            margin: 20px auto 0 auto;
            display: block;
        }
        @media (max-width: 768px) {
            .header-fijo {
                flex-wrap: wrap;
                padding: 10px 14px;
                gap: 8px;
            }
            .header-fijo h2 {
                font-size: 0.98rem;
            }
            .header-fijo nav ul {
                flex-wrap: wrap;
                gap: 6px;
                justify-content: center;
            }
            .dashboard-container {
                padding: 12px;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }
            .notif-item {
                flex-direction: column;
            }
            .fecha-bar {
                flex-direction: column;
                align-items: stretch;
            }
            .fecha-bar input[type="date"] {
                max-width: none;
                width: 100%;
            }
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
                <li><a href="#pasar-lista">Pasar Lista</a></li>
                <li><a href="#notificaciones">Notificaciones<?php echo $sinLeer > 0 ? " ({$sinLeer})" : ""; ?></a></li>
                <li><a href="#asistencias">Historial</a></li>
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
            <a href="panel_representante.php?fecha=<?php echo urlencode($fecha_lista); ?>" class="pill-btn <?php echo $filtro_est === 0 ? 'activo' : ''; ?>">
                Todos mis Representados (<?php echo count($estudiantes); ?>)
            </a>
            <?php foreach ($estudiantes as $est) { ?>
                <a href="panel_representante.php?fecha=<?php echo urlencode($fecha_lista); ?>&id_estudiante=<?php echo (int) $est['id_estudiante']; ?>"
                   class="pill-btn <?php echo $filtro_est === (int) $est['id_estudiante'] ? 'activo' : ''; ?>">
                    <?php echo htmlspecialchars(trim($est['nombre'] . ' ' . $est['apellido'])); ?>
                    (Sección <?php echo htmlspecialchars($est['nombre_seccion'] ?? 'N/A'); ?>)
                </a>
            <?php } ?>
        </div>
        <?php } ?>

        <!-- Sección 0: Pasar Lista Directamente desde el Teléfono -->
        <div id="pasar-lista" class="container" style="margin-top: 16px; margin-bottom: 32px;">
            <h2 style="text-align: left; margin-bottom: 4px;">Pasar Lista de Asistencia</h2>
            <p style="text-align: left; margin-top: 0;">Marque directamente desde su teléfono si su representado asistió, faltó o presenta inasistencia justificada. El registro se sincroniza al instante con el docente y administración.</p>

            <form method="POST" action="panel_representante.php?fecha=<?php echo urlencode($fecha_lista); ?><?php echo $filtro_est > 0 ? '&id_estudiante=' . $filtro_est : ''; ?>"
                  style="max-width: none; margin: 0; padding: 0; background: transparent; box-shadow: none;">
                <input type="hidden" name="accion" value="pasar_lista_representante">

                <div class="fecha-bar">
                    <label for="fecha_lista_input">Fecha del pase de lista:</label>
                    <input type="date" id="fecha_lista_input" name="fecha"
                           value="<?php echo htmlspecialchars($fecha_lista); ?>" required
                           onchange="window.location.href='panel_representante.php?fecha=' + encodeURIComponent(this.value) + '<?php echo $filtro_est > 0 ? '&id_estudiante=' . $filtro_est : ''; ?>#pasar-lista';">
                </div>

                <?php
                $estudiantesLista = [];
                foreach ($estudiantes as $estItem) {
                    if ($filtro_est > 0 && (int) $estItem['id_estudiante'] !== $filtro_est) {
                        continue;
                    }
                    $estudiantesLista[] = $estItem;
                }
                ?>

                <?php if (!empty($estudiantesLista)) { ?>
                <div class="lista-movil-grid">
                    <?php foreach ($estudiantesLista as $estItem) {
                        $eId = (int) $estItem['id_estudiante'];
                        $regHoy = $asistenciaFechaMapa[$eId] ?? null;
                        $estadoActual = $regHoy ? (string) $regHoy['estado'] : 'Presente';
                        $motivoActual = $regHoy ? (string) ($regHoy['motivo'] ?? '') : '';
                        $tieneSeccion = !empty($estItem['id_materia']);

                        $motivoPre = 'Cita Médica';
                        foreach (['Cita Médica', 'Reposo', 'Malestar', 'Motivos Personales'] as $opM) {
                            if ($motivoActual !== '' && stripos($motivoActual, $opM) !== false) {
                                $motivoPre = $opM;
                                break;
                            }
                        }
                    ?>
                    <div class="est-check-card">
                        <div class="est-check-head">
                            <div>
                                <p class="est-check-name"><?php echo htmlspecialchars(trim($estItem['nombre'] . ' ' . $estItem['apellido'])); ?></p>
                                <p class="est-check-sub">
                                    Sección <?php echo htmlspecialchars($estItem['nombre_seccion'] ?? 'Sin asignar'); ?> |
                                    <?php echo htmlspecialchars($estItem['nombre_materia'] ?? 'Sin materia'); ?>
                                </p>
                            </div>
                            <?php if ($regHoy) {
                                $bHoy = $estadoActual === 'Ausente' ? 'badge-ausente' : ($estadoActual === 'Justificado' ? 'badge-justificado' : 'badge-presente');
                            ?>
                                <span class="badge <?php echo $bHoy; ?>"><?php echo htmlspecialchars($estadoActual); ?></span>
                            <?php } else { ?>
                                <span class="badge-nota">Pendiente</span>
                            <?php } ?>
                        </div>

                        <?php if ($tieneSeccion) { ?>
                        <div class="estado-toggle-group">
                            <label class="estado-opt">
                                <input type="radio" name="estado[<?php echo $eId; ?>]" value="Presente"
                                       <?php echo $estadoActual === 'Presente' ? 'checked' : ''; ?>
                                       onchange="toggleJustBox(<?php echo $eId; ?>, false)">
                                <span>Presente</span>
                            </label>
                            <label class="estado-opt">
                                <input type="radio" name="estado[<?php echo $eId; ?>]" value="Ausente"
                                       <?php echo $estadoActual === 'Ausente' ? 'checked' : ''; ?>
                                       onchange="toggleJustBox(<?php echo $eId; ?>, false)">
                                <span>Ausente</span>
                            </label>
                            <label class="estado-opt">
                                <input type="radio" name="estado[<?php echo $eId; ?>]" value="Justificado"
                                       <?php echo $estadoActual === 'Justificado' ? 'checked' : ''; ?>
                                       onchange="toggleJustBox(<?php echo $eId; ?>, true)">
                                <span>Justificado</span>
                            </label>
                        </div>

                        <div id="just_box_<?php echo $eId; ?>" class="just-box" style="display: <?php echo $estadoActual === 'Justificado' ? 'block' : 'none'; ?>;">
                            <select name="motivo_just[<?php echo $eId; ?>]">
                                <option value="Cita Médica" <?php echo $motivoPre === 'Cita Médica' ? 'selected' : ''; ?>>Motivo: Cita Médica</option>
                                <option value="Reposo" <?php echo $motivoPre === 'Reposo' ? 'selected' : ''; ?>>Motivo: Reposo</option>
                                <option value="Malestar" <?php echo $motivoPre === 'Malestar' ? 'selected' : ''; ?>>Motivo: Malestar</option>
                                <option value="Motivos Personales" <?php echo $motivoPre === 'Motivos Personales' ? 'selected' : ''; ?>>Motivo: Motivos Personales</option>
                            </select>
                        </div>

                        <input type="text" class="obs-movil-input"
                               name="motivo_extra[<?php echo $eId; ?>]"
                               placeholder="Observación opcional (ej. llegó puntual, reposo 2 días...)">
                        <?php } else { ?>
                            <p style="font-size: 0.85rem; color: #b91c1c; margin: 0;">Este estudiante aún no tiene una sección/materia asignada.</p>
                        <?php } ?>
                    </div>
                    <?php } ?>
                </div>

                <button type="submit" class="btn-guardar-movil">Guardar Pase de Lista del Día</button>
                <?php } else { ?>
                    <p style="color: #64748b;">No hay estudiantes vinculados a su cuenta para pasar lista.</p>
                <?php } ?>
            </form>
        </div>

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

    <script>
        function toggleJustBox(estId, mostrar) {
            var box = document.getElementById('just_box_' + estId);
            if (box) {
                box.style.display = mostrar ? 'block' : 'none';
            }
        }
    </script>

</body>
</html>
<?php $conn->close(); ?>
