<?php
require_once __DIR__ . '/../config/conexion.php';
verificarSesionAdmin('iniciar_sesion.html');

$instituciones = $conn->query("SELECT id_institucion, nombre FROM instituciones ORDER BY nombre");
$docentes      = $conn->query("SELECT id_docente, nombre, apellido FROM docentes ORDER BY apellido, nombre");
$materias      = $conn->query("SELECT id_materia, nombre_materia FROM materias ORDER BY nombre_materia");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar Sección</title>
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
                <li><a href="../api/logout.php">Cerrar Sesión</a></li>
            </ul>
        </nav>
    </header>

    <main style="margin-top: 120px; text-align: center;">
        <h2>Registrar Nueva Sección</h2>

        <form action="../api/registrar_seccion.php" method="POST">
            <div>
                <label for="nombre_seccion">Nombre de la Sección (Ej: Sección A, 1er Año):</label>
                <input type="text" id="nombre_seccion" name="nombre_seccion" required placeholder="Ej. Sección 1-A">
            </div>

            <div>
                <label for="id_institucion">Institución:</label>
                <select id="id_institucion" name="id_institucion" required>
                    <option value="">Seleccione una institución</option>
                    <?php
                    if ($instituciones && $instituciones->num_rows > 0) {
                        while ($inst = $instituciones->fetch_assoc()) {
                    ?>
                        <option value="<?php echo (int) $inst['id_institucion']; ?>">
                            <?php echo htmlspecialchars($inst['nombre']); ?>
                        </option>
                    <?php
                        }
                    } else {
                        echo "<option value='' disabled>No hay instituciones registradas</option>";
                    }
                    ?>
                </select>
            </div>

            <div>
                <label for="id_docente">Docente Asignado:</label>
                <select id="id_docente" name="id_docente" required>
                    <option value="">Seleccione un docente</option>
                    <?php
                    if ($docentes && $docentes->num_rows > 0) {
                        while ($doc = $docentes->fetch_assoc()) {
                    ?>
                        <option value="<?php echo (int) $doc['id_docente']; ?>">
                            <?php echo htmlspecialchars(trim($doc['nombre'] . ' ' . $doc['apellido'])); ?>
                        </option>
                    <?php
                        }
                    } else {
                        echo "<option value='' disabled>No hay docentes registrados</option>";
                    }
                    ?>
                </select>
            </div>

            <div>
                <label for="id_materia">Materia Asignada:</label>
                <select id="id_materia" name="id_materia" required>
                    <option value="">Seleccione una materia</option>
                    <?php
                    if ($materias && $materias->num_rows > 0) {
                        while ($mat = $materias->fetch_assoc()) {
                    ?>
                        <option value="<?php echo (int) $mat['id_materia']; ?>">
                            <?php echo htmlspecialchars($mat['nombre_materia']); ?>
                        </option>
                    <?php
                        }
                    } else {
                        echo "<option value='' disabled>No hay materias registradas</option>";
                    }
                    ?>
                </select>
            </div>

            <button type="submit" class="btn-guardar">Guardar Sección</button>
        </form>
    </main>

</body>
</html>
<?php $conn->close(); ?>
