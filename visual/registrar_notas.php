<?php
require_once __DIR__ . '/../config/conexion.php';
verificarSesionDocente('login_profesor.php');

$id_seccion = intval($_GET['id_seccion'] ?? 0);
$id_materia = intval($_GET['id_materia'] ?? 0);
$id_docente = (int) $_SESSION['id_docente'];

$lapsosValidos = ['1er Lapso', '2do Lapso', '3er Lapso'];
$lapsoActual   = trim($_GET['lapso'] ?? $_POST['lapso'] ?? '1er Lapso');
if (!in_array($lapsoActual, $lapsosValidos, true)) {
    $lapsoActual = '1er Lapso';
}

if ($id_seccion <= 0 || $id_materia <= 0) {
    header("Location: panel_profesor.php");
    exit();
}

// Verificar que la sección y materia pertenecen al docente logueado en el período activo
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
    alertaYRedirigir('No tiene permiso para gestionar notas en esta sección.', 'panel_profesor.php');
}

$baseUrl = "registrar_notas.php?id_seccion={$id_seccion}&id_materia={$id_materia}&lapso=" . urlencode($lapsoActual);

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = trim($_POST['accion'] ?? '');

    // 1. Crear nueva actividad ponderada (validando límite 100%)
    if ($accion === 'crear_actividad') {
        $nombreAct   = trim($_POST['nombre_actividad'] ?? '');
        $ponderacion = round(floatval($_POST['ponderacion'] ?? 0), 2);
        $fechaEval   = trim($_POST['fecha_evaluacion'] ?? date('Y-m-d'));

        if ($nombreAct === '' || $ponderacion <= 0 || $ponderacion > 100) {
            alertaYRedirigir('Indique el nombre de la actividad y una ponderación entre 1% y 100%.', $baseUrl);
        }

        // Calcular ponderación ya asignada en este lapso
        $stmtSum = $conn->prepare("
            SELECT COALESCE(SUM(ponderacion), 0) AS total_pct
            FROM evaluaciones_plan
            WHERE id_seccion = ? AND id_materia = ? AND lapso = ?
        ");
        $stmtSum->bind_param("iis", $id_seccion, $id_materia, $lapsoActual);
        $stmtSum->execute();
        $rowSum   = $stmtSum->get_result()->fetch_assoc();
        $totalPct = (float) ($rowSum['total_pct'] ?? 0);
        $stmtSum->close();

        if ($totalPct + $ponderacion > 100.001) {
            $restante = max(0, round(100 - $totalPct, 2));
            alertaYRedirigir("La ponderación supera el 100% del lapso. Actualmente tiene {$totalPct}% asignado y solo quedan {$restante}% disponibles.", $baseUrl);
        }

        $idPeriodoRaw = (int) ($seccionInfo['id_periodo'] ?? obtenerPeriodoActivoId($conn) ?? 0);
        $idPeriodo = $idPeriodoRaw > 0 ? $idPeriodoRaw : null;
        $stmtIns = $conn->prepare("
            INSERT INTO evaluaciones_plan (id_seccion, id_materia, id_docente, id_periodo, lapso, nombre_actividad, ponderacion, fecha_evaluacion)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtIns->bind_param("iiiissds", $id_seccion, $id_materia, $id_docente, $idPeriodo, $lapsoActual, $nombreAct, $ponderacion, $fechaEval);
        if ($stmtIns->execute()) {
            $nuevaEvalId = (int) ($stmtIns->insert_id ?: $conn->insert_id);
            $stmtIns->close();
            $conn->close();
            alertaYRedirigir(
                "Actividad «{$nombreAct}» ({$ponderacion}%) creada. Ahora puede cargar las notas de cada estudiante.",
                $baseUrl . "&id_evaluacion={$nuevaEvalId}#planilla"
            );
        }
        $stmtIns->close();
        alertaYRedirigir('Error al guardar la actividad en el plan de evaluación.', $baseUrl);
    }

    // 2. Eliminar una actividad del plan (y sus calificaciones asociadas)
    if ($accion === 'eliminar_actividad') {
        $idEvalDel = intval($_POST['id_evaluacion'] ?? 0);
        if ($idEvalDel > 0) {
            $stmtDelN = $conn->prepare("DELETE FROM notas_academicas WHERE id_evaluacion = ? AND id_materia = ?");
            if ($stmtDelN) {
                $stmtDelN->bind_param("ii", $idEvalDel, $id_materia);
                $stmtDelN->execute();
                $stmtDelN->close();
            }
            $stmtDelE = $conn->prepare("DELETE FROM evaluaciones_plan WHERE id_evaluacion = ? AND id_seccion = ? AND id_materia = ? AND id_docente = ?");
            if ($stmtDelE) {
                $stmtDelE->bind_param("iiii", $idEvalDel, $id_seccion, $id_materia, $id_docente);
                $stmtDelE->execute();
                $stmtDelE->close();
            }
            $conn->close();
            alertaYRedirigir('Actividad eliminada del plan de evaluación.', $baseUrl);
        }
    }

    // 3. Guardar planilla completa de notas para todos los alumnos de una actividad
    if ($accion === 'guardar_planilla') {
        $idEval = intval($_POST['id_evaluacion'] ?? 0);
        $notasPost = $_POST['nota'] ?? [];
        $obsPost   = $_POST['observacion'] ?? [];

        $stmtEv = $conn->prepare("
            SELECT id_evaluacion, nombre_actividad, ponderacion, fecha_evaluacion, lapso, id_periodo
            FROM evaluaciones_plan
            WHERE id_evaluacion = ? AND id_seccion = ? AND id_materia = ? AND id_docente = ?
            LIMIT 1
        ");
        $stmtEv->bind_param("iiii", $idEval, $id_seccion, $id_materia, $id_docente);
        $stmtEv->execute();
        $evInfo = $stmtEv->get_result()->fetch_assoc();
        $stmtEv->close();

        if (!$evInfo) {
            alertaYRedirigir('La actividad seleccionada no existe o no pertenece a esta sección.', $baseUrl);
        }

        $nombreActividad = $evInfo['nombre_actividad'];
        $ponderacion     = (float) $evInfo['ponderacion'];
        $fechaRegistro   = $evInfo['fecha_evaluacion'] ?: date('Y-m-d');
        $lapsoEv         = $evInfo['lapso'] ?: $lapsoActual;
        $idPeriodoRaw    = (int) ($evInfo['id_periodo'] ?? $seccionInfo['id_periodo'] ?? obtenerPeriodoActivoId($conn) ?? 0);
        $idPeriodo       = $idPeriodoRaw > 0 ? $idPeriodoRaw : null;
        $nomMat          = $seccionInfo['nombre_materia'];

        $stmtEstCheck = $conn->prepare("
            SELECT id_estudiante, nombre, apellido, id_representante
            FROM estudiantes
            WHERE id_estudiante = ? AND id_seccion = ?
            LIMIT 1
        ");
        $stmtNotaCheck = $conn->prepare("
            SELECT id_nota
            FROM notas_academicas
            WHERE id_estudiante = ? AND id_evaluacion = ?
            LIMIT 1
        ");
        $stmtNotaIns = $conn->prepare("
            INSERT INTO notas_academicas (id_estudiante, id_materia, id_docente, id_periodo, id_evaluacion, lapso, evaluacion, ponderacion, calificacion, observacion, fecha_registro)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtNotaUpd = $conn->prepare("
            UPDATE notas_academicas
            SET calificacion = ?, ponderacion = ?, evaluacion = ?, observacion = ?, fecha_registro = ?, id_docente = ?
            WHERE id_nota = ?
        ");
        $stmtAcum = $conn->prepare("
            SELECT COALESCE(SUM(calificacion * ponderacion / 100.0), 0) AS acumulado,
                   COALESCE(SUM(ponderacion), 0) AS pct_eval
            FROM notas_academicas
            WHERE id_estudiante = ? AND id_materia = ? AND lapso = ?
        ");

        $guardadas = 0;
        $notificadas = 0;

        foreach ($notasPost as $idEstRaw => $valorRaw) {
            $idEst = intval($idEstRaw);
            $valorStr = trim((string) $valorRaw);
            if ($idEst <= 0 || $valorStr === '') {
                continue;
            }
            $calif = round(floatval($valorStr), 2);
            if ($calif < 0 || $calif > 20) {
                continue;
            }

            // Verificar que el estudiante pertenece a esta sección
            $stmtEstCheck->bind_param("ii", $idEst, $id_seccion);
            $stmtEstCheck->execute();
            $estData = $stmtEstCheck->get_result()->fetch_assoc();
            if (!$estData) {
                continue;
            }

            $obsTxt = trim((string) ($obsPost[$idEstRaw] ?? ''));
            $obsVal = $obsTxt !== '' ? $obsTxt : null;

            // Verificar si ya tenía nota para esta actividad (upsert)
            $stmtNotaCheck->bind_param("ii", $idEst, $idEval);
            $stmtNotaCheck->execute();
            $notaEx = $stmtNotaCheck->get_result()->fetch_assoc();

            $okOp = false;
            if ($notaEx) {
                $idNotaEx = (int) $notaEx['id_nota'];
                $stmtNotaUpd->bind_param("ddsssii", $calif, $ponderacion, $nombreActividad, $obsVal, $fechaRegistro, $id_docente, $idNotaEx);
                $okOp = (bool) $stmtNotaUpd->execute();
            } else {
                $stmtNotaIns->bind_param("iiiiissddss", $idEst, $id_materia, $id_docente, $idPeriodo, $idEval, $lapsoEv, $nombreActividad, $ponderacion, $calif, $obsVal, $fechaRegistro);
                $okOp = (bool) $stmtNotaIns->execute();
            }

            if ($okOp) {
                $guardadas++;

                // Recalcular acumulado del lapso y notificar directamente al Portal de Representantes
                $idRep = (int) ($estData['id_representante'] ?? 0);
                if ($idRep > 0) {
                    $stmtAcum->bind_param("iis", $idEst, $id_materia, $lapsoEv);
                    $stmtAcum->execute();
                    $acumRow = $stmtAcum->get_result()->fetch_assoc();
                    $acumPts = number_format((float) ($acumRow['acumulado'] ?? 0), 2);
                    $pctEval = number_format((float) ($acumRow['pct_eval'] ?? 0), 0);

                    $nomEst    = trim($estData['nombre'] . ' ' . $estData['apellido']);
                    $aportePts = number_format(($calif * $ponderacion) / 100.0, 2);
                    $califFmt  = number_format($calif, 2);
                    $pondFmt   = number_format($ponderacion, 0);

                    $tituloN = "Nota publicada — {$nomMat} ({$pondFmt}%)";
                    $msgN    = "{$nomEst} obtuvo {$califFmt} / 20 (aporte ponderado: {$aportePts} pts — {$pondFmt}%) en «{$nombreActividad}» ({$lapsoEv}). Acumulado del lapso ({$pctEval}% evaluado): {$acumPts} / 20." . ($obsVal ? " Observación: {$obsVal}." : "");

                    if (registrarNotificacionRepresentante($conn, $idRep, $idEst, 'nota', $tituloN, $msgN)) {
                        $notificadas++;
                    }
                }
            }
        }

        $stmtEstCheck->close();
        $stmtNotaCheck->close();
        $stmtNotaIns->close();
        $stmtNotaUpd->close();
        $stmtAcum->close();
        $conn->close();

        alertaYRedirigir(
            "Planilla guardada para «{$nombreActividad}». Notas registradas/actualizadas: {$guardadas}. Notificaciones enviadas al Portal de Representantes: {$notificadas}.",
            $baseUrl . "&id_evaluacion={$idEval}#consolidado"
        );
    }
}

// Cargar estudiantes de la sección
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

// Cargar actividades planificadas del lapso actual
$stmtPlan = $conn->prepare("
    SELECT id_evaluacion, nombre_actividad, ponderacion, fecha_evaluacion
    FROM evaluaciones_plan
    WHERE id_seccion = ? AND id_materia = ? AND lapso = ?
    ORDER BY fecha_evaluacion ASC, id_evaluacion ASC
");
$stmtPlan->bind_param("iis", $id_seccion, $id_materia, $lapsoActual);
$stmtPlan->execute();
$resPlan = $stmtPlan->get_result();
$actividades = [];
$totalPonderacion = 0.0;
if ($resPlan) {
    while ($act = $resPlan->fetch_assoc()) {
        $actividades[] = $act;
        $totalPonderacion += (float) $act['ponderacion'];
    }
}
$stmtPlan->close();

$ponderacionDisponible = max(0.0, round(100.0 - $totalPonderacion, 2));

// Determinar qué actividad está seleccionada para calificar
$idEvalSeleccionada = intval($_GET['id_evaluacion'] ?? 0);
$actividadActiva = null;
foreach ($actividades as $act) {
    if ((int) $act['id_evaluacion'] === $idEvalSeleccionada) {
        $actividadActiva = $act;
        break;
    }
}
if ($actividadActiva === null && !empty($actividades)) {
    $actividadActiva = $actividades[0];
    $idEvalSeleccionada = (int) $actividadActiva['id_evaluacion'];
}

// Cargar todas las notas del lapso actual para esta sección y materia
$stmtAllNotas = $conn->prepare("
    SELECT n.id_nota, n.id_estudiante, n.id_evaluacion, n.evaluacion, n.ponderacion, n.calificacion, n.observacion, n.fecha_registro
    FROM notas_academicas n
    INNER JOIN estudiantes e ON n.id_estudiante = e.id_estudiante
    WHERE e.id_seccion = ? AND n.id_materia = ? AND n.lapso = ?
");
$stmtAllNotas->bind_param("iis", $id_seccion, $id_materia, $lapsoActual);
$stmtAllNotas->execute();
$resAllNotas = $stmtAllNotas->get_result();
$mapaNotas = []; // [$id_estudiante][$id_evaluacion] => row
$conteoPorEval = [];
if ($resAllNotas) {
    while ($nt = $resAllNotas->fetch_assoc()) {
        $estId = (int) $nt['id_estudiante'];
        $evId  = (int) ($nt['id_evaluacion'] ?? 0);
        if ($evId > 0) {
            $mapaNotas[$estId][$evId] = $nt;
            $conteoPorEval[$evId] = ($conteoPorEval[$evId] ?? 0) + 1;
        }
    }
}
$stmtAllNotas->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Notas Ponderadas (100%) - Portal Docente</title>
    <link rel="stylesheet" href="../css/estilos.css">
    <style>
        .lapso-tabs {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 4px;
            max-width: 480px;
            background: #e2e8f0;
            padding: 4px;
            border-radius: 14px;
            margin: 0 0 20px 0;
        }
        .lapso-tab {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 8px 12px;
            border-radius: 10px;
            border: none;
            background: transparent;
            color: #475569;
            font-weight: 700;
            text-decoration: none;
            font-size: 0.88rem;
            text-align: center;
            transition: background-color 0.16s ease, color 0.16s ease, box-shadow 0.16s ease;
        }
        .lapso-tab:hover {
            color: #0f172a;
            background: rgba(255, 255, 255, 0.55);
        }
        .lapso-tab.activo {
            background: #ffffff;
            color: #1d4ed8;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.1);
        }
        .plan-head-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            text-align: left;
        }
        .plan-head-row h2 {
            margin: 0;
            text-align: left;
        }
        .plan-head-row p {
            margin: 4px 0 0 0;
        }
        .progress-wrap {
            background: #e2e8f0;
            border-radius: 999px;
            height: 12px;
            width: 100%;
            overflow: hidden;
            margin: 14px 0 10px 0;
            box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.08);
        }
        .progress-fill {
            height: 100%;
            border-radius: 999px;
            transition: width 0.3s ease;
            background: linear-gradient(90deg, #2563eb 0%, #3b82f6 100%);
        }
        .progress-fill.completo {
            background: linear-gradient(90deg, #059669 0%, #10b981 100%);
        }
        .plan-form-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 12px;
            align-items: end;
            max-width: none;
            margin: 16px 0 20px 0;
            padding: 16px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            box-shadow: none;
        }
        .plan-form-grid > div {
            margin-bottom: 0;
        }
        .plan-form-grid label {
            display: block;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin-bottom: 5px;
            color: #475569;
            text-align: left;
        }
        .plan-form-grid input {
            margin-bottom: 0;
            background: #ffffff;
        }
        .plan-form-grid button {
            margin: 0;
            white-space: nowrap;
            padding: 11px 18px;
            min-height: 44px;
        }
        .form-eliminar-mini {
            margin: 0;
            padding: 0;
            border: none;
            background: transparent;
            box-shadow: none;
            max-width: none;
            width: auto;
            display: inline-block;
        }
        .input-nota {
            width: 96px !important;
            padding: 8px 10px !important;
            margin-bottom: 0 !important;
            text-align: center;
            font-weight: 700;
            background: #ffffff;
        }
        .input-obs {
            width: 100% !important;
            min-width: 180px;
            padding: 8px 10px !important;
            margin-bottom: 0 !important;
            background: #ffffff;
        }
        .aporte-badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 9px;
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
            font-weight: 700;
            font-size: 0.86rem;
            min-width: 82px;
            font-variant-numeric: tabular-nums;
        }
        .btn-peligro-mini {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
            padding: 7px 12px;
            border-radius: 9px;
            font-size: 0.82rem;
            font-weight: 700;
            cursor: pointer;
            margin: 0;
            width: auto;
            box-shadow: none;
        }
        .btn-peligro-mini:hover {
            background: #fee2e2;
            transform: none;
            box-shadow: none;
            filter: none;
        }
        .actividades-movil-grid {
            display: none;
        }
        .num-movil-inline {
            display: none;
        }
        .swipe-hint-movil {
            display: none;
        }
        .guardar-planilla-wrap {
            margin-top: 20px;
            text-align: center;
        }
        .guardar-planilla-wrap button {
            max-width: 440px;
            min-height: 48px;
            border-radius: 12px;
        }
        @media (max-width: 820px) {
            .plan-form-grid {
                grid-template-columns: 1fr;
                padding: 14px;
            }
            .plan-form-grid button {
                width: 100%;
                min-height: 46px;
            }
        }
        @media (max-width: 768px) {
            .lapso-tabs {
                max-width: none;
                width: 100%;
                margin-bottom: 14px;
            }
            .lapso-tab {
                font-size: 0.82rem;
                padding: 8px 6px;
                min-height: 42px;
            }
            .tabla-actividades-desktop {
                display: none;
            }
            .actividades-movil-grid {
                display: grid;
                grid-template-columns: 1fr;
                gap: 10px;
                margin-top: 12px;
            }
            .act-movil-card {
                background: #ffffff;
                border: 1px solid #dbe4f0;
                border-radius: 15px;
                padding: 14px;
                text-align: left;
                box-shadow: 0 3px 10px rgba(15, 23, 42, 0.04);
            }
            .act-movil-card.activa {
                border-color: #3b82f6;
                background: #f8fbff;
                box-shadow: 0 4px 14px rgba(37, 99, 235, 0.12);
            }
            .act-movil-top {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                gap: 8px;
                margin-bottom: 6px;
            }
            .act-movil-top strong {
                font-size: 0.96rem;
                color: #0f172a;
                line-height: 1.3;
                word-break: break-word;
            }
            .act-movil-meta {
                display: flex;
                flex-wrap: wrap;
                gap: 6px 12px;
                font-size: 0.8rem;
                color: #64748b;
                margin-bottom: 12px;
            }
            .act-movil-actions {
                display: grid;
                grid-template-columns: 1fr auto;
                gap: 8px;
                align-items: stretch;
            }
            .act-movil-actions > a {
                min-height: 42px;
                border-radius: 10px;
                justify-content: center;
                font-size: 0.84rem;
                font-weight: 700;
            }
            .act-movil-actions .form-eliminar-mini {
                display: flex;
            }
            .act-movil-actions .btn-peligro-mini {
                min-height: 42px;
                padding: 8px 14px;
                border-radius: 10px;
            }

            /* Transformación CSS pura de la planilla de notas a tarjetas móviles táctiles */
            .planilla-table-wrap {
                border: none !important;
                background: transparent !important;
                overflow: visible !important;
            }
            .tabla-planilla-movil,
            .tabla-planilla-movil tbody {
                display: block;
                width: 100%;
                background: transparent;
                border: none;
                box-shadow: none;
            }
            .tabla-planilla-movil thead {
                display: none;
            }
            .tabla-planilla-movil tbody tr {
                display: grid;
                grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
                gap: 10px;
                background: #ffffff;
                border: 1px solid #dbe4f0;
                border-radius: 15px;
                padding: 14px;
                margin-bottom: 10px;
                box-shadow: 0 3px 10px rgba(15, 23, 42, 0.04);
            }
            .tabla-planilla-movil tbody tr:hover {
                background: #ffffff;
            }
            .tabla-planilla-movil td {
                display: block;
                padding: 0 !important;
                border: none !important;
                text-align: left !important;
            }
            .tabla-planilla-movil td.td-num {
                display: none;
            }
            .num-movil-inline {
                display: inline;
            }
            .tabla-planilla-movil td.td-est {
                grid-column: 1 / -1;
                font-size: 0.96rem;
                color: #0f172a;
                line-height: 1.3;
            }
            .tabla-planilla-movil td.td-ced {
                grid-column: 1 / -1;
                margin-top: -6px;
                font-size: 0.79rem;
                color: #64748b;
            }
            .tabla-planilla-movil td.td-ced::before {
                content: "C.I. ";
                font-weight: 600;
            }
            .tabla-planilla-movil td.td-nota,
            .tabla-planilla-movil td.td-aporte {
                grid-column: span 1;
            }
            .tabla-planilla-movil td.td-obs {
                grid-column: 1 / -1;
            }
            .tabla-planilla-movil td.td-nota::before,
            .tabla-planilla-movil td.td-aporte::before,
            .tabla-planilla-movil td.td-obs::before {
                content: attr(data-label);
                display: block;
                font-size: 0.72rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.03em;
                color: #475569;
                margin-bottom: 5px;
            }
            .tabla-planilla-movil .input-nota {
                width: 100% !important;
                min-height: 44px;
                font-size: 16px;
                border-radius: 11px;
            }
            .tabla-planilla-movil .aporte-badge {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 100%;
                min-height: 44px;
                border-radius: 11px;
                font-size: 0.92rem;
            }
            .tabla-planilla-movil .input-obs {
                min-width: 0;
                width: 100% !important;
                min-height: 44px;
                font-size: 16px;
                border-radius: 11px;
            }
            .guardar-planilla-wrap {
                position: sticky;
                bottom: 8px;
                z-index: 40;
                background: rgba(255, 255, 255, 0.96);
                backdrop-filter: blur(12px);
                -webkit-backdrop-filter: blur(12px);
                padding: 10px;
                border: 1px solid #cbd5e1;
                border-radius: 15px;
                box-shadow: 0 10px 26px rgba(15, 23, 42, 0.14);
                margin-top: 14px;
            }
            .guardar-planilla-wrap button {
                max-width: none;
                width: 100%;
                margin: 0;
                min-height: 48px;
                font-size: 0.92rem;
            }
            .swipe-hint-movil {
                display: block;
                font-size: 0.76rem;
                font-weight: 600;
                color: #64748b;
                text-align: left;
                margin: 6px 0 0 0;
            }
            .tabla-consolidado th:first-child,
            .tabla-consolidado td:first-child {
                position: sticky;
                left: 0;
                background: #ffffff;
                z-index: 2;
                text-align: left;
                min-width: 135px;
                box-shadow: 2px 0 6px rgba(15, 23, 42, 0.05);
            }
            .tabla-consolidado th:first-child {
                background: #f1f5f9;
                z-index: 3;
            }
        }
    </style>
</head>
<body class="portal-docente">

    <header class="header-fijo" id="appHeader">
        <div class="header-brand">
            <a href="panel_profesor.php" class="logo" aria-label="Volver al Portal Docente">
                <img src="logo.png" alt="Logo SIE">
            </a>
            <h2 class="header-title">Notas Ponderadas</h2>
        </div>

        <div class="header-mobile-controls">
            <a href="panel_profesor.php" class="header-quick-back">Panel</a>
            <button type="button" class="nav-toggle" id="navToggle" aria-label="Abrir menú de navegación" aria-expanded="false" aria-controls="headerNav">
                <span class="nav-toggle-bar"></span>
                <span class="nav-toggle-bar"></span>
                <span class="nav-toggle-bar"></span>
            </button>
        </div>

        <nav class="header-nav" id="headerNav" aria-label="Navegación gestión de notas">
            <ul>
                <li><a href="panel_profesor.php">Volver al Panel</a></li>
                <li><a href="registrar_inasistencia.php?id_seccion=<?php echo $id_seccion; ?>&id_materia=<?php echo $id_materia; ?>">Tomar Asistencia</a></li>
                <li><a href="../api/logout.php">Cerrar Sesión</a></li>
            </ul>
        </nav>
    </header>
    <div class="nav-backdrop" id="navBackdrop" aria-hidden="true"></div>

    <div class="dashboard-container">
        <div class="welcome-card docente-hero">
            <span class="docente-hero-eyebrow">Sección <?php echo htmlspecialchars($seccionInfo['nombre_seccion']); ?></span>
            <h2><?php echo htmlspecialchars($seccionInfo['nombre_materia']); ?></h2>
            <p>
                Configure su plan de evaluación sobre el <strong>100%</strong>, cargue las calificaciones (0 a 20 pts) y notifique automáticamente al Portal de Representantes.
            </p>
            <div class="docente-hero-meta">
                <span class="docente-hero-chip"><?php echo htmlspecialchars($lapsoActual); ?></span>
                <span class="docente-hero-chip">Planificado: <?php echo number_format($totalPonderacion, 0); ?>% / 100%</span>
                <span class="docente-hero-chip">Matrícula: <?php echo count($estudiantes); ?></span>
            </div>
        </div>

        <!-- Selector de Lapso tipo Segmented Control -->
        <div class="lapso-tabs" role="tablist" aria-label="Seleccionar lapso académico">
            <?php foreach ($lapsosValidos as $lp) { ?>
                <a href="registrar_notas.php?id_seccion=<?php echo $id_seccion; ?>&id_materia=<?php echo $id_materia; ?>&lapso=<?php echo urlencode($lp); ?>"
                   class="lapso-tab <?php echo $lp === $lapsoActual ? 'activo' : ''; ?>">
                    <?php echo htmlspecialchars($lp); ?>
                </a>
            <?php } ?>
        </div>

        <!-- Bloque 1: Plan de Evaluación sobre 100% -->
        <div class="container" style="margin-top: 0; margin-bottom: 24px;">
            <div class="plan-head-row">
                <div>
                    <h2>1. Plan de Evaluación (<?php echo htmlspecialchars($lapsoActual); ?>)</h2>
                    <p>
                        Ponderación planificada: <strong><?php echo number_format($totalPonderacion, 0); ?>% / 100%</strong>
                        (Disponible: <strong><?php echo number_format($ponderacionDisponible, 0); ?>%</strong>)
                    </p>
                </div>
                <span class="badge <?php echo $totalPonderacion >= 99.99 ? 'badge-presente' : 'badge-justificado'; ?>">
                    <?php echo $totalPonderacion >= 99.99 ? 'Plan Completo (100%)' : 'Falta ' . number_format($ponderacionDisponible, 0) . '% por asignar'; ?>
                </span>
            </div>

            <div class="progress-wrap">
                <div class="progress-fill <?php echo $totalPonderacion >= 99.99 ? 'completo' : ''; ?>"
                     style="width: <?php echo min(100, $totalPonderacion); ?>%;"></div>
            </div>

            <?php if ($ponderacionDisponible > 0) { ?>
            <form method="POST" action="<?php echo htmlspecialchars($baseUrl); ?>" class="plan-form-grid">
                <input type="hidden" name="accion" value="crear_actividad">
                <input type="hidden" name="lapso" value="<?php echo htmlspecialchars($lapsoActual); ?>">

                <div>
                    <label for="nombre_actividad">Nueva Actividad / Evaluación</label>
                    <input type="text" id="nombre_actividad" name="nombre_actividad"
                           placeholder="Ej. Actividad 3: Examen Práctico" required>
                </div>

                <div>
                    <label for="ponderacion">Ponderación (%)</label>
                    <input type="number" id="ponderacion" name="ponderacion"
                           min="1" max="<?php echo $ponderacionDisponible; ?>" step="1"
                           value="<?php echo min(25, $ponderacionDisponible); ?>" required>
                </div>

                <div>
                    <label for="fecha_evaluacion">Fecha</label>
                    <input type="date" id="fecha_evaluacion" name="fecha_evaluacion"
                           value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <button type="submit">Agregar Actividad</button>
            </form>
            <?php } else { ?>
                <p style="color: #166534; font-weight: 600; margin: 12px 0; text-align: left;">
                    La ponderación completa del 100% ya está distribuida para este lapso.
                </p>
            <?php } ?>

            <div class="tabla-actividades-desktop table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Actividad</th>
                            <th>Ponderación</th>
                            <th>Valor sobre 20 pts</th>
                            <th>Fecha</th>
                            <th>Calificados</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($actividades)) { ?>
                            <?php foreach ($actividades as $act) {
                                $actId   = (int) $act['id_evaluacion'];
                                $pond    = (float) $act['ponderacion'];
                                $ptsMax  = ($pond * 20.0) / 100.0;
                                $cantCal = $conteoPorEval[$actId] ?? 0;
                                $esAct   = ($actId === $idEvalSeleccionada);
                            ?>
                            <tr style="<?php echo $esAct ? 'background: #eff6ff;' : ''; ?>">
                                <td><strong><?php echo htmlspecialchars($act['nombre_actividad']); ?></strong></td>
                                <td><span class="badge badge-presente"><?php echo number_format($pond, 0); ?>%</span></td>
                                <td><?php echo number_format($ptsMax, 2); ?> pts</td>
                                <td><?php echo htmlspecialchars($act['fecha_evaluacion']); ?></td>
                                <td><?php echo $cantCal; ?> / <?php echo count($estudiantes); ?> alumnos</td>
                                <td>
                                    <div class="acciones-container">
                                        <a href="<?php echo htmlspecialchars($baseUrl . '&id_evaluacion=' . $actId . '#planilla'); ?>"
                                           class="<?php echo $esAct ? 'btn-asistencia' : 'btn-ver'; ?>">
                                            <?php echo $esAct ? 'Calificando ahora' : 'Calificar Alumnos'; ?>
                                        </a>
                                        <form method="POST" action="<?php echo htmlspecialchars($baseUrl); ?>"
                                              onsubmit="return confirm('¿Eliminar esta actividad y sus notas registradas?');"
                                              class="form-eliminar-mini">
                                            <input type="hidden" name="accion" value="eliminar_actividad">
                                            <input type="hidden" name="lapso" value="<?php echo htmlspecialchars($lapsoActual); ?>">
                                            <input type="hidden" name="id_evaluacion" value="<?php echo $actId; ?>">
                                            <button type="submit" class="btn-peligro-mini">Eliminar</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php } ?>
                        <?php } else { ?>
                            <tr><td colspan="6">Aún no hay actividades creadas en <?php echo htmlspecialchars($lapsoActual); ?>. Agregue la primera actividad arriba (ej. 25%).</td></tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <!-- Tarjetas móviles de actividades planificadas -->
            <div class="actividades-movil-grid">
                <?php if (!empty($actividades)) { ?>
                    <?php foreach ($actividades as $act) {
                        $actId   = (int) $act['id_evaluacion'];
                        $pond    = (float) $act['ponderacion'];
                        $ptsMax  = ($pond * 20.0) / 100.0;
                        $cantCal = $conteoPorEval[$actId] ?? 0;
                        $esAct   = ($actId === $idEvalSeleccionada);
                    ?>
                    <div class="act-movil-card <?php echo $esAct ? 'activa' : ''; ?>">
                        <div class="act-movil-top">
                            <strong><?php echo htmlspecialchars($act['nombre_actividad']); ?></strong>
                            <span class="badge badge-presente"><?php echo number_format($pond, 0); ?>%</span>
                        </div>
                        <div class="act-movil-meta">
                            <span>Máx: <strong><?php echo number_format($ptsMax, 2); ?> pts</strong></span>
                            <span>Fecha: <?php echo htmlspecialchars($act['fecha_evaluacion']); ?></span>
                            <span>Calificados: <strong><?php echo $cantCal; ?> / <?php echo count($estudiantes); ?></strong></span>
                        </div>
                        <div class="act-movil-actions">
                            <a href="<?php echo htmlspecialchars($baseUrl . '&id_evaluacion=' . $actId . '#planilla'); ?>"
                               class="<?php echo $esAct ? 'btn-asistencia' : 'btn-ver'; ?>">
                                <?php echo $esAct ? 'Calificando ahora' : 'Calificar Alumnos'; ?>
                            </a>
                            <form method="POST" action="<?php echo htmlspecialchars($baseUrl); ?>"
                                  onsubmit="return confirm('¿Eliminar esta actividad y sus notas registradas?');"
                                  class="form-eliminar-mini">
                                <input type="hidden" name="accion" value="eliminar_actividad">
                                <input type="hidden" name="lapso" value="<?php echo htmlspecialchars($lapsoActual); ?>">
                                <input type="hidden" name="id_evaluacion" value="<?php echo $actId; ?>">
                                <button type="submit" class="btn-peligro-mini">Eliminar</button>
                            </form>
                        </div>
                    </div>
                    <?php } ?>
                <?php } else { ?>
                    <p style="background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 14px; padding: 16px; text-align: center; font-size: 0.88rem; margin: 0;">
                        Aún no hay actividades creadas en <?php echo htmlspecialchars($lapsoActual); ?>. Agregue la primera actividad arriba (ej. 25%).
                    </p>
                <?php } ?>
            </div>
        </div>

        <!-- Bloque 2: Planilla de Notas por Alumno -->
        <?php if ($actividadActiva !== null) {
            $actIdSel = (int) $actividadActiva['id_evaluacion'];
            $pondSel  = (float) $actividadActiva['ponderacion'];
            $ptsMaxSel = ($pondSel * 20.0) / 100.0;
        ?>
        <div id="planilla" class="container" style="margin-top: 0; margin-bottom: 24px;">
            <h2>2. Cargar Notas: <?php echo htmlspecialchars($actividadActiva['nombre_actividad']); ?></h2>
            <p>
                Ponderación: <strong><?php echo number_format($pondSel, 0); ?>%</strong>
                (hasta <strong><?php echo number_format($ptsMaxSel, 2); ?> puntos</strong> sobre 20).
                Ingrese la calificación (0 a 20) y pulse Guardar para actualizar el acumulado y notificar al representante.
            </p>

            <form method="POST" action="<?php echo htmlspecialchars($baseUrl . '&id_evaluacion=' . $actIdSel); ?>"
                  style="max-width: none; margin: 12px 0 0 0; padding: 0; border: none; box-shadow: none; background: transparent;">
                <input type="hidden" name="accion" value="guardar_planilla">
                <input type="hidden" name="lapso" value="<?php echo htmlspecialchars($lapsoActual); ?>">
                <input type="hidden" name="id_evaluacion" value="<?php echo $actIdSel; ?>">

                <div class="table-responsive planilla-table-wrap">
                    <table class="tabla-planilla-movil">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Estudiante</th>
                                <th>Cédula</th>
                                <th>Nota (0 - 20)</th>
                                <th>Aporte (<?php echo number_format($pondSel, 0); ?>%)</th>
                                <th>Observación (opcional)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $idx = 1;
                            foreach ($estudiantes as $e) {
                                $eId = (int) $e['id_estudiante'];
                                $notaGuardada = $mapaNotas[$eId][$actIdSel] ?? null;
                                $valNota = $notaGuardada !== null ? number_format((float) $notaGuardada['calificacion'], 2, '.', '') : '';
                                $valObs  = $notaGuardada !== null ? (string) ($notaGuardada['observacion'] ?? '') : '';
                                $valAporte = $notaGuardada !== null ? number_format(((float) $notaGuardada['calificacion'] * $pondSel) / 100.0, 2) . ' pts' : '—';
                            ?>
                            <tr>
                                <td class="td-num"><?php echo $idx; ?></td>
                                <td class="td-est"><strong><span class="num-movil-inline"><?php echo $idx++; ?>. </span><?php echo htmlspecialchars($e['apellido'] . ', ' . $e['nombre']); ?></strong></td>
                                <td class="td-ced"><?php echo htmlspecialchars($e['cedula']); ?></td>
                                <td class="td-nota" data-label="Nota (0 - 20)">
                                    <input type="number"
                                           class="input-nota"
                                           name="nota[<?php echo $eId; ?>]"
                                           min="0" max="20" step="0.5"
                                           value="<?php echo htmlspecialchars($valNota); ?>"
                                           placeholder="0 - 20"
                                           data-pond="<?php echo $pondSel; ?>"
                                           data-target="aporte_<?php echo $eId; ?>"
                                           oninput="actualizarAporte(this)">
                                </td>
                                <td class="td-aporte" data-label="Aporte (<?php echo number_format($pondSel, 0); ?>%)">
                                    <span id="aporte_<?php echo $eId; ?>" class="aporte-badge"><?php echo $valAporte; ?></span>
                                </td>
                                <td class="td-obs" data-label="Observación (opcional)">
                                    <input type="text"
                                           class="input-obs"
                                           name="observacion[<?php echo $eId; ?>]"
                                           value="<?php echo htmlspecialchars($valObs); ?>"
                                           placeholder="Comentario breve para el representante">
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>

                <div class="guardar-planilla-wrap">
                    <button type="submit">
                        Guardar Calificaciones y Notificar al Portal
                    </button>
                </div>
            </form>
        </div>
        <?php } ?>

        <!-- Bloque 3: Matriz Consolidada del 100% -->
        <div id="consolidado" class="container" style="margin-top: 0;">
            <h2>3. Resumen Consolidado (<?php echo htmlspecialchars($lapsoActual); ?> — 100%)</h2>
            <p>Cálculo automático de la nota acumulada sobre 20 puntos según la ponderación de cada actividad.</p>
            <p class="swipe-hint-movil">Deslice horizontalmente la tabla para consultar todas las actividades y el acumulado.</p>

            <div class="table-responsive">
                <table class="tabla-consolidado">
                    <thead>
                        <tr>
                            <th>Estudiante</th>
                            <?php foreach ($actividades as $act) { ?>
                                <th>
                                    <?php echo htmlspecialchars($act['nombre_actividad']); ?><br>
                                    <small>(<?php echo number_format((float) $act['ponderacion'], 0); ?>%)</small>
                                </th>
                            <?php } ?>
                            <th>% Evaluado</th>
                            <th>Nota Acumulada (/ 20)</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($estudiantes)) { ?>
                            <?php foreach ($estudiantes as $e) {
                                $eId = (int) $e['id_estudiante'];
                                $acumulado = 0.0;
                                $pctEvaluado = 0.0;
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($e['apellido'] . ', ' . $e['nombre']); ?></strong></td>
                                <?php foreach ($actividades as $act) {
                                    $actId = (int) $act['id_evaluacion'];
                                    $pond  = (float) $act['ponderacion'];
                                    $nt    = $mapaNotas[$eId][$actId] ?? null;
                                    if ($nt !== null) {
                                        $c = (float) $nt['calificacion'];
                                        $ap = ($c * $pond) / 100.0;
                                        $acumulado += $ap;
                                        $pctEvaluado += $pond;
                                        echo '<td><strong>' . number_format($c, 2) . '</strong><br><small style="color:#475569;">(' . number_format($ap, 2) . ' pts)</small></td>';
                                    } else {
                                        echo '<td style="color:#94a3b8;">Pendiente</td>';
                                    }
                                } ?>
                                <td><?php echo number_format($pctEvaluado, 0); ?>%</td>
                                <td><strong><?php echo number_format($acumulado, 2); ?> / 20</strong></td>
                                <td>
                                    <?php if ($acumulado >= 10.0) { ?>
                                        <span class="badge badge-presente">Aprobado</span>
                                    <?php } elseif ($pctEvaluado < 100.0) { ?>
                                        <span class="badge badge-justificado">En Curso</span>
                                    <?php } else { ?>
                                        <span class="badge badge-ausente">En Recuperación</span>
                                    <?php } ?>
                                </td>
                            </tr>
                            <?php } ?>
                        <?php } else { ?>
                            <tr><td colspan="<?php echo count($actividades) + 4; ?>">No hay estudiantes inscritos en esta sección.</td></tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function actualizarAporte(inputEl) {
            var targetId = inputEl.getAttribute('data-target');
            var pond = parseFloat(inputEl.getAttribute('data-pond') || '0');
            var span = document.getElementById(targetId);
            if (!span) return;
            var val = parseFloat(inputEl.value);
            if (isNaN(val) || inputEl.value.trim() === '') {
                span.textContent = '—';
                return;
            }
            if (val < 0) val = 0;
            if (val > 20) val = 20;
            var aporte = (val * pond) / 100.0;
            span.textContent = aporte.toFixed(2) + ' pts';
        }

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
