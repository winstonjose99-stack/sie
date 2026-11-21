<?php
require_once __DIR__ . '/../config/conexion.php';
verificarSesionAdmin('iniciar_sesion.html');

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: estudiantes.php");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM estudiantes WHERE id_estudiante = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$resultado = $stmt->get_result();
$row = $resultado->fetch_assoc();
$stmt->close();

if (!$row) {
    alertaYRedirigir('Estudiante no encontrado.', 'estudiantes.php');
}

$result_rep = $conn->query("SELECT id_representante, nombre, apellido FROM representantes ORDER BY apellido, nombre");
$result_sec = $conn->query("
    SELECT s.id_seccion, s.nombre_seccion, m.nombre_materia
    FROM secciones s
    LEFT JOIN materias m ON s.id_materia = m.id_materia
    ORDER BY s.nombre_seccion
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Estudiante</title>
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
                <li><a href="estudiantes.php">Volver al Listado</a></li>
                <li><a href="../api/logout.php">Cerrar Sesión</a></li>
            </ul>
        </nav>
    </header>

    <main style="margin-top: 120px; padding: 20px; text-align: center;">
        <h2>Editar Datos del Estudiante</h2>

        <form action="../api/actualizar.php" method="POST">
            <input type="hidden" name="tabla" value="estudiantes">
            <input type="hidden" name="id" value="<?php echo (int) $row['id_estudiante']; ?>">

            <div>
                <label for="nombre">Nombre:</label>
                <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($row['nombre']); ?>" required>
            </div>

            <div>
                <label for="apellido">Apellido:</label>
                <input type="text" id="apellido" name="apellido" value="<?php echo htmlspecialchars($row['apellido']); ?>" required>
            </div>

            <div>
                <label for="cedula">Cédula:</label>
                <input type="text" id="cedula" name="cedula" value="<?php echo htmlspecialchars($row['cedula']); ?>" required>
            </div>

            <div>
                <label for="fecha_nacimiento">Fecha de Nacimiento:</label>
                <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" value="<?php echo htmlspecialchars($row['fecha_nacimiento'] ?? ''); ?>">
            </div>

            <div>
                <label for="sexo">Sexo:</label>
                <select id="sexo" name="sexo">
                    <option value="">Seleccione...</option>
                    <option value="Masculino" <?php echo ($row['sexo'] === 'Masculino') ? 'selected' : ''; ?>>Masculino</option>
                    <option value="Femenino" <?php echo ($row['sexo'] === 'Femenino') ? 'selected' : ''; ?>>Femenino</option>
                </select>
            </div>

            <div>
                <label for="enfermedad_condicion">Enfermedad o condición médica:</label>
                <textarea id="enfermedad_condicion" name="enfermedad_condicion" rows="2"><?php echo htmlspecialchars($row['enfermedad_condicion'] ?? 'Ninguna'); ?></textarea>
            </div>

            <div>
                <label for="id_seccion">Sección:</label>
                <select id="id_seccion" name="id_seccion" required>
                    <option value="">Seleccione una sección</option>
                    <?php
                    if ($result_sec) {
                        while ($sec = $result_sec->fetch_assoc()) {
                            $selected = ((int) $sec['id_seccion'] === (int) $row['id_seccion']) ? 'selected' : '';
                            $label = $sec['nombre_seccion'] . (!empty($sec['nombre_materia']) ? ' - ' . $sec['nombre_materia'] : '');
                            echo "<option value='" . (int) $sec['id_seccion'] . "' $selected>" . htmlspecialchars($label) . "</option>";
                        }
                    }
                    ?>
                </select>
            </div>

            <div>
                <label for="id_representante">Representante:</label>
                <select id="id_representante" name="id_representante" required>
                    <option value="">Seleccione un representante</option>
                    <?php
                    if ($result_rep) {
                        while ($rep = $result_rep->fetch_assoc()) {
                            $selected = ((int) $rep['id_representante'] === (int) $row['id_representante']) ? 'selected' : '';
                            $nombre_completo = htmlspecialchars(trim($rep['nombre'] . ' ' . $rep['apellido']));
                            echo "<option value='" . (int) $rep['id_representante'] . "' $selected>" . $nombre_completo . "</option>";
                        }
                    }
                    ?>
                </select>
            </div>

            <button type="submit">Guardar Cambios</button>
        </form>
    </main>

</body>
</html>
<?php $conn->close(); ?>
