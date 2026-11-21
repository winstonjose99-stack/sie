<?php
require_once __DIR__ . '/../config/conexion.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $usuario    = trim($_POST['usuario'] ?? '');
    $contrasena = $_POST['contrasena'] ?? '';
    $correo     = trim($_POST['correo'] ?? '');

    if ($usuario === '' || $contrasena === '' || $correo === '') {
        alertaYRedirigir('Todos los campos son obligatorios.', '../visual/registro.html');
    }

    if (strlen($contrasena) < 6) {
        alertaYRedirigir('La contraseña debe tener al menos 6 caracteres.', '../visual/registro.html');
    }

    $hashed_password = password_hash($contrasena, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO registro (usuario, contrasena, correo) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $usuario, $hashed_password, $correo);

    if ($stmt->execute()) {
        $stmt->close();
        alertaYRedirigir('Usuario registrado con éxito.', '../visual/iniciar_sesion.html');
    }

    $errno = $stmt->errno;
    $stmt->close();

    if ($errno === 1062) {
        alertaYRedirigir('El nombre de usuario ya está registrado.', '../visual/registro.html');
    }

    alertaYRedirigir('No se pudo completar el registro. Intente nuevamente.', '../visual/registro.html');
}

header("Location: ../visual/registro.html");
exit();
?>
