<?php
require_once __DIR__ . '/../config/conexion.php';
verificarSesionAdmin('../visual/iniciar_sesion.html');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../visual/formulario_r_e.php");
    exit();
}

$nombre           = trim($_POST['nombre'] ?? '');
$apellido         = trim($_POST['apellido'] ?? '');
$cedula           = trim($_POST['cedula'] ?? '');
$id_seccion       = intval($_POST['id_seccion'] ?? 0);
$id_representante = intval($_POST['id_representante'] ?? 0);

$fecha_nacimiento     = (!empty($_POST['fecha_nacimiento']) && $_POST['fecha_nacimiento'] !== '0000-00-00') ? $_POST['fecha_nacimiento'] : null;
$sexo                 = !empty($_POST['sexo']) ? $_POST['sexo'] : null;
$enfermedad_condicion = !empty(trim($_POST['enfermedad_condicion'] ?? '')) ? trim($_POST['enfermedad_condicion']) : 'Ninguna';

if ($nombre === '' || $apellido === '' || $cedula === '' || $id_seccion <= 0 || $id_representante <= 0) {
    alertaYRedirigir('Por favor complete todos los campos obligatorios.');
}

$id_periodo = obtenerPeriodoActivoId($conn);

$sql = "INSERT INTO estudiantes (nombre, apellido, cedula, fecha_nacimiento, sexo, enfermedad_condicion, id_seccion, id_representante, id_periodo)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("ssssssiii", $nombre, $apellido, $cedula, $fecha_nacimiento, $sexo, $enfermedad_condicion, $id_seccion, $id_representante, $id_periodo);

    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        alertaYRedirigir('¡Estudiante registrado con éxito!', '../visual/formulario_r_e.php');
    }
    $stmt->close();
}

$conn->close();
alertaYRedirigir('Error al registrar el estudiante.');
?>
