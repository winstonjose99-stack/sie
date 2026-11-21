<?php
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/zapier.php';
iniciarSesionSegura();

$esAdmin   = isset($_SESSION['id']);
$esDocente = isset($_SESSION['id_docente']);

if (!$esAdmin && !$esDocente) {
    header("Location: ../visual/iniciar_sesion.html");
    exit();
}

$mensajeEstado = '';
$tipoAlerta    = '';
$resultadoTest = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar_url') {
        $nuevaUrl = trim($_POST['webhook_url'] ?? '');
        if ($nuevaUrl !== '' && !filter_var($nuevaUrl, FILTER_VALIDATE_URL)) {
            $mensajeEstado = 'La URL ingresada no tiene un formato válido.';
            $tipoAlerta    = 'error';
        } else {
            if (guardarZapierWebhookUrl($nuevaUrl)) {
                $mensajeEstado = 'URL del Webhook de Zapier guardada correctamente.';
                $tipoAlerta    = 'exito';
            } else {
                $mensajeEstado = 'No se pudo guardar la configuración local.';
                $tipoAlerta    = 'error';
            }
        }
    } elseif ($accion === 'probar_webhook') {
        $urlPrueba = trim($_POST['webhook_url'] ?? obtenerZapierWebhookUrl());
        if ($urlPrueba !== '' && filter_var($urlPrueba, FILTER_VALIDATE_URL)) {
            guardarZapierWebhookUrl($urlPrueba);
        }

        // Datos de ejemplo realistas para el "Test Trigger" en Zapier
        $datosPrueba = [
            'evento'                 => 'inasistencia_registrada',
            'fecha'                  => date('Y-m-d'),
            'estado'                 => 'Ausente',
            'motivo'                 => 'Inasistencia sin justificar (Prueba de integración Zapier)',
            'id_estudiante'          => 19,
            'estudiante_nombre'      => 'Santiago',
            'estudiante_apellido'    => 'Rivas',
            'estudiante_completo'    => 'Santiago Rivas',
            'estudiante_cedula'      => '31904512',
            'estudiante_nacimiento'  => '2011-06-18',
            'estudiante_sexo'        => 'Masculino',
            'representante_nombre'   => 'Orlexy Materano',
            'representante_telefono' => '584124710360',
            'id_seccion'             => 8,
            'seccion'                => '1A',
            'id_materia'             => 13,
            'materia'                => 'Informatica',
            'institucion'            => 'Sede Principal',
            'id_docente'             => 4,
            'docente'                => 'Jose Rodriguez',
            'mensaje'                => 'Estimado/a Orlexy Materano, le informamos que el estudiante Santiago Rivas (Sección 1A) no asistió el día ' . date('Y-m-d') . ' a la materia Informatica [Estado: Ausente].',
        ];

        $resultadoTest = enviarInasistenciaAZapier($datosPrueba, $urlPrueba);
        if ($resultadoTest['ok']) {
            $mensajeEstado = 'Petición HTTP POST enviada con éxito a Zapier (HTTP ' . $resultadoTest['http_code'] . '). Ya puedes verificar los campos en tu Zap.';
            $tipoAlerta    = 'exito';
        } else {
            $mensajeEstado = 'No se pudo completar el envío a Zapier: ' . $resultadoTest['error'];
            $tipoAlerta    = 'error';
        }
    }
}

$urlActual = obtenerZapierWebhookUrl();
$configurado = zapierWebhookEstaConfigurado($urlActual);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Integración Webhook Zapier - Sistema de Asistencias</title>
    <link rel="stylesheet" href="../css/estilos.css">
    <style>
        .zapier-box {
            max-width: 780px;
            margin: 110px auto 50px auto;
            padding: 32px;
            background: var(--surface);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-md);
            text-align: left;
        }
        .zapier-box form {
            max-width: 100%;
            box-shadow: none;
            border: none;
            padding: 0;
            margin: 20px 0 0 0;
        }
        .alerta-msg {
            padding: 14px 18px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            font-weight: 600;
            font-size: 0.92rem;
        }
        .alerta-exito {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .alerta-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .code-preview {
            background: #0f172a;
            color: #e2e8f0;
            padding: 16px;
            border-radius: var(--radius-sm);
            font-family: Consolas, monospace;
            font-size: 0.84rem;
            overflow-x: auto;
            white-space: pre-wrap;
            margin-top: 12px;
        }
        .btns-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 14px;
        }
        .btns-row button {
            width: auto;
            flex: 1;
            min-width: 220px;
        }
    </style>
</head>
<body>

    <header class="header-fijo">
        <div class="logo">
            <img src="../visual/logo.png" alt="Logo">
        </div>
        <h2>INTEGRACIÓN WEBHOOK ZAPIER</h2>
        <nav>
            <ul>
                <?php if ($esAdmin) { ?>
                    <li><a href="dashboard.php">Volver al Panel</a></li>
                <?php } else { ?>
                    <li><a href="../visual/panel_profesor.php">Volver al Panel Docente</a></li>
                <?php } ?>
                <li><a href="logout.php">Cerrar Sesión</a></li>
            </ul>
        </nav>
    </header>

    <div class="zapier-box">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <h2 style="margin: 0; text-align: left;">Configuración de Webhook de Zapier</h2>
            <?php if ($configurado) { ?>
                <span class="badge badge-presente">Webhook Activo</span>
            <?php } else { ?>
                <span class="badge badge-justificado">Pendiente de URL</span>
            <?php } ?>
        </div>
        <p style="margin-top: 8px;">
            Cada vez que un docente guarda una inasistencia (<strong>Ausente</strong> o <strong>Justificado</strong>), el backend envía automáticamente una petición <code>HTTP POST</code> en formato <code>JSON</code> hacia esta URL de Zapier (<em>Webhooks by Zapier → Catch Hook</em>).
        </p>

        <?php if ($mensajeEstado !== '') { ?>
            <div class="alerta-msg <?php echo $tipoAlerta === 'exito' ? 'alerta-exito' : 'alerta-error'; ?>">
                <?php echo htmlspecialchars($mensajeEstado); ?>
            </div>
        <?php } ?>

        <form method="POST" action="">
            <div>
                <label for="webhook_url">URL del Webhook de Zapier (Catch Hook):</label>
                <input type="url" id="webhook_url" name="webhook_url"
                       value="<?php echo htmlspecialchars($configurado ? $urlActual : ''); ?>"
                       placeholder="https://hooks.zapier.com/hooks/catch/12345678/abcdefg/"
                       required>
                <small class="help-text">Copia la URL que te entrega Zapier al crear un Trigger de tipo "Webhooks by Zapier" → "Catch Hook".</small>
            </div>

            <div class="btns-row">
                <button type="submit" name="accion" value="guardar_url">Guardar URL del Webhook</button>
                <button type="submit" name="accion" value="probar_webhook" style="background: linear-gradient(135deg, var(--success) 0%, var(--success-hover) 100%);">
                    Enviar Prueba JSON a Zapier
                </button>
            </div>
        </form>

        <?php if ($resultadoTest !== null) { ?>
            <div style="margin-top: 26px;">
                <h3 style="text-align: left; margin-bottom: 6px;">Respuesta de Zapier (HTTP <?php echo (int) $resultadoTest['http_code']; ?>):</h3>
                <div class="code-preview"><?php echo htmlspecialchars($resultadoTest['response'] ?: ($resultadoTest['error'] ?: 'Sin cuerpo de respuesta')); ?></div>

                <h3 style="text-align: left; margin-top: 18px; margin-bottom: 6px;">JSON Enviado en el POST:</h3>
                <div class="code-preview"><?php echo htmlspecialchars(json_encode($resultadoTest['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></div>
            </div>
        <?php } else { ?>
            <div style="margin-top: 28px;">
                <h3 style="text-align: left; margin-bottom: 6px;">Estructura JSON que envía el sistema a Zapier:</h3>
                <div class="code-preview">{
    "evento": "inasistencia_registrada",
    "origen": "Sistema de Asistencias Escolares (SIE)",
    "timestamp_envio": "<?php echo date('c'); ?>",
    "fecha_clase": "<?php echo date('Y-m-d'); ?>",
    "estado_asistencia": "Ausente",
    "motivo": "Sin justificar",
    "id_estudiante": 19,
    "estudiante_nombre": "Santiago",
    "estudiante_apellido": "Rivas",
    "estudiante_completo": "Santiago Rivas",
    "estudiante_cedula": "31904512",
    "estudiante_nacimiento": "2011-06-18",
    "estudiante_sexo": "Masculino",
    "representante_nombre": "Orlexy Materano",
    "representante_telefono": "584124710360",
    "id_seccion": 8,
    "seccion": "1A",
    "id_materia": 13,
    "materia": "Informatica",
    "institucion": "Sede Principal",
    "id_docente": 4,
    "docente": "Jose Rodriguez",
    "mensaje_notificacion": "Estimado/a Orlexy Materano, le informamos que el estudiante Santiago Rivas (Sección 1A) no asistió..."
}</div>
            </div>
        <?php } ?>
    </div>

</body>
</html>
