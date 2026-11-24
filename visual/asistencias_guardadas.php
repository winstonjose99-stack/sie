<?php
require_once __DIR__ . '/../config/conexion.php';
verificarSesionDocente('login_profesor.php');

$id_docente = (int) $_SESSION['id_docente'];

// 1. Obtener todas las secciones del profesor (para el selector)
$stmt_sec = $conn->prepare("
    SELECT s.id_seccion, s.nombre_seccion, m.id_materia, m.nombre_materia, i.nombre AS nombre_institucion
    FROM secciones s
    INNER JOIN materias m ON s.id_materia = m.id_materia
    LEFT JOIN instituciones i ON s.id_institucion = i.id_institucion
    WHERE s.id_docente = ?
    ORDER BY s.nombre_seccion
");
$stmt_sec->bind_param("i", $id_docente);
$stmt_sec->execute();
$res_sec = $stmt_sec->get_result();
$secciones = [];
if ($res_sec) {
    while ($r = $res_sec->fetch_assoc()) {
        $secciones[] = $r;
    }
}
$stmt_sec->close();

$id_seccion_sel = intval($_GET['id_seccion'] ?? ($secciones[0]['id_seccion'] ?? 0));
$id_materia_sel = intval($_GET['id_materia'] ?? 0);

// Buscar la sección seleccionada en la lista del profesor
$seccion_actual = null;
foreach ($secciones as $sec) {
    if ((int) $sec['id_seccion'] === $id_seccion_sel && ($id_materia_sel === 0 || (int) $sec['id_materia'] === $id_materia_sel)) {
        $seccion_actual = $sec;
        $id_materia_sel = (int) $sec['id_materia'];
        break;
    }
}
if (!$seccion_actual && !empty($secciones)) {
    $seccion_actual = $secciones[0];
    $id_seccion_sel = (int) $seccion_actual['id_seccion'];
    $id_materia_sel = (int) $seccion_actual['id_materia'];
}

// 2. Obtener fechas que tienen registros guardados para esta sección y materia
$fechas_guardadas = [];
if ($seccion_actual) {
    $stmt_fechas = $conn->prepare("
        SELECT DISTINCT i.fecha
        FROM inasistencias i
        INNER JOIN estudiantes e ON i.id_estudiante = e.id_estudiante
        WHERE e.id_seccion = ? AND i.id_materia = ?
        ORDER BY i.fecha DESC
    ");
    $stmt_fechas->bind_param("ii", $id_seccion_sel, $id_materia_sel);
    $stmt_fechas->execute();
    $res_f = $stmt_fechas->get_result();
    if ($res_f) {
        while ($frow = $res_f->fetch_assoc()) {
            $fechas_guardadas[] = $frow['fecha'];
        }
    }
    $stmt_fechas->close();
}

$fecha_sel = trim($_GET['fecha'] ?? ($fechas_guardadas[0] ?? date('Y-m-d')));

// 3. Obtener la nómina completa de estudiantes de la sección y cruzar con inasistencias de la fecha seleccionada
$nomina = [];
$total_presentes    = 0;
$total_ausentes     = 0;
$total_justificados = 0;

if ($seccion_actual) {
    $stmt_nomina = $conn->prepare("
        SELECT e.id_estudiante, e.nombre, e.apellido, e.cedula, e.fecha_nacimiento, e.sexo,
               r.nombre AS rep_nombre, r.apellido AS rep_apellido, r.telefono AS rep_telefono,
               i.id_inasistencia, i.estado, i.motivo
        FROM estudiantes e
        LEFT JOIN representantes r ON e.id_representante = r.id_representante
        LEFT JOIN inasistencias i
               ON i.id_estudiante = e.id_estudiante
              AND i.id_materia = ?
              AND i.fecha = ?
        WHERE e.id_seccion = ?
        ORDER BY e.apellido, e.nombre
    ");
    $stmt_nomina->bind_param("isi", $id_materia_sel, $fecha_sel, $id_seccion_sel);
    $stmt_nomina->execute();
    $res_nom = $stmt_nomina->get_result();
    if ($res_nom) {
        while ($al = $res_nom->fetch_assoc()) {
            if ($al['estado'] === 'Ausente') {
                $total_ausentes++;
            } elseif ($al['estado'] === 'Justificado') {
                $total_justificados++;
            } else {
                $al['estado'] = 'Presente';
                $total_presentes++;
            }
            $nomina[] = $al;
        }
    }
    $stmt_nomina->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asistencias Guardadas - Portal Docente</title>
    <link rel="stylesheet" href="../css/estilos.css">
    <style>
        .fechas-label {
            display: block;
            color: var(--text-muted);
            font-weight: 700;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 8px;
        }
        .fechas-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: center;
            margin: 0 0 22px 0;
        }
        .fecha-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 36px;
            padding: 6px 14px;
            border-radius: 999px;
            background: #f1f5f9;
            color: var(--text-main);
            text-decoration: none;
            font-size: 0.84rem;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
            border: 1px solid var(--border-color);
            transition: all 0.18s ease;
            white-space: nowrap;
        }
        .fecha-pill:hover,
        .fecha-pill.activa {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
            box-shadow: 0 3px 10px rgba(37, 99, 235, 0.22);
        }
        .filtros-acciones-sec {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 0 !important;
        }
        .nomina-movil-grid {
            display: none;
        }
        .nomina-titulo {
            text-align: left;
            font-size: 1.08rem;
            color: var(--secondary);
            margin: 6px 0 12px 0;
        }
        @media (max-width: 768px) {
            .fechas-label {
                text-align: left;
            }
            .fechas-pills {
                flex-wrap: nowrap;
                justify-content: flex-start;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                padding-bottom: 6px;
                margin-bottom: 16px;
            }
            .fecha-pill {
                flex-shrink: 0;
                min-height: 38px;
            }
            .filtros-acciones-sec {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 8px;
                width: 100%;
            }
            .filtros-acciones-sec a {
                padding: 10px 8px !important;
                font-size: 0.83rem;
            }
            .nomina-desktop {
                display: none;
            }
            .nomina-movil-grid {
                display: grid;
                grid-template-columns: 1fr;
                gap: 10px;
                margin-top: 10px;
            }
            .nomina-card {
                background: #ffffff;
                border: 1px solid #dbe4f0;
                border-left: 4px solid #16a34a;
                border-radius: 15px;
                padding: 14px;
                text-align: left;
                box-shadow: 0 3px 10px rgba(15, 23, 42, 0.04);
            }
            .nomina-card.st-ausente {
                border-left-color: #dc2626;
                background: #fffafa;
                border-color: #fecaca;
                border-left-color: #dc2626;
            }
            .nomina-card.st-justificado {
                border-left-color: #d97706;
                background: #fffdf5;
                border-color: #fde68a;
                border-left-color: #d97706;
            }
            .nomina-card-top {
                display: flex;
                align-items: flex-start;
                gap: 11px;
                margin-bottom: 10px;
            }
            .nomina-card-info {
                flex: 1;
                min-width: 0;
            }
            .nomina-card-head {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                gap: 8px;
                flex-wrap: wrap;
            }
            .nomina-card-name {
                margin: 0;
                font-size: 0.96rem;
                font-weight: 700;
                color: #0f172a;
                line-height: 1.3;
                word-break: break-word;
            }
            .nomina-card-sub {
                margin: 3px 0 0 0;
                font-size: 0.78rem;
                color: #64748b;
            }
            .nomina-meta-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 6px 10px;
                padding: 9px 10px;
                border-radius: 10px;
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                font-size: 0.8rem;
                color: #334155;
            }
            .nomina-meta-grid span {
                display: block;
                font-size: 0.7rem;
                font-weight: 700;
                text-transform: uppercase;
                color: #64748b;
            }
            .nomina-motivo {
                margin: 9px 0 0 0;
                font-size: 0.82rem;
                color: #475569;
                line-height: 1.4;
            }
            .nomina-motivo strong {
                color: #1e293b;
            }
        }
    </style>
</head>
<body class="portal-docente">

    <header class="header-fijo" id="appHeader">
        <div class="header-brand">
            <a href="panel_profesor.php" class="logo" aria-label="Volver al Portal Docente">
                <img src="logo.png" alt="Logo">
            </a>
            <h2 class="header-title">Asistencias</h2>
        </div>

        <div class="header-mobile-controls">
            <a href="panel_profesor.php" class="header-quick-back">Panel</a>
            <button type="button" class="nav-toggle" id="navToggle" aria-label="Abrir menú de navegación" aria-expanded="false" aria-controls="headerNav">
                <span class="nav-toggle-bar"></span>
                <span class="nav-toggle-bar"></span>
                <span class="nav-toggle-bar"></span>
            </button>
        </div>

        <nav class="header-nav" id="headerNav" aria-label="Navegación asistencias guardadas">
            <ul>
                <li><a href="panel_profesor.php">Volver a Mi Panel</a></li>
                <li><a href="historial_inasistencias.php">Historial General</a></li>
                <li><a href="../api/logout.php">Cerrar Sesión</a></li>
            </ul>
        </nav>
    </header>
    <div class="nav-backdrop" id="navBackdrop" aria-hidden="true"></div>

    <div class="dashboard-container">
        <div class="welcome-card docente-hero">
            <span class="docente-hero-eyebrow">Registro Histórico</span>
            <h2>Asistencias Guardadas</h2>
            <p>Consulte la nómina completa por sección y fecha, revise los estados registrados o descargue el reporte PDF.</p>
            <?php if ($seccion_actual) { ?>
            <div class="docente-hero-meta">
                <span class="docente-hero-chip">Sección <?php echo htmlspecialchars($seccion_actual['nombre_seccion']); ?></span>
                <span class="docente-hero-chip"><?php echo htmlspecialchars($seccion_actual['nombre_materia']); ?></span>
                <span class="docente-hero-chip">Fecha: <?php echo htmlspecialchars($fecha_sel); ?></span>
            </div>
            <?php } ?>
        </div>

        <div class="container" style="margin-top: 0;">
            <h2>Filtrar por Sección y Fecha</h2>
            <p>Seleccione la sección y la fecha de la clase para consultar o editar el registro guardado.</p>

            <!-- Selector de Sección y Fecha -->
            <form method="GET" class="filtros">
                <div>
                    <label for="id_seccion">Sección y Materia:</label>
                    <select id="id_seccion" name="id_seccion">
                        <?php foreach ($secciones as $sec) {
                            $sel = ((int) $sec['id_seccion'] === $id_seccion_sel) ? 'selected' : '';
                        ?>
                            <option value="<?php echo (int) $sec['id_seccion']; ?>" <?php echo $sel; ?>>
                                <?php echo htmlspecialchars($sec['nombre_seccion'] . ' — ' . $sec['nombre_materia']); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>

                <div>
                    <label for="fecha">Fecha de la Clase:</label>
                    <input type="date" id="fecha" name="fecha" value="<?php echo htmlspecialchars($fecha_sel); ?>">
                </div>

                <button type="submit">Consultar Asistencia</button>

                <?php if ($seccion_actual) { ?>
                <div class="filtros-acciones-sec">
                    <a href="registrar_inasistencia.php?id_seccion=<?php echo $id_seccion_sel; ?>&id_materia=<?php echo $id_materia_sel; ?>&fecha=<?php echo urlencode($fecha_sel); ?>" class="btn-editar" style="padding: 11px 18px;">
                        Editar Asistencia
                    </a>
                    <a href="../api/generar_pdf.php?tipo=asistencia_clase&id_seccion=<?php echo $id_seccion_sel; ?>&id_materia=<?php echo $id_materia_sel; ?>&fecha=<?php echo urlencode($fecha_sel); ?>" class="btn-pdf" style="padding: 11px 18px;">
                        Descargar PDF
                    </a>
                </div>
                <?php } ?>
            </form>

            <?php if (!empty($fechas_guardadas)) { ?>
                <div>
                    <span class="fechas-label">Fechas con registro guardado</span>
                    <div class="fechas-pills">
                        <?php foreach ($fechas_guardadas as $fg) {
                            $activa = ($fg === $fecha_sel) ? 'activa' : '';
                        ?>
                            <a href="asistencias_guardadas.php?id_seccion=<?php echo $id_seccion_sel; ?>&id_materia=<?php echo $id_materia_sel; ?>&fecha=<?php echo urlencode($fg); ?>" class="fecha-pill <?php echo $activa; ?>">
                                <?php echo htmlspecialchars($fg); ?>
                            </a>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>

            <?php if ($seccion_actual) { ?>
                <!-- Resumen KPI de esa clase -->
                <div class="stats-grid docente-kpis" style="margin-top: 16px;">
                    <div class="stat-card">
                        <div>
                            <p class="stat-value"><?php echo count($nomina); ?></p>
                            <p class="stat-label">Matrícula</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div>
                            <p class="stat-value"><?php echo $total_presentes; ?></p>
                            <p class="stat-label">Presentes</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div>
                            <p class="stat-value"><?php echo $total_justificados; ?></p>
                            <p class="stat-label">Justificados</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div>
                            <p class="stat-value"><?php echo $total_ausentes; ?></p>
                            <p class="stat-label">Ausentes</p>
                        </div>
                    </div>
                </div>

                <h3 class="nomina-titulo">
                    Nómina de Clase: <?php echo htmlspecialchars($seccion_actual['nombre_seccion'] . ' — ' . $seccion_actual['nombre_materia']); ?>
                    (<?php echo htmlspecialchars($fecha_sel); ?>)
                </h3>

                <div class="nomina-desktop table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Cédula</th>
                                <th>Estudiante</th>
                                <th>Fecha de Nacimiento</th>
                                <th>Sexo</th>
                                <th>Representante</th>
                                <th>Teléfono</th>
                                <th>Estado Guardado</th>
                                <th>Observación / Motivo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($nomina)) {
                                foreach ($nomina as $al) {
                                    if ($al['estado'] === 'Ausente') {
                                        $badgeClass = 'badge-ausente';
                                    } elseif ($al['estado'] === 'Justificado') {
                                        $badgeClass = 'badge-justificado';
                                    } else {
                                        $badgeClass = 'badge-presente';
                                    }
                                    $repNombre = trim(($al['rep_nombre'] ?? '') . ' ' . ($al['rep_apellido'] ?? '')) ?: 'Sin asignar';
                                    $fechaNac  = (!empty($al['fecha_nacimiento']) && $al['fecha_nacimiento'] !== '0000-00-00') ? $al['fecha_nacimiento'] : 'N/A';
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($al['cedula']); ?></td>
                                <td><strong><?php echo htmlspecialchars($al['nombre'] . ' ' . $al['apellido']); ?></strong></td>
                                <td><?php echo htmlspecialchars($fechaNac); ?></td>
                                <td><?php echo htmlspecialchars(!empty($al['sexo']) ? $al['sexo'] : 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($repNombre); ?></td>
                                <td><?php echo htmlspecialchars($al['rep_telefono'] ?? '—'); ?></td>
                                <td><span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($al['estado']); ?></span></td>
                                <td><?php echo htmlspecialchars($al['motivo'] ?? ($al['estado'] === 'Presente' ? 'Asistió a clase' : 'Inasistencia sin justificar')); ?></td>
                            </tr>
                            <?php }
                            } else {
                                echo "<tr><td colspan='8'>No hay estudiantes inscritos en esta sección.</td></tr>";
                            } ?>
                        </tbody>
                    </table>
                </div>

                <!-- Tarjetas móviles de la nómina guardada -->
                <div class="nomina-movil-grid">
                    <?php if (!empty($nomina)) {
                        foreach ($nomina as $al) {
                            if ($al['estado'] === 'Ausente') {
                                $badgeClass = 'badge-ausente';
                                $stClass    = 'st-ausente';
                            } elseif ($al['estado'] === 'Justificado') {
                                $badgeClass = 'badge-justificado';
                                $stClass    = 'st-justificado';
                            } else {
                                $badgeClass = 'badge-presente';
                                $stClass    = 'st-presente';
                            }
                            $repNombre = trim(($al['rep_nombre'] ?? '') . ' ' . ($al['rep_apellido'] ?? '')) ?: 'Sin asignar';
                            $fechaNac  = (!empty($al['fecha_nacimiento']) && $al['fecha_nacimiento'] !== '0000-00-00') ? $al['fecha_nacimiento'] : 'N/A';
                            $motivoTxt = $al['motivo'] ?? ($al['estado'] === 'Presente' ? 'Asistió a clase' : 'Inasistencia sin justificar');
                            $iniNom = trim((string) ($al['nombre'] ?? ''));
                            $iniApe = trim((string) ($al['apellido'] ?? ''));
                            $l1 = function_exists('mb_substr') ? mb_substr($iniNom, 0, 1, 'UTF-8') : substr($iniNom, 0, 1);
                            $l2 = function_exists('mb_substr') ? mb_substr($iniApe, 0, 1, 'UTF-8') : substr($iniApe, 0, 1);
                            $iniciales = strtoupper(trim($l1 . $l2)) ?: 'AL';
                    ?>
                    <div class="nomina-card <?php echo $stClass; ?>">
                        <div class="nomina-card-top">
                            <div class="avatar-iniciales <?php echo $stClass; ?>" aria-hidden="true">
                                <?php echo htmlspecialchars($iniciales); ?>
                            </div>
                            <div class="nomina-card-info">
                                <div class="nomina-card-head">
                                    <p class="nomina-card-name"><?php echo htmlspecialchars($al['nombre'] . ' ' . $al['apellido']); ?></p>
                                    <span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($al['estado']); ?></span>
                                </div>
                                <p class="nomina-card-sub">
                                    C.I. <?php echo htmlspecialchars($al['cedula']); ?> ·
                                    F. Nac: <?php echo htmlspecialchars($fechaNac); ?> ·
                                    <?php echo htmlspecialchars(!empty($al['sexo']) ? $al['sexo'] : 'N/A'); ?>
                                </p>
                            </div>
                        </div>
                        <div class="nomina-meta-grid">
                            <div>
                                <span>Representante</span>
                                <?php echo htmlspecialchars($repNombre); ?>
                            </div>
                            <div>
                                <span>Teléfono</span>
                                <?php echo htmlspecialchars($al['rep_telefono'] ?? '—'); ?>
                            </div>
                        </div>
                        <p class="nomina-motivo"><strong>Observación:</strong> <?php echo htmlspecialchars($motivoTxt); ?></p>
                    </div>
                    <?php }
                    } else { ?>
                        <p style="background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 14px; padding: 16px; text-align: center; font-size: 0.88rem; margin: 0;">
                            No hay estudiantes inscritos en esta sección.
                        </p>
                    <?php } ?>
                </div>
            <?php } else { ?>
                <p>No tienes secciones asignadas todavía.</p>
            <?php } ?>
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
