<?php
require_once __DIR__ . '/../config/conexion.php';
verificarSesionAdmin('iniciar_sesion.html');

$id_periodo = obtenerPeriodoActivoId($conn);
if ($id_periodo !== null) {
    $stmt_sec = $conn->prepare("
        SELECT s.id_seccion, s.nombre_seccion, m.nombre_materia
        FROM secciones s
        LEFT JOIN materias m ON s.id_materia = m.id_materia
        WHERE s.id_periodo = ?
        ORDER BY s.nombre_seccion
    ");
    $stmt_sec->bind_param("i", $id_periodo);
    $stmt_sec->execute();
    $secciones = $stmt_sec->get_result();
} else {
    $secciones = $conn->query("
        SELECT s.id_seccion, s.nombre_seccion, m.nombre_materia
        FROM secciones s
        LEFT JOIN materias m ON s.id_materia = m.id_materia
        ORDER BY s.nombre_seccion
    ");
}

$representantes = $conn->query("SELECT id_representante, nombre, apellido FROM representantes ORDER BY apellido, nombre");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Estudiante</title>
    <link rel="stylesheet" href="../css/estilos.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
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
        <h2>Registrar Nuevo Estudiante</h2>

        <form action="../api/registrar_estudiante.php" method="POST">
            <div>
                <label for="nombre">Nombre:</label>
                <input type="text" id="nombre" name="nombre" required>
            </div>

            <div>
                <label for="apellido">Apellido:</label>
                <input type="text" id="apellido" name="apellido" required>
            </div>

            <div>
                <label for="cedula">Cédula o ID escolar:</label>
                <input type="text" id="cedula" name="cedula" required>
            </div>

            <div>
                <label for="fecha_nacimiento">Fecha de Nacimiento:</label>
                <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" required>
            </div>

            <div>
                <label for="sexo">Sexo:</label>
                <select id="sexo" name="sexo" required>
                    <option value="">Seleccione...</option>
                    <option value="Masculino">Masculino</option>
                    <option value="Femenino">Femenino</option>
                </select>
            </div>

            <div>
                <label for="enfermedad_condicion">¿Padece alguna enfermedad o condición médica?</label>
                <textarea id="enfermedad_condicion" name="enfermedad_condicion" rows="2" placeholder="Describa si padece alguna condición o escriba 'Ninguna'"></textarea>
            </div>

            <div>
                <label for="id_seccion">Sección:</label>
                <select id="id_seccion" name="id_seccion" required>
                    <option value="">Seleccione una sección</option>
                    <?php
                    if ($secciones && $secciones->num_rows > 0) {
                        while ($sec = $secciones->fetch_assoc()) {
                            $etiqueta = $sec['nombre_seccion'] . (!empty($sec['nombre_materia']) ? ' - ' . $sec['nombre_materia'] : '');
                    ?>
                        <option value="<?php echo (int) $sec['id_seccion']; ?>">
                            <?php echo htmlspecialchars($etiqueta); ?>
                        </option>
                    <?php
                        }
                    } else {
                        echo "<option value='' disabled>No hay secciones registradas en el período activo</option>";
                    }
                    ?>
                </select>
            </div>

            <div>
                <label for="id_representante">Representante:</label>
                <select id="id_representante" name="id_representante" required>
                    <option value="">Seleccione un representante</option>
                    <?php
                    if ($representantes && $representantes->num_rows > 0) {
                        while ($rep = $representantes->fetch_assoc()) {
                            $nombre_completo = trim($rep['nombre'] . ' ' . $rep['apellido']);
                    ?>
                        <option value="<?php echo (int) $rep['id_representante']; ?>">
                            <?php echo htmlspecialchars($nombre_completo); ?>
                        </option>
                    <?php
                        }
                    } else {
                        echo "<option value='' disabled>No hay representantes registrados</option>";
                    }
                    ?>
                </select>
            </div>
            <button type="submit">Registrar Estudiante</button>
        </form>
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#id_representante').select2({
                placeholder: "Escriba el nombre...",
                allowClear: true
            });
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>
