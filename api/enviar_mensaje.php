<?php
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/whatsapp.php';
verificarSesionAdmin('../visual/iniciar_sesion.html');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Método no permitido. Use POST con los campos telefono y mensaje.';
    exit();
}

$telefono = trim($_POST['telefono'] ?? '');
$mensaje  = trim($_POST['mensaje'] ?? '');

if ($telefono === '' || $mensaje === '') {
    http_response_code(400);
    echo 'Debe proporcionar teléfono y mensaje.';
    exit();
}

$resultado = enviarNotificacionWhatsApp($telefono, $mensaje);
header('Content-Type: application/json; charset=utf-8');
echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
?>
