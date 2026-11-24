<?php
require_once __DIR__ . '/../config/conexion.php';
verificarSesionAdmin('../visual/iniciar_sesion.html');

// Consultas de métricas KPI en tiempo real
$totalEstudiantes = 0;
$resEst = $conn->query("SELECT COUNT(*) AS total FROM estudiantes");
if ($resEst && $rowEst = $resEst->fetch_assoc()) {
    $totalEstudiantes = (int) $rowEst['total'];
}

$totalDocentes = 0;
$resDoc = $conn->query("SELECT COUNT(*) AS total FROM docentes");
if ($resDoc && $rowDoc = $resDoc->fetch_assoc()) {
    $totalDocentes = (int) $rowDoc['total'];
}

$idPeriodoActivo = obtenerPeriodoActivoId($conn);
$totalSecciones = 0;
if ($idPeriodoActivo !== null) {
    $stmtSec = $conn->prepare("SELECT COUNT(*) AS total FROM secciones WHERE id_periodo = ?");
    if ($stmtSec) {
        $stmtSec->bind_param("i", $idPeriodoActivo);
        $stmtSec->execute();
        $resSec = $stmtSec->get_result();
        if ($resSec && $rowSec = $resSec->fetch_assoc()) {
            $totalSecciones = (int) $rowSec['total'];
        }
        $stmtSec->close();
    }
} else {
    $resSec = $conn->query("SELECT COUNT(*) AS total FROM secciones");
    if ($resSec && $rowSec = $resSec->fetch_assoc()) {
        $totalSecciones = (int) $rowSec['total'];
    }
}

$totalInasistencias = 0;
$resIna = $conn->query("SELECT COUNT(*) AS total FROM inasistencias WHERE estado IN ('Ausente', 'Justificado')");
if ($resIna && $rowIna = $resIna->fetch_assoc()) {
    $totalInasistencias = (int) $rowIna['total'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Gerencial - Sistema de Asistencias</title>
    <link rel="stylesheet" href="../css/estilos.css">
</head>
<body>

    <header class="header-fijo">
        <div class="logo">
            <img src="../visual/logo.png" alt="Logo del Sistema">
        </div>
        <h2>PANEL DE GERENCIA ESCOLAR</h2>
        <nav>
            <ul>
                <li><span>👤 <?= htmlspecialchars($_SESSION['usuario'] ?? 'Administrador') ?></span></li>
                <li><a href="logout.php">Cerrar Sesión</a></li>
            </ul>
        </nav>
    </header>

    <div class="dashboard-container">
        <div class="welcome-card">
            <h2>Centro de Control Académico y Asistencias</h2>
            <p>Supervise las métricas institucionales en tiempo real y acceda a los módulos de registro, gestión y reportería.</p>
        </div>

        <!-- Barra de Métricas en Tiempo Real (KPIs) -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👨‍🎓</div>
                <div>
                    <p class="stat-value"><?= $totalEstudiantes ?></p>
                    <p class="stat-label">Estudiantes Registrados</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">👨‍🏫</div>
                <div>
                    <p class="stat-value"><?= $totalDocentes ?></p>
                    <p class="stat-label">Docentes Activos</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">🏷️</div>
                <div>
                    <p class="stat-value"><?= $totalSecciones ?></p>
                    <p class="stat-label">Secciones (Período Activo)</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">📋</div>
                <div>
                    <p class="stat-value"><?= $totalInasistencias ?></p>
                    <p class="stat-label">Inasistencias Registradas</p>
                </div>
            </div>
        </div>

        <div class="modules-grid">
            <a href="../visual/formulario_inst.html" class="module-card">
                <div class="icon-box">🏫</div>
                <h3>Instituciones</h3>
                <p>Registrar nuevas sedes o planteles educativos en la plataforma.</p>
            </a>

            <a href="../visual/formulario_doc.php" class="module-card">
                <div class="icon-box">👨‍🏫</div>
                <h3>Profesores</h3>
                <p>Dar de alta a docentes y asignarles su institución correspondiente.</p>
            </a>

            <a href="../visual/formulario_mate.php" class="module-card">
                <div class="icon-box">📚</div>
                <h3>Materias</h3>
                <p>Crear asignaturas académicas y enlazarlas con los docentes.</p>
            </a>

            <a href="../visual/formulario_seccion.php" class="module-card">
                <div class="icon-box">🏷️</div>
                <h3>Secciones</h3>
                <p>Crear y administrar grupos, años o secciones del período escolar.</p>
            </a>

            <a href="../visual/formulario_r_e.php" class="module-card">
                <div class="icon-box">👨‍🎓</div>
                <h3>Estudiantes</h3>
                <p>Inscribir alumnos y vincularlos con su sección y representante.</p>
            </a>

            <a href="../visual/formulario_repre.php" class="module-card">
                <div class="icon-box">👨‍👩‍👧</div>
                <h3>Representantes</h3>
                <p>Registrar datos de contacto y WhatsApp de los representantes legales.</p>
            </a>

            <a href="../visual/gestionar.php" class="module-card">
                <div class="icon-box">⚙️</div>
                <h3>Gestionar Registros</h3>
                <p>Visualizar, filtrar, actualizar o eliminar información en las tablas.</p>
            </a>

            <a href="../visual/admin_periodos.php" class="module-card">
                <div class="icon-box">📅</div>
                <h3>Período Activo</h3>
                <p>Gestionar el año escolar en curso y promover secciones al nuevo período.</p>
            </a>

            <a href="../visual/historial_inasistencias.php" class="module-card">
                <div class="icon-box">📋</div>
                <h3>Reporte de Inasistencias</h3>
                <p>Consultar y auditar el historial de faltas y justificaciones por fecha.</p>
            </a>

            <a href="../visual/notas_admin.php" class="module-card">
                <div class="icon-box">📝</div>
                <h3>Notas Académicas (100%)</h3>
                <p>Supervisar planes de evaluación, calificaciones ponderadas y boletines PDF.</p>
            </a>

            <a href="webhook_zapier.php" class="module-card">
                <div class="icon-box">🔗</div>
                <h3>Webhook Zapier</h3>
                <p>Configurar la URL del webhook y probar el envío de inasistencias en JSON.</p>
            </a>
        </div>
    </div>

</body>
</html>
