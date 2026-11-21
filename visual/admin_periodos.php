<?php
require_once __DIR__ . '/../config/conexion.php';
verificarSesionAdmin('iniciar_sesion.html');

// 1. Obtener el período académico activo
$res_periodo = $conn->query("SELECT * FROM periodos_academicos WHERE activo = 1 LIMIT 1");
$periodo_activo = $res_periodo ? $res_periodo->fetch_assoc() : null;
$id_periodo_activo = (int) ($periodo_activo['id_periodo'] ?? 0);

// 2. Consultar las secciones del período activo
$query = "
    SELECT s.id_seccion, s.nombre_seccion, m.nombre_materia, i.nombre AS nombre_institucion,
           (SELECT COUNT(*) FROM estudiantes e WHERE e.id_seccion = s.id_seccion) AS total_estudiantes
    FROM secciones s
    INNER JOIN materias m ON s.id_materia = m.id_materia
    LEFT JOIN instituciones i ON s.id_institucion = i.id_institucion
    WHERE s.id_periodo = ?
    ORDER BY s.nombre_seccion
";

$stmt_secciones = $conn->prepare($query);
$stmt_secciones->bind_param("i", $id_periodo_activo);
$stmt_secciones->execute();
$resultado_secciones = $stmt_secciones->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Administración - Período Activo</title>
    <link rel="stylesheet" href="../css/estilos.css">
    <style>
        .info-box { text-align: center; margin-bottom: 24px; }
    </style>
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

    <div class="container">
        <h2>Panel de Control: Período Académico Actual</h2>

        <div class="info-box">
            <p>Período Escolar en Curso:</p>
            <span class="periodo-badge">
                <?php echo htmlspecialchars($periodo_activo['nombre_periodo'] ?? 'No hay período activo configurado'); ?>
            </span>
            <br><br>
            <a href="promover_periodo.php" style="background-color: #e67e22; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px; font-weight: bold; display: inline-block;">
                Iniciar Nuevo Período y Promover
            </a>
        </div>

        <h3>Secciones Registradas en este Período</h3>

        <table>
            <thead>
                <tr>
                    <th>Año y Sección</th>
                    <th>Materia</th>
                    <th>Institución</th>
                    <th>Estudiantes Inscritos</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($resultado_secciones && $resultado_secciones->num_rows > 0) {
                    while ($sec = $resultado_secciones->fetch_assoc()) {
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($sec['nombre_seccion']); ?></td>
                    <td><?php echo htmlspecialchars($sec['nombre_materia']); ?></td>
                    <td><?php echo htmlspecialchars($sec['nombre_institucion'] ?? 'N/A'); ?></td>
                    <td><strong><?php echo (int) $sec['total_estudiantes']; ?></strong> alumnos</td>
                    <td>
                        <div class="acciones-container">
                            <a href="../api/ver_estudiantes_seccion.php?id_seccion=<?php echo (int) $sec['id_seccion']; ?>" class="btn-ver">Ver Estudiantes</a>
                            <a href="../api/generar_pdf.php?tipo=estudiantes_seccion&id_seccion=<?php echo (int) $sec['id_seccion']; ?>" class="btn-pdf">PDF</a>
                        </div>
                    </td>
                </tr>
                <?php
                    }
                } else {
                    echo "<tr><td colspan='5'>No hay secciones configuradas para el período académico activo.</td></tr>";
                }
                $stmt_secciones->close();
                ?>
            </tbody>
        </table>
    </div>

</body>
</html>
<?php $conn->close(); ?>
