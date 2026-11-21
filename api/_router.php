<?php
// Enrutador Serverless para Vercel: permite ejecutar index.php y visual/*.php a través del runtime PHP en api/
$route = $_GET['__route'] ?? 'index.php';

$allowedRoutes = [
    'index.php',
    'visual/admin_periodos.php',
    'visual/ainstitucion.php',
    'visual/asistencias_guardadas.php',
    'visual/editar_estudiante.php',
    'visual/editar_institucion.php',
    'visual/editar_materia.php',
    'visual/editar_profesor.php',
    'visual/editar_representante.php',
    'visual/editar_seccion.php',
    'visual/estudiantes.php',
    'visual/formulario_doc.php',
    'visual/formulario_mate.php',
    'visual/formulario_r_e.php',
    'visual/formulario_repre.php',
    'visual/formulario_seccion.php',
    'visual/gestionar.php',
    'visual/historial_inasistencias.php',
    'visual/login_profesor.php',
    'visual/materias.php',
    'visual/panel_profesor.php',
    'visual/profesores.php',
    'visual/promover_periodo.php',
    'visual/registrar_inasistencia.php',
    'visual/representantes.php',
    'visual/secciones.php',
];

if (!in_array($route, $allowedRoutes, true)) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo 'Página no encontrada.';
    exit();
}

$rootDir    = dirname(__DIR__);
$targetFile = $rootDir . '/' . $route;

if (!file_exists($targetFile)) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo 'Archivo no encontrado.';
    exit();
}

header('Content-Type: text/html; charset=utf-8');
chdir(dirname($targetFile));
require $targetFile;
?>
