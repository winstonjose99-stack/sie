<?php
require_once __DIR__ . '/../config/conexion.php';
verificarSesionAdmin('iniciar_sesion.html');

$result_inst = $conn->query("SELECT id_institucion, nombre FROM instituciones ORDER BY nombre");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Docente</title>
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
        <h2>Registrar Nuevo Docente</h2>

        <form action="../api/registro_docente.php" method="POST">
            <div>
                <label for="nombre">Nombre:</label>
                <input type="text" id="nombre" name="nombre" required>
            </div>
            <br>

            <div>
                <label for="apellido">Apellido:</label>
                <input type="text" id="apellido" name="apellido" required>
            </div>
            <br>

            <div>
                <label for="cedula">Cédula:</label>
                <input type="text" id="cedula" name="cedula" required>
            </div>
            <br>

            <div>
                <label for="telefono">Teléfono:</label>
                <input type="tel" id="telefono" name="telefono" placeholder="Ej: 584121234567" required>
            </div>
            <br>

            <div>
                <label for="correo">Correo:</label>
                <input type="email" id="correo" name="correo" required>
            </div>
            <br>

            <div>
                <label for="id_institucion">Institución:</label>
                <select id="id_institucion" name="id_institucion" required>
                    <option value="">Seleccione una institución</option>
                    <?php
                    if ($result_inst && $result_inst->num_rows > 0) {
                        while ($row = $result_inst->fetch_assoc()) {
                            echo "<option value='" . (int) $row['id_institucion'] . "'>" . htmlspecialchars($row['nombre']) . "</option>";
                        }
                    } else {
                        echo "<option value=''>No hay instituciones registradas</option>";
                    }
                    ?>
                </select>
            </div>
            <br>

            <button type="submit">Registrar Docente</button>
        </form>
    </main>

</body>
</html>
<?php $conn->close(); ?>
