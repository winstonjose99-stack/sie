<?php
// Evita excepciones fatales no capturadas en PHP 8+ para manejar errores con $stmt->error / $conn->errno
mysqli_report(MYSQLI_REPORT_OFF);

$servidor   = getenv('DB_HOST') ?: 'localhost';
$usuario    = getenv('DB_USER') ?: 'root';
$contrasena = getenv('DB_PASS') ?: '';
$base_datos = getenv('DB_NAME') ?: 'sistema_asistencias';

$conn = null;
$esEntornoVercel = !empty(getenv('VERCEL')) || !empty($_SERVER['VERCEL']);

// Si estamos en local (XAMPP) o se configuró un DB_HOST remoto explícito, intentamos MySQL primero
if (!$esEntornoVercel || (getenv('DB_HOST') && getenv('DB_HOST') !== 'localhost')) {
    $mysqliConn = @new mysqli($servidor, $usuario, $contrasena, $base_datos);
    if (!$mysqliConn->connect_error) {
        $mysqliConn->set_charset("utf8mb4");
        $conn = $mysqliConn;
    }
}

// Fallback automático a SQLite precargado cuando se ejecuta en Vercel Serverless sin MySQL externo
if ($conn === null) {
    require_once __DIR__ . '/sqlite_fallback.php';
    $sqlitePath = rtrim(sys_get_temp_dir(), '/\\') . '/sistema_asistencias_v1.sqlite';
    $conn = new SQLiteMysqliCompat($sqlitePath);
}

// Alias de compatibilidad
$conexion = $conn;

if (!defined('SIE_COOKIE_SECRET')) {
    define('SIE_COOKIE_SECRET', getenv('WHATSAPP_PHONE_ID') ?: 'sie_escolar_secret_key_2026');
}

if (!function_exists('sincronizarCookieSesion')) {
    function sincronizarCookieSesion(): void {
        if (headers_sent()) {
            return;
        }
        $payload = [];
        if (isset($_SESSION['id'])) {
            $payload['id']      = (int) $_SESSION['id'];
            $payload['usuario'] = (string) ($_SESSION['usuario'] ?? 'Administrador');
        }
        if (isset($_SESSION['id_docente'])) {
            $payload['id_docente']     = (int) $_SESSION['id_docente'];
            $payload['nombre_docente'] = (string) ($_SESSION['nombre_docente'] ?? 'Profesor');
        }
        if (empty($payload)) {
            return;
        }
        $dataB64 = base64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE));
        $sig     = hash_hmac('sha256', $dataB64, SIE_COOKIE_SECRET);
        setcookie('SIE_AUTH', $dataB64 . '.' . $sig, [
            'expires'  => time() + 86400,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

if (!function_exists('limpiarCookieSesion')) {
    function limpiarCookieSesion(): void {
        if (!headers_sent()) {
            setcookie('SIE_AUTH', '', [
                'expires'  => time() - 42000,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }
}

if (!function_exists('iniciarSesionSegura')) {
    function iniciarSesionSegura(): void {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        // Restaurar sesión desde cookie firmada en entornos Serverless (Vercel) si la instancia cambió
        if (!isset($_SESSION['id']) && !isset($_SESSION['id_docente']) && !empty($_COOKIE['SIE_AUTH'])) {
            $parts = explode('.', (string) $_COOKIE['SIE_AUTH'], 2);
            if (count($parts) === 2) {
                [$dataB64, $sig] = $parts;
                $expected = hash_hmac('sha256', $dataB64, SIE_COOKIE_SECRET);
                if (hash_equals($expected, $sig)) {
                    $decoded = json_decode((string) base64_decode($dataB64, true), true);
                    if (is_array($decoded)) {
                        foreach ($decoded as $k => $v) {
                            $_SESSION[$k] = $v;
                        }
                    }
                }
            }
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
    function obtenerPeriodoActivoId($conn): ?int {
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
