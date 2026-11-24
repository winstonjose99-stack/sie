<?php
require_once __DIR__ . '/../config/conexion.php';
verificarSesionAdmin('iniciar_sesion.html');

$id_seccion_f = intval($_GET['id_seccion'] ?? 0);
$id_materia_f = intval($_GET['id_materia'] ?? 0);
$lapso_f      = trim($_GET['lapso'] ?? '');
$lapsosValidos = ['1er Lapso', '2do Lapso', '3er Lapso'];
if ($lapso_f !== '' && !in_array($lapso_f, $lapsosValidos, true)) {
    $lapso_f = '';
}

$urlRetorno = 'notas_admin.php?' . http_build_query([
    'id_seccion' => $id_seccion_f,
    'id_materia' => $id_materia_f,
    'lapso'      => $lapso_f,
]);

// =========================================================================
// ACCIONES POST ADMINISTRATIVAS: CREAR ACTIVIDAD (100%) O CARGAR/EDITAR NOTA
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = trim($_POST['accion'] ?? '');

    if ($accion === 'crear_actividad_admin') {
        $idSecAct    = intval($_POST['id_seccion_act'] ?? 0);
        $lapsoAct    = trim($_POST['lapso_act'] ?? '1er Lapso');
        $nombreAct   = trim($_POST['nombre_actividad'] ?? '');
        $ponderacion = round(floatval($_POST['ponderacion'] ?? 0), 2);
        $fechaEval   = trim($_POST['fecha_evaluacion'] ?? date('Y-m-d'));

        if (!in_array($lapsoAct, $lapsosValidos, true)) {
            $lapsoAct = '1er Lapso';
        }
        if ($idSecAct <= 0 || $nombreAct === '' || $ponderacion <= 0 || $ponderacion > 100) {
            alertaYRedirigir('Seleccione una sección, indique la actividad y una ponderación válida (1% a 100%).', $urlRetorno);
        }

        $stmtSecInfo = $conn->prepare("SELECT id_seccion, id_materia, id_docente, id_periodo FROM secciones WHERE id_seccion = ? LIMIT 1");
        $stmtSecInfo->bind_param("i", $idSecAct);
        $stmtSecInfo->execute();
        $secRow = $stmtSecInfo->get_result()->fetch_assoc();
        $stmtSecInfo->close();

        if (!$secRow) {
            alertaYRedirigir('La sección seleccionada no existe.', $urlRetorno);
        }

        $idMatAct = (int) $secRow['id_materia'];
        $idDocAct = (int) $secRow['id_docente'];
        $idPerRaw = (int) ($secRow['id_periodo'] ?? obtenerPeriodoActivoId($conn) ?? 0);
        $idPerAct = $idPerRaw > 0 ? $idPerRaw : null;

        $stmtSum = $conn->prepare("SELECT COALESCE(SUM(ponderacion), 0) AS total_pct FROM evaluaciones_plan WHERE id_seccion = ? AND id_materia = ? AND lapso = ?");
        $stmtSum->bind_param("iis", $idSecAct, $idMatAct, $lapsoAct);
        $stmtSum->execute();
        $totalPct = (float) ($stmtSum->get_result()->fetch_assoc()['total_pct'] ?? 0);
        $stmtSum->close();

        if ($totalPct + $ponderacion > 100.001) {
            $restante = max(0, round(100 - $totalPct, 2));
            alertaYRedirigir("La ponderación supera el 100% en esa sección/lapso. Ya hay {$totalPct}% asignado y quedan {$restante}% disponibles.", $urlRetorno);
        }

        $stmtIns = $conn->prepare("
            INSERT INTO evaluaciones_plan (id_seccion, id_materia, id_docente, id_periodo, lapso, nombre_actividad, ponderacion, fecha_evaluacion)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtIns->bind_param("iiiissds", $idSecAct, $idMatAct, $idDocAct, $idPerAct, $lapsoAct, $nombreAct, $ponderacion, $fechaEval);
        if ($stmtIns->execute()) {
            $stmtIns->close();
            $conn->close();
            alertaYRedirigir("Actividad «{$nombreAct}» ({$ponderacion}%) creada y sincronizada con el Panel Docente.", $urlRetorno);
        }
        $stmtIns->close();
        alertaYRedirigir('Error al crear la actividad en el plan de evaluación.', $urlRetorno);
    }

    if ($accion === 'guardar_nota_admin') {
        $idEval = intval($_POST['id_evaluacion'] ?? 0);
        $idEst  = intval($_POST['id_estudiante'] ?? 0);
        $calif  = round(floatval($_POST['calificacion'] ?? -1), 2);
        $obsTxt = trim($_POST['observacion'] ?? '');
        $obsVal = $obsTxt !== '' ? $obsTxt : null;

        if ($idEval <= 0 || $idEst <= 0 || $calif < 0 || $calif > 20) {
            alertaYRedirigir('Debe seleccionar una actividad, un estudiante y una calificación entre 0 y 20.', $urlRetorno);
        }

        $stmtEv = $conn->prepare("
            SELECT ep.id_evaluacion, ep.id_seccion, ep.id_materia, ep.id_docente, ep.id_periodo,
                   ep.lapso, ep.nombre_actividad, ep.ponderacion, ep.fecha_evaluacion,
                   m.nombre_materia
            FROM evaluaciones_plan ep
            INNER JOIN materias m ON ep.id_materia = m.id_materia
            WHERE ep.id_evaluacion = ?
            LIMIT 1
        ");
        $stmtEv->bind_param("i", $idEval);
        $stmtEv->execute();
        $ev = $stmtEv->get_result()->fetch_assoc();
        $stmtEv->close();

        if (!$ev) {
            alertaYRedirigir('La actividad seleccionada no existe.', $urlRetorno);
        }

        $stmtEst = $conn->prepare("SELECT id_estudiante, nombre, apellido, id_seccion, id_representante FROM estudiantes WHERE id_estudiante = ? LIMIT 1");
        $stmtEst->bind_param("i", $idEst);
        $stmtEst->execute();
        $est = $stmtEst->get_result()->fetch_assoc();
        $stmtEst->close();

        if (!$est || (int) $est['id_seccion'] !== (int) $ev['id_seccion']) {
            alertaYRedirigir('El estudiante seleccionado no pertenece a la sección de esa actividad.', $urlRetorno);
        }

        $idMat    = (int) $ev['id_materia'];
        $idDoc    = (int) $ev['id_docente'];
        $idPerRaw = (int) ($ev['id_periodo'] ?? obtenerPeriodoActivoId($conn) ?? 0);
        $idPer    = $idPerRaw > 0 ? $idPerRaw : null;
        $lapsoEv  = $ev['lapso'] ?: '1er Lapso';
        $nomAct   = $ev['nombre_actividad'];
        $pond     = (float) $ev['ponderacion'];
        $fechaReg = $ev['fecha_evaluacion'] ?: date('Y-m-d');
        $nomMat   = $ev['nombre_materia'] ?? 'Materia';

        // Upsert en notas_academicas (misma tabla que usa el docente)
        $stmtChk = $conn->prepare("SELECT id_nota FROM notas_academicas WHERE id_estudiante = ? AND id_evaluacion = ? LIMIT 1");
        $stmtChk->bind_param("ii", $idEst, $idEval);
        $stmtChk->execute();
        $ex = $stmtChk->get_result()->fetch_assoc();
        $stmtChk->close();

        $okSave = false;
        if ($ex) {
            $idNotaEx = (int) $ex['id_nota'];
            $stmtUpd = $conn->prepare("
                UPDATE notas_academicas
                SET calificacion = ?, ponderacion = ?, evaluacion = ?, observacion = ?, fecha_registro = ?, id_docente = ?
                WHERE id_nota = ?
            ");
            $stmtUpd->bind_param("ddsssii", $calif, $pond, $nomAct, $obsVal, $fechaReg, $idDoc, $idNotaEx);
            $okSave = (bool) $stmtUpd->execute();
            $stmtUpd->close();
        } else {
            $stmtInsN = $conn->prepare("
                INSERT INTO notas_academicas (id_estudiante, id_materia, id_docente, id_periodo, id_evaluacion, lapso, evaluacion, ponderacion, calificacion, observacion, fecha_registro)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtInsN->bind_param("iiiiissddss", $idEst, $idMat, $idDoc, $idPer, $idEval, $lapsoEv, $nomAct, $pond, $calif, $obsVal, $fechaReg);
            $okSave = (bool) $stmtInsN->execute();
            $stmtInsN->close();
        }

        if ($okSave) {
            // Notificar al Portal de Representantes
            $idRep = (int) ($est['id_representante'] ?? 0);
            if ($idRep > 0) {
                $stmtAc = $conn->prepare("SELECT COALESCE(SUM(calificacion * ponderacion / 100.0), 0) AS acum, COALESCE(SUM(ponderacion), 0) AS pct FROM notas_academicas WHERE id_estudiante = ? AND id_materia = ? AND lapso = ?");
                $stmtAc->bind_param("iis", $idEst, $idMat, $lapsoEv);
                $stmtAc->execute();
                $acRow = $stmtAc->get_result()->fetch_assoc();
                $stmtAc->close();

                $acumPts   = number_format((float) ($acRow['acum'] ?? 0), 2);
                $pctEval   = number_format((float) ($acRow['pct'] ?? 0), 0);
                $nomEst    = trim($est['nombre'] . ' ' . $est['apellido']);
                $aportePts = number_format(($calif * $pond) / 100.0, 2);
                $califFmt  = number_format($calif, 2);
                $pondFmt   = number_format($pond, 0);

                $tituloN = "Nota registrada — {$nomMat} ({$pondFmt}%)";
                $msgN    = "{$nomEst} obtuvo {$califFmt} / 20 (aporte ponderado: {$aportePts} pts — {$pondFmt}%) en «{$nomAct}» ({$lapsoEv}). Acumulado del lapso ({$pctEval}% evaluado): {$acumPts} / 20." . ($obsVal ? " Observación: {$obsVal}." : "");
                registrarNotificacionRepresentante($conn, $idRep, $idEst, 'nota', $tituloN, $msgN);
            }

            $conn->close();
            alertaYRedirigir('Calificación guardada desde Administración y sincronizada con el Panel Docente y Portal de Representantes.', $urlRetorno);
        }

        alertaYRedirigir('Error al guardar la calificación.', $urlRetorno);
    }
}

// Catálogos para filtros y formularios administrativos
$secciones = [];
$resSec = $conn->query("
    SELECT s.id_seccion, s.nombre_seccion, m.nombre_materia, d.nombre AS doc_nombre, d.apellido AS doc_apellido
    FROM secciones s
    LEFT JOIN materias m ON s.id_materia = m.id_materia
    LEFT JOIN docentes d ON s.id_docente = d.id_docente
    ORDER BY s.nombre_seccion ASC
");
if ($resSec) {
    while ($s = $resSec->fetch_assoc()) {
        $secciones[] = $s;
    }
}

$materias = [];
$resMat = $conn->query("SELECT id_materia, nombre_materia FROM materias ORDER BY nombre_materia ASC");
if ($resMat) {
    while ($m = $resMat->fetch_assoc()) {
        $materias[] = $m;
    }
}

$actividadesCatalogo = [];
$resActCat = $conn->query("
    SELECT ep.id_evaluacion, ep.id_seccion, ep.lapso, ep.nombre_actividad, ep.ponderacion,
           s.nombre_seccion, m.nombre_materia
    FROM evaluaciones_plan ep
    INNER JOIN secciones s ON ep.id_seccion = s.id_seccion
    INNER JOIN materias m ON ep.id_materia = m.id_materia
    ORDER BY s.nombre_seccion ASC, ep.lapso ASC, ep.fecha_evaluacion ASC
");
if ($resActCat) {
    while ($ac = $resActCat->fetch_assoc()) {
        $actividadesCatalogo[] = $ac;
    }
}

$estudiantesCatalogo = [];
$resEstCat = $conn->query("
    SELECT e.id_estudiante, e.nombre, e.apellido, e.cedula, e.id_seccion, s.nombre_seccion
    FROM estudiantes e
    LEFT JOIN secciones s ON e.id_seccion = s.id_seccion
    ORDER BY s.nombre_seccion ASC, e.apellido ASC, e.nombre ASC
");
if ($resEstCat) {
    while ($ec = $resEstCat->fetch_assoc()) {
        $estudiantesCatalogo[] = $ec;
    }
}

// 1. Planes de evaluación configurados por los docentes (resumen por sección/materia/lapso)
$sqlPlan = "
    SELECT ep.id_seccion, ep.id_materia, ep.lapso,
           s.nombre_seccion, m.nombre_materia,
           d.nombre AS doc_nombre, d.apellido AS doc_apellido,
           COUNT(ep.id_evaluacion) AS total_actividades,
           COALESCE(SUM(ep.ponderacion), 0) AS total_ponderacion
    FROM evaluaciones_plan ep
    INNER JOIN secciones s ON ep.id_seccion = s.id_seccion
    INNER JOIN materias m ON ep.id_materia = m.id_materia
    LEFT JOIN docentes d ON ep.id_docente = d.id_docente
    WHERE 1=1
";
$pTypes = '';
$pParams = [];
if ($id_seccion_f > 0) {
    $sqlPlan .= " AND ep.id_seccion = ?";
    $pTypes .= 'i';
    $pParams[] = $id_seccion_f;
}
if ($id_materia_f > 0) {
    $sqlPlan .= " AND ep.id_materia = ?";
    $pTypes .= 'i';
    $pParams[] = $id_materia_f;
}
if ($lapso_f !== '') {
    $sqlPlan .= " AND ep.lapso = ?";
    $pTypes .= 's';
    $pParams[] = $lapso_f;
}
$sqlPlan .= " GROUP BY ep.id_seccion, ep.id_materia, ep.lapso, s.nombre_seccion, m.nombre_materia, d.nombre, d.apellido
              ORDER BY s.nombre_seccion ASC, m.nombre_materia ASC, ep.lapso ASC";

$stmtPlan = $conn->prepare($sqlPlan);
if ($pTypes !== '') {
    $stmtPlan->bind_param($pTypes, ...$pParams);
}
$stmtPlan->execute();
$resPlan = $stmtPlan->get_result();
$planesDocentes = [];
$totalActividadesPlan = 0;
if ($resPlan) {
    while ($pl = $resPlan->fetch_assoc()) {
        $planesDocentes[] = $pl;
        $totalActividadesPlan += (int) $pl['total_actividades'];
    }
}
$stmtPlan->close();

// 2. Calificaciones detalladas cargadas por docentes y administración
$sqlNotas = "
    SELECT n.id_nota, n.id_estudiante, n.lapso, n.evaluacion, n.ponderacion, n.calificacion, n.observacion, n.fecha_registro,
           e.nombre AS est_nombre, e.apellido AS est_apellido, e.cedula AS est_cedula,
           s.id_seccion, s.nombre_seccion,
           m.id_materia, m.nombre_materia,
           d.nombre AS doc_nombre, d.apellido AS doc_apellido
    FROM notas_academicas n
    INNER JOIN estudiantes e ON n.id_estudiante = e.id_estudiante
    LEFT JOIN secciones s ON e.id_seccion = s.id_seccion
    LEFT JOIN materias m ON n.id_materia = m.id_materia
    LEFT JOIN docentes d ON n.id_docente = d.id_docente
    WHERE 1=1
";
$nTypes = '';
$nParams = [];
if ($id_seccion_f > 0) {
    $sqlNotas .= " AND e.id_seccion = ?";
    $nTypes .= 'i';
    $nParams[] = $id_seccion_f;
}
if ($id_materia_f > 0) {
    $sqlNotas .= " AND n.id_materia = ?";
    $nTypes .= 'i';
    $nParams[] = $id_materia_f;
}
if ($lapso_f !== '') {
    $sqlNotas .= " AND n.lapso = ?";
    $nTypes .= 's';
    $nParams[] = $lapso_f;
}
$sqlNotas .= " ORDER BY n.fecha_registro DESC, s.nombre_seccion ASC, e.apellido ASC, n.id_nota DESC LIMIT 200";

$stmtNotas = $conn->prepare($sqlNotas);
if ($nTypes !== '') {
    $stmtNotas->bind_param($nTypes, ...$nParams);
}
$stmtNotas->execute();
$resNotas = $stmtNotas->get_result();

$notasDetalle = [];
$consolidado  = [];
$estUnicos    = [];
$sumaCalif    = 0.0;

if ($resNotas) {
    while ($nt = $resNotas->fetch_assoc()) {
        $pond   = (float) ($nt['ponderacion'] ?? 0);
        $calif  = (float) $nt['calificacion'];
        $aporte = $pond > 0 ? ($calif * $pond) / 100.0 : 0.0;
        $nt['aporte_ponderado'] = $aporte;
        $notasDetalle[] = $nt;

        $estUnicos[(int) $nt['id_estudiante']] = true;
        $sumaCalif += $calif;

        $clave = $nt['id_estudiante'] . '|' . ($nt['id_materia'] ?? 0) . '|' . ($nt['lapso'] ?? '1er Lapso');
        if (!isset($consolidado[$clave])) {
            $consolidado[$clave] = [
                'estudiante' => trim($nt['est_apellido'] . ', ' . $nt['est_nombre']),
                'cedula'     => $nt['est_cedula'] ?? 'N/A',
                'seccion'    => $nt['nombre_seccion'] ?? 'N/A',
                'materia'    => $nt['nombre_materia'] ?? 'N/A',
                'docente'    => trim(($nt['doc_nombre'] ?? '') . ' ' . ($nt['doc_apellido'] ?? '')) ?: 'N/A',
                'lapso'      => $nt['lapso'] ?? '1er Lapso',
                'pct'        => 0.0,
                'acumulado'  => 0.0,
                'cantidad'   => 0,
            ];
        }
        $consolidado[$clave]['pct']       += $pond;
        $consolidado[$clave]['acumulado'] += $aporte;
        $consolidado[$clave]['cantidad']++;
    }
}
$stmtNotas->close();

$promedioGeneral = count($notasDetalle) > 0 ? number_format($sumaCalif / count($notasDetalle), 2) : 'N/A';

$pdfQuery = http_build_query([
    'tipo'       => 'notas_admin',
    'id_seccion' => $id_seccion_f,
    'id_materia' => $id_materia_f,
    'lapso'      => $lapso_f,
]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte Administrativo de Notas Académicas</title>
    <link rel="stylesheet" href="../css/estilos.css">
    <style>
        .filtros-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 12px;
            align-items: end;
            max-width: none;
            margin: 0 0 24px 0;
            padding: 18px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.05);
        }
        .filtros-grid label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 6px;
            color: #334155;
            text-align: left;
        }
        .filtros-grid select,
        .filtros-grid input {
            margin-bottom: 0;
        }
        .filtros-acciones {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .filtros-acciones button,
        .filtros-acciones a {
            margin: 0;
            padding: 10px 16px;
            white-space: nowrap;
        }
        .badge-nota {
            background: #dbeafe;
            color: #1e40af;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
        }
        .admin-forms-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 20px;
            margin-bottom: 28px;
        }
        .admin-form-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.05);
            text-align: left;
        }
        .admin-form-card h3 {
            margin: 0 0 6px 0;
            text-align: left;
        }
        .admin-form-card p {
            margin: 0 0 14px 0;
            font-size: 0.88rem;
            color: #475569;
        }
        .admin-form-card form {
            max-width: none;
            margin: 0;
            padding: 0;
            background: transparent;
            box-shadow: none;
        }
    </style>
</head>
<body>

    <header class="header-fijo">
        <div class="logo">
            <img src="logo.png" alt="Logo SIE">
        </div>
        <h2>CONTROL ADMINISTRATIVO DE NOTAS</h2>
        <nav>
            <ul>
                <li><a href="../api/dashboard.php">Panel Principal</a></li>
                <li><a href="historial_inasistencias.php">Inasistencias</a></li>
                <li><a href="gestionar.php">Gestionar Registros</a></li>
                <li><a href="../api/logout.php">Cerrar Sesión</a></li>
            </ul>
        </nav>
    </header>

    <div class="dashboard-container">
        <div class="welcome-card">
            <h2>Consolidado Institucional de Notas Académicas (100%)</h2>
            <p>Supervise, cargue o actualice en tiempo real las actividades ponderadas y calificaciones sincronizadas con los docentes y el Portal de Representantes.</p>
        </div>

        <!-- KPIs Administrativos de Notas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div>
                    <p class="stat-value"><?php echo $totalActividadesPlan; ?></p>
                    <p class="stat-label">Actividades Planificadas</p>
                </div>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-value"><?php echo count($notasDetalle); ?></p>
                    <p class="stat-label">Calificaciones Registradas</p>
                </div>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-value"><?php echo count($estUnicos); ?></p>
                    <p class="stat-label">Estudiantes Evaluados</p>
                </div>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-value"><?php echo $promedioGeneral; ?></p>
                    <p class="stat-label">Promedio General (/ 20)</p>
                </div>
            </div>
        </div>

        <!-- Filtros + Exportación PDF -->
        <form method="GET" action="notas_admin.php" class="filtros-grid">
            <div>
                <label for="id_seccion">Sección:</label>
                <select id="id_seccion" name="id_seccion">
                    <option value="0">Todas las secciones</option>
                    <?php foreach ($secciones as $s) { ?>
                        <option value="<?php echo (int) $s['id_seccion']; ?>" <?php echo $id_seccion_f === (int) $s['id_seccion'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['nombre_seccion'] . ' — ' . ($s['nombre_materia'] ?? '')); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>

            <div>
                <label for="id_materia">Materia:</label>
                <select id="id_materia" name="id_materia">
                    <option value="0">Todas las materias</option>
                    <?php foreach ($materias as $m) { ?>
                        <option value="<?php echo (int) $m['id_materia']; ?>" <?php echo $id_materia_f === (int) $m['id_materia'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($m['nombre_materia']); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>

            <div>
                <label for="lapso">Lapso:</label>
                <select id="lapso" name="lapso">
                    <option value="">Todos los lapsos</option>
                    <?php foreach ($lapsosValidos as $lp) { ?>
                        <option value="<?php echo htmlspecialchars($lp); ?>" <?php echo $lapso_f === $lp ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($lp); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>

            <div class="filtros-acciones">
                <button type="submit">Filtrar</button>
                <a href="notas_admin.php" class="btn-ver">Limpiar</a>
                <a href="../api/generar_pdf.php?<?php echo htmlspecialchars($pdfQuery); ?>" class="btn-pdf">Exportar PDF</a>
            </div>
        </form>

        <!-- Gestión directa desde Administración (carga de actividades y notas sincronizadas) -->
        <div class="admin-forms-grid">
            <div class="admin-form-card">
                <h3>Cargar o Actualizar Nota (Administración)</h3>
                <p>Las notas guardadas aquí actualizan de inmediato el Panel Docente y envían notificación al Portal de Representantes.</p>
                <form method="POST" action="<?php echo htmlspecialchars($urlRetorno); ?>">
                    <input type="hidden" name="accion" value="guardar_nota_admin">

                    <label for="adm_eval">Actividad Ponderada:</label>
                    <select id="adm_eval" name="id_evaluacion" required onchange="filtrarEstudiantesPorSeccion()">
                        <option value="">-- Seleccione una actividad --</option>
                        <?php foreach ($actividadesCatalogo as $ac) { ?>
                            <option value="<?php echo (int) $ac['id_evaluacion']; ?>" data-sec="<?php echo (int) $ac['id_seccion']; ?>">
                                <?php echo htmlspecialchars($ac['nombre_seccion'] . ' | ' . $ac['nombre_materia'] . ' | ' . $ac['lapso'] . ' — ' . $ac['nombre_actividad'] . ' (' . number_format((float) $ac['ponderacion'], 0) . '%)'); ?>
                            </option>
                        <?php } ?>
                    </select>

                    <label for="adm_est">Estudiante:</label>
                    <select id="adm_est" name="id_estudiante" required>
                        <option value="">-- Seleccione un estudiante --</option>
                        <?php foreach ($estudiantesCatalogo as $ec) { ?>
                            <option value="<?php echo (int) $ec['id_estudiante']; ?>" data-sec="<?php echo (int) ($ec['id_seccion'] ?? 0); ?>">
                                <?php echo htmlspecialchars($ec['apellido'] . ', ' . $ec['nombre'] . ' (Sección ' . ($ec['nombre_seccion'] ?? 'N/A') . ' — C.I. ' . $ec['cedula'] . ')'); ?>
                            </option>
                        <?php } ?>
                    </select>

                    <label for="adm_calif">Calificación (0 a 20 pts):</label>
                    <input type="number" id="adm_calif" name="calificacion" min="0" max="20" step="0.5" placeholder="Ej. 17.5" required>

                    <label for="adm_obs">Observación (opcional):</label>
                    <input type="text" id="adm_obs" name="observacion" placeholder="Ej. Nota cargada/ajustada por Coordinación">

                    <button type="submit">Guardar Nota y Sincronizar</button>
                </form>
            </div>

            <div class="admin-form-card">
                <h3>Agregar Actividad al Plan (100%)</h3>
                <p>Cree una nueva actividad ponderada para cualquier sección y lapso (validando no exceder el 100%).</p>
                <form method="POST" action="<?php echo htmlspecialchars($urlRetorno); ?>">
                    <input type="hidden" name="accion" value="crear_actividad_admin">

                    <label for="act_sec">Sección y Materia:</label>
                    <select id="act_sec" name="id_seccion_act" required>
                        <option value="">-- Seleccione sección --</option>
                        <?php foreach ($secciones as $s) { ?>
                            <option value="<?php echo (int) $s['id_seccion']; ?>">
                                <?php echo htmlspecialchars($s['nombre_seccion'] . ' — ' . ($s['nombre_materia'] ?? 'Sin materia') . ' (' . trim(($s['doc_nombre'] ?? '') . ' ' . ($s['doc_apellido'] ?? '')) . ')'); ?>
                            </option>
                        <?php } ?>
                    </select>

                    <label for="act_lapso">Lapso:</label>
                    <select id="act_lapso" name="lapso_act" required>
                        <?php foreach ($lapsosValidos as $lp) { ?>
                            <option value="<?php echo htmlspecialchars($lp); ?>"><?php echo htmlspecialchars($lp); ?></option>
                        <?php } ?>
                    </select>

                    <label for="act_nom">Nombre de la Actividad:</label>
                    <input type="text" id="act_nom" name="nombre_actividad" placeholder="Ej. Actividad 3: Proyecto Final" required>

                    <label for="act_pond">Ponderación (%):</label>
                    <input type="number" id="act_pond" name="ponderacion" min="1" max="100" step="1" value="25" required>

                    <label for="act_fecha">Fecha de Evaluación:</label>
                    <input type="date" id="act_fecha" name="fecha_evaluacion" value="<?php echo date('Y-m-d'); ?>" required>

                    <button type="submit">Crear Actividad en el Plan</button>
                </form>
            </div>
        </div>

        <!-- Bloque 1: Avance de Planes de Evaluación por Docente -->
        <div class="container" style="margin-top: 0; margin-bottom: 28px;">
            <h2>1. Planes de Evaluación Configurados (Docentes y Administración)</h2>
            <p>Seguimiento del porcentaje planificado sobre el 100% en cada sección y materia.</p>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Sección</th>
                            <th>Materia</th>
                            <th>Docente</th>
                            <th>Lapso</th>
                            <th>Actividades</th>
                            <th>Ponderación Planificada</th>
                            <th>Estado del Plan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($planesDocentes)) { ?>
                            <?php foreach ($planesDocentes as $pl) {
                                $pctPl = (float) $pl['total_ponderacion'];
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($pl['nombre_seccion']); ?></strong></td>
                                <td><?php echo htmlspecialchars($pl['nombre_materia']); ?></td>
                                <td><?php echo htmlspecialchars(trim(($pl['doc_nombre'] ?? '') . ' ' . ($pl['doc_apellido'] ?? '')) ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($pl['lapso']); ?></td>
                                <td><?php echo (int) $pl['total_actividades']; ?> actividad(es)</td>
                                <td><strong><?php echo number_format($pctPl, 0); ?>% / 100%</strong></td>
                                <td>
                                    <span class="badge <?php echo $pctPl >= 99.99 ? 'badge-presente' : 'badge-justificado'; ?>">
                                        <?php echo $pctPl >= 99.99 ? 'Completo (100%)' : 'En Progreso'; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php } ?>
                        <?php } else { ?>
                            <tr><td colspan="7">No hay planes de evaluación registrados para los filtros seleccionados.</td></tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Bloque 2: Consolidado Ponderado por Estudiante -->
        <div class="container" style="margin-top: 0; margin-bottom: 28px;">
            <h2>2. Consolidado Ponderado por Estudiante</h2>
            <p>Nota acumulada sobre 20 puntos calculada automáticamente a partir de las ponderaciones cargadas.</p>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Estudiante</th>
                            <th>Cédula</th>
                            <th>Sección</th>
                            <th>Materia</th>
                            <th>Docente</th>
                            <th>Lapso</th>
                            <th>% Evaluado</th>
                            <th>Nota Acumulada (/ 20)</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($consolidado)) { ?>
                            <?php foreach ($consolidado as $row) {
                                $acum = (float) $row['acumulado'];
                                $pct  = (float) $row['pct'];
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['estudiante']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['cedula']); ?></td>
                                <td><?php echo htmlspecialchars($row['seccion']); ?></td>
                                <td><?php echo htmlspecialchars($row['materia']); ?></td>
                                <td><?php echo htmlspecialchars($row['docente']); ?></td>
                                <td><?php echo htmlspecialchars($row['lapso']); ?></td>
                                <td><?php echo number_format($pct, 0); ?>% / 100%</td>
                                <td><strong><?php echo number_format($acum, 2); ?> / 20</strong></td>
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
                        <?php } else { ?>
                            <tr><td colspan="9">No hay notas acumuladas para mostrar con los filtros actuales.</td></tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Bloque 3: Detalle de Evaluaciones -->
        <div class="container" style="margin-top: 0;">
            <h2>3. Detalle Completo de Calificaciones</h2>
            <p>Registro individual por estudiante, actividad evaluada, porcentaje asignado y aporte ponderado.</p>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Estudiante</th>
                            <th>Sección</th>
                            <th>Materia</th>
                            <th>Docente</th>
                            <th>Lapso</th>
                            <th>Actividad</th>
                            <th>Ponderación</th>
                            <th>Nota (/ 20)</th>
                            <th>Aporte</th>
                            <th>Observación</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($notasDetalle)) { ?>
                            <?php foreach ($notasDetalle as $nt) {
                                $pond   = (float) ($nt['ponderacion'] ?? 0);
                                $calif  = (float) $nt['calificacion'];
                                $aporte = (float) ($nt['aporte_ponderado'] ?? 0);
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($nt['fecha_registro']); ?></td>
                                <td><strong><?php echo htmlspecialchars(trim($nt['est_apellido'] . ', ' . $nt['est_nombre'])); ?></strong></td>
                                <td><?php echo htmlspecialchars($nt['nombre_seccion'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($nt['nombre_materia'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars(trim(($nt['doc_nombre'] ?? '') . ' ' . ($nt['doc_apellido'] ?? '')) ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($nt['lapso']); ?></td>
                                <td><?php echo htmlspecialchars($nt['evaluacion']); ?></td>
                                <td><span class="badge-nota"><?php echo $pond > 0 ? number_format($pond, 0) . '%' : '—'; ?></span></td>
                                <td><strong><?php echo number_format($calif, 2); ?></strong></td>
                                <td><?php echo $pond > 0 ? number_format($aporte, 2) . ' pts' : '—'; ?></td>
                                <td><?php echo htmlspecialchars($nt['observacion'] ?: 'Sin observaciones'); ?></td>
                            </tr>
                            <?php } ?>
                        <?php } else { ?>
                            <tr><td colspan="11">No hay calificaciones registradas para los filtros seleccionados.</td></tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function filtrarEstudiantesPorSeccion() {
            var evalSel = document.getElementById('adm_eval');
            var estSel  = document.getElementById('adm_est');
            if (!evalSel || !estSel) return;
            var opt = evalSel.options[evalSel.selectedIndex];
            var secId = opt ? opt.getAttribute('data-sec') : '';
            estSel.value = '';
            for (var i = 0; i < estSel.options.length; i++) {
                var o = estSel.options[i];
                if (!o.value) {
                    o.hidden = false;
                    continue;
                }
                o.hidden = (secId && o.getAttribute('data-sec') !== secId);
            }
        }
    </script>

</body>
</html>
<?php $conn->close(); ?>
