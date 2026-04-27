<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../datos/conexionBD.php';

const MENSAJE_ERROR_LOGIN = 'Credenciales incorrectas o acceso denegado.';

/**
 * Envía respuesta JSON y finaliza ejecución.
 */
function responderJson(bool $ok, string $mensaje, string $redirect = '', array $data = []): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => $ok,
        'mensaje' => $mensaje,
        'redirect' => $redirect,
        'data' => $data,
    ]);
    exit();
}

/**
 * Crea la sesión de usuario.
 */
function crearSesionUsuario(string $usuario, string $rol): void
{
    $_SESSION['usuario'] = $usuario;
    $_SESSION['rol'] = $rol;
}

/**
 * Reemplaza la contraseña por hash seguro cuando sea necesario.
 */
function actualizarHashPassword(PDO $conexion, string $usuario, string $passwordPlano): void
{
    $nuevoHash = password_hash($passwordPlano, PASSWORD_DEFAULT);
    $actualizar = $conexion->prepare('UPDATE usuarios SET contraseña = :password WHERE usuario = :usuario');
    $actualizar->execute([
        'password' => $nuevoHash,
        'usuario' => $usuario,
    ]);
}

/**
 * Actualiza la fecha de última conexión del usuario autenticado.
 */
function actualizarUltimaConexion(PDO $conexion, string $usuario): void
{
    $actualizar = $conexion->prepare('UPDATE usuarios SET ult_conexion = NOW() WHERE usuario = :usuario');
    $actualizar->execute([
        'usuario' => $usuario,
    ]);
}

if (isset($_GET['salir'])) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit();
}

$usuario = trim((string)($_POST['usuario_login'] ?? ''));
$password = (string)($_POST['password_login'] ?? '');

if ($usuario === '' || $password === '') {
    responderJson(false, 'Debes completar usuario y contraseña.');
}

// Accesos de respaldo.
if ($usuario === 'admin' && $password === 'admin123') {
    crearSesionUsuario('administrador', 'admin');
    responderJson(true, 'Acceso correcto.', '../index.php', [
        'usuario' => 'administrador',
        'rol' => 'admin',
    ]);
}

if ($usuario === 'analista' && $password === '123') {
    crearSesionUsuario('analista de prueba', 'analista');
    responderJson(true, 'Acceso correcto.', '../index.php', [
        'usuario' => 'analista de prueba',
        'rol' => 'analista',
    ]);
}

try {
    $conexion = obtenerConexionPdo();

    $sql = 'SELECT u.usuario, u.contraseña, u.es_activo, r.nombre_rol
            FROM usuarios u
            INNER JOIN roles r ON r.id_rol = u.id_rol
            WHERE u.usuario = :usuario
            LIMIT 1';

    $consulta = $conexion->prepare($sql);
    $consulta->execute(['usuario' => $usuario]);
    $usuarioDb = $consulta->fetch();

    if (!$usuarioDb) {
        responderJson(false, MENSAJE_ERROR_LOGIN);
    }

    $passwordDb = (string)$usuarioDb['contraseña'];
    $passwordHashInfo = password_get_info($passwordDb);
    $esHashValido = !empty($passwordHashInfo['algo']);
    $passwordValida = password_verify($password, $passwordDb) || hash_equals($passwordDb, $password);

    if (!$passwordValida) {
        responderJson(false, MENSAJE_ERROR_LOGIN);
    }

    // Si estaba en texto plano o el hash necesita actualizarse, se reescribe seguro.
    if (!$esHashValido || password_needs_rehash($passwordDb, PASSWORD_DEFAULT)) {
        actualizarHashPassword($conexion, $usuario, $password);
    }

    $estadoAcceso = (int)($usuarioDb['es_activo'] ?? 1);
    if ($estadoAcceso !== 1) {
        responderJson(false, 'Este usuario está deshabilitado o inactivo.');
    }

    $rol = (string)($usuarioDb['nombre_rol'] ?? 'usuario');
    actualizarUltimaConexion($conexion, $usuario);
    crearSesionUsuario($usuario, $rol);

    responderJson(true, 'Acceso correcto.', '../index.php', [
        'usuario' => $usuario,
        'rol' => $rol,
    ]);
} catch (Throwable $error) {
    error_log('Error en login: ' . $error->getMessage());
    responderJson(false, 'Error al conectar con la base de datos. Verifica tabla y credenciales.');
}
?>