<?php
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/whatsapp.php';
require_once __DIR__ . '/../config/zapier.php';
iniciarSesionSegura();

if (!isset($_SESSION['id']) && !isset($_SESSION['id_docente'])) {
    header("Location: ../visual/iniciar_sesion.html");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_estudiante = intval($_POST['id_estudiante'] ?? 0);
    $fecha         = trim($_POST['fecha'] ?? '');
    $estado        = trim($_POST['estado'] ?? '');

    if ($id_estudiante <= 0 || $fecha === '' || $estado === '') {
        alertaYRedirigir('Todos los campos son obligatorios.');
    }

    $stmt = $conn->prepare("INSERT INTO asistencias (id_estudiante, fecha, estado) VALUES (?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param('iss', $id_estudiante, $fecha, $estado);

        if ($stmt->execute()) {
            $mensajeResultado = 'Asistencia registrada exitosamente.';

            $sql_telefono = "SELECT e.nombre, e.apellido, e.id_representante, r.telefono
                             FROM representantes r
                             JOIN estudiantes e ON r.id_representante = e.id_representante
                             WHERE e.id_estudiante = ?";
            $stmt_tel = $conn->prepare($sql_telefono);
            $stmt_tel->bind_param('i', $id_estudiante);
            $stmt_tel->execute();
            $result_telefono = $stmt_tel->get_result();

            if ($row = $result_telefono->fetch_assoc()) {
                $nombreEst = trim($row['nombre'] . ' ' . $row['apellido']);
                $idRep     = (int) ($row['id_representante'] ?? 0);
                $esFalta   = in_array(strtolower($estado), ['ausente', 'falta'], true);

                if ($idRep > 0) {
                    $tipoNotif   = $esFalta ? 'ausencia' : 'asistencia';
                    $tituloNotif = $esFalta ? 'Inasistencia registrada' : 'Asistencia confirmada';
                    $msgPortal   = $esFalta
                        ? "El estudiante {$nombreEst} no asistió a clase el día {$fecha} ({$estado})."
                        : "El estudiante {$nombreEst} asistió a clase el día {$fecha} ({$estado}).";
                    registrarNotificacionRepresentante($conn, $idRep, $id_estudiante, $tipoNotif, $tituloNotif, $msgPortal);
                    $mensajeResultado .= ' Notificación publicada en el Portal de Representantes.';
                }

                if ($esFalta) {
                    $mensaje = "Hola, le informamos que el estudiante {$nombreEst} ha faltado el día {$fecha}.";

                    if (zapierWebhookEstaConfigurado()) {
                        $zap = enviarInasistenciaAZapier([
                            'evento'                 => 'inasistencia_registrada',
                            'fecha'                  => $fecha,
                            'estado'                 => $estado,
                            'id_estudiante'          => $id_estudiante,
                            'estudiante_nombre'      => $row['nombre'],
                            'estudiante_apellido'    => $row['apellido'],
                            'estudiante_completo'    => $nombreEst,
                            'representante_telefono' => $row['telefono'],
                            'mensaje'                => $mensaje,
                        ]);
                        if ($zap['ok']) {
                            $mensajeResultado .= ' Datos enviados a Zapier.';
                        }
                    }

                    $wa = enviarNotificacionWhatsApp($row['telefono'], $mensaje);

                    if ($wa['ok']) {
                        $mensajeResultado .= ' Notificación de WhatsApp enviada.';
                    } else {
                        $mensajeResultado .= ' No se pudo enviar WhatsApp (' . $wa['error'] . ').';
                    }
                }
            }
            $stmt_tel->close();

            $stmt->close();
            $conn->close();
            alertaYRedirigir($mensajeResultado, '../visual/formulario_asis.html');
        }
        $stmt->close();
    }
}

$conn->close();
alertaYRedirigir('Error al registrar la asistencia.');
?>
