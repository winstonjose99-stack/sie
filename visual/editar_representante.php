<?php
require_once __DIR__ . '/../config/conexion.php';
verificarSesionAdmin('iniciar_sesion.html');

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: representantes.php");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM representantes WHERE id_representante = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$resultado = $stmt->get_result();
$row = $resultado->fetch_assoc();
$stmt->close();

if (!$row) {
    alertaYRedirigir('Representante no encontrado.', 'representantes.php');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Representante</title>
    <link rel="stylesheet" href="../css/estilos.css">
</head>
<body>

    <header class="header-fijo">
        <div class="logo">
            <img src="logo.png" alt="Logo">
        </div>
        <nav>
            <ul>
                <li><a href="../api/dashboard.php">Inicio</a></li>
                <li><a href="representantes.php">Volver al Listado</a></li>
                <li><a href="../api/logout.php">Cerrar Sesión</a></li>
            </ul>
        </nav>
    </header>

    <main style="margin-top: 120px; padding: 20px; text-align: center;">
        <h2>Editar Datos del Representante</h2>

        <form action="../api/actualizar.php" method="POST">
            <input type="hidden" name="tabla" value="representantes">
            <input type="hidden" name="id" value="<?php echo (int) $row['id_representante']; ?>">

            <div>
                <label for="nombre">Nombre:</label>
                <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($row['nombre']); ?>" required>
            </div>
            <br>

            <div>
                <label for="apellido">Apellido:</label>
                <input type="text" id="apellido" name="apellido" value="<?php echo htmlspecialchars($row['apellido']); ?>" required>
            </div>
            <br>

            <div>
                <label for="cedula">Cédula:</label>
                <input type="text" id="cedula" name="cedula" value="<?php echo htmlspecialchars($row['cedula']); ?>" required>
            </div>
            <br>

            <div>
                <label for="telefono">Teléfono (WhatsApp, ej. 584121234567):</label>
                <input type="tel" id="telefono" name="telefono" value="<?php echo htmlspecialchars($row['telefono']); ?>" pattern="[0-9]{10,15}" required>
            </div>
            <br>

            <button type="submit">Guardar Cambios</button>
        </form>
    </main>

</body>
</html>
<?php $conn->close(); ?>
