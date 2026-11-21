<?php
require_once __DIR__ . '/../config/conexion.php';
verificarSesionAdmin('iniciar_sesion.html');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="../css/estilos.css">
    <meta charset="UTF-8">
    <title>Registrar Representante</title>
</head>
<body>
    <header class="header-fijo">
        <div class="logo">
            <img src="logo.png" alt="Logo del Sistema">
        </div>
        <nav>
            <ul>
                <li><a href="../api/dashboard.php">Inicio</a></li>
                <li><a href="../api/logout.php">Cerrar Sesión</a></li>
            </ul>
        </nav>
    </header>

    <main style="margin-top: 120px; text-align: center;">
        <h2>Registrar Nuevo Representante</h2>

        <form action="../api/registrar_representante.php" method="POST">
            <div>
                <label for="nombre">Nombre:</label>
                <input type="text" id="nombre" name="nombre" required>
            </div>

            <div>
                <label for="apellido">Apellido:</label>
                <input type="text" id="apellido" name="apellido" required>
            </div>

            <div>
                <label for="cedula">Cédula:</label>
                <input type="text" id="cedula" name="cedula" required>
            </div>

            <div>
                <label for="telefono">Teléfono (WhatsApp):</label>
                <input type="tel" id="telefono" name="telefono" placeholder="Ej: 584121234567" pattern="[0-9]{10,15}" required>
                <small class="help-text">Incluye el código de país sin el símbolo '+' (Ej. 58 para Venezuela seguido del número).</small>
            </div>

            <button type="submit">Registrar Representante</button>
        </form>
    </main>
</body>
</html>
<?php $conn->close(); ?>
