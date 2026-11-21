<?php
require_once __DIR__ . '/../config/conexion.php';
verificarSesionAdmin('../visual/iniciar_sesion.html');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $tabla = $_POST['tabla'] ?? '';
    $id    = intval($_POST['id'] ?? 0);

    if ($id <= 0) {
        alertaYRedirigir('Identificador no válido.');
    }

    // 1. Actualizar Instituciones
    if ($tabla === 'instituciones') {
        $nombre = trim($_POST['nombre'] ?? '');
        $dir    = trim($_POST['direccion'] ?? '');

        $stmt = $conn->prepare("UPDATE instituciones SET nombre = ?, direccion = ? WHERE id_institucion = ?");
        $stmt->bind_param("ssi", $nombre, $dir, $id);

        if ($stmt->execute()) {
            $stmt->close();
            alertaYRedirigir('Institución actualizada con éxito.', '../visual/editar_institucion.php');
        }
        $stmt->close();
        alertaYRedirigir('Error al actualizar la institución.');
    }

    // 2. Actualizar Profesores / Docentes
    elseif ($tabla === 'docentes') {
        $nombre         = trim($_POST['nombre'] ?? '');
        $apellido       = trim($_POST['apellido'] ?? '');
        $cedula         = trim($_POST['cedula'] ?? '');
        $telefono       = preg_replace('/[^0-9]/', '', $_POST['telefono'] ?? '');
        $correo         = trim($_POST['correo'] ?? '');
        $id_institucion = intval($_POST['id_institucion'] ?? 0);

        $stmt = $conn->prepare("UPDATE docentes SET nombre = ?, apellido = ?, cedula = ?, telefono = ?, correo = ?, id_institucion = ? WHERE id_docente = ?");
        $stmt->bind_param("sssssii", $nombre, $apellido, $cedula, $telefono, $correo, $id_institucion, $id);

        if ($stmt->execute()) {
            $stmt->close();
            alertaYRedirigir('Profesor actualizado con éxito.', '../visual/profesores.php');
        }
        $stmt->close();
        alertaYRedirigir('Error al actualizar el profesor.');
    }

    // 3. Actualizar Materias
    elseif ($tabla === 'materias') {
        $nombre_materia = trim($_POST['nombre_materia'] ?? '');
        $id_docente     = intval($_POST['id_docente'] ?? 0);

        $stmt = $conn->prepare("UPDATE materias SET nombre_materia = ?, id_docente = ? WHERE id_materia = ?");
        $stmt->bind_param("sii", $nombre_materia, $id_docente, $id);

        if ($stmt->execute()) {
            $stmt->close();
            alertaYRedirigir('Materia actualizada con éxito.', '../visual/materias.php');
        }
        $stmt->close();
        alertaYRedirigir('Error al actualizar la materia.');
    }

    // 4. Actualizar Secciones
    elseif ($tabla === 'secciones') {
        $nombre_seccion = trim($_POST['nombre_seccion'] ?? '');
        $id_institucion = intval($_POST['id_institucion'] ?? 0);
        $id_docente     = intval($_POST['id_docente'] ?? 0);
        $id_materia     = intval($_POST['id_materia'] ?? 0);

        $stmt = $conn->prepare("UPDATE secciones SET nombre_seccion = ?, id_institucion = ?, id_docente = ?, id_materia = ? WHERE id_seccion = ?");
        $stmt->bind_param("siiii", $nombre_seccion, $id_institucion, $id_docente, $id_materia, $id);

        if ($stmt->execute()) {
            $stmt->close();
            alertaYRedirigir('Sección actualizada con éxito.', '../visual/secciones.php');
        }
        $stmt->close();
        alertaYRedirigir('Error al actualizar la sección.');
    }

    // 5. Actualizar Representantes (sin columna inexistente `direccion`)
    elseif ($tabla === 'representantes') {
        $nombre   = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $cedula   = trim($_POST['cedula'] ?? '');
        $telefono = preg_replace('/[^0-9]/', '', $_POST['telefono'] ?? '');

        if (strlen($telefono) < 10) {
            alertaYRedirigir('El teléfono debe contener al menos 10 dígitos (incluyendo código de país).');
        }

        $stmt = $conn->prepare("UPDATE representantes SET nombre = ?, apellido = ?, cedula = ?, telefono = ? WHERE id_representante = ?");
        $stmt->bind_param("ssssi", $nombre, $apellido, $cedula, $telefono, $id);

        if ($stmt->execute()) {
            $stmt->close();
            alertaYRedirigir('Representante actualizado con éxito.', '../visual/representantes.php');
        }
        $stmt->close();
        alertaYRedirigir('Error al actualizar el representante.');
    }

    // 6. Actualizar Estudiantes (incluye ficha médica y sección)
    elseif ($tabla === 'estudiantes') {
        $nombre               = trim($_POST['nombre'] ?? '');
        $apellido             = trim($_POST['apellido'] ?? '');
        $cedula               = trim($_POST['cedula'] ?? '');
        $fecha_nacimiento     = (!empty($_POST['fecha_nacimiento']) && $_POST['fecha_nacimiento'] !== '0000-00-00') ? $_POST['fecha_nacimiento'] : null;
        $sexo                 = !empty($_POST['sexo']) ? $_POST['sexo'] : null;
        $enfermedad_condicion = !empty(trim($_POST['enfermedad_condicion'] ?? '')) ? trim($_POST['enfermedad_condicion']) : 'Ninguna';
        $id_seccion           = !empty($_POST['id_seccion']) ? intval($_POST['id_seccion']) : null;
        $id_representante     = !empty($_POST['id_representante']) ? intval($_POST['id_representante']) : null;

        $stmt = $conn->prepare("UPDATE estudiantes SET nombre = ?, apellido = ?, cedula = ?, fecha_nacimiento = ?, sexo = ?, enfermedad_condicion = ?, id_seccion = ?, id_representante = ? WHERE id_estudiante = ?");
        $stmt->bind_param("ssssssiii", $nombre, $apellido, $cedula, $fecha_nacimiento, $sexo, $enfermedad_condicion, $id_seccion, $id_representante, $id);

        if ($stmt->execute()) {
            $stmt->close();
            alertaYRedirigir('Estudiante actualizado con éxito.', '../visual/estudiantes.php');
        }
        $stmt->close();
        alertaYRedirigir('Error al actualizar el estudiante.');
    }
}

$conn->close();
alertaYRedirigir('Solicitud no válida.', '../visual/gestionar.php');
?>
