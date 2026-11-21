<?php
// Evita excepciones fatales no capturadas en PHP 8+ para manejar errores con $stmt->error / $conn->errno
mysqli_report(MYSQLI_REPORT_OFF);

$servidor   = getenv('DB_HOST') ?: 'localhost';
$usuario    = getenv('DB_USER') ?: 'root';
$contrasena = getenv('DB_PASS') ?: '';
$base_datos = getenv('DB_NAME') ?: 'sistema_asistencias';

$conn = new mysqli($servidor, $usuario, $contrasena, $base_datos);
if ($conn->connect_error) {
    die("Error de conexión a la base de datos.");
}

$conn->set_charset("utf8mb4");

// Alias de compatibilidad
$conexion = $conn;

if (!function_exists('iniciarSesionSegura')) {
    function iniciarSesionSegura(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}

if (!function_exists('verificarSesionAdmin')) {
    function verificarSesionAdmin(string $rutaLogin = '../visual/iniciar_sesion.html'): void {
        iniciarSesionSegura();
        if (!isset($_SESSION['id'])) {
            header("Location: " . $rutaLogin);
            exit();
        }
    }
}

if (!function_exists('verificarSesionDocente')) {
    function verificarSesionDocente(string $rutaLogin = 'login_profesor.php'): void {
        iniciarSesionSegura();
        if (!isset($_SESSION['id_docente'])) {
            header("Location: " . $rutaLogin);
            exit();
        }
    }
}

if (!function_exists('obtenerPeriodoActivoId')) {
    function obtenerPeriodoActivoId(mysqli $conn): ?int {
        $res = $conn->query("SELECT id_periodo FROM periodos_academicos WHERE activo = 1 LIMIT 1");
        if ($res && $row = $res->fetch_assoc()) {
            return (int) $row['id_periodo'];
        }
        return null;
    }
}

if (!function_exists('alertaYRedirigir')) {
    function alertaYRedirigir(string $mensaje, ?string $url = null): void {
        $msgJs = json_encode($mensaje, JSON_UNESCAPED_UNICODE);
        if ($url !== null) {
            $urlJs = json_encode($url, JSON_UNESCAPED_UNICODE);
            echo "<script>alert($msgJs); window.location.href = $urlJs;</script>";
        } else {
            echo "<script>alert($msgJs); window.history.back();</script>";
        }
        exit();
    }
}
?>
