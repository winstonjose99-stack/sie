<?php
require_once __DIR__ . '/../config/conexion.php';
verificarSesionAdmin('iniciar_sesion.html');

$query = "
    SELECT s.id_seccion, s.nombre_seccion,
           i.nombre AS nombre_institucion,
           d.nombre AS doc_nombre, d.apellido AS doc_apellido,
           m.nombre_materia,
           p.nombre_periodo
    FROM secciones s
    LEFT JOIN instituciones i ON s.id_institucion = i.id_institucion
    LEFT JOIN docentes d ON s.id_docente = d.id_docente
    LEFT JOIN materias m ON s.id_materia = m.id_materia
    LEFT JOIN periodos_academicos p ON s.id_periodo = p.id_periodo
    ORDER BY s.id_seccion
";
$resultado = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestionar Secciones</title>
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

    <div class="container">
        <h2>Listado de Secciones Registradas</h2>
        <div class="acciones-container" style="margin: 14px 0 22px 0;">
            <a href="formulario_seccion.php" class="btn-nuevo" style="margin: 0;">Registrar Nueva Sección</a>
            <a href="../api/generar_pdf.php?tipo=listado_general&modulo=secciones" class="btn-pdf" style="padding: 10px 18px; border-radius: 999px;">Descargar Lista PDF</a>
        </div>

        <table>
            <tr>
                <th>ID</th>
                <th>Nombre de la Sección</th>
                <th>Institución</th>
                <th>Docente Asignado</th>
                <th>Materia</th>
                <th>Período</th>
                <th>Acciones</th>
            </tr>
            <?php
            if ($resultado && $resultado->num_rows > 0) {
                while ($row = $resultado->fetch_assoc()) {
            ?>
            <tr>
                <td><?php echo (int) $row['id_seccion']; ?></td>
                <td><?php echo htmlspecialchars($row['nombre_seccion']); ?></td>
                <td><?php echo htmlspecialchars($row['nombre_institucion'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars(trim(($row['doc_nombre'] ?? '') . ' ' . ($row['doc_apellido'] ?? '')) ?: 'Sin asignar'); ?></td>
                <td><?php echo htmlspecialchars($row['nombre_materia'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($row['nombre_periodo'] ?? 'N/A'); ?></td>
                <td>
                    <div class="acciones-container">
                        <a href="editar_seccion.php?id=<?php echo (int) $row['id_seccion']; ?>" class="btn-editar">Editar</a>
                        <a href="../api/eliminar.php?tabla=secciones&id=<?php echo (int) $row['id_seccion']; ?>"
                           class="btn-eliminar"
                           onclick="return confirm('¿Estás seguro de eliminar esta sección?');">
                           Eliminar
                        </a>
                    </div>
                </td>
            </tr>
            <?php
                }
            } else {
                echo "<tr><td colspan='7'>No hay secciones registradas en el sistema.</td></tr>";
            }
            $conn->close();
            ?>
        </table>
    </div>

</body>
</html>
