<?php
require_once __DIR__ . '/../config/conexion.php';
iniciarSesionSegura();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $usuario    = trim($_POST['usuario'] ?? '');
    $contrasena = $_POST['contrasena'] ?? '';

    if ($usuario === '' || $contrasena === '') {
        alertaYRedirigir('Debe ingresar usuario y contraseña.', '../visual/iniciar_sesion.html');
    }

    $stmt = $conn->prepare("SELECT id, contrasena FROM registro WHERE usuario = ? LIMIT 1");
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $stmt->store_result();

    $autenticado = false;
    if ($stmt->num_rows > 0) {
        $id = null;
        $hashed_password = null;
        $stmt->bind_result($id, $hashed_password);
        $stmt->fetch();

        if ($hashed_password !== null && password_verify($contrasena, $hashed_password)) {
            $autenticado = true;
            @session_regenerate_id(true);
            $_SESSION['id'] = (int) $id;
            $_SESSION['usuario'] = $usuario;
            sincronizarCookieSesion();
        }
    }
    $stmt->close();

    if ($autenticado) {
        header("Location: dashboard.php");
        exit();
    }

    alertaYRedirigir('Usuario o contraseña incorrectos.', '../visual/iniciar_sesion.html');
}

header("Location: ../visual/iniciar_sesion.html");
exit();
?>
