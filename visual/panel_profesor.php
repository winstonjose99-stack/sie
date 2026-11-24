<?php
require_once __DIR__ . '/../config/conexion.php';
verificarSesionDocente('login_profesor.php');

$id_docente = (int) $_SESSION['id_docente'];

// 1. Consultar las secciones asignadas a este profesor en el período académico activo
$stmt = $conn->prepare("
    SELECT s.id_seccion, s.nombre_seccion, m.id_materia, m.nombre_materia, i.nombre AS nombre_institucion,
           (SELECT COUNT(*) FROM estudiantes e WHERE e.id_seccion = s.id_seccion) AS total_estudiantes
    FROM secciones s
    INNER JOIN materias m ON s.id_materia = m.id_materia
    LEFT JOIN instituciones i ON s.id_institucion = i.id_institucion
    INNER JOIN periodos_academicos p ON s.id_periodo = p.id_periodo
    WHERE s.id_docente = ? AND p.activo = 1
    ORDER BY s.nombre_seccion
");
$stmt->bind_param("i", $id_docente);
$stmt->execute();
$res_sec = $stmt->get_result();
$secciones_docente = [];
$total_alumnos_docente = 0;
if ($res_sec) {
    while ($row = $res_sec->fetch_assoc()) {
        $secciones_docente[] = $row;
        $total_alumnos_docente += (int) $row['total_estudiantes'];
    }
}
$stmt->close();

// 2. Consultar las asistencias guardadas por el profesor agrupadas por Fecha + Sección + Materia
$stmt_guardadas = $conn->prepare("
    SELECT i.fecha,
           s.id_seccion, s.nombre_seccion,
           m.id_materia, m.nombre_materia,
           (SELECT COUNT(*) FROM estudiantes e2 WHERE e2.id_seccion = s.id_seccion) AS total_matricula,
           SUM(CASE WHEN i.estado = 'Ausente' THEN 1 ELSE 0 END) AS total_ausentes,
           SUM(CASE WHEN i.estado = 'Justificado' THEN 1 ELSE 0 END) AS total_justificados
    FROM inasistencias i
    INNER JOIN estudiantes e ON i.id_estudiante = e.id_estudiante
    INNER JOIN secciones s ON e.id_seccion = s.id_seccion
    INNER JOIN materias m ON i.id_materia = m.id_materia
    WHERE i.id_docente = ? OR s.id_docente = ?
    GROUP BY i.fecha, s.id_seccion, s.nombre_seccion, m.id_materia, m.nombre_materia
    ORDER BY i.fecha DESC, s.nombre_seccion ASC
    LIMIT 30
");
$stmt_guardadas->bind_param("ii", $id_docente, $id_docente);
$stmt_guardadas->execute();
$res_guardadas = $stmt_guardadas->get_result();
$sesiones_guardadas = [];
$total_faltas_registradas = 0;
if ($res_guardadas) {
    while ($g = $res_guardadas->fetch_assoc()) {
        $sesiones_guardadas[] = $g;
        $total_faltas_registradas += (int) $g['total_ausentes'] + (int) $g['total_justificados'];
    }
}
$stmt_guardadas->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal del Docente - Control de Asistencias</title>
    <link rel="stylesheet" href="../css/estilos.css">
    <style>
        .secciones-movil-grid,
        .guardadas-movil-grid {
            display: none;
        }
        .section-head-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 14px;
            text-align: left;
        }
        .section-head-row h2 {
            margin: 0;
            text-align: left;
        }
        .section-head-row p {
            margin: 4px 0 0 0;
        }
        .btn-ver-pill {
            padding: 10px 18px;
            border-radius: 999px;
            white-space: nowrap;
        }
        @media (max-width: 768px) {
            .tabla-secciones-desktop,
            .tabla-guardadas-desktop {
                display: none;
            }
            .secciones-movil-grid,
            .guardadas-movil-grid {
                display: grid;
                grid-template-columns: 1fr;
                gap: 12px;
                margin-top: 12px;
            }
            .section-head-row {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }
            .btn-ver-pill {
                width: 100%;
                min-height: 44px;
                border-radius: 12px;
                justify-content: center;
            }
            .sec-movil-card,
            .guardada-movil-card {
                background: #ffffff;
                border: 1px solid #dbe4f0;
                border-radius: 16px;
                padding: 15px;
                text-align: left;
                box-shadow: 0 3px 12px rgba(15, 23, 42, 0.04);
            }
            .sec-movil-top,
            .guardada-movil-top {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 8px;
                margin-bottom: 8px;
                flex-wrap: wrap;
            }
            .sec-chip {
                display: inline-flex;
                align-items: center;
                padding: 4px 10px;
                border-radius: 999px;
                background: #eff6ff;
                color: #1d4ed8;
                border: 1px solid #bfdbfe;
                font-size: 0.76rem;
                font-weight: 700;
                letter-spacing: 0.02em;
            }
            .sec-count-chip {
                display: inline-flex;
                align-items: center;
                padding: 4px 10px;
                border-radius: 999px;
                background: #f1f5f9;
                color: #334155;
                font-size: 0.76rem;
                font-weight: 700;
            }
            .sec-movil-card h3 {
                margin: 0 0 4px 0;
                text-align: left;
                font-size: 1.06rem;
                font-weight: 800;
                color: #0f172a;
                line-height: 1.3;
                word-break: break-word;
            }
            .sec-movil-inst {
                margin: 0 0 13px 0;
                font-size: 0.83rem;
                color: #64748b;
                line-height: 1.35;
            }
            .sec-movil-btns {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 8px;
            }
            .sec-movil-btns a {
                text-align: center;
                padding: 10px 6px !important;
                min-height: 44px;
                border-radius: 11px !important;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 700;
                font-size: 0.82rem;
                line-height: 1.2;
            }
            .sec-movil-btns .btn-asistencia {
                grid-column: 1 / -1;
                min-height: 46px;
                font-size: 0.92rem;
                border-radius: 12px !important;
            }
            .guardada-fecha-chip {
                display: inline-flex;
                align-items: center;
                padding: 4px 10px;
                border-radius: 8px;
                background: #0f172a;
                color: #ffffff;
                font-size: 0.78rem;
                font-weight: 700;
                font-variant-numeric: tabular-nums;
            }
            .guardada-movil-titulo {
                margin: 0 0 10px 0;
                font-size: 0.98rem;
                font-weight: 700;
                color: #0f172a;
                line-height: 1.3;
            }
            .guardada-stats-strip {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 8px;
                margin-bottom: 12px;
            }
            .guardada-stat-box {
                border-radius: 10px;
                padding: 8px 6px;
                text-align: center;
            }
            .guardada-stat-box strong {
                display: block;
                font-size: 1.05rem;
                line-height: 1.15;
                font-variant-numeric: tabular-nums;
            }
            .guardada-stat-box span {
                display: block;
                font-size: 0.7rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.02em;
                margin-top: 2px;
            }
            .guardada-stat-box.p { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
            .guardada-stat-box.a { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
            .guardada-stat-box.j { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
            .guardada-movil-actions {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 8px;
            }
            .guardada-movil-actions a {
                min-height: 42px;
                border-radius: 10px;
                font-size: 0.82rem;
                font-weight: 700;
                padding: 8px 6px;
                justify-content: center;
            }
            .estado-vacio-movil {
                background: #f8fafc;
                border: 1px dashed #cbd5e1;
                border-radius: 14px;
                padding: 18px 14px;
                font-size: 0.88rem;
                color: #64748b;
                text-align: center;
                margin: 0;
            }
        }
    </style>
</head>
<body class="portal-docente">

    <header class="header-fijo" id="appHeader">
        <div class="header-brand">
            <a href="panel_profesor.php" class="logo" aria-label="Inicio Portal Docente">
                <img src="logo.png" alt="Logo">
            </a>
            <h2 class="header-title">Portal Docente</h2>
        </div>

        <div class="header-mobile-controls">
            <button type="button" class="nav-toggle" id="navToggle" aria-label="Abrir menú de navegación" aria-expanded="false" aria-controls="headerNav">
                <span class="nav-toggle-bar"></span>
                <span class="nav-toggle-bar"></span>
                <span class="nav-toggle-bar"></span>
            </button>
        </div>

        <nav class="header-nav" id="headerNav" aria-label="Menú principal docente">
            <ul>
                <li><span><?php echo htmlspecialchars($_SESSION['nombre_docente'] ?? 'Profesor'); ?></span></li>
                <li><a href="asistencias_guardadas.php">Asistencias Guardadas</a></li>
                <li><a href="historial_inasistencias.php">Historial General</a></li>
                <li><a href="../api/webhook_zapier.php">Webhook Zapier</a></li>
                <li><a href="../api/logout.php">Cerrar Sesión</a></li>
            </ul>
        </nav>
    </header>
    <div class="nav-backdrop" id="navBackdrop" aria-hidden="true"></div>

    <div class="dashboard-container">
        <div class="welcome-card docente-hero">
            <span class="docente-hero-eyebrow">Período Activo</span>
            <h2>Hola, <?php echo htmlspecialchars($_SESSION['nombre_docente'] ?? 'Profesor'); ?></h2>
            <p>Gestione la asistencia diaria de sus secciones asignadas, registre calificaciones y consulte sus listas guardadas.</p>
        </div>

        <!-- KPIs del Docente -->
        <div class="stats-grid docente-kpis">
            <div class="stat-card">
                <div>
                    <p class="stat-value"><?php echo count($secciones_docente); ?></p>
                    <p class="stat-label">Secciones Activas</p>
                </div>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-value"><?php echo $total_alumnos_docente; ?></p>
                    <p class="stat-label">Estudiantes a Cargo</p>
                </div>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-value"><?php echo count($sesiones_guardadas); ?></p>
                    <p class="stat-label">Clases Guardadas</p>
                </div>
            </div>
            <div class="stat-card">
                <div>
                    <p class="stat-value"><?php echo $total_faltas_registradas; ?></p>
                    <p class="stat-label">Inasistencias Registradas</p>
                </div>
            </div>
        </div>

        <!-- Apartado 1: Secciones Asignadas -->
        <div class="container" style="margin-bottom: 28px;">
            <h2>Mis Secciones Asignadas (Período Activo)</h2>
            <p>Seleccione una sección para pasar lista hoy o consultar sus asistencias guardadas.</p>

            <div class="tabla-secciones-desktop table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Sección</th>
                            <th>Materia</th>
                            <th>Institución</th>
                            <th>Matrícula</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (!empty($secciones_docente)) {
                            foreach ($secciones_docente as $sec) {
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($sec['nombre_seccion']); ?></strong></td>
                            <td><?php echo htmlspecialchars($sec['nombre_materia']); ?></td>
                            <td><?php echo htmlspecialchars($sec['nombre_institucion'] ?? 'N/A'); ?></td>
                            <td><?php echo (int) $sec['total_estudiantes']; ?> alumnos</td>
                            <td>
                                <div class="acciones-container">
                                    <a href="registrar_inasistencia.php?id_seccion=<?php echo (int) $sec['id_seccion']; ?>&id_materia=<?php echo (int) $sec['id_materia']; ?>" class="btn-asistencia">
                                        Tomar Asistencia
                                    </a>
                                    <a href="registrar_notas.php?id_seccion=<?php echo (int) $sec['id_seccion']; ?>&id_materia=<?php echo (int) $sec['id_materia']; ?>" class="btn-editar">
                                        Gestionar Notas (100%)
                                    </a>
                                    <a href="asistencias_guardadas.php?id_seccion=<?php echo (int) $sec['id_seccion']; ?>&id_materia=<?php echo (int) $sec['id_materia']; ?>" class="btn-ver">
                                        Asistencias Guardadas
                                    </a>
                                    <a href="../api/generar_pdf.php?tipo=estudiantes_seccion&id_seccion=<?php echo (int) $sec['id_seccion']; ?>" class="btn-pdf">
                                        Nómina PDF
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php
                            }
                        } else {
                            echo "<tr><td colspan='5'>No tienes secciones asignadas para el período académico actual.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <!-- Tarjetas táctiles para teléfono en el Portal Docente -->
            <div class="secciones-movil-grid">
                <?php if (!empty($secciones_docente)) {
                    foreach ($secciones_docente as $sec) { ?>
                    <div class="sec-movil-card">
                        <div class="sec-movil-top">
                            <span class="sec-chip">Sección <?php echo htmlspecialchars($sec['nombre_seccion']); ?></span>
                            <span class="sec-count-chip"><?php echo (int) $sec['total_estudiantes']; ?> alumnos</span>
                        </div>
                        <h3><?php echo htmlspecialchars($sec['nombre_materia']); ?></h3>
                        <p class="sec-movil-inst"><?php echo htmlspecialchars($sec['nombre_institucion'] ?? 'N/A'); ?></p>
                        <div class="sec-movil-btns">
                            <a href="registrar_inasistencia.php?id_seccion=<?php echo (int) $sec['id_seccion']; ?>&id_materia=<?php echo (int) $sec['id_materia']; ?>" class="btn-asistencia">
                                Pasar Lista Ahora
                            </a>
                            <a href="registrar_notas.php?id_seccion=<?php echo (int) $sec['id_seccion']; ?>&id_materia=<?php echo (int) $sec['id_materia']; ?>" class="btn-editar">
                                Notas (100%)
                            </a>
                            <a href="asistencias_guardadas.php?id_seccion=<?php echo (int) $sec['id_seccion']; ?>&id_materia=<?php echo (int) $sec['id_materia']; ?>" class="btn-ver">
                                Guardadas
                            </a>
                            <a href="../api/generar_pdf.php?tipo=estudiantes_seccion&id_seccion=<?php echo (int) $sec['id_seccion']; ?>" class="btn-pdf">
                                Nómina PDF
                            </a>
                        </div>
                    </div>
                <?php }
                } else { ?>
                    <p class="estado-vacio-movil">No tienes secciones asignadas para el período académico actual.</p>
                <?php } ?>
            </div>
        </div>

        <!-- Apartado 2: Asistencias Guardadas por Clase/Fecha -->
        <div class="container" style="margin-top: 0;">
            <div class="section-head-row">
                <div>
                    <h2>Mis Asistencias Guardadas</h2>
                    <p>Resumen de las listas de asistencia tomadas por fecha y sección.</p>
                </div>
                <a href="asistencias_guardadas.php" class="btn-ver btn-ver-pill">
                    Ver Nómina Detallada
                </a>
            </div>

            <div class="tabla-guardadas-desktop table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha de Clase</th>
                            <th>Sección</th>
                            <th>Materia</th>
                            <th>Presentes</th>
                            <th>Ausentes</th>
                            <th>Justificados</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (!empty($sesiones_guardadas)) {
                            foreach ($sesiones_guardadas as $sg) {
                                $mat  = (int) $sg['total_matricula'];
                                $aus  = (int) $sg['total_ausentes'];
                                $jus  = (int) $sg['total_justificados'];
                                $pres = max(0, $mat - $aus - $jus);
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($sg['fecha']); ?></strong></td>
                            <td><?php echo htmlspecialchars($sg['nombre_seccion']); ?></td>
                            <td><?php echo htmlspecialchars($sg['nombre_materia']); ?></td>
                            <td><span class="badge badge-presente"><?php echo $pres; ?> Presente(s)</span></td>
                            <td><span class="badge badge-ausente"><?php echo $aus; ?> Ausente(s)</span></td>
                            <td><span class="badge badge-justificado"><?php echo $jus; ?> Justificado(s)</span></td>
                            <td>
                                <div class="acciones-container">
                                    <a href="asistencias_guardadas.php?id_seccion=<?php echo (int) $sg['id_seccion']; ?>&id_materia=<?php echo (int) $sg['id_materia']; ?>&fecha=<?php echo urlencode($sg['fecha']); ?>" class="btn-ver">
                                        Ver Lista
                                    </a>
                                    <a href="registrar_inasistencia.php?id_seccion=<?php echo (int) $sg['id_seccion']; ?>&id_materia=<?php echo (int) $sg['id_materia']; ?>&fecha=<?php echo urlencode($sg['fecha']); ?>" class="btn-editar">
                                        Editar
                                    </a>
                                    <a href="../api/generar_pdf.php?tipo=asistencia_clase&id_seccion=<?php echo (int) $sg['id_seccion']; ?>&id_materia=<?php echo (int) $sg['id_materia']; ?>&fecha=<?php echo urlencode($sg['fecha']); ?>" class="btn-pdf">
                                        PDF
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php
                            }
                        } else {
                            echo "<tr><td colspan='7'>Aún no hay listas de asistencia guardadas. Seleccione una sección arriba y pulse «Tomar Asistencia».</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <!-- Tarjetas móviles de asistencias guardadas -->
            <div class="guardadas-movil-grid">
                <?php if (!empty($sesiones_guardadas)) {
                    foreach ($sesiones_guardadas as $sg) {
                        $mat  = (int) $sg['total_matricula'];
                        $aus  = (int) $sg['total_ausentes'];
                        $jus  = (int) $sg['total_justificados'];
                        $pres = max(0, $mat - $aus - $jus);
                ?>
                    <div class="guardada-movil-card">
                        <div class="guardada-movil-top">
                            <span class="guardada-fecha-chip"><?php echo htmlspecialchars($sg['fecha']); ?></span>
                            <span class="sec-chip">Sección <?php echo htmlspecialchars($sg['nombre_seccion']); ?></span>
                        </div>
                        <p class="guardada-movil-titulo"><?php echo htmlspecialchars($sg['nombre_materia']); ?></p>
                        <div class="guardada-stats-strip">
                            <div class="guardada-stat-box p">
                                <strong><?php echo $pres; ?></strong>
                                <span>Presentes</span>
                            </div>
                            <div class="guardada-stat-box a">
                                <strong><?php echo $aus; ?></strong>
                                <span>Ausentes</span>
                            </div>
                            <div class="guardada-stat-box j">
                                <strong><?php echo $jus; ?></strong>
                                <span>Justif.</span>
                            </div>
                        </div>
                        <div class="guardada-movil-actions">
                            <a href="asistencias_guardadas.php?id_seccion=<?php echo (int) $sg['id_seccion']; ?>&id_materia=<?php echo (int) $sg['id_materia']; ?>&fecha=<?php echo urlencode($sg['fecha']); ?>" class="btn-ver">
                                Ver Lista
                            </a>
                            <a href="registrar_inasistencia.php?id_seccion=<?php echo (int) $sg['id_seccion']; ?>&id_materia=<?php echo (int) $sg['id_materia']; ?>&fecha=<?php echo urlencode($sg['fecha']); ?>" class="btn-editar">
                                Editar
                            </a>
                            <a href="../api/generar_pdf.php?tipo=asistencia_clase&id_seccion=<?php echo (int) $sg['id_seccion']; ?>&id_materia=<?php echo (int) $sg['id_materia']; ?>&fecha=<?php echo urlencode($sg['fecha']); ?>" class="btn-pdf">
                                PDF
                            </a>
                        </div>
                    </div>
                <?php }
                } else { ?>
                    <p class="estado-vacio-movil">Aún no hay listas de asistencia guardadas. Seleccione una sección arriba y pulse «Pasar Lista Ahora».</p>
                <?php } ?>
            </div>
        </div>
    </div>

    <script>
        (function() {
            var toggle = document.getElementById('navToggle');
            var nav = document.getElementById('headerNav');
            var backdrop = document.getElementById('navBackdrop');
            if (!toggle || !nav) return;

            function setMenu(open) {
                nav.classList.toggle('is-open', open);
                if (backdrop) backdrop.classList.toggle('is-open', open);
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            }

            toggle.addEventListener('click', function() {
                setMenu(!nav.classList.contains('is-open'));
            });
            if (backdrop) {
                backdrop.addEventListener('click', function() { setMenu(false); });
            }
            nav.querySelectorAll('a').forEach(function(link) {
                link.addEventListener('click', function() { setMenu(false); });
            });
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') setMenu(false);
            });
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) setMenu(false);
            });
        })();
    </script>

</body>
</html>
<?php $conn->close(); ?>
