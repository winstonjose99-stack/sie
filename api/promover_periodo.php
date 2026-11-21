<?php
require_once __DIR__ . '/../config/conexion.php';
verificarSesionAdmin('../visual/iniciar_sesion.html');

// Unificado en visual/promover_periodo.php para evitar código duplicado
header("Location: ../visual/promover_periodo.php");
exit();
?>
