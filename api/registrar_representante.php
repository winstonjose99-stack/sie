<?php
require_once __DIR__ . '/../config/conexion.php';
verificarSesionAdmin('../visual/iniciar_sesion.html');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../visual/formulario_repre.php");
    exit();
}

$nombre       = trim($_POST['nombre'] ?? '');
$apellido     = trim($_POST['apellido'] ?? '');
$cedula       = trim($_POST['cedula'] ?? '');
$telefono_raw = $_POST['telefono'] ?? '';

// Limpieza del teléfono para dejarlo solo con números (formato internacional para WhatsApp)
$telefono = preg_replace('/[^0-9]/', '', $telefono_raw);

if ($nombre !== '' && $apellido !== '' && $cedula !== '' && strlen($telefono) >= 10) {
    $stmt = $conn->prepare("INSERT INTO representantes (nombre, apellido, cedula, telefono) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $nombre, $apellido, $cedula, $telefono);

    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        alertaYRedirigir('Representante registrado exitosamente.', '../visual/formulario_repre.php');
    }

    $stmt->close();
    $conn->close();
    alertaYRedirigir('Error al registrar el representante.');
}

$conn->close();
alertaYRedirigir('Por favor verifique los datos. El teléfono debe contener solo números con código de país (mínimo 10 dígitos).');
?>
