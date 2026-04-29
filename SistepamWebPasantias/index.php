<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/datos/conexionBD.php';
require_once __DIR__ . '/datos/helpers_gestion_bd.php';

if (!isset($_SESSION['usuario'])) {
    header('Location: Login/login.php');
    exit();
}

$migrarOk = false;
try {
    $pdoSesion = obtenerConexionPdo();
    migrarEsquemaAplicacionOpcional($pdoSesion);
    $migrarOk = true;
} catch (Throwable $e) {
    error_log('index.php sesión BD: ' . $e->getMessage());
}

$modsCompletos = ['registro', 'consulta', 'horarios', 'reportes', 'gestion'];
$usuarioLogin = (string)$_SESSION['usuario'];
$nombreSesionRol = strtolower((string)($_SESSION['rol'] ?? ''));

$permisosModulos = [];
if ($migrarOk && isset($pdoSesion)) {
    try {
        $permisosModulos = obtenerModulosUsuario($pdoSesion, $usuarioLogin);
    } catch (Throwable $e) {
        $permisosModulos = [];
    }
}

if ($nombreSesionRol === 'admin' || strpos($nombreSesionRol, 'admin') !== false) {
    $permisosModulos = $modsCompletos;
} elseif ($nombreSesionRol === 'analista' || strpos($nombreSesionRol, 'analista') !== false) {
    if ($permisosModulos === []) {
        $permisosModulos = ['consulta', 'horarios', 'reportes'];
    }
}

$sesionClienteJson = json_encode([
    'usuario' => $usuarioLogin,
    'nombre' => $usuarioLogin,
    'rol' => ($nombreSesionRol !== '' && (strpos($nombreSesionRol, 'admin') !== false || $nombreSesionRol === 'admin'))
        ? 'admin'
        : 'analista',
    'permisos' => array_values(array_unique($permisosModulos)),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

// Toma el parámetro 'p' de la URL, o 'inicio' si no existe
$p = $_GET['p'] ?? 'inicio';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestión</title>

    <script type="application/json" id="__sesionPhp"><?php echo $sesionClienteJson ?: '{}'; ?></script>
    <!-- Recursos globales -->
    <script src="./index.js?v=3"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="./index.css?v=5">

    <!-- CSS específico por módulo -->
    <?php
    if ($p === 'registro') echo '<link rel="stylesheet" href="./Registro/Registro.css">';
    if ($p === 'consulta') {
        echo '<link rel="stylesheet" href="./Consulta/Consulta.css">';
        echo '<link rel="stylesheet" href="./Calendario/Calendario.css">';
    }
    if ($p === 'horarios') echo '<link rel="stylesheet" href="./Calendario/Calendario.css">';
    if ($p === 'gestion') echo '<link rel="stylesheet" href="./GestionesDeUsuario/GestionesdeUsuario.css">';
    if ($p === 'reportes') echo '<link rel="stylesheet" href="./Reportes/Reportes.css">';
    if ($p === 'auditorias') echo '<link rel="stylesheet" href="./Auditorias/Auditorias.css">';
    ?>
</head>
<body>

<aside class="Sidebar">
    <header class="SidebarLogo">
        <img src="./Public/IMG/logo_sistema.png" alt="Logo">
        <h2>SISTEMA</h2>
    </header>

    <nav class="SidebarMenu">
        <ul>
            <li><a href="index.php?p=inicio" class="<?php echo ($p==='inicio'?'active':''); ?>"><i class="fas fa-home"></i> Inicio</a></li>
            <li><a href="index.php?p=registro" id="EnlaceResgistroUI" class="<?php echo ($p==='registro'?'active':''); ?>"><i class="fas fa-user-plus"></i> Registro</a></li>
            <li><a href="index.php?p=consulta" class="<?php echo ($p==='consulta'?'active':''); ?>"><i class="fas fa-search"></i> Consultas</a></li>
            <li><a href="index.php?p=horarios" class="<?php echo ($p==='horarios'?'active':''); ?>"><i class="fas fa-calendar-alt"></i> Horarios</a></li>
            <li><a href="index.php?p=reportes" class="<?php echo ($p==='reportes'?'active':''); ?>"><i class="fas fa-chart-bar"></i> Reportes</a></li>
            <li><a href="index.php?p=gestion" id="EnlaceGestionUI" style="display: none;" class="<?php echo ($p==='gestion'?'active':''); ?>"><i class="fas fa-user-shield"></i> Gestión</a></li>
            <li><a href="index.php?p=auditorias" class="<?php echo ($p==='auditorias'?'active':''); ?>"><i class="fas fa-clipboard-list"></i> Auditorías</a></li>
        </ul>
    </nav>

    <footer class="SidebarFooter">
        <a href="#" onclick="cerrarSesion(event)"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
    </footer>
</aside>

<main class="MainContent">
    <header class="TopHeader">
        <h1>Bienvenido al Sistema de Gestión</h1>
        <section class="UserProfile">
            <span id="MensajeRol">Cargando sesión...</span>
            <strong id="NombreUsuarioUI"></strong>
        </section>
    </header>

    <section class="ContentArea">
        <?php
        // Módulos permitidos
        $modulos_permitidos = ['inicio', 'registro', 'consulta', 'horarios', 'reportes', 'gestion', 'auditorias'];
        if (!in_array($p, $modulos_permitidos)) $p = 'inicio';

        switch ($p) {
            case 'inicio':
                echo '
                <article class="WelcomeBox">
                    <i class="fas fa-chart-line"></i>
                    <h2>Panel de Control</h2>
                    <p>Seleccione una opción del menú lateral para comenzar.<br>
                    Gestione empresas, personal y reportes desde una sola interfaz.</p>
                </article>';
                break;
            case 'registro': include './Registro/Registro.php'; break;
            case 'horarios': include './Calendario/Calendario.php'; break;
            case 'consulta':
                include './Calendario/Calendario.php';
                include './Consulta/Consulta.php';
                break;
            case 'gestion': include './GestionesDeUsuario/GestionesdeUsuario.php'; break;
            case 'reportes': include './Reportes/Reportes.php'; break;
            case 'auditorias': include './Auditorias/Auditorias.php'; break;
            default: echo "<h2>Módulo no encontrado</h2>";
        }
        ?>
    </section>
</main>

<!-- JavaScripts específicos por módulo -->
<?php
if ($p === 'registro') echo '<script src="./Registro/Registro.js"></script>';
if ($p === 'horarios' || $p === 'consulta') {
    echo '<script src="./Calendario/Calendario.js?v=2"></script>';
    if ($p === 'consulta') echo '<script src="./Consulta/Consulta.js?v=2"></script>';
}
if ($p === 'gestion') echo '<script src="./GestionesDeUsuario/GestionesdeUsuario.js"></script>';
if ($p === 'reportes') echo '<script src="./Reportes/Reportes.js"></script>';
if ($p === 'auditorias') echo '<script src="./Auditorias/Auditorias.js"></script>';
?>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        if (typeof aplicarMenuSegunRol === "function") {
            aplicarMenuSegunRol();
        }
    });
</script>
</body>
</html>