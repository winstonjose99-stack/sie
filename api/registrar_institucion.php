<?php
require_once __DIR__ . '/../config/conexion.php';
verificarSesionAdmin('../visual/iniciar_sesion.html');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../visual/formulario_inst.html");
    exit();
}

$nombre    = trim($_POST['nombre'] ?? '');
$direccion = trim($_POST['direccion'] ?? '');

if ($nombre === '' || $direccion === '') {
    alertaYRedirigir('Por favor complete todos los campos.');
}

$stmt = $conn->prepare("INSERT INTO instituciones (nombre, direccion) VALUES (?, ?)");
$stmt->bind_param("ss", $nombre, $direccion);

if ($stmt->execute()) {
    $stmt->close();
    $conn->close();
    alertaYRedirigir('Institución registrada con éxito.', '../api/dashboard.php');
}

$stmt->close();
$conn->close();
alertaYRedirigir('Error al registrar la institución.');
?>
