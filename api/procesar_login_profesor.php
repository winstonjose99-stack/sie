<?php
require_once __DIR__ . '/../config/conexion.php';
iniciarSesionSegura();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $cedula = trim($_POST['cedula'] ?? '');
    $correo = trim($_POST['correo'] ?? '');

    if ($cedula === '' || $correo === '') {
        alertaYRedirigir('Debe ingresar cédula y correo.', '../visual/login_profesor.php');
    }

    $stmt = $conn->prepare("SELECT id_docente, nombre, apellido FROM docentes WHERE cedula = ? AND correo = ? LIMIT 1");
    $stmt->bind_param("ss", $cedula, $correo);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($row = $resultado->fetch_assoc()) {
        session_regenerate_id(true);
        $_SESSION['id_docente']     = (int) $row['id_docente'];
        $_SESSION['nombre_docente'] = trim($row['nombre'] . ' ' . $row['apellido']);

        $stmt->close();
        header("Location: ../visual/panel_profesor.php");
        exit();
    }

    $stmt->close();
    alertaYRedirigir('Cédula o correo incorrectos.', '../visual/login_profesor.php');
}

header("Location: ../visual/login_profesor.php");
exit();
?>
