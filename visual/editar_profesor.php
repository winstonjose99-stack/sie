<?php
require_once __DIR__ . '/../config/conexion.php';
verificarSesionAdmin('iniciar_sesion.html');

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: profesores.php");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM docentes WHERE id_docente = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$resultado = $stmt->get_result();
$row = $resultado->fetch_assoc();
$stmt->close();

if (!$row) {
    alertaYRedirigir('Profesor no encontrado.', 'profesores.php');
}

$result_inst = $conn->query("SELECT id_institucion, nombre FROM instituciones ORDER BY nombre");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Profesor</title>
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
                <li><a href="profesores.php">Volver al Listado</a></li>
                <li><a href="../api/logout.php">Cerrar Sesión</a></li>
            </ul>
        </nav>
    </header>

    <main style="margin-top: 120px; padding: 20px; text-align: center;">
        <h2>Editar Datos del Docente</h2>

        <form action="../api/actualizar.php" method="POST">
            <input type="hidden" name="tabla" value="docentes">
            <input type="hidden" name="id" value="<?php echo (int) $row['id_docente']; ?>">

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
                <label for="telefono">Teléfono:</label>
                <input type="tel" id="telefono" name="telefono" value="<?php echo htmlspecialchars($row['telefono']); ?>" required>
            </div>
            <br>

            <div>
                <label for="correo">Correo:</label>
                <input type="email" id="correo" name="correo" value="<?php echo htmlspecialchars($row['correo']); ?>" required>
            </div>
            <br>

            <div>
                <label for="id_institucion">Institución:</label>
                <select id="id_institucion" name="id_institucion" required>
                    <option value="">Seleccione una institución</option>
                    <?php
                    if ($result_inst) {
                        while ($inst = $result_inst->fetch_assoc()) {
                            $selected = ((int) $inst['id_institucion'] === (int) $row['id_institucion']) ? 'selected' : '';
                            echo "<option value='" . (int) $inst['id_institucion'] . "' $selected>" . htmlspecialchars($inst['nombre']) . "</option>";
                        }
                    }
                    ?>
                </select>
            </div>
            <br>

            <button type="submit">Guardar Cambios</button>
        </form>
    </main>

</body>
</html>
<?php $conn->close(); ?>
