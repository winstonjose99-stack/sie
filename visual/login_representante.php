<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal de Representantes - Control de Asistencias</title>
    <link rel="stylesheet" href="../css/estilos.css">
</head>
<body>
    <header class="header-fijo">
        <div class="logo">
            <img src="logo.png" alt="Logo SIE">
        </div>
        <h2>PORTAL DE REPRESENTANTES</h2>
        <nav>
            <ul>
                <li><a href="inicio.html">Inicio</a></li>
                <li><a href="login_profesor.php">Portal Docente</a></li>
                <li><a href="iniciar_sesion.html">Administración</a></li>
            </ul>
        </nav>
    </header>

    <main class="auth-wrapper">
        <div class="auth-card">
            <h2>Portal de Representantes</h2>
            <p class="auth-subtitle">Consulte en tiempo real las notificaciones de asistencia, inasistencias y notas académicas de sus representados.</p>

            <form action="../api/procesar_login_representante.php" method="POST">
                <div>
                    <label for="cedula">Cédula de Identidad:</label>
                    <input type="text" id="cedula" name="cedula" placeholder="Ingrese su Cédula (solo números)" required>
                </div>

                <div>
                    <label for="telefono">Teléfono Registrado:</label>
                    <input type="text" id="telefono" name="telefono" placeholder="Ej. 04124710360 o 584124710360" required>
                </div>

                <button type="submit">Ingresar al Portal</button>
            </form>

            <div class="auth-footer">
                <span>¿Es usted docente? <a href="login_profesor.php">Acceso Portal Docente</a></span>
                <span>¿Pertenece al personal directivo? <a href="iniciar_sesion.html">Acceso Administración</a></span>
            </div>
        </div>
    </main>

</body>
</html>
