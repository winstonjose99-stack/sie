<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Profesores - Control de Asistencias</title>
    <link rel="stylesheet" href="../css/estilos.css">
</head>
<body>
    <header class="header-fijo">
        <div class="logo">
            <img src="../visual/logo.png" alt="Logo">
        </div>
        <h2>PORTAL DOCENTE</h2>
        <nav>
            <ul>
                <li><a href="inicio.html">Inicio</a></li>
                <li><a href="iniciar_sesion.html">Administración</a></li>
            </ul>
        </nav>
    </header>

    <main class="auth-wrapper">
        <div class="auth-card">
            <h2>Portal Docente</h2>
            <p class="auth-subtitle">Acceda con su cédula y correo institucional para registrar la asistencia de sus secciones.</p>

            <form action="../api/procesar_login_profesor.php" method="POST">
                <div>
                    <label for="cedula">Cédula de Identidad:</label>
                    <input type="text" id="cedula" name="cedula" placeholder="Ingrese su Cédula (solo números)" required>
                </div>

                <div>
                    <label for="correo">Correo Electrónico:</label>
                    <input type="email" id="correo" name="correo" placeholder="docente@institucion.edu.ve" required>
                </div>

                <button type="submit">Ingresar al Sistema</button>
            </form>

            <div class="auth-footer">
                <span>¿Pertenece al personal directivo? <a href="iniciar_sesion.html">Acceso Administración</a></span>
            </div>
        </div>
    </main>

</body>
</html>
