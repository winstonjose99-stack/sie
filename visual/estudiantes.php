<?php
require_once __DIR__ . '/../config/conexion.php';
verificarSesionAdmin('iniciar_sesion.html');

$query = "SELECT e.*, r.nombre AS nombre_representante, r.apellido AS apellido_representante, s.nombre_seccion
          FROM estudiantes e
          LEFT JOIN representantes r ON e.id_representante = r.id_representante
          LEFT JOIN secciones s ON e.id_seccion = s.id_seccion
          ORDER BY e.id_estudiante";
$resultado = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Estudiantes</title>
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
        <h2>Listado de Estudiantes</h2>

        <div class="acciones-container" style="margin: 14px 0 22px 0;">
            <a href="formulario_r_e.php" class="btn-nuevo" style="margin: 0;">Registrar Nuevo Estudiante</a>
            <a href="../api/generar_pdf.php?tipo=listado_general&modulo=estudiantes" class="btn-pdf" style="padding: 10px 18px; border-radius: 999px;">Descargar Lista PDF</a>
        </div>

        <table>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Apellido</th>
                <th>Cédula</th>
                <th>F. Nacimiento</th>
                <th>Sexo</th>
                <th>Sección</th>
                <th>Representante</th>
                <th>Acciones</th>
            </tr>
            <?php
            if ($resultado && $resultado->num_rows > 0) {
                while ($row = $resultado->fetch_assoc()) {
                    $representante_completo = (!empty($row['nombre_representante'])) ? trim($row['nombre_representante'] . ' ' . $row['apellido_representante']) : 'Sin asignar';
                    $fechaNac = (!empty($row['fecha_nacimiento']) && $row['fecha_nacimiento'] !== '0000-00-00') ? $row['fecha_nacimiento'] : 'N/A';
            ?>
            <tr>
                <td><?php echo (int) $row['id_estudiante']; ?></td>
                <td><?php echo htmlspecialchars($row['nombre']); ?></td>
                <td><?php echo htmlspecialchars($row['apellido']); ?></td>
                <td><?php echo htmlspecialchars($row['cedula']); ?></td>
                <td><?php echo htmlspecialchars($fechaNac); ?></td>
                <td><?php echo htmlspecialchars(!empty($row['sexo']) ? $row['sexo'] : 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($row['nombre_seccion'] ?? 'Sin asignar'); ?></td>
                <td><?php echo htmlspecialchars($representante_completo); ?></td>
                <td>
                    <div class="acciones-container">
                        <a href="editar_estudiante.php?id=<?php echo (int) $row['id_estudiante']; ?>" class="btn-editar">Editar</a>
                        <a href="../api/eliminar.php?tabla=estudiantes&id=<?php echo (int) $row['id_estudiante']; ?>"
                           class="btn-eliminar"
                           onclick="return confirm('¿Estás seguro de que deseas eliminar este estudiante?');">
                           Eliminar
                        </a>
                    </div>
                </td>
            </tr>
            <?php
                }
            } else {
                echo "<tr><td colspan='9'>No hay estudiantes registrados.</td></tr>";
            }
            ?>
        </table>
    </main>

</body>
</html>
<?php $conn->close(); ?>
