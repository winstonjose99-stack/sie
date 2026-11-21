<?php
require_once __DIR__ . '/../config/conexion.php';
verificarSesionAdmin('iniciar_sesion.html');

$resultado = $conn->query("SELECT * FROM representantes ORDER BY id_representante");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Representantes</title>
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
                <li><a href="gestionar.php">Volver a Gestión</a></li>
                <li><a href="../api/logout.php">Cerrar Sesión</a></li>
            </ul>
        </nav>
    </header>

    <main style="margin-top: 120px; text-align: center;">
        <h2>Listado de Representantes</h2>

        <div class="acciones-container" style="margin: 14px 0 22px 0;">
            <a href="formulario_repre.php" class="btn-nuevo" style="margin: 0;">Registrar Nuevo Representante</a>
            <a href="../api/generar_pdf.php?tipo=listado_general&modulo=representantes" class="btn-pdf" style="padding: 10px 18px; border-radius: 999px;">Descargar Lista PDF</a>
        </div>

        <table>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Apellido</th>
                <th>Cédula</th>
                <th>Teléfono</th>
                <th>Acciones</th>
            </tr>
            <?php
            if ($resultado && $resultado->num_rows > 0) {
                while ($row = $resultado->fetch_assoc()) {
            ?>
            <tr>
                <td><?php echo (int) $row['id_representante']; ?></td>
                <td><?php echo htmlspecialchars($row['nombre']); ?></td>
                <td><?php echo htmlspecialchars($row['apellido']); ?></td>
                <td><?php echo htmlspecialchars($row['cedula']); ?></td>
                <td><?php echo htmlspecialchars($row['telefono']); ?></td>
                <td>
                    <div class="acciones-container">
                        <a href="editar_representante.php?id=<?php echo (int) $row['id_representante']; ?>" class="btn-editar">Editar</a>
                        <a href="../api/eliminar.php?tabla=representantes&id=<?php echo (int) $row['id_representante']; ?>"
                           class="btn-eliminar"
                           onclick="return confirm('¿Estás seguro de que deseas eliminar este representante?');">
                           Eliminar
                        </a>
                    </div>
                </td>
            </tr>
            <?php
                }
            } else {
                echo "<tr><td colspan='6'>No hay representantes registrados.</td></tr>";
            }
            ?>
        </table>
    </main>

</body>
</html>
<?php $conn->close(); ?>
