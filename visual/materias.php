<?php
require_once __DIR__ . '/../config/conexion.php';
verificarSesionAdmin('iniciar_sesion.html');

$query = "SELECT m.*, d.nombre AS nombre_docente, d.apellido AS apellido_docente
          FROM materias m
          LEFT JOIN docentes d ON m.id_docente = d.id_docente
          ORDER BY m.id_materia";
$resultado = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Materias</title>
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
        <h2>Listado de Materias</h2>

        <div class="acciones-container" style="margin: 14px 0 22px 0;">
            <a href="formulario_mate.php" class="btn-nuevo" style="margin: 0;">Registrar Nueva Materia</a>
            <a href="../api/generar_pdf.php?tipo=listado_general&modulo=materias" class="btn-pdf" style="padding: 10px 18px; border-radius: 999px;">Descargar Lista PDF</a>
        </div>

        <table>
            <tr>
                <th>ID</th>
                <th>Nombre de la Materia</th>
                <th>Docente Asignado</th>
                <th>Acciones</th>
            </tr>
            <?php
            if ($resultado && $resultado->num_rows > 0) {
                while ($row = $resultado->fetch_assoc()) {
                    $docente_completo = (!empty($row['nombre_docente'])) ? trim($row['nombre_docente'] . ' ' . $row['apellido_docente']) : 'Sin asignar';
            ?>
            <tr>
                <td><?php echo (int) $row['id_materia']; ?></td>
                <td><?php echo htmlspecialchars($row['nombre_materia']); ?></td>
                <td><?php echo htmlspecialchars($docente_completo); ?></td>
                <td>
                    <div class="acciones-container">
                        <a href="editar_materia.php?id=<?php echo (int) $row['id_materia']; ?>" class="btn-editar">Editar</a>
                        <a href="../api/eliminar.php?tabla=materias&id=<?php echo (int) $row['id_materia']; ?>"
                           class="btn-eliminar"
                           onclick="return confirm('¿Estás seguro de que deseas eliminar esta materia?');">
                           Eliminar
                        </a>
                    </div>
                </td>
            </tr>
            <?php
                }
            } else {
                echo "<tr><td colspan='4'>No hay materias registradas.</td></tr>";
            }
            ?>
        </table>
    </main>

</body>
</html>
<?php $conn->close(); ?>
