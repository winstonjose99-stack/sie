<?php
require_once __DIR__ . '/../config/conexion.php';
verificarSesionAdmin('../visual/iniciar_sesion.html');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../visual/formulario_doc.php");
    exit();
}

$nombre         = trim($_POST['nombre'] ?? '');
$apellido       = trim($_POST['apellido'] ?? '');
$cedula         = trim($_POST['cedula'] ?? '');
$correo         = trim($_POST['correo'] ?? '');
$telefono       = preg_replace('/[^0-9]/', '', $_POST['telefono'] ?? '');
$id_institucion = intval($_POST['id_institucion'] ?? 0);

if ($nombre === '' || $apellido === '' || $cedula === '' || $correo === '' || strlen($telefono) < 7 || $id_institucion <= 0) {
    alertaYRedirigir('Por favor complete todos los campos correctamente.');
}

$stmt = $conn->prepare("INSERT INTO docentes (nombre, apellido, cedula, correo, telefono, id_institucion) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("sssssi", $nombre, $apellido, $cedula, $correo, $telefono, $id_institucion);

if ($stmt->execute()) {
    $stmt->close();
    $conn->close();
    alertaYRedirigir('Docente registrado con éxito.', '../api/dashboard.php');
}

$stmt->close();
$conn->close();
alertaYRedirigir('Error al registrar el docente.');
?>
