<?php
/**
 * Integración Backend con Webhooks de Zapier (Catch Hook).
 * Envía los datos de las inasistencias mediante una petición HTTP POST en formato JSON.
 *
 * Puedes pegar tu URL de Zapier directamente en ZAPIER_WEBHOOK_URL_DEFAULT
 * o configurarla desde el panel web en api/webhook_zapier.php (se guarda en config/zapier.local.php).
 */

if (!defined('ZAPIER_WEBHOOK_URL_DEFAULT')) {
    define('ZAPIER_WEBHOOK_URL_DEFAULT', 'https://hooks.zapier.com/hooks/catch/TU_ID/TU_HOOK/');
}

if (!function_exists('obtenerZapierWebhookUrl')) {
    function obtenerZapierWebhookUrl(): string {
        $envUrl = getenv('ZAPIER_WEBHOOK_URL');
        if (!empty($envUrl)) {
            return trim((string) $envUrl);
        }

        $localFile = __DIR__ . '/zapier.local.php';
        if (file_exists($localFile)) {
            $cfg = require $localFile;
            if (is_array($cfg) && !empty($cfg['webhook_url'])) {
                return trim((string) $cfg['webhook_url']);
            }
        }

        return trim((string) ZAPIER_WEBHOOK_URL_DEFAULT);
    }
}

if (!function_exists('zapierWebhookEstaConfigurado')) {
    function zapierWebhookEstaConfigurado(?string $url = null): bool {
        $target = $url !== null ? trim($url) : obtenerZapierWebhookUrl();
        if ($target === '' || strpos($target, 'TU_ID/TU_HOOK') !== false) {
            return false;
        }
        return (bool) filter_var($target, FILTER_VALIDATE_URL);
    }
}

if (!function_exists('guardarZapierWebhookUrl')) {
    function guardarZapierWebhookUrl(string $webhookUrl): bool {
        $webhookUrl = trim($webhookUrl);
        $localFile  = __DIR__ . '/zapier.local.php';
        $contenido  = "<?php\n// Configuración local del Webhook de Zapier\nreturn [\n    'webhook_url' => " . var_export($webhookUrl, true) . ",\n];\n?>\n";
        return file_put_contents($localFile, $contenido) !== false;
    }
}

if (!function_exists('enviarInasistenciaAZapier')) {
    /**
     * Realiza una petición HTTP POST hacia la URL del Webhook de Zapier
     * enviando la información detallada de la inasistencia en formato JSON.
     *
     * @param array       $datosInasistencia Arreglo asociativo con los datos de la inasistencia.
     * @param string|null $webhookUrlOverride URL opcional para sobreescribir la configurada.
     * @return array{ok: bool, http_code: int, response: string, error: string, payload: array}
     */
    function enviarInasistenciaAZapier(array $datosInasistencia, ?string $webhookUrlOverride = null): array {
        $webhookUrl = $webhookUrlOverride !== null ? trim($webhookUrlOverride) : obtenerZapierWebhookUrl();

        // Estructurar el payload JSON con campos planos y anidados para facilitar el mapeo en Zapier
        $payload = [
            'evento'                 => $datosInasistencia['evento'] ?? 'inasistencia_registrada',
            'origen'                 => 'Sistema de Asistencias Escolares (SIE)',
            'timestamp_envio'        => date('c'),
            'fecha_clase'            => $datosInasistencia['fecha'] ?? date('Y-m-d'),
            'estado_asistencia'      => $datosInasistencia['estado'] ?? 'Ausente',
            'motivo'                 => $datosInasistencia['motivo'] ?? 'Sin justificar',
            'id_estudiante'          => (int) ($datosInasistencia['id_estudiante'] ?? 0),
            'estudiante_nombre'      => $datosInasistencia['estudiante_nombre'] ?? '',
            'estudiante_apellido'    => $datosInasistencia['estudiante_apellido'] ?? '',
            'estudiante_completo'    => $datosInasistencia['estudiante_completo'] ?? trim(($datosInasistencia['estudiante_nombre'] ?? '') . ' ' . ($datosInasistencia['estudiante_apellido'] ?? '')),
            'estudiante_cedula'      => $datosInasistencia['estudiante_cedula'] ?? '',
            'estudiante_nacimiento'  => $datosInasistencia['estudiante_nacimiento'] ?? '',
            'estudiante_sexo'        => $datosInasistencia['estudiante_sexo'] ?? '',
            'representante_nombre'   => $datosInasistencia['representante_nombre'] ?? 'Representante',
            'representante_telefono' => $datosInasistencia['representante_telefono'] ?? '',
            'id_seccion'             => (int) ($datosInasistencia['id_seccion'] ?? 0),
            'seccion'                => $datosInasistencia['seccion'] ?? '',
            'id_materia'             => (int) ($datosInasistencia['id_materia'] ?? 0),
            'materia'                => $datosInasistencia['materia'] ?? '',
            'institucion'            => $datosInasistencia['institucion'] ?? '',
            'id_docente'             => (int) ($datosInasistencia['id_docente'] ?? 0),
            'docente'                => $datosInasistencia['docente'] ?? '',
            'mensaje_notificacion'   => $datosInasistencia['mensaje'] ?? '',
        ];

        if (!zapierWebhookEstaConfigurado($webhookUrl)) {
            return [
                'ok'        => false,
                'http_code' => 0,
                'response'  => '',
                'error'     => 'URL de Webhook de Zapier no configurada. Configúrela en api/webhook_zapier.php o en config/zapier.php.',
                'payload'   => $payload,
            ];
        }

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $ch = curl_init($webhookUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $jsonPayload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json; charset=utf-8',
                'Accept: application/json',
                'User-Agent: SIE-Asistencias-Webhook/1.0',
            ],
        ]);

        $response = curl_exec($ch);
        $curlErrno = curl_errno($ch);
        $curlErr   = curl_error($ch);
        $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // En XAMPP sobre Windows es común que php.ini no tenga configurado curl.cainfo (error SSL 60/77).
        // Si ocurre ese error específico de certificado local, reintentamos desactivando la verificación de CA local.
        if ($response === false && in_array($curlErrno, [60, 77], true)) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response  = curl_exec($ch);
            $curlErrno = curl_errno($ch);
            $curlErr   = curl_error($ch);
            $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        }

        curl_close($ch);

        if ($response === false || $curlErr !== '') {
            return [
                'ok'        => false,
                'http_code' => $httpCode,
                'response'  => '',
                'error'     => $curlErr ?: 'Error de conexión cURL al enviar datos a Zapier.',
                'payload'   => $payload,
            ];
        }

        $ok = ($httpCode >= 200 && $httpCode < 300);

        return [
            'ok'        => $ok,
            'http_code' => $httpCode,
            'response'  => (string) $response,
            'error'     => $ok ? '' : "Zapier devolvió código HTTP {$httpCode}",
            'payload'   => $payload,
        ];
    }
}
?>
