<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso al Sistema</title>
    <link rel="stylesheet" href="login.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
        /* validacion rapida de sesion para evitar entrar si ya estas logueado */
        try {
            const raw = sessionStorage.getItem("usuario_activo");
            if (raw) {
                const usuario = JSON.parse(raw);
                if (usuario) window.location.href = "../index.php";
            }
        } catch (e) { 
            sessionStorage.removeItem("usuario_activo"); 
        }
    </script>
</head>
<body>

    <main class="LoginCard">
        
        <section class="LoginFormSide">
            <header class="LoginHeader">
                <span class="LoginIcon"><i class="fas fa-user-shield"></i></span>
                <h1>Acceso al Sistema</h1>
            </header>

            <article id="ContenedorError">
                <?php 
                session_start();
                if (isset($_SESSION['error'])): ?>
                    <p id="MensajeError" class="ErrorMensaje" style="display:block;">
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    </p>
                <?php else: ?>
                    <p id="MensajeError" class="ErrorMensaje" style="display:none;"></p>
                <?php endif; ?>
            </article>

            <form id="FormLogin" autocomplete="off">
                
                <fieldset class="InputGroup">
                    <i class="fas fa-user"></i>
                    <input type="text" id="usuario" name="usuario_login" placeholder="Usuario" required>
                </fieldset>

                <fieldset class="InputGroup">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="password" name="password_login" placeholder="Contraseña" required>
                </fieldset>

                <button type="submit" class="LoginButton">INICIAR SESIÓN</button>
            </form>
        </section>

        <aside class="LoginVisualSide">
            <article class="WelcomeContent">
                <h2>¡Bienvenido(a)!</h2>
                <p>Gestiona asistencias, personal y reportes de forma eficiente y segura.</p>
            </article>
        </aside>

    </main>

    <script src="login.js"></script>
</body>
</html>