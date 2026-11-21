<?php
require_once __DIR__ . '/../config/conexion.php';
verificarSesionAdmin('../visual/iniciar_sesion.html');

$id_seccion = intval($_GET['id_seccion'] ?? 0);

$info_seccion = null;
$stmt_sec = $conn->prepare("
    SELECT s.nombre_seccion, m.nombre_materia
    FROM secciones s
    INNER JOIN materias m ON s.id_materia = m.id_materia
    WHERE s.id_seccion = ?
");
$stmt_sec->bind_param("i", $id_seccion);
$stmt_sec->execute();
$res_sec = $stmt_sec->get_result();
if ($res_sec && $res_sec->num_rows > 0) {
    $info_seccion = $res_sec->fetch_assoc();
}
$stmt_sec->close();

$stmt_est = $conn->prepare("SELECT * FROM estudiantes WHERE id_seccion = ? ORDER BY apellido, nombre");
$stmt_est->bind_param("i", $id_seccion);
$stmt_est->execute();
$estudiantes = $stmt_est->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Estudiantes de la Sección</title>
    <link rel="stylesheet" href="../css/estilos.css">
</head>
<body>

    <header class="header-fijo">
        <div class="logo"><img src="../visual/logo.png" alt="Logo"></div>
        <nav>
            <ul>
                <li><a href="../visual/admin_periodos.php">Volver al Panel</a></li>
                <li><a href="logout.php">Cerrar Sesión</a></li>
            </ul>
        </nav>
    </header>

    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 12px;">
            <a href="../visual/admin_periodos.php" class="btn-volver">← Volver a Secciones</a>
            <a href="generar_pdf.php?tipo=estudiantes_seccion&id_seccion=<?php echo (int) $id_seccion; ?>" class="btn-pdf" style="padding: 10px 18px; border-radius: 999px;">
                Descargar Nómina en PDF
            </a>
        </div>
        <h2>Estudiantes Inscritos</h2>
        <h3>Sección: <?php echo htmlspecialchars($info_seccion['nombre_seccion'] ?? 'Desconocida'); ?> - <?php echo htmlspecialchars($info_seccion['nombre_materia'] ?? ''); ?></h3>

        <table>
            <thead>
                <tr>
                    <th>Cédula</th>
                    <th>Nombre y Apellido</th>
                    <th>Fecha de Nacimiento</th>
                    <th>Sexo</th>
                    <th>Condición Médica</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($estudiantes && $estudiantes->num_rows > 0) {
                    while ($est = $estudiantes->fetch_assoc()) { ?>
                    <tr>
                        <td><?php echo htmlspecialchars($est['cedula']); ?></td>
                        <td><?php echo htmlspecialchars($est['nombre'] . ' ' . $est['apellido']); ?></td>
                        <td><?php echo htmlspecialchars($est['fecha_nacimiento'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($est['sexo'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($est['enfermedad_condicion'] ?? 'Ninguna'); ?></td>
                    </tr>
                <?php }
                } else {
                    echo "<tr><td colspan='5'>No hay estudiantes registrados en esta sección.</td></tr>";
                }
                $stmt_est->close();
                ?>
            </tbody>
        </table>
    </div>

</body>
</html>
<?php $conn->close(); ?>
