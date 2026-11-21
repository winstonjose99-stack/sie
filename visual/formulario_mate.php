<?php
require_once __DIR__ . '/../config/conexion.php';
verificarSesionAdmin('iniciar_sesion.html');

$result_doc = $conn->query("SELECT id_docente, nombre, apellido FROM docentes ORDER BY apellido, nombre");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Materia</title>
    <link rel="stylesheet" href="../css/estilos.css">
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

    <main style="margin-top: 120px; padding: 20px; text-align: center;">
        <h2>Registrar Nueva Materia</h2>

        <form action="../api/registrar_materia.php" method="POST">
            <div>
                <label for="nombre_materia">Nombre de la Materia:</label>
                <input type="text" id="nombre_materia" name="nombre_materia" required>
            </div>

            <br>

            <div>
                <label for="id_docente">Docente Asignado:</label>
                <select id="id_docente" name="id_docente" required>
                    <option value="">Seleccione un docente</option>
                    <?php
                    if ($result_doc && $result_doc->num_rows > 0) {
                        while ($row = $result_doc->fetch_assoc()) {
                            $nombre_completo = htmlspecialchars(trim($row['nombre'] . ' ' . $row['apellido']));
                            echo "<option value='" . (int) $row['id_docente'] . "'>" . $nombre_completo . "</option>";
                        }
                    } else {
                        echo "<option value=''>No hay docentes registrados</option>";
                    }
                    ?>
                </select>
            </div>

            <br>

            <button type="submit">Registrar Materia</button>
        </form>
    </main>

</body>
</html>
<?php $conn->close(); ?>
