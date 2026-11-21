<?php
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/SimplePDF.php';
iniciarSesionSegura();

$esAdmin   = isset($_SESSION['id']);
$esDocente = isset($_SESSION['id_docente']);

if (!$esAdmin && !$esDocente) {
    header("Location: ../visual/iniciar_sesion.html");
    exit();
}

$tipo     = trim($_GET['tipo'] ?? 'asistencia_clase');
$download = empty($_GET['ver']);

// Helper de color para estados de asistencia
function obtenerEstiloBadgePDF(string $estado): array {
    if ($estado === 'Ausente') {
        return ['bg' => [254, 226, 226], 'border' => [252, 165, 165], 'color' => [153, 27, 27]];
    }
    if ($estado === 'Justificado') {
        return ['bg' => [254, 243, 199], 'border' => [252, 211, 77], 'color' => [146, 64, 14]];
    }
    return ['bg' => [209, 250, 229], 'border' => [110, 231, 183], 'color' => [6, 95, 70]];
}

// =========================================================================
// 1. LISTA DE ASISTENCIA GUARDADA POR CLASE (Sección + Materia + Fecha)
// =========================================================================
if ($tipo === 'asistencia_clase') {
    $id_seccion = intval($_GET['id_seccion'] ?? 0);
    $id_materia = intval($_GET['id_materia'] ?? 0);
    $fecha      = trim($_GET['fecha'] ?? date('Y-m-d'));

    if ($id_seccion <= 0) {
        alertaYRedirigir('Sección no válida para generar el PDF.');
    }

    $sql_sec = "
        SELECT s.id_seccion, s.nombre_seccion,
               m.id_materia, m.nombre_materia,
               i.nombre AS nombre_institucion,
               d.id_docente, d.nombre AS doc_nombre, d.apellido AS doc_apellido,
               p.nombre_periodo
        FROM secciones s
        INNER JOIN materias m ON s.id_materia = m.id_materia
        LEFT JOIN instituciones i ON s.id_institucion = i.id_institucion
        LEFT JOIN docentes d ON s.id_docente = d.id_docente
        LEFT JOIN periodos_academicos p ON s.id_periodo = p.id_periodo
        WHERE s.id_seccion = ?
    ";
    if ($id_materia > 0) {
        $sql_sec .= " AND s.id_materia = ?";
    }
    $sql_sec .= " LIMIT 1";

    $stmt_sec = $conn->prepare($sql_sec);
    if ($id_materia > 0) {
        $stmt_sec->bind_param("ii", $id_seccion, $id_materia);
    } else {
        $stmt_sec->bind_param("i", $id_seccion);
    }
    $stmt_sec->execute();
    $res_sec = $stmt_sec->get_result();
    $sec = $res_sec ? $res_sec->fetch_assoc() : null;
    $stmt_sec->close();

    if (!$sec) {
        alertaYRedirigir('No se encontró la sección solicitada.');
    }

    // Si es docente (y no admin), verificar que sea su sección
    if (!$esAdmin && $esDocente && (int) $sec['id_docente'] !== (int) $_SESSION['id_docente']) {
        alertaYRedirigir('No tiene permiso para exportar esta sección.');
    }

    $id_materia = (int) $sec['id_materia'];

    $stmt_nom = $conn->prepare("
        SELECT e.cedula, e.nombre, e.apellido, e.fecha_nacimiento, e.sexo,
               r.nombre AS rep_nombre, r.apellido AS rep_apellido, r.telefono AS rep_telefono,
               i.estado, i.motivo
        FROM estudiantes e
        LEFT JOIN representantes r ON e.id_representante = r.id_representante
        LEFT JOIN inasistencias i
               ON i.id_estudiante = e.id_estudiante
              AND i.id_materia = ?
              AND i.fecha = ?
        WHERE e.id_seccion = ?
        ORDER BY e.apellido, e.nombre
    ");
    $stmt_nom->bind_param("isi", $id_materia, $fecha, $id_seccion);
    $stmt_nom->execute();
    $res_nom = $stmt_nom->get_result();

    $filas        = [];
    $presentes    = 0;
    $ausentes     = 0;
    $justificados = 0;

    if ($res_nom) {
        while ($al = $res_nom->fetch_assoc()) {
            if ($al['estado'] === 'Ausente') {
                $ausentes++;
            } elseif ($al['estado'] === 'Justificado') {
                $justificados++;
            } else {
                $al['estado'] = 'Presente';
                $presentes++;
            }
            $filas[] = $al;
        }
    }
    $stmt_nom->close();

    $docenteNombre = trim(($sec['doc_nombre'] ?? '') . ' ' . ($sec['doc_apellido'] ?? '')) ?: 'No asignado';

    $pdf = new SimplePDF('P');
    $pdf->setHeaderInfo(
        'Lista de Asistencia de Clase',
        'Fecha: ' . $fecha,
        [
            'Seccion'     => $sec['nombre_seccion'],
            'Materia'     => $sec['nombre_materia'],
            'Docente'     => $docenteNombre,
            'Institucion' => $sec['nombre_institucion'] ?? 'Sede Principal',
        ]
    );
    $pdf->addPage();

    $pdf->drawSummaryBoxes([
        ['label' => 'Matricula Total', 'value' => count($filas) . ' Alumnos', 'bg' => [239, 246, 255], 'border' => [191, 219, 254], 'color' => [30, 58, 138]],
        ['label' => 'Presentes',       'value' => (string) $presentes,        'bg' => [209, 250, 229], 'border' => [110, 231, 183], 'color' => [6, 95, 70]],
        ['label' => 'Justificados',    'value' => (string) $justificados,     'bg' => [254, 243, 199], 'border' => [252, 211, 77],  'color' => [146, 64, 14]],
        ['label' => 'Ausentes',        'value' => (string) $ausentes,         'bg' => [254, 226, 226], 'border' => [252, 165, 165], 'color' => [153, 27, 27]],
    ]);

    $pdf->setTableColumns(
        ['#', 'Estudiante', 'Cedula', 'F. Nacimiento', 'Sexo', 'Representante', 'Telefono', 'Estado', 'Motivo'],
        [4, 19, 10, 11, 8, 16, 11, 9, 12]
    );

    if (!empty($filas)) {
        foreach ($filas as $idx => $al) {
            $repNombre = trim(($al['rep_nombre'] ?? '') . ' ' . ($al['rep_apellido'] ?? '')) ?: 'Sin asignar';
            $fechaNac  = (!empty($al['fecha_nacimiento']) && $al['fecha_nacimiento'] !== '0000-00-00') ? $al['fecha_nacimiento'] : 'N/A';
            $sexo      = !empty($al['sexo']) ? $al['sexo'] : 'N/A';
            $motivo    = $al['motivo'] ?? ($al['estado'] === 'Presente' ? 'Asistio a clase' : 'Sin justificar');

            $pdf->addTableRow([
                $idx + 1,
                trim($al['apellido'] . ', ' . $al['nombre']),
                $al['cedula'],
                $fechaNac,
                $sexo,
                $repNombre,
                $al['rep_telefono'] ?: '-',
                $al['estado'],
                $motivo,
            ], $idx);
        }
    } else {
        $pdf->addTableRow(['-', 'No hay estudiantes inscritos en esta seccion', '-', '-', '-', '-', '-', '-', '-'], 0);
    }

    $pdf->drawSignatureFooter('Firma del Docente: ' . $docenteNombre, 'Sello de Control de Estudios');
    $conn->close();

    $filename = 'Asistencia_' . $sec['nombre_seccion'] . '_' . $sec['nombre_materia'] . '_' . $fecha . '.pdf';
    $pdf->output($filename, $download);
}

// =========================================================================
// 2. NÓMINA DE ESTUDIANTES INSCRITOS EN UNA SECCIÓN
// =========================================================================
if ($tipo === 'estudiantes_seccion') {
    $id_seccion = intval($_GET['id_seccion'] ?? 0);
    if ($id_seccion <= 0) {
        alertaYRedirigir('Sección no válida.');
    }

    $stmt_sec = $conn->prepare("
        SELECT s.nombre_seccion, m.nombre_materia, i.nombre AS nombre_institucion,
               d.nombre AS doc_nombre, d.apellido AS doc_apellido, p.nombre_periodo
        FROM secciones s
        LEFT JOIN materias m ON s.id_materia = m.id_materia
        LEFT JOIN instituciones i ON s.id_institucion = i.id_institucion
        LEFT JOIN docentes d ON s.id_docente = d.id_docente
        LEFT JOIN periodos_academicos p ON s.id_periodo = p.id_periodo
        WHERE s.id_seccion = ?
        LIMIT 1
    ");
    $stmt_sec->bind_param("i", $id_seccion);
    $stmt_sec->execute();
    $res_sec = $stmt_sec->get_result();
    $sec = $res_sec ? $res_sec->fetch_assoc() : null;
    $stmt_sec->close();

    if (!$sec) {
        alertaYRedirigir('Sección no encontrada.');
    }

    $stmt_est = $conn->prepare("
        SELECT e.*, r.nombre AS rep_nombre, r.apellido AS rep_apellido, r.telefono AS rep_telefono
        FROM estudiantes e
        LEFT JOIN representantes r ON e.id_representante = r.id_representante
        WHERE e.id_seccion = ?
        ORDER BY e.apellido, e.nombre
    ");
    $stmt_est->bind_param("i", $id_seccion);
    $stmt_est->execute();
    $res_est = $stmt_est->get_result();
    $alumnos = [];
    if ($res_est) {
        while ($row = $res_est->fetch_assoc()) {
            $alumnos[] = $row;
        }
    }
    $stmt_est->close();

    $docenteNombre = trim(($sec['doc_nombre'] ?? '') . ' ' . ($sec['doc_apellido'] ?? '')) ?: 'No asignado';

    $pdf = new SimplePDF('P');
    $pdf->setHeaderInfo(
        'Nomina Oficial de Estudiantes por Seccion',
        'Periodo: ' . ($sec['nombre_periodo'] ?? 'Activo'),
        [
            'Seccion'     => $sec['nombre_seccion'],
            'Materia'     => $sec['nombre_materia'] ?? 'General',
            'Docente'     => $docenteNombre,
            'Total'       => count($alumnos) . ' Estudiantes',
        ]
    );
    $pdf->addPage();

    $pdf->setTableColumns(
        ['#', 'Apellidos y Nombres', 'Cedula', 'F. Nacimiento', 'Sexo', 'Condicion Medica', 'Representante'],
        [5, 25, 13, 13, 10, 16, 18]
    );

    if (!empty($alumnos)) {
        foreach ($alumnos as $idx => $est) {
            $rep = trim(($est['rep_nombre'] ?? '') . ' ' . ($est['rep_apellido'] ?? '')) ?: 'Sin asignar';
            $pdf->addTableRow([
                $idx + 1,
                trim($est['apellido'] . ', ' . $est['nombre']),
                $est['cedula'],
                $est['fecha_nacimiento'] ?: 'N/A',
                $est['sexo'] ?: 'N/A',
                $est['enfermedad_condicion'] ?: 'Ninguna',
                $rep,
            ], $idx);
        }
    } else {
        $pdf->addTableRow(['-', 'No hay estudiantes registrados en esta seccion', '-', '-', '-', '-', '-'], 0);
    }

    $pdf->drawSignatureFooter('Coordinacion Academica', 'Sello del Plantel');
    $conn->close();

    $filename = 'Nomina_Seccion_' . $sec['nombre_seccion'] . '.pdf';
    $pdf->output($filename, $download);
}

// =========================================================================
// 3. REPORTE / HISTORIAL DE INASISTENCIAS (con filtros)
// =========================================================================
if ($tipo === 'historial_inasistencias') {
    $fecha_filtro  = trim($_GET['fecha'] ?? '');
    $estado_filtro = trim($_GET['estado'] ?? '');
    $id_seccion_f  = intval($_GET['id_seccion'] ?? 0);

    $sql = "
        SELECT i.fecha, i.estado, i.motivo,
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
    $res = $stmt->get_result();

    $filas        = [];
    $ausentes     = 0;
    $justificados = 0;
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            if ($r['estado'] === 'Justificado') {
                $justificados++;
            } else {
                $ausentes++;
            }
            $filas[] = $r;
        }
    }
    $stmt->close();

    $pdf = new SimplePDF('P');
    $pdf->setHeaderInfo(
        'Reporte General de Inasistencias',
        'Total: ' . count($filas) . ' registros',
        [
            'Filtro Fecha'   => $fecha_filtro !== '' ? $fecha_filtro : 'Todas las fechas',
            'Filtro Estado'  => $estado_filtro !== '' ? $estado_filtro : 'Todos los estados',
            'Total Ausentes' => (string) $ausentes,
            'Justificados'   => (string) $justificados,
        ]
    );
    $pdf->addPage();

    $pdf->setTableColumns(
        ['Fecha', 'Estudiante', 'Cedula', 'Seccion', 'Materia', 'Estado', 'Motivo'],
        [12, 23, 12, 11, 16, 12, 14]
    );

    if (!empty($filas)) {
        foreach ($filas as $idx => $r) {
            $badgeCfg = [5 => obtenerEstiloBadgePDF($r['estado'])];
            $pdf->addTableRow([
                $r['fecha'],
                trim($r['est_apellido'] . ', ' . $r['est_nombre']),
                $r['est_cedula'],
                $r['nombre_seccion'] ?? 'N/A',
                $r['nombre_materia'],
                $r['estado'],
                $r['motivo'] ?: '-',
            ], $idx, $badgeCfg);
        }
    } else {
        $pdf->addTableRow(['-', 'Sin registros para los filtros seleccionados', '-', '-', '-', '-', '-'], 0);
    }

    $pdf->drawSignatureFooter('Responsable del Reporte', 'Sello de Control de Estudios');
    $conn->close();

    $pdf->output('Reporte_Inasistencias_' . date('Y-m-d') . '.pdf', $download);
}

// =========================================================================
// 4. LISTADOS GENERALES DE GESTIÓN (Estudiantes, Docentes, Representantes, Secciones, Materias, Instituciones)
// =========================================================================
if ($tipo === 'listado_general') {
    if (!$esAdmin) {
        alertaYRedirigir('Solo la administración puede exportar los listados generales.');
    }

    $modulo = trim($_GET['modulo'] ?? 'estudiantes');
    $pdf = new SimplePDF('P');

    if ($modulo === 'estudiantes') {
        $res = $conn->query("
            SELECT e.*, r.nombre AS rep_nombre, r.apellido AS rep_apellido, s.nombre_seccion
            FROM estudiantes e
            LEFT JOIN representantes r ON e.id_representante = r.id_representante
            LEFT JOIN secciones s ON e.id_seccion = s.id_seccion
            ORDER BY e.apellido, e.nombre
        ");
        $filas = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

        $pdf->setHeaderInfo('Listado General de Estudiantes', 'Total: ' . count($filas));
        $pdf->addPage();
        $pdf->setTableColumns(
            ['ID', 'Apellidos y Nombres', 'Cedula', 'F. Nac.', 'Sexo', 'Seccion', 'Condicion Medica', 'Representante'],
            [5, 22, 11, 11, 9, 10, 15, 17]
        );
        foreach ($filas as $idx => $row) {
            $rep = trim(($row['rep_nombre'] ?? '') . ' ' . ($row['rep_apellido'] ?? '')) ?: 'Sin asignar';
            $fechaNac = (!empty($row['fecha_nacimiento']) && $row['fecha_nacimiento'] !== '0000-00-00') ? $row['fecha_nacimiento'] : 'N/A';
            $pdf->addTableRow([
                $row['id_estudiante'],
                trim($row['apellido'] . ', ' . $row['nombre']),
                $row['cedula'],
                $fechaNac,
                !empty($row['sexo']) ? $row['sexo'] : 'N/A',
                $row['nombre_seccion'] ?? 'Sin asignar',
                $row['enfermedad_condicion'] ?: 'Ninguna',
                $rep,
            ], $idx);
        }
        $pdf->drawSignatureFooter();
        $conn->close();
        $pdf->output('Listado_Estudiantes.pdf', $download);
    }

    if ($modulo === 'docentes') {
        $res = $conn->query("
            SELECT d.*, i.nombre AS nombre_institucion
            FROM docentes d
            LEFT JOIN instituciones i ON d.id_institucion = i.id_institucion
            ORDER BY d.apellido, d.nombre
        ");
        $filas = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

        $pdf->setHeaderInfo('Listado General de Profesores', 'Total: ' . count($filas));
        $pdf->addPage();
        $pdf->setTableColumns(
            ['ID', 'Apellidos y Nombres', 'Cedula', 'Telefono', 'Correo', 'Institucion'],
            [7, 25, 13, 15, 22, 18]
        );
        foreach ($filas as $idx => $row) {
            $pdf->addTableRow([
                $row['id_docente'],
                trim($row['apellido'] . ', ' . $row['nombre']),
                $row['cedula'],
                $row['telefono'],
                $row['correo'],
                $row['nombre_institucion'] ?? 'Sin asignar',
            ], $idx);
        }
        $pdf->drawSignatureFooter();
        $conn->close();
        $pdf->output('Listado_Profesores.pdf', $download);
    }

    if ($modulo === 'representantes') {
        $res = $conn->query("SELECT * FROM representantes ORDER BY apellido, nombre");
        $filas = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

        $pdf->setHeaderInfo('Listado General de Representantes', 'Total: ' . count($filas));
        $pdf->addPage();
        $pdf->setTableColumns(
            ['ID', 'Nombre', 'Apellido', 'Cedula', 'Telefono (WhatsApp)'],
            [10, 25, 25, 18, 22]
        );
        foreach ($filas as $idx => $row) {
            $pdf->addTableRow([
                $row['id_representante'],
                $row['nombre'],
                $row['apellido'],
                $row['cedula'],
                $row['telefono'],
            ], $idx);
        }
        $pdf->drawSignatureFooter();
        $conn->close();
        $pdf->output('Listado_Representantes.pdf', $download);
    }

    if ($modulo === 'secciones') {
        $res = $conn->query("
            SELECT s.id_seccion, s.nombre_seccion,
                   i.nombre AS nombre_institucion,
                   d.nombre AS doc_nombre, d.apellido AS doc_apellido,
                   m.nombre_materia, p.nombre_periodo
            FROM secciones s
            LEFT JOIN instituciones i ON s.id_institucion = i.id_institucion
            LEFT JOIN docentes d ON s.id_docente = d.id_docente
            LEFT JOIN materias m ON s.id_materia = m.id_materia
            LEFT JOIN periodos_academicos p ON s.id_periodo = p.id_periodo
            ORDER BY s.nombre_seccion
        ");
        $filas = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

        $pdf->setHeaderInfo('Listado General de Secciones', 'Total: ' . count($filas));
        $pdf->addPage();
        $pdf->setTableColumns(
            ['ID', 'Seccion', 'Materia', 'Docente Asignado', 'Institucion', 'Periodo'],
            [7, 15, 20, 24, 20, 14]
        );
        foreach ($filas as $idx => $row) {
            $doc = trim(($row['doc_nombre'] ?? '') . ' ' . ($row['doc_apellido'] ?? '')) ?: 'Sin asignar';
            $pdf->addTableRow([
                $row['id_seccion'],
                $row['nombre_seccion'],
                $row['nombre_materia'] ?? 'N/A',
                $doc,
                $row['nombre_institucion'] ?? 'N/A',
                $row['nombre_periodo'] ?? 'N/A',
            ], $idx);
        }
        $pdf->drawSignatureFooter();
        $conn->close();
        $pdf->output('Listado_Secciones.pdf', $download);
    }

    if ($modulo === 'materias') {
        $res = $conn->query("
            SELECT m.*, d.nombre AS nombre_docente, d.apellido AS apellido_docente
            FROM materias m
            LEFT JOIN docentes d ON m.id_docente = d.id_docente
            ORDER BY m.nombre_materia
        ");
        $filas = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

        $pdf->setHeaderInfo('Listado General de Materias', 'Total: ' . count($filas));
        $pdf->addPage();
        $pdf->setTableColumns(['ID', 'Nombre de la Materia', 'Docente Asignado'], [12, 46, 42]);
        foreach ($filas as $idx => $row) {
            $doc = trim(($row['nombre_docente'] ?? '') . ' ' . ($row['apellido_docente'] ?? '')) ?: 'Sin asignar';
            $pdf->addTableRow([$row['id_materia'], $row['nombre_materia'], $doc], $idx);
        }
        $pdf->drawSignatureFooter();
        $conn->close();
        $pdf->output('Listado_Materias.pdf', $download);
    }

    if ($modulo === 'instituciones') {
        $res = $conn->query("SELECT * FROM instituciones ORDER BY id_institucion");
        $filas = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

        $pdf->setHeaderInfo('Listado General de Instituciones', 'Total: ' . count($filas));
        $pdf->addPage();
        $pdf->setTableColumns(['ID', 'Nombre del Plantel', 'Direccion'], [10, 42, 48]);
        foreach ($filas as $idx => $row) {
            $pdf->addTableRow([$row['id_institucion'], $row['nombre'], $row['direccion']], $idx);
        }
        $pdf->drawSignatureFooter();
        $conn->close();
        $pdf->output('Listado_Instituciones.pdf', $download);
    }
}

$conn->close();
alertaYRedirigir('Tipo de reporte PDF no reconocido.');
?>
