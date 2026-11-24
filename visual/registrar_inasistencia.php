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
$estudiantes = $stmt_est->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Tomar Asistencia - Portal Docente</title>
    <link rel="stylesheet" href="../css/estilos.css">
    <style>
        main form {
            max-width: 980px;
        }
        .form-container {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 18px;
        }
        .form-container label {
            margin-bottom: 0;
        }
        .form-container input[type="date"] {
            max-width: 200px;
            margin-bottom: 0;
        }
        .btn-guardar {
            max-width: 340px;
            margin: 24px auto 0 auto;
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
                <li><a href="panel_profesor.php">← Volver al Panel</a></li>
                <li><a href="asistencias_guardadas.php?id_seccion=<?php echo $id_seccion; ?>&id_materia=<?php echo $id_materia; ?>&fecha=<?php echo urlencode($fecha_actual); ?>">Ver Asistencias Guardadas</a></li>
                <li><a href="../api/logout.php">Cerrar Sesión</a></li>
            </ul>
        </nav>
    </header>

    <main>
        <h2>Control de Asistencia e Inasistencias</h2>
        <h3>
            Sección: <?php echo htmlspecialchars($seccion_info['nombre_seccion']); ?> |
            Materia: <?php echo htmlspecialchars($seccion_info['nombre_materia']); ?>
        </h3>

        <form action="" method="POST">
            <div class="form-container">
                <label for="fecha">Fecha de la clase:</label>
                <input type="date" id="fecha" name="fecha" value="<?php echo htmlspecialchars($fecha_actual); ?>" required
                       onchange="window.location.href='registrar_inasistencia.php?id_seccion=<?php echo $id_seccion; ?>&id_materia=<?php echo $id_materia; ?>&fecha=' + encodeURIComponent(this.value);">
                <a href="asistencias_guardadas.php?id_seccion=<?php echo $id_seccion; ?>&id_materia=<?php echo $id_materia; ?>&fecha=<?php echo urlencode($fecha_actual); ?>" class="btn-ver">
                    Ver Lista Guardada de esta Fecha
                </a>
            </div>

            <div class="table-responsive">
                <table>
                    <tr>
                        <th>ID</th>
                        <th>Nombre y Apellido</th>
                        <th>Cédula</th>
                        <th>Fecha de Nacimiento</th>
                        <th>Sexo</th>
                        <th>Estado Guardado</th>
                        <th>Seleccionar Asistencia</th>
                    </tr>
                    <?php
                    if ($estudiantes && $estudiantes->num_rows > 0) {
                        while ($est = $estudiantes->fetch_assoc()) {
                            $estadoG = $est['estado_guardado'] ?? null;
                            $motivoG = $est['motivo_guardado'] ?? null;

                            // Determinar valor preseleccionado en el <select>
                            $valorPreseleccionado = 'Presente';
                            if ($estadoG === 'Ausente') {
                                $valorPreseleccionado = 'Ausente';
                            } elseif ($estadoG === 'Justificado') {
                                $candidato = 'Justificado - ' . ($motivoG ?: 'Cita Médica');
                                $opcionesValidas = [
                                    'Justificado - Cita Médica',
                                    'Justificado - Reposo',
                                    'Justificado - Malestar',
                                    'Justificado - Motivos Personales',
                                ];
                                $valorPreseleccionado = in_array($candidato, $opcionesValidas, true) ? $candidato : 'Justificado - Motivos Personales';
                            }
                    ?>
                    <tr>
                        <td><?php echo (int) $est['id_estudiante']; ?></td>
                        <td><strong><?php echo htmlspecialchars($est['nombre'] . ' ' . $est['apellido']); ?></strong></td>
                        <td><?php echo htmlspecialchars($est['cedula']); ?></td>
                        <td><?php echo htmlspecialchars((!empty($est['fecha_nacimiento']) && $est['fecha_nacimiento'] !== '0000-00-00') ? $est['fecha_nacimiento'] : 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars(!empty($est['sexo']) ? $est['sexo'] : 'N/A'); ?></td>
                        <td>
                            <?php if ($estadoG === 'Ausente') { ?>
                                <span class="badge badge-ausente">Ausente</span>
                            <?php } elseif ($estadoG === 'Justificado') { ?>
                                <span class="badge badge-justificado">Justificado (<?php echo htmlspecialchars($motivoG ?: 'General'); ?>)</span>
                            <?php } else { ?>
                                <span class="badge badge-presente">Presente</span>
                            <?php } ?>
                        </td>
                        <td>
                            <select name="estado[<?php echo (int) $est['id_estudiante']; ?>]" style="padding: 8px; margin-bottom: 0;">
                                <option value="Presente" <?php echo $valorPreseleccionado === 'Presente' ? 'selected' : ''; ?>>Presente (Asistió)</option>
                                <option value="Ausente" <?php echo $valorPreseleccionado === 'Ausente' ? 'selected' : ''; ?>>Ausente (Falta)</option>
                                <option value="Justificado - Cita Médica" <?php echo $valorPreseleccionado === 'Justificado - Cita Médica' ? 'selected' : ''; ?>>Justificado: Cita Médica</option>
                                <option value="Justificado - Reposo" <?php echo $valorPreseleccionado === 'Justificado - Reposo' ? 'selected' : ''; ?>>Justificado: Reposo</option>
                                <option value="Justificado - Malestar" <?php echo $valorPreseleccionado === 'Justificado - Malestar' ? 'selected' : ''; ?>>Justificado: Malestar</option>
                                <option value="Justificado - Motivos Personales" <?php echo $valorPreseleccionado === 'Justificado - Motivos Personales' ? 'selected' : ''; ?>>Justificado: Motivos Personales</option>
                            </select>
                        </td>
                    </tr>
                    <?php
                        }
                    } else {
                        echo "<tr><td colspan='7'>No hay estudiantes inscritos en esta sección.</td></tr>";
                    }
                    $stmt_est->close();
                    ?>
                </table>
            </div>

            <button type="submit" class="btn-guardar">Guardar Asistencia de la Clase</button>
        </form>
    </main>

</body>
</html>
<?php $conn->close(); ?>
