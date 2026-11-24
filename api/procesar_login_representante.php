<?php
require_once __DIR__ . '/../config/conexion.php';
iniciarSesionSegura();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cedula   = preg_replace('/[^0-9]/', '', trim($_POST['cedula'] ?? ''));
    $telefono = normalizarTelefonoVenezuela(trim($_POST['telefono'] ?? ''));

    if ($cedula === '' || $telefono === '') {
        alertaYRedirigir('Debe ingresar su cédula y teléfono registrado.', '../visual/login_representante.php');
    }

    $stmt = $conn->prepare("
        SELECT r.id_representante, r.nombre, r.apellido, r.cedula, r.telefono,
               (SELECT COUNT(*) FROM estudiantes e WHERE e.id_representante = r.id_representante) AS total_estudiantes
        FROM representantes r
        WHERE r.cedula = ?
        ORDER BY total_estudiantes DESC, r.id_representante ASC
    ");
    $stmt->bind_param("s", $cedula);
    $stmt->execute();
    $resultado = $stmt->get_result();

    $match = null;
    if ($resultado) {
        while ($row = $resultado->fetch_assoc()) {
            $telBd = normalizarTelefonoVenezuela((string) ($row['telefono'] ?? ''));
            if ($telBd === $telefono) {
                $match = $row;
                break;
            }
        }
    }
    $stmt->close();

    if ($match) {
        @session_regenerate_id(true);
        $_SESSION['id_representante']     = (int) $match['id_representante'];
        $_SESSION['nombre_representante'] = trim($match['nombre'] . ' ' . $match['apellido']);
        sincronizarCookieSesion();

        header("Location: ../visual/panel_representante.php");
        exit();
    }

    alertaYRedirigir('Cédula o teléfono incorrectos. Verifique los datos registrados en la institución.', '../visual/login_representante.php');
}

header("Location: ../visual/login_representante.php");
exit();
?>
