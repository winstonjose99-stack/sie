<?php
require_once __DIR__ . '/../config/conexion.php';
verificarSesionAdmin('../visual/iniciar_sesion.html');

$tabla = $_POST['tabla'] ?? $_GET['tabla'] ?? '';
$id    = intval($_POST['id'] ?? $_GET['id'] ?? 0);

$tablas_permitidas = [
    'instituciones'  => ['campo' => 'id_institucion',   'redirect' => '../visual/editar_institucion.php'],
    'docentes'       => ['campo' => 'id_docente',       'redirect' => '../visual/profesores.php'],
    'representantes' => ['campo' => 'id_representante', 'redirect' => '../visual/representantes.php'],
    'estudiantes'    => ['campo' => 'id_estudiante',    'redirect' => '../visual/estudiantes.php'],
    'materias'       => ['campo' => 'id_materia',       'redirect' => '../visual/materias.php'],
    'secciones'      => ['campo' => 'id_seccion',       'redirect' => '../visual/secciones.php'],
];

if (array_key_exists($tabla, $tablas_permitidas) && $id > 0) {
    $campo_id = $tablas_permitidas[$tabla]['campo'];
    $redirect = $tablas_permitidas[$tabla]['redirect'];

    $stmt = $conn->prepare("DELETE FROM $tabla WHERE $campo_id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        alertaYRedirigir('Registro eliminado con éxito.', $redirect);
    }

    $errno = $stmt->errno;
    $stmt->close();
    $conn->close();

    if ($errno === 1451) {
        alertaYRedirigir('No se puede eliminar este registro porque tiene otros datos asociados (materias, secciones o docentes).', $redirect);
    }

    alertaYRedirigir('No se pudo eliminar el registro.', $redirect);
}

$conn->close();
alertaYRedirigir('Parámetros no válidos.', '../visual/gestionar.php');
?>
