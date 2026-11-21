<?php
require_once __DIR__ . '/../config/conexion.php';
verificarSesionAdmin('iniciar_sesion.html');

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: materias.php");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM materias WHERE id_materia = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$resultado = $stmt->get_result();
$row = $resultado->fetch_assoc();
$stmt->close();

if (!$row) {
    alertaYRedirigir('Materia no encontrada.', 'materias.php');
}

$result_doc = $conn->query("SELECT id_docente, nombre, apellido FROM docentes ORDER BY apellido, nombre");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Materia</title>
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
                <li><a href="materias.php">Volver al Listado</a></li>
                <li><a href="../api/logout.php">Cerrar Sesión</a></li>
            </ul>
        </nav>
    </header>

    <main style="margin-top: 120px; padding: 20px; text-align: center;">
        <h2>Editar Materia</h2>

        <form action="../api/actualizar.php" method="POST">
            <input type="hidden" name="tabla" value="materias">
            <input type="hidden" name="id" value="<?php echo (int) $row['id_materia']; ?>">

            <div>
                <label for="nombre_materia">Nombre de la Materia:</label>
                <input type="text" id="nombre_materia" name="nombre_materia" value="<?php echo htmlspecialchars($row['nombre_materia']); ?>" required>
            </div>
            <br>

            <div>
                <label for="id_docente">Docente Asignado:</label>
                <select id="id_docente" name="id_docente" required>
                    <option value="">Seleccione un docente</option>
                    <?php
                    if ($result_doc) {
                        while ($doc = $result_doc->fetch_assoc()) {
                            $selected = ((int) $doc['id_docente'] === (int) $row['id_docente']) ? 'selected' : '';
                            $nombre_completo = htmlspecialchars(trim($doc['nombre'] . ' ' . $doc['apellido']));
                            echo "<option value='" . (int) $doc['id_docente'] . "' $selected>" . $nombre_completo . "</option>";
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
