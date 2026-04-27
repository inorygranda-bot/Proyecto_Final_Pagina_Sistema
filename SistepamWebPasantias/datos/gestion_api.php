<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexionBD.php';

function responder(bool $ok, string $mensaje, array $data = []): void
{
    echo json_encode([
        'ok' => $ok,
        'mensaje' => $mensaje,
        'data' => $data,
    ]);
    exit();
}

function post(string $key, string $default = ''): string
{
    return trim((string)($_POST[$key] ?? $default));
}

function postJsonArray(string $key): array
{
    $raw = $_POST[$key] ?? '[]';
    if (!is_string($raw) || $raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function postInt(string $key, int $default = 0): int
{
    return (int)($_POST[$key] ?? $default);
}

function asegurarTablaPersistencia(PDO $conexion): void
{
    $conexion->exec(
        'CREATE TABLE IF NOT EXISTS sistema_storage (
            id_storage INT AUTO_INCREMENT PRIMARY KEY,
            clave VARCHAR(100) NOT NULL UNIQUE,
            valor_json LONGTEXT NOT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function leerStorageJson(PDO $conexion, string $clave, array $default = []): array
{
    $consulta = $conexion->prepare(
        'SELECT valor_json
         FROM sistema_storage
         WHERE clave = :clave
         LIMIT 1'
    );
    $consulta->execute(['clave' => $clave]);
    $fila = $consulta->fetch();
    if (!$fila || !isset($fila['valor_json'])) {
        return $default;
    }

    $decoded = json_decode((string)$fila['valor_json'], true);
    return is_array($decoded) ? $decoded : $default;
}

function guardarStorageJson(PDO $conexion, string $clave, array $valor): void
{
    $json = json_encode($valor, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new RuntimeException('No se pudo serializar la data.');
    }

    $guardar = $conexion->prepare(
        'INSERT INTO sistema_storage (clave, valor_json)
         VALUES (:clave, :valor_json)
         ON DUPLICATE KEY UPDATE
            valor_json = VALUES(valor_json),
            updated_at = CURRENT_TIMESTAMP'
    );
    $guardar->execute([
        'clave' => $clave,
        'valor_json' => $json,
    ]);
}

function normalizarDatosSistema(array $datos): array
{
    $resultado = $datos;

    foreach (['empresas', 'departamentos', 'empleados', 'usuarios', 'incidencias'] as $coleccion) {
        if (!isset($resultado[$coleccion]) || !is_array($resultado[$coleccion])) {
            $resultado[$coleccion] = [];
        }
    }

    if (!isset($resultado['calendarios']) || !is_array($resultado['calendarios'])) {
        $resultado['calendarios'] = [];
    }

    $calendarios = $resultado['calendarios'];
    foreach (['general', 'empresas', 'departamentos', 'empleados'] as $capa) {
        if (!isset($calendarios[$capa]) || !is_array($calendarios[$capa])) {
            $calendarios[$capa] = [];
        }
    }

    if (!isset($calendarios['horarios']) || !is_array($calendarios['horarios'])) {
        $calendarios['horarios'] = [];
    }

    if (!isset($calendarios['horarios']['general']) || !is_array($calendarios['horarios']['general'])) {
        $calendarios['horarios']['general'] = [
            'desdeM' => '',
            'hastaM' => '',
            'desdeT' => '',
            'hastaT' => '',
        ];
    }

    foreach (['empresas', 'departamentos', 'empleados'] as $capaHorario) {
        if (!isset($calendarios['horarios'][$capaHorario]) || !is_array($calendarios['horarios'][$capaHorario])) {
            $calendarios['horarios'][$capaHorario] = [];
        }
    }

    foreach (['horariosFecha', 'horariosSemana', 'feriadosSemana'] as $claveCalendario) {
        if (!isset($calendarios[$claveCalendario]) || !is_array($calendarios[$claveCalendario])) {
            $calendarios[$claveCalendario] = [];
        }

        foreach (['general', 'empresas', 'departamentos', 'empleados'] as $capa) {
            if (!isset($calendarios[$claveCalendario][$capa]) || !is_array($calendarios[$claveCalendario][$capa])) {
                $calendarios[$claveCalendario][$capa] = [];
            }
        }
    }

    $resultado['calendarios'] = $calendarios;
    return $resultado;
}

function obtenerIdRol(PDO $conexion, string $rolCliente): int
{
    $rolNormalizado = mb_strtolower($rolCliente, 'UTF-8');
    $candidatos = $rolNormalizado === 'admin'
        ? ['admin', 'administrador']
        : ['analista', 'usuario'];

    $sql = 'SELECT id_rol
            FROM roles
            WHERE LOWER(nombre_rol) IN (?, ?)
            ORDER BY id_rol ASC
            LIMIT 1';

    $consulta = $conexion->prepare($sql);
    $consulta->execute($candidatos);
    $fila = $consulta->fetch();

    if ($fila && isset($fila['id_rol'])) {
        return (int)$fila['id_rol'];
    }

    $nombreNuevo = $rolNormalizado === 'admin' ? 'administrador' : 'analista';
    $insertar = $conexion->prepare('INSERT INTO roles (nombre_rol) VALUES (:nombre_rol)');
    $insertar->execute([
        'nombre_rol' => $nombreNuevo,
    ]);

    return (int)$conexion->lastInsertId();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(false, 'Método HTTP no permitido.');
}

$accion = post('accion');
if ($accion === '') {
    responder(false, 'Acción requerida.');
}

try {
    $conexion = obtenerConexionPdo();
    asegurarTablaPersistencia($conexion);

    switch ($accion) {
        case 'crear_empresa':
            $nombre = post('nombre');
            $rif = strtoupper(post('rif'));
            $causa = post('causa');

            if ($nombre === '' || $rif === '') {
                responder(false, 'Nombre y RIF son obligatorios.');
            }

            $validar = $conexion->prepare('SELECT id_empresa FROM Empresa WHERE rif_empresa = :rif LIMIT 1');
            $validar->execute(['rif' => $rif]);
            if ($validar->fetch()) {
                responder(false, 'El RIF de la empresa ya existe.');
            }

            $insertar = $conexion->prepare(
                'INSERT INTO Empresa (rif_empresa, nombre, causa, id_horario)
                 VALUES (:rif_empresa, :nombre, :causa, NULL)'
            );
            $insertar->execute([
                'rif_empresa' => $rif,
                'nombre' => $nombre,
                'causa' => $causa,
            ]);

            responder(true, 'Empresa registrada en base de datos.');
            break;

        case 'crear_departamento':
            $empresaNombre = post('empresa');
            $nombreDepto = post('nombre');
            $causa = post('causa');

            if ($empresaNombre === '' || $nombreDepto === '') {
                responder(false, 'Empresa y nombre del departamento son obligatorios.');
            }

            $empresaQuery = $conexion->prepare('SELECT id_empresa FROM Empresa WHERE nombre = :nombre LIMIT 1');
            $empresaQuery->execute(['nombre' => $empresaNombre]);
            $empresa = $empresaQuery->fetch();
            if (!$empresa) {
                responder(false, 'La empresa seleccionada no existe en base de datos.');
            }

            $idEmpresa = (int)$empresa['id_empresa'];

            $existe = $conexion->prepare(
                'SELECT id_departamento
                 FROM departamento
                 WHERE id_empresa = :id_empresa AND nombre_departamento = :nombre
                 LIMIT 1'
            );
            $existe->execute([
                'id_empresa' => $idEmpresa,
                'nombre' => $nombreDepto,
            ]);
            if ($existe->fetch()) {
                responder(false, 'El departamento ya existe en la empresa seleccionada.');
            }

            $insertar = $conexion->prepare(
                'INSERT INTO departamento (nombre_departamento, causa, id_empresa, id_usuario, id_horario)
                 VALUES (:nombre_departamento, :causa, :id_empresa, NULL, NULL)'
            );
            $insertar->execute([
                'nombre_departamento' => $nombreDepto,
                'causa' => $causa,
                'id_empresa' => $idEmpresa,
            ]);

            responder(true, 'Departamento registrado en base de datos.');
            break;

        case 'crear_empleado':
            $codigo = strtoupper(post('codigo'));
            $cedula = strtoupper(post('cedula'));
            $rif = strtoupper(post('rif'));
            $nombres = post('nombres');
            $apellidos = post('apellidos');
            $cargo = post('cargo');

            if ($codigo === '' || $cedula === '' || $rif === '' || $nombres === '' || $apellidos === '' || $cargo === '') {
                responder(false, 'Todos los campos de empleado son obligatorios.');
            }

            $existe = $conexion->prepare(
                'SELECT id_empleado
                 FROM Empleados
                 WHERE cedula_empleado = :cedula
                 LIMIT 1'
            );
            $existe->execute(['cedula' => $cedula]);
            if ($existe->fetch()) {
                responder(false, 'La cédula del empleado ya existe.');
            }

            $insertar = $conexion->prepare(
                'INSERT INTO Empleados
                (es_supervisor, codigo_empleado, cedula_empleado, rif_empleado, nombre, apellido, cargo, id_horario)
                 VALUES (0, :codigo, :cedula, :rif, :nombre, :apellido, :cargo, NULL)'
            );
            $insertar->execute([
                'codigo' => $codigo,
                'cedula' => $cedula,
                'rif' => $rif,
                'nombre' => $nombres,
                'apellido' => $apellidos,
                'cargo' => $cargo,
            ]);

            responder(true, 'Empleado registrado en base de datos.');
            break;

        case 'crear_usuario':
            $usuario = post('usuario');
            $password = post('password');
            $rol = post('rol');

            if ($usuario === '' || $password === '' || $rol === '') {
                responder(false, 'Usuario, contraseña y rol son obligatorios.');
            }

            $existe = $conexion->prepare('SELECT id_usuario FROM usuarios WHERE usuario = :usuario LIMIT 1');
            $existe->execute(['usuario' => $usuario]);
            if ($existe->fetch()) {
                responder(false, 'El nombre de usuario ya existe.');
            }

            $idRol = obtenerIdRol($conexion, $rol);
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $insertar = $conexion->prepare(
                'INSERT INTO usuarios (usuario, id_rol, contraseña, es_activo, ult_conexion)
                 VALUES (:usuario, :id_rol, :contrasena, 1, NOW())'
            );
            $insertar->execute([
                'usuario' => $usuario,
                'id_rol' => $idRol,
                'contrasena' => $hash,
            ]);

            responder(true, 'Usuario registrado en base de datos.');
            break;

        case 'listar_usuarios':
            $consulta = $conexion->query(
                'SELECT u.id_usuario, u.usuario, u.id_rol, u.es_activo, r.nombre_rol
                 FROM usuarios u
                 LEFT JOIN roles r ON r.id_rol = u.id_rol
                 ORDER BY u.usuario ASC'
            );
            $usuarios = $consulta->fetchAll();
            responder(true, 'Usuarios cargados desde base de datos.', [
                'usuarios' => $usuarios,
            ]);
            break;

        case 'actualizar_usuario':
            $usuarioOriginal = post('usuario_original');
            $usuarioNuevo = post('usuario');
            $password = post('password');
            $rol = post('rol');
            $estado = postInt('estado', 1) === 1 ? 1 : 0;

            if ($usuarioOriginal === '' || $usuarioNuevo === '' || $rol === '') {
                responder(false, 'Usuario original, usuario nuevo y rol son obligatorios.');
            }

            $idRol = obtenerIdRol($conexion, $rol);
            $params = [
                'usuario_nuevo' => $usuarioNuevo,
                'id_rol' => $idRol,
                'es_activo' => $estado,
                'usuario_original' => $usuarioOriginal,
            ];

            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $actualizar = $conexion->prepare(
                    'UPDATE usuarios
                     SET usuario = :usuario_nuevo,
                         id_rol = :id_rol,
                         es_activo = :es_activo,
                         contraseña = :contrasena
                     WHERE usuario = :usuario_original'
                );
                $params['contrasena'] = $hash;
            } else {
                $actualizar = $conexion->prepare(
                    'UPDATE usuarios
                     SET usuario = :usuario_nuevo,
                         id_rol = :id_rol,
                         es_activo = :es_activo
                     WHERE usuario = :usuario_original'
                );
            }

            $actualizar->execute($params);
            if ($actualizar->rowCount() === 0) {
                responder(false, 'No se encontró el usuario en base de datos.');
            }

            responder(true, 'Usuario actualizado en base de datos.');
            break;

        case 'eliminar_usuario':
            $usuario = post('usuario');
            if ($usuario === '') {
                responder(false, 'Usuario requerido.');
            }

            $eliminar = $conexion->prepare('DELETE FROM usuarios WHERE usuario = :usuario');
            $eliminar->execute(['usuario' => $usuario]);
            if ($eliminar->rowCount() === 0) {
                responder(false, 'No se encontró el usuario en base de datos.');
            }
            responder(true, 'Usuario eliminado en base de datos.');
            break;

        case 'cambiar_estado_usuario':
            $usuario = post('usuario');
            $estado = (int)post('estado', '1');
            $estado = $estado === 1 ? 1 : 0;

            if ($usuario === '') {
                responder(false, 'Usuario requerido.');
            }

            $actualizar = $conexion->prepare(
                'UPDATE usuarios
                 SET es_activo = :es_activo
                 WHERE usuario = :usuario'
            );
            $actualizar->execute([
                'es_activo' => $estado,
                'usuario' => $usuario,
            ]);

            if ($actualizar->rowCount() === 0) {
                responder(false, 'No se encontró el usuario en base de datos.');
            }

            responder(true, 'Estado de usuario actualizado en base de datos.');
            break;

        case 'obtener_datos_sistema':
            $datos = leerStorageJson($conexion, 'datos_gestion', []);
            $datos = normalizarDatosSistema($datos);
            responder(true, 'Datos de sistema cargados desde base de datos.', [
                'datos' => $datos,
            ]);
            break;

        case 'guardar_datos_sistema':
            $datos = postJsonArray('datos');
            $datosLimpios = normalizarDatosSistema($datos);
            guardarStorageJson($conexion, 'datos_gestion', $datosLimpios);
            responder(true, 'Datos de sistema guardados en base de datos.');
            break;

        case 'obtener_roles_sistema':
            $roles = leerStorageJson($conexion, 'roles_sistema', []);
            responder(true, 'Roles cargados desde base de datos.', [
                'roles' => $roles,
            ]);
            break;

        case 'guardar_roles_sistema':
            $roles = postJsonArray('roles');
            guardarStorageJson($conexion, 'roles_sistema', $roles);
            responder(true, 'Roles guardados en base de datos.');
            break;

        case 'obtener_auditorias':
            $auditorias = leerStorageJson($conexion, 'auditorias', []);
            responder(true, 'Auditorías cargadas desde base de datos.', [
                'auditorias' => $auditorias,
            ]);
            break;

        case 'registrar_auditoria':
            $usuario = post('usuario');
            $accionAuditoria = post('accion_auditoria');
            $detalle = post('detalle');
            $fecha = post('fecha');

            if ($usuario === '' || $accionAuditoria === '' || $fecha === '') {
                responder(false, 'Usuario, acción y fecha de auditoría son obligatorios.');
            }

            $auditorias = leerStorageJson($conexion, 'auditorias', []);
            $auditorias[] = [
                'usuario' => $usuario,
                'accion' => $accionAuditoria,
                'detalle' => $detalle,
                'fecha' => $fecha,
            ];
            guardarStorageJson($conexion, 'auditorias', $auditorias);
            responder(true, 'Auditoría registrada en base de datos.');
            break;

        default:
            responder(false, 'Acción no reconocida.');
    }
} catch (Throwable $error) {
    error_log('Error en gestion_api.php: ' . $error->getMessage());
    responder(false, 'Error de base de datos. Verifique tablas y credenciales.');
}

