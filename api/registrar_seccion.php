<?php
require_once __DIR__ . '/../config/conexion.php';
verificarSesionAdmin('../visual/iniciar_sesion.html');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../visual/formulario_seccion.php");
    exit();
}

$nombre_seccion = trim($_POST['nombre_seccion'] ?? '');
$id_institucion = intval($_POST['id_institucion'] ?? 0);
$id_docente     = intval($_POST['id_docente'] ?? 0);
$id_materia     = intval($_POST['id_materia'] ?? 0);

if ($nombre_seccion === '' || $id_institucion <= 0 || $id_docente <= 0 || $id_materia <= 0) {
    alertaYRedirigir('Por favor complete todos los campos.');
}

$id_periodo = obtenerPeriodoActivoId($conn);
if ($id_periodo === null) {
    alertaYRedirigir('No hay un período académico activo. Configure uno antes de registrar secciones.', '../visual/admin_periodos.php');
}

$stmt = $conn->prepare("INSERT INTO secciones (nombre_seccion, id_institucion, id_docente, id_materia, id_periodo) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("siiii", $nombre_seccion, $id_institucion, $id_docente, $id_materia, $id_periodo);

if ($stmt->execute()) {
    $stmt->close();
    $conn->close();
    alertaYRedirigir('Sección registrada exitosamente en el período activo.', '../api/dashboard.php');
}

$stmt->close();
$conn->close();
alertaYRedirigir('Error al registrar la sección.');
?>
