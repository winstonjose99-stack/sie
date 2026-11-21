<?php
/**
 * Configuración centralizada de WhatsApp Cloud API (Meta).
 * Las credenciales reales deben colocarse en config/whatsapp.local.php (excluido en .gitignore)
 * o mediante variables de entorno WHATSAPP_TOKEN y WHATSAPP_PHONE_ID.
 */

$whatsappConfig = [
    'token'        => getenv('WHATSAPP_TOKEN') ?: '',
    'phone_id'     => getenv('WHATSAPP_PHONE_ID') ?: '',
    'api_version'  => getenv('WHATSAPP_API_VERSION') ?: 'v25.0',
];

$localConfigPath = __DIR__ . '/whatsapp.local.php';
if (file_exists($localConfigPath)) {
    $localConfig = require $localConfigPath;
    if (is_array($localConfig)) {
        $whatsappConfig = array_merge($whatsappConfig, $localConfig);
    }
}

if (!function_exists('enviarNotificacionWhatsApp')) {
    /**
     * Envía un mensaje de texto mediante WhatsApp Cloud API.
     *
     * @return array{ok: bool, http_code: int, response: string, error: string}
     */
    function enviarNotificacionWhatsApp(string $telefonoDestino, string $mensajeTexto): array {
        global $whatsappConfig;

        $token      = trim((string) ($whatsappConfig['token'] ?? ''));
        $phoneId    = trim((string) ($whatsappConfig['phone_id'] ?? ''));
        $apiVersion = trim((string) ($whatsappConfig['api_version'] ?? 'v25.0'));

        $telefono = preg_replace('/[^0-9]/', '', $telefonoDestino);
        if (strlen($telefono) < 10) {
            return [
                'ok'        => false,
                'http_code' => 0,
                'response'  => '',
                'error'     => 'Número de teléfono inválido.',
            ];
        }

        if ($token === '' || $phoneId === '') {
            return [
                'ok'        => false,
                'http_code' => 0,
                'response'  => '',
                'error'     => 'Credenciales de WhatsApp no configuradas en config/whatsapp.local.php.',
            ];
        }

        $url = "https://graph.facebook.com/{$apiVersion}/{$phoneId}/messages";
        $payload = json_encode([
            'messaging_product' => 'whatsapp',
            'to'                => $telefono,
            'type'              => 'text',
            'text'              => ['body' => $mensajeTexto],
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $curlErr  = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $curlErr !== '') {
            return [
                'ok'        => false,
                'http_code' => $httpCode,
                'response'  => '',
                'error'     => $curlErr ?: 'Error de cURL al contactar WhatsApp API.',
            ];
        }

        $ok = ($httpCode >= 200 && $httpCode < 300);
        $errorMsg = '';
        if (!$ok) {
            $decoded = json_decode($response, true);
            $errorMsg = $decoded['error']['message'] ?? ("HTTP {$httpCode}");
        }

        return [
            'ok'        => $ok,
            'http_code' => $httpCode,
            'response'  => (string) $response,
            'error'     => $errorMsg,
        ];
    }
}
?>
