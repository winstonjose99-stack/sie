<?php
// Punto de entrada único Serverless para Vercel (1 sola Lambda compatible con el plan Hobby).
// Ejecuta index.php, api/*.php y visual/*.php conservando sus directorios de trabajo.

$route = $_GET['__route'] ?? 'index.php';
$route = ltrim(str_replace('\\', '/', $route), '/');

$allowedRoutes = [
    'index.php',
    // Endpoints en api/
    'api/actualizar.php',
    'api/dashboard.php',
    'api/eliminar.php',
    'api/enviar_mensaje.php',
    'api/generar_pdf.php',
    'api/login.php',
    'api/logout.php',
    'api/procesar_login_profesor.php',
    'api/promover_periodo.php',
    'api/registrar_asistencia.php',
    'api/registrar_estudiante.php',
    'api/registrar_institucion.php',
    'api/registrar_materia.php',
    'api/registrar_representante.php',
    'api/registrar_seccion.php',
    'api/registro.php',
    'api/registro_docente.php',
    'api/ver_estudiantes_seccion.php',
    'api/webhook_zapier.php',
    // Vistas en visual/
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

if ($route !== 'api/generar_pdf.php') {
    header('Content-Type: text/html; charset=utf-8');
}

chdir(dirname($targetFile));
require $targetFile;
?>
