<?php
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/whatsapp.php';
require_once __DIR__ . '/../config/zapier.php';
verificarSesionDocente('login_profesor.php');

$id_seccion = intval($_GET['id_seccion'] ?? 0);
$id_materia = intval($_GET['id_materia'] ?? 0);
$id_docente = (int) $_SESSION['id_docente'];
$fecha_actual = trim($_GET['fecha'] ?? date('Y-m-d'));

if ($id_seccion <= 0 || $id_materia <= 0) {
    header("Location: panel_profesor.php");
    exit();
}

// Verificar que la sección y materia realmente pertenecen al docente logueado en el período activo
$stmt_sec = $conn->prepare("
    SELECT s.id_seccion, s.nombre_seccion, m.id_materia, m.nombre_materia, i.nombre AS nombre_institucion
    FROM secciones s
    INNER JOIN materias m ON s.id_materia = m.id_materia
    LEFT JOIN instituciones i ON s.id_institucion = i.id_institucion
    INNER JOIN periodos_academicos p ON s.id_periodo = p.id_periodo
    WHERE s.id_seccion = ? AND s.id_materia = ? AND s.id_docente = ? AND p.activo = 1
    LIMIT 1
");
$stmt_sec->bind_param("iii", $id_seccion, $id_materia, $id_docente);
$stmt_sec->execute();
$res_sec = $stmt_sec->get_result();
$seccion_info = $res_sec ? $res_sec->fetch_assoc() : null;
$stmt_sec->close();

if (!$seccion_info) {
    alertaYRedirigir('No tiene permiso para gestionar esta sección o no pertenece al período activo.', 'panel_profesor.php');
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $fecha   = $_POST['fecha'] ?? date('Y-m-d');
    $estados = $_POST['estado'] ?? [];

    $stmt_check = $conn->prepare("
        SELECT id_inasistencia, estado
        FROM inasistencias
        WHERE id_estudiante = ? AND id_materia = ? AND fecha = ?
        LIMIT 1
    ");

    $stmt_insert = $conn->prepare("
        INSERT INTO inasistencias (id_estudiante, id_materia, id_docente, fecha, estado, motivo)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt_update = $conn->prepare("
        UPDATE inasistencias
        SET estado = ?, motivo = ?, id_docente = ?
        WHERE id_inasistencia = ?
    ");

    $stmt_info = $conn->prepare("
        SELECT e.nombre AS est_nombre, e.apellido AS est_apellido,
               e.cedula AS est_cedula, e.fecha_nacimiento AS est_nacimiento, e.sexo AS est_sexo,
               e.id_representante,
               r.nombre AS rep_nombre, r.apellido AS rep_apellido, r.telefono AS rep_telefono
        FROM estudiantes e
        LEFT JOIN representantes r ON e.id_representante = r.id_representante
        WHERE e.id_estudiante = ? AND e.id_seccion = ?
        LIMIT 1
    ");

    if (!$stmt_check || !$stmt_insert || !$stmt_update || !$stmt_info) {
        alertaYRedirigir('Error interno al preparar el registro de asistencias.');
    }

    $nombre_materia = $seccion_info['nombre_materia'] ?? 'la asignatura';
    $nombre_seccion = $seccion_info['nombre_seccion'] ?? '';
    $presentes      = 0;
    $faltasNuevas   = 0;
    $actualizadas   = 0;
    $portalNotifs   = 0;
    $waEnviados     = 0;
    $waFallidos     = 0;
    $zapierEnviados = 0;
    $zapierFallidos = 0;

    foreach ($estados as $id_est_raw => $valorSeleccionado) {
        $id_estudiante = intval($id_est_raw);
        if ($id_estudiante <= 0) {
            continue;
        }

        // Validar que el estudiante pertenece realmente a esta sección
        $stmt_info->bind_param("ii", $id_estudiante, $id_seccion);
        $stmt_info->execute();
        $res_info = $stmt_info->get_result();
        $info = $res_info ? $res_info->fetch_assoc() : null;
        if (!$info) {
            continue;
        }

        // Mapear selección al ENUM ('Presente', 'Ausente', 'Justificado') + columna `motivo`
        $estado_bd = 'Presente';
        $motivo_bd = null;

        if ($valorSeleccionado === 'Presente') {
            $estado_bd = 'Presente';
            $motivo_bd = 'Asistió a clase';
            $presentes++;
        } elseif (strpos($valorSeleccionado, 'Justificado') === 0) {
            $estado_bd = 'Justificado';
            $partes = explode(' - ', $valorSeleccionado, 2);
            $motivo_bd = isset($partes[1]) ? trim($partes[1]) : 'Justificado';
        } elseif ($valorSeleccionado === 'Ausente') {
            $estado_bd = 'Ausente';
            $motivo_bd = null;
        } else {
            continue;
        }

        // Revisar si ya existe un registro para este estudiante/materia/fecha
        $stmt_check->bind_param("iis", $id_estudiante, $id_materia, $fecha);
        $stmt_check->execute();
        $res_check = $stmt_check->get_result();
        $existente = $res_check ? $res_check->fetch_assoc() : null;

        $enviarAlerta = false;
        $registroGuardado = false;
        if ($existente) {
            $id_ina = (int) $existente['id_inasistencia'];
            $stmt_update->bind_param("ssii", $estado_bd, $motivo_bd, $id_docente, $id_ina);
            if ($stmt_update->execute()) {
                $actualizadas++;
                $registroGuardado = true;
                if (in_array($estado_bd, ['Ausente', 'Justificado'], true)) {
                    $enviarAlerta = true;
                }
            }
        } else {
            $stmt_insert->bind_param("iiisss", $id_estudiante, $id_materia, $id_docente, $fecha, $estado_bd, $motivo_bd);
            if ($stmt_insert->execute()) {
                $registroGuardado = true;
                if (in_array($estado_bd, ['Ausente', 'Justificado'], true)) {
                    $faltasNuevas++;
                    $enviarAlerta = true;
                }
            }
        }

        $nombre_estudiante    = trim($info['est_nombre'] . ' ' . $info['est_apellido']);
        $nombre_representante = trim(($info['rep_nombre'] ?? '') . ' ' . ($info['rep_apellido'] ?? '')) ?: 'Representante';
        $id_rep               = (int) ($info['id_representante'] ?? 0);

        // Registrar notificación interna en el Portal de Representantes (asistió, faltó o justificado)
        if ($registroGuardado && $id_rep > 0) {
            if ($estado_bd === 'Presente') {
                $tipoNotif   = 'asistencia';
                $tituloNotif = "Asistencia confirmada — {$nombre_materia}";
                $msgPortal   = "{$nombre_estudiante} asistió a la clase de {$nombre_materia} (Sección {$nombre_seccion}) el día {$fecha}.";
            } elseif ($estado_bd === 'Justificado') {
                $tipoNotif   = 'justificado';
                $tituloNotif = "Inasistencia justificada — {$nombre_materia}";
                $msgPortal   = "{$nombre_estudiante} registró inasistencia justificada en {$nombre_materia} (Sección {$nombre_seccion}) el {$fecha}." . ($motivo_bd ? " Motivo: {$motivo_bd}." : "");
            } else {
                $tipoNotif   = 'ausencia';
                $tituloNotif = "Inasistencia registrada — {$nombre_materia}";
                $msgPortal   = "{$nombre_estudiante} no asistió a la clase de {$nombre_materia} (Sección {$nombre_seccion}) el día {$fecha}. Estado: Ausente.";
            }
            if (registrarNotificacionRepresentante($conn, $id_rep, $id_estudiante, $tipoNotif, $tituloNotif, $msgPortal)) {
                $portalNotifs++;
            }
        }

        // Enviar datos a Zapier (si está habilitado) y notificación de WhatsApp cuando se marca inasistencia
        if ($enviarAlerta) {
            $detalleEstado        = $motivo_bd ? "{$estado_bd} ({$motivo_bd})" : $estado_bd;

            $mensaje = "Estimado/a {$nombre_representante}, le informamos que el estudiante *{$nombre_estudiante}* (Sección {$nombre_seccion}) no asistió el día {$fecha} a la materia *{$nombre_materia}* [Estado: {$detalleEstado}].";

            // 1. Petición HTTP POST en JSON hacia el Webhook de Zapier (si está configurado)
            if (zapierWebhookEstaConfigurado()) {
                $zapRes = enviarInasistenciaAZapier([
                    'evento'                 => 'inasistencia_registrada',
                    'fecha'                  => $fecha,
                    'estado'                 => $estado_bd,
                    'motivo'                 => $motivo_bd ?? 'Sin justificar',
                    'id_estudiante'          => $id_estudiante,
                    'estudiante_nombre'      => $info['est_nombre'] ?? '',
                    'estudiante_apellido'    => $info['est_apellido'] ?? '',
                    'estudiante_completo'    => $nombre_estudiante,
                    'estudiante_cedula'      => $info['est_cedula'] ?? '',
                    'estudiante_nacimiento'  => $info['est_nacimiento'] ?? '',
                    'estudiante_sexo'        => $info['est_sexo'] ?? '',
                    'representante_nombre'   => $nombre_representante,
                    'representante_telefono' => $info['rep_telefono'] ?? '',
                    'id_seccion'             => $id_seccion,
                    'seccion'                => $nombre_seccion,
                    'id_materia'             => $id_materia,
                    'materia'                => $nombre_materia,
                    'institucion'            => $seccion_info['nombre_institucion'] ?? '',
                    'id_docente'             => $id_docente,
                    'docente'                => $_SESSION['nombre_docente'] ?? '',
                    'mensaje'                => $mensaje,
                ]);
                if ($zapRes['ok']) {
                    $zapierEnviados++;
                } else {
                    $zapierFallidos++;
                }
            }

            // 2. Envío directo por WhatsApp Cloud API (si tiene teléfono)
            if (!empty($info['rep_telefono'])) {
                $wa = enviarNotificacionWhatsApp($info['rep_telefono'], $mensaje);
                if ($wa['ok']) {
                    $waEnviados++;
                } else {
                    $waFallidos++;
                    $ultimoErrorWa = $wa['error'];
                }
            }
        }
    }

    $stmt_check->close();
    $stmt_insert->close();
    $stmt_update->close();
    $stmt_info->close();
    $conn->close();

    $resumen = "Lista de asistencia guardada ({$fecha}). Presentes: {$presentes}, Inasistencias nuevas: {$faltasNuevas}";
    if ($actualizadas > 0) {
        $resumen .= ", registros actualizados: {$actualizadas}";
    }
    if ($portalNotifs > 0) {
        $resumen .= ". Notificaciones enviadas al Portal de Representantes: {$portalNotifs}";
    }
    if ($zapierEnviados > 0) {
        $resumen .= ". Enviadas a Zapier: {$zapierEnviados}";
    }
    if ($zapierFallidos > 0) {
        $resumen .= ". Aviso Zapier: {$zapierFallidos} envío(s) fallaron";
    }
    if ($waEnviados > 0) {
        $resumen .= ". Notificaciones WhatsApp enviadas: {$waEnviados}";
    }
    if ($waFallidos > 0) {
        $detalleErr = !empty($ultimoErrorWa) ? " ({$ultimoErrorWa})" : "";
        $resumen .= ". Aviso WhatsApp: {$waFallidos} no enviada(s){$detalleErr}.";
    }

    $urlDestino = "asistencias_guardadas.php?id_seccion={$id_seccion}&id_materia={$id_materia}&fecha=" . urlencode($fecha);
    alertaYRedirigir($resumen, $urlDestino);
}

// Cargar los estudiantes inscritos en esta sección junto con su estado ya guardado para $fecha_actual
$stmt_est = $conn->prepare("
    SELECT e.*, i.id_inasistencia, i.estado AS estado_guardado, i.motivo AS motivo_guardado
    FROM estudiantes e
    LEFT JOIN inasistencias i
           ON i.id_estudiante = e.id_estudiante
          AND i.id_materia = ?
          AND i.fecha = ?
    WHERE e.id_seccion = ?
    ORDER BY e.apellido, e.nombre
");
$stmt_est->bind_param("isi", $id_materia, $fecha_actual, $id_seccion);
$stmt_est->execute();
$res_est = $stmt_est->get_result();
$listaEstudiantes = [];
if ($res_est) {
    while ($rowE = $res_est->fetch_assoc()) {
        $listaEstudiantes[] = $rowE;
    }
}
$stmt_est->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tomar Asistencia - Portal Docente</title>
    <link rel="stylesheet" href="../css/estilos.css">
    <style>
        .asistencia-wrap {
            max-width: 1040px;
            margin: 22px auto 96px auto;
            padding: 0 16px;
        }
        .toolbar-movil {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: flex-end;
            justify-content: space-between;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 14px;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.04);
        }
        .toolbar-grupo {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 10px;
        }
        .toolbar-campo {
            display: flex;
            flex-direction: column;
            gap: 4px;
            margin: 0 !important;
        }
        .toolbar-movil label {
            margin: 0;
            font-weight: 700;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #475569;
        }
        .toolbar-movil input[type="date"],
        .toolbar-movil input[type="search"] {
            margin: 0;
            min-height: 44px;
            font-size: 0.94rem;
            border-radius: 11px;
        }
        .toolbar-movil input[type="date"] {
            max-width: 185px;
            font-weight: 600;
        }
        .toolbar-movil input[type="search"] {
            min-width: 240px;
        }
        .toolbar-acciones {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            margin: 0 !important;
        }
        .btn-todos-presentes {
            background: #ecfdf5 !important;
            color: #065f46 !important;
            border: 1px solid #86efac !important;
            padding: 10px 14px !important;
            min-height: 44px;
            border-radius: 11px !important;
            font-weight: 700 !important;
            font-size: 0.86rem !important;
            cursor: pointer;
            margin: 0 !important;
            width: auto !important;
            box-shadow: none !important;
            transition: background-color 0.15s ease, transform 0.12s ease;
        }
        .btn-todos-presentes:hover {
            background: #d1fae5 !important;
            transform: translateY(-1px);
        }
        .btn-todos-presentes:active {
            transform: scale(0.97);
        }
        .btn-ver-guardada {
            min-height: 44px;
            padding: 10px 15px !important;
            border-radius: 11px !important;
            font-size: 0.86rem !important;
            font-weight: 700 !important;
            white-space: nowrap;
        }
        .resumen-vivo {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 16px;
        }
        .resumen-pill {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-top: 3px solid #cbd5e1;
            border-radius: 14px;
            padding: 11px 10px;
            text-align: center;
            box-shadow: 0 3px 10px rgba(15, 23, 42, 0.04);
        }
        .resumen-pill.p { border-top-color: #16a34a; }
        .resumen-pill.a { border-top-color: #dc2626; }
        .resumen-pill.j { border-top-color: #d97706; }
        .resumen-pill .num {
            display: block;
            font-size: 1.45rem;
            font-weight: 800;
            line-height: 1.15;
            font-variant-numeric: tabular-nums;
        }
        .resumen-pill .lbl {
            display: block;
            margin-top: 2px;
            font-size: 0.74rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .resumen-pill.p .num, .resumen-pill.p .lbl { color: #166534; }
        .resumen-pill.a .num, .resumen-pill.a .lbl { color: #991b1b; }
        .resumen-pill.j .num, .resumen-pill.j .lbl { color: #92400e; }

        .alumnos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 12px;
        }
        .alumno-card {
            background: #ffffff;
            border: 1px solid #dbe4f0;
            border-radius: 16px;
            padding: 15px;
            text-align: left;
            box-shadow: 0 3px 12px rgba(15, 23, 42, 0.04);
            transition: border-color 0.16s ease, background-color 0.16s ease, box-shadow 0.16s ease;
        }
        .alumno-card.st-presente {
            border-left: 4px solid #16a34a;
        }
        .alumno-card.st-ausente {
            border-left: 4px solid #dc2626;
            background: #fffafa;
            border-color: #fecaca;
            border-left-color: #dc2626;
        }
        .alumno-card.st-justificado {
            border-left: 4px solid #d97706;
            background: #fffdf5;
            border-color: #fde68a;
            border-left-color: #d97706;
        }
        .alumno-top {
            display: flex;
            align-items: flex-start;
            gap: 11px;
            margin-bottom: 12px;
        }
        .alumno-info {
            flex: 1;
            min-width: 0;
        }
        .alumno-name-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 8px;
            flex-wrap: wrap;
        }
        .alumno-nombre {
            margin: 0;
            font-size: 0.98rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.3;
            word-break: break-word;
        }
        .alumno-meta {
            margin: 4px 0 0 0;
            font-size: 0.79rem;
            color: #64748b;
            line-height: 1.35;
        }
        .chip-guardado {
            padding: 3px 9px;
            font-size: 0.72rem;
            font-weight: 700;
            border-radius: 999px;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .chip-sin-guardar {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
        }
        .botones-estado {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 7px;
            background: #f1f5f9;
            padding: 4px;
            border-radius: 13px;
        }
        .btn-estado-tap {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 8px 4px !important;
            border-radius: 10px !important;
            border: 1.5px solid transparent !important;
            background: transparent !important;
            color: #475569 !important;
            font-weight: 700 !important;
            font-size: 0.84rem !important;
            cursor: pointer;
            margin: 0 !important;
            width: 100% !important;
            box-shadow: none !important;
            white-space: nowrap;
            transition: background-color 0.14s ease, color 0.14s ease, border-color 0.14s ease, box-shadow 0.14s ease, transform 0.1s ease;
        }
        .btn-estado-tap:hover {
            transform: none !important;
            filter: none !important;
            background: rgba(255, 255, 255, 0.7) !important;
        }
        .btn-estado-tap:active {
            transform: scale(0.96) !important;
        }
        .btn-estado-tap.activo-p {
            background: #16a34a !important;
            border-color: #15803d !important;
            color: #ffffff !important;
            box-shadow: 0 3px 10px rgba(22, 163, 74, 0.25) !important;
        }
        .btn-estado-tap.activo-a {
            background: #dc2626 !important;
            border-color: #b91c1c !important;
            color: #ffffff !important;
            box-shadow: 0 3px 10px rgba(220, 38, 38, 0.25) !important;
        }
        .btn-estado-tap.activo-j {
            background: #d97706 !important;
            border-color: #b45309 !important;
            color: #ffffff !important;
            box-shadow: 0 3px 10px rgba(217, 119, 6, 0.25) !important;
        }
        .just-motivo-wrap {
            margin-top: 10px;
        }
        .just-motivo-wrap select {
            margin: 0;
            min-height: 44px;
            font-size: 0.9rem;
            background: #ffffff;
            border-color: #fcd34d;
        }
        .barra-guardar-fija {
            position: sticky;
            bottom: 10px;
            z-index: 60;
            margin-top: 20px;
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border: 1px solid #cbd5e1;
            border-radius: 16px;
            padding: 12px 16px;
            box-shadow: 0 12px 32px rgba(15, 23, 42, 0.16);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }
        .barra-guardar-meta {
            font-size: 0.86rem;
            color: #334155;
            font-weight: 600;
            text-align: left;
        }
        .barra-guardar-fija .btn-guardar {
            margin: 0;
            min-height: 48px;
            font-size: 0.96rem;
            font-weight: 700;
            border-radius: 12px;
            max-width: 340px;
            width: 100%;
        }
        @media (max-width: 768px) {
            .asistencia-wrap {
                margin: 12px auto 32px auto;
                padding: 0 12px;
            }
            .toolbar-movil {
                flex-direction: column;
                align-items: stretch;
                padding: 14px;
                gap: 10px;
            }
            .toolbar-grupo {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }
            .toolbar-movil input[type="date"],
            .toolbar-movil input[type="search"] {
                max-width: none !important;
                min-width: 0 !important;
                width: 100% !important;
            }
            .toolbar-acciones {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 8px;
                width: 100%;
            }
            .btn-todos-presentes,
            .btn-ver-guardada {
                width: 100% !important;
                padding: 10px 8px !important;
                font-size: 0.82rem !important;
                justify-content: center;
            }
            .resumen-vivo {
                gap: 8px;
                margin-bottom: 14px;
            }
            .resumen-pill {
                padding: 9px 6px;
                border-radius: 12px;
            }
            .resumen-pill .num {
                font-size: 1.3rem;
            }
            .resumen-pill .lbl {
                font-size: 0.68rem;
            }
            .alumnos-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            .alumno-card {
                padding: 14px 13px;
            }
            .btn-estado-tap {
                font-size: 0.81rem !important;
                min-height: 45px;
            }
            .barra-guardar-fija {
                flex-direction: column;
                align-items: stretch;
                bottom: 8px;
                padding: 11px 12px;
                gap: 8px;
                border-radius: 16px;
            }
            .barra-guardar-meta {
                text-align: center;
                font-size: 0.82rem;
            }
            .barra-guardar-fija .btn-guardar {
                max-width: none;
                min-height: 50px;
            }
        }
    </style>
</head>
<body class="portal-docente">

    <header class="header-fijo" id="appHeader">
        <div class="header-brand">
            <a href="panel_profesor.php" class="logo" aria-label="Volver al Portal Docente">
                <img src="logo.png" alt="Logo">
            </a>
            <h2 class="header-title">Pase de Lista</h2>
        </div>

        <div class="header-mobile-controls">
            <a href="panel_profesor.php" class="header-quick-back">Panel</a>
            <button type="button" class="nav-toggle" id="navToggle" aria-label="Abrir menú de navegación" aria-expanded="false" aria-controls="headerNav">
                <span class="nav-toggle-bar"></span>
                <span class="nav-toggle-bar"></span>
                <span class="nav-toggle-bar"></span>
            </button>
        </div>

        <nav class="header-nav" id="headerNav" aria-label="Navegación pase de lista">
            <ul>
                <li><a href="panel_profesor.php">Volver al Panel</a></li>
                <li><a href="asistencias_guardadas.php?id_seccion=<?php echo $id_seccion; ?>&id_materia=<?php echo $id_materia; ?>&fecha=<?php echo urlencode($fecha_actual); ?>">Asistencias Guardadas</a></li>
                <li><a href="../api/logout.php">Cerrar Sesión</a></li>
            </ul>
        </nav>
    </header>
    <div class="nav-backdrop" id="navBackdrop" aria-hidden="true"></div>

    <div class="asistencia-wrap">
        <div class="asistencia-header-card docente-hero">
            <span class="docente-hero-eyebrow">Sección <?php echo htmlspecialchars($seccion_info['nombre_seccion']); ?></span>
            <h2><?php echo htmlspecialchars($seccion_info['nombre_materia']); ?></h2>
            <p>Toque el estado de cada estudiante y guarde la asistencia de la clase al finalizar.</p>
            <div class="docente-hero-meta">
                <span class="docente-hero-chip">Matrícula: <?php echo count($listaEstudiantes); ?> estudiante(s)</span>
                <span class="docente-hero-chip">Fecha: <?php echo htmlspecialchars($fecha_actual); ?></span>
            </div>
        </div>

        <form action="" method="POST" style="max-width: none; margin: 0; padding: 0; border: none; background: transparent; box-shadow: none;">
            <div class="toolbar-movil">
                <div class="toolbar-grupo">
                    <div class="toolbar-campo">
                        <label for="fecha">Fecha de Clase</label>
                        <input type="date" id="fecha" name="fecha" value="<?php echo htmlspecialchars($fecha_actual); ?>" required
                               onchange="window.location.href='registrar_inasistencia.php?id_seccion=<?php echo $id_seccion; ?>&id_materia=<?php echo $id_materia; ?>&fecha=' + encodeURIComponent(this.value);">
                    </div>
                    <div class="toolbar-campo">
                        <label for="buscador_alumno">Buscar Estudiante</label>
                        <input type="search" id="buscador_alumno" placeholder="Nombre, apellido o cédula..." oninput="filtrarAlumnos(this.value)">
                    </div>
                </div>

                <div class="toolbar-acciones">
                    <button type="button" class="btn-todos-presentes" onclick="marcarTodosPresentes()">Todos Presentes</button>
                    <a href="asistencias_guardadas.php?id_seccion=<?php echo $id_seccion; ?>&id_materia=<?php echo $id_materia; ?>&fecha=<?php echo urlencode($fecha_actual); ?>" class="btn-ver btn-ver-guardada">
                        Ver Guardada
                    </a>
                </div>
            </div>

            <!-- Contadores en tiempo real -->
            <div class="resumen-vivo">
                <div class="resumen-pill p">
                    <span class="num" id="cnt_presentes">0</span>
                    <span class="lbl">Presentes</span>
                </div>
                <div class="resumen-pill a">
                    <span class="num" id="cnt_ausentes">0</span>
                    <span class="lbl">Ausentes</span>
                </div>
                <div class="resumen-pill j">
                    <span class="num" id="cnt_justificados">0</span>
                    <span class="lbl">Justificados</span>
                </div>
            </div>

            <?php if (!empty($listaEstudiantes)) { ?>
            <div class="alumnos-grid" id="lista_alumnos_grid">
                <?php
                $num = 1;
                foreach ($listaEstudiantes as $est) {
                    $eId     = (int) $est['id_estudiante'];
                    $estadoG = $est['estado_guardado'] ?? null;
                    $motivoG = $est['motivo_guardado'] ?? null;

                    $tipoBase = 'Presente';
                    $motivoSel = 'Cita Médica';
                    $valorFinal = 'Presente';

                    if ($estadoG === 'Ausente') {
                        $tipoBase = 'Ausente';
                        $valorFinal = 'Ausente';
                    } elseif ($estadoG === 'Justificado') {
                        $tipoBase = 'Justificado';
                        $motivosValidos = ['Cita Médica', 'Reposo', 'Malestar', 'Motivos Personales'];
                        $motivoSel = in_array((string) $motivoG, $motivosValidos, true) ? (string) $motivoG : 'Motivos Personales';
                        $valorFinal = 'Justificado - ' . $motivoSel;
                    }

                    $cardClass = $tipoBase === 'Ausente' ? 'st-ausente' : ($tipoBase === 'Justificado' ? 'st-justificado' : 'st-presente');
                    $nombreCompleto = trim($est['apellido'] . ', ' . $est['nombre']);
                    $searchKey = strtolower($est['nombre'] . ' ' . $est['apellido'] . ' ' . $est['cedula']);
                    $fechaNac = (!empty($est['fecha_nacimiento']) && $est['fecha_nacimiento'] !== '0000-00-00') ? $est['fecha_nacimiento'] : 'N/A';
                    $sexoTxt  = !empty($est['sexo']) ? $est['sexo'] : 'N/A';

                    $iniNom = trim((string) ($est['nombre'] ?? ''));
                    $iniApe = trim((string) ($est['apellido'] ?? ''));
                    $l1 = function_exists('mb_substr') ? mb_substr($iniNom, 0, 1, 'UTF-8') : substr($iniNom, 0, 1);
                    $l2 = function_exists('mb_substr') ? mb_substr($iniApe, 0, 1, 'UTF-8') : substr($iniApe, 0, 1);
                    $iniciales = strtoupper(trim($l1 . $l2)) ?: 'AL';
                ?>
                <div class="alumno-card <?php echo $cardClass; ?>" id="card_<?php echo $eId; ?>" data-search="<?php echo htmlspecialchars($searchKey); ?>">
                    <input type="hidden" id="estado_val_<?php echo $eId; ?>" name="estado[<?php echo $eId; ?>]" value="<?php echo htmlspecialchars($valorFinal); ?>" data-tipo="<?php echo $tipoBase; ?>">

                    <div class="alumno-top">
                        <div class="avatar-iniciales <?php echo $cardClass; ?>" id="avatar_<?php echo $eId; ?>" aria-hidden="true">
                            <?php echo htmlspecialchars($iniciales); ?>
                        </div>
                        <div class="alumno-info">
                            <div class="alumno-name-row">
                                <p class="alumno-nombre"><?php echo $num++; ?>. <?php echo htmlspecialchars($nombreCompleto); ?></p>
                                <?php if ($estadoG === 'Ausente') { ?>
                                    <span class="badge badge-ausente chip-guardado">Guardado: Ausente</span>
                                <?php } elseif ($estadoG === 'Justificado') { ?>
                                    <span class="badge badge-justificado chip-guardado">Guardado: Justif.</span>
                                <?php } elseif ($estadoG === 'Presente') { ?>
                                    <span class="badge badge-presente chip-guardado">Guardado: Presente</span>
                                <?php } else { ?>
                                    <span class="badge chip-guardado chip-sin-guardar">Sin guardar</span>
                                <?php } ?>
                            </div>
                            <p class="alumno-meta">
                                C.I. <?php echo htmlspecialchars($est['cedula']); ?> ·
                                F. Nac: <?php echo htmlspecialchars($fechaNac); ?> ·
                                Sexo: <?php echo htmlspecialchars($sexoTxt); ?>
                            </p>
                        </div>
                    </div>

                    <div class="botones-estado">
                        <button type="button" id="btn_p_<?php echo $eId; ?>"
                                class="btn-estado-tap <?php echo $tipoBase === 'Presente' ? 'activo-p' : ''; ?>"
                                onclick="seleccionarEstado(<?php echo $eId; ?>, 'Presente')">
                            Presente
                        </button>
                        <button type="button" id="btn_a_<?php echo $eId; ?>"
                                class="btn-estado-tap <?php echo $tipoBase === 'Ausente' ? 'activo-a' : ''; ?>"
                                onclick="seleccionarEstado(<?php echo $eId; ?>, 'Ausente')">
                            Ausente
                        </button>
                        <button type="button" id="btn_j_<?php echo $eId; ?>"
                                class="btn-estado-tap <?php echo $tipoBase === 'Justificado' ? 'activo-j' : ''; ?>"
                                onclick="seleccionarEstado(<?php echo $eId; ?>, 'Justificado')">
                            Justificado
                        </button>
                    </div>

                    <div class="just-motivo-wrap" id="wrap_motivo_<?php echo $eId; ?>" style="display: <?php echo $tipoBase === 'Justificado' ? 'block' : 'none'; ?>;">
                        <select id="motivo_sel_<?php echo $eId; ?>" onchange="actualizarMotivo(<?php echo $eId; ?>)">
                            <option value="Cita Médica" <?php echo $motivoSel === 'Cita Médica' ? 'selected' : ''; ?>>Motivo: Cita Médica</option>
                            <option value="Reposo" <?php echo $motivoSel === 'Reposo' ? 'selected' : ''; ?>>Motivo: Reposo</option>
                            <option value="Malestar" <?php echo $motivoSel === 'Malestar' ? 'selected' : ''; ?>>Motivo: Malestar</option>
                            <option value="Motivos Personales" <?php echo $motivoSel === 'Motivos Personales' ? 'selected' : ''; ?>>Motivo: Motivos Personales</option>
                        </select>
                    </div>
                </div>
                <?php } ?>
            </div>

            <div class="barra-guardar-fija">
                <div class="barra-guardar-meta">
                    Fecha: <strong><?php echo htmlspecialchars($fecha_actual); ?></strong> — Sección <?php echo htmlspecialchars($seccion_info['nombre_seccion']); ?>
                </div>
                <button type="submit" class="btn-guardar">Guardar Asistencia de la Clase</button>
            </div>
            <?php } else { ?>
                <div class="container">
                    <p>No hay estudiantes inscritos en esta sección.</p>
                </div>
            <?php } ?>
        </form>
    </div>

    <script>
        function seleccionarEstado(id, tipo) {
            var hidden = document.getElementById('estado_val_' + id);
            var card   = document.getElementById('card_' + id);
            var avatar = document.getElementById('avatar_' + id);
            var btnP   = document.getElementById('btn_p_' + id);
            var btnA   = document.getElementById('btn_a_' + id);
            var btnJ   = document.getElementById('btn_j_' + id);
            var wrapM  = document.getElementById('wrap_motivo_' + id);
            var selM   = document.getElementById('motivo_sel_' + id);

            btnP.classList.remove('activo-p');
            btnA.classList.remove('activo-a');
            btnJ.classList.remove('activo-j');
            card.classList.remove('st-presente', 'st-ausente', 'st-justificado');
            if (avatar) avatar.classList.remove('st-presente', 'st-ausente', 'st-justificado');

            if (tipo === 'Presente') {
                hidden.value = 'Presente';
                hidden.setAttribute('data-tipo', 'Presente');
                btnP.classList.add('activo-p');
                card.classList.add('st-presente');
                if (avatar) avatar.classList.add('st-presente');
                wrapM.style.display = 'none';
            } else if (tipo === 'Ausente') {
                hidden.value = 'Ausente';
                hidden.setAttribute('data-tipo', 'Ausente');
                btnA.classList.add('activo-a');
                card.classList.add('st-ausente');
                if (avatar) avatar.classList.add('st-ausente');
                wrapM.style.display = 'none';
            } else {
                var motivo = selM ? selM.value : 'Motivos Personales';
                hidden.value = 'Justificado - ' + motivo;
                hidden.setAttribute('data-tipo', 'Justificado');
                btnJ.classList.add('activo-j');
                card.classList.add('st-justificado');
                if (avatar) avatar.classList.add('st-justificado');
                wrapM.style.display = 'block';
            }
            actualizarContadores();
        }

        function actualizarMotivo(id) {
            var hidden = document.getElementById('estado_val_' + id);
            var selM   = document.getElementById('motivo_sel_' + id);
            if (hidden && selM && hidden.getAttribute('data-tipo') === 'Justificado') {
                hidden.value = 'Justificado - ' + selM.value;
            }
        }

        function marcarTodosPresentes() {
            var inputs = document.querySelectorAll('input[id^="estado_val_"]');
            inputs.forEach(function(inp) {
                var id = inp.id.replace('estado_val_', '');
                seleccionarEstado(id, 'Presente');
            });
        }

        function filtrarAlumnos(texto) {
            var q = (texto || '').trim().toLowerCase();
            var cards = document.querySelectorAll('.alumno-card');
            cards.forEach(function(card) {
                var key = card.getAttribute('data-search') || '';
                card.style.display = (q === '' || key.indexOf(q) !== -1) ? '' : 'none';
            });
        }

        function actualizarContadores() {
            var p = 0, a = 0, j = 0;
            var inputs = document.querySelectorAll('input[id^="estado_val_"]');
            inputs.forEach(function(inp) {
                var t = inp.getAttribute('data-tipo');
                if (t === 'Ausente') a++;
                else if (t === 'Justificado') j++;
                else p++;
            });
            var elP = document.getElementById('cnt_presentes');
            var elA = document.getElementById('cnt_ausentes');
            var elJ = document.getElementById('cnt_justificados');
            if (elP) elP.textContent = p;
            if (elA) elA.textContent = a;
            if (elJ) elJ.textContent = j;
        }

        actualizarContadores();

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
<?php $conn->close(); ?>
