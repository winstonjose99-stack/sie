<?php
require_once __DIR__ . '/../config/conexion.php';
verificarSesionAdmin('../visual/iniciar_sesion.html');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../visual/formulario_mate.php");
    exit();
}

$nombre_materia = trim($_POST['nombre_materia'] ?? '');
$id_docente     = intval($_POST['id_docente'] ?? 0);

if ($nombre_materia === '' || $id_docente <= 0) {
    alertaYRedirigir('Por favor complete todos los campos.');
}

$stmt = $conn->prepare("INSERT INTO materias (nombre_materia, id_docente) VALUES (?, ?)");
$stmt->bind_param("si", $nombre_materia, $id_docente);

if ($stmt->execute()) {
    $stmt->close();
    $conn->close();
    alertaYRedirigir('Materia registrada con éxito.', '../api/dashboard.php');
}

$stmt->close();
$conn->close();
alertaYRedirigir('Error al registrar la materia.');
?>
