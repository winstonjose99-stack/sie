<?php
require_once __DIR__ . '/../config/conexion.php';
verificarSesionAdmin('iniciar_sesion.html');

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: secciones.php");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM secciones WHERE id_seccion = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$resultado = $stmt->get_result();
$row = $resultado->fetch_assoc();
$stmt->close();

if (!$row) {
    alertaYRedirigir('Sección no encontrada.', 'secciones.php');
}

$instituciones = $conn->query("SELECT id_institucion, nombre FROM instituciones ORDER BY nombre");
$docentes      = $conn->query("SELECT id_docente, nombre, apellido FROM docentes ORDER BY apellido, nombre");
$materias      = $conn->query("SELECT id_materia, nombre_materia FROM materias ORDER BY nombre_materia");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Sección</title>
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
                <li><a href="secciones.php">Volver al Listado</a></li>
                <li><a href="../api/logout.php">Cerrar Sesión</a></li>
            </ul>
        </nav>
    </header>

    <main style="margin-top: 120px; padding: 20px; text-align: center;">
        <h2>Editar Sección</h2>

        <form action="../api/actualizar.php" method="POST">
            <input type="hidden" name="tabla" value="secciones">
            <input type="hidden" name="id" value="<?php echo (int) $row['id_seccion']; ?>">

            <div>
                <label for="nombre_seccion">Nombre de la Sección:</label>
                <input type="text" id="nombre_seccion" name="nombre_seccion" value="<?php echo htmlspecialchars($row['nombre_seccion']); ?>" required>
            </div>
            <br>

            <div>
                <label for="id_institucion">Institución:</label>
                <select id="id_institucion" name="id_institucion" required>
                    <option value="">Seleccione una institución</option>
                    <?php
                    if ($instituciones) {
                        while ($inst = $instituciones->fetch_assoc()) {
                            $selected = ((int) $inst['id_institucion'] === (int) $row['id_institucion']) ? 'selected' : '';
                            echo "<option value='" . (int) $inst['id_institucion'] . "' $selected>" . htmlspecialchars($inst['nombre']) . "</option>";
                        }
                    }
                    ?>
                </select>
            </div>
            <br>

            <div>
                <label for="id_docente">Docente Asignado:</label>
                <select id="id_docente" name="id_docente" required>
                    <option value="">Seleccione un docente</option>
                    <?php
                    if ($docentes) {
                        while ($doc = $docentes->fetch_assoc()) {
                            $selected = ((int) $doc['id_docente'] === (int) $row['id_docente']) ? 'selected' : '';
                            echo "<option value='" . (int) $doc['id_docente'] . "' $selected>" . htmlspecialchars(trim($doc['nombre'] . ' ' . $doc['apellido'])) . "</option>";
                        }
                    }
                    ?>
                </select>
            </div>
            <br>

            <div>
                <label for="id_materia">Materia Asignada:</label>
                <select id="id_materia" name="id_materia" required>
                    <option value="">Seleccione una materia</option>
                    <?php
                    if ($materias) {
                        while ($mat = $materias->fetch_assoc()) {
                            $selected = ((int) $mat['id_materia'] === (int) $row['id_materia']) ? 'selected' : '';
                            echo "<option value='" . (int) $mat['id_materia'] . "' $selected>" . htmlspecialchars($mat['nombre_materia']) . "</option>";
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
