<?php
declare(strict_types=1);

require_once __DIR__ . '/../datos/conexionBD.php';

function respuestaError(string $mensaje): void
{
    header('Content-Type: text/plain; charset=utf-8');
    echo $mensaje;
    exit();
}

function obtenerFormato(): string
{
    $formato = $_POST['formato'] ?? $_GET['formato'] ?? 'txt';
    $formato = strtolower(trim($formato));
    return in_array($formato, ['txt', 'pdf', 'excel'], true) ? $formato : 'txt';
}

function obtenerCedula(): string
{
    return trim($_POST['cedula'] ?? '');
}

function obtenerFechaDesde(): ?string
{
    $fecha = trim($_POST['fechaDesde'] ?? '');
    return $fecha !== '' ? $fecha : null;
}

function obtenerFechaHasta(): ?string
{
    $fecha = trim($_POST['fechaHasta'] ?? '');
    return $fecha !== '' ? $fecha : null;
}

function fechaEnRango(string $fechaRegistro, ?string $desde, ?string $hasta): bool
{
    if (!$desde && !$hasta) return true;

    // Convertir fechaRegistro de xx/xx/xx a DateTime
    $partes = explode('/', $fechaRegistro);
    if (count($partes) !== 3) return false;
    $dia = (int)$partes[0];
    $mes = (int)$partes[1];
    $ano = 2000 + (int)$partes[2]; // Asumir 20xx
    $fechaReg = DateTime::createFromFormat('Y-m-d', sprintf('%04d-%02d-%02d', $ano, $mes, $dia));
    if (!$fechaReg) return false;

    if ($desde) {
        $fechaDesde = DateTime::createFromFormat('Y-m-d', $desde);
        if ($fechaDesde && $fechaReg < $fechaDesde) return false;
    }

    if ($hasta) {
        $fechaHasta = DateTime::createFromFormat('Y-m-d', $hasta);
        if ($fechaHasta && $fechaReg > $fechaHasta) return false;
    }

    return true;
}

function leerArchivoSubido(): array
{
    if (!isset($_FILES['asistenciaTxt'])) {
        return [];
    }

    $archivo = $_FILES['asistenciaTxt'];
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        respuestaError('No se pudo subir el archivo de asistencia.');
    }

    $tmp = $archivo['tmp_name'];
    if (!is_readable($tmp)) {
        respuestaError('El archivo subido no se puede leer.');
    }

    $contenido = file_get_contents($tmp);
    $lineas = preg_split('/\r?\n/', trim($contenido));
    $registros = [];

    foreach ($lineas as $indice => $linea) {
        $linea = trim($linea);
        if ($linea === '') {
            continue;
        }

        $partes = preg_split('/\s+/', $linea);
        if (count($partes) !== 5) {
            continue;
        }

        [$idBiometrico, $cedula, $fecha, $hora, $departamento] = $partes;
        $cedula = trim($cedula);
        $fecha = trim($fecha);
        $hora = trim($hora);
        $departamento = trim($departamento);

        if (!preg_match('/^\d+$/', $idBiometrico)) {
            continue;
        }
        if (!preg_match('/^\d+$/', $cedula)) {
            continue;
        }
        if (!preg_match('/^\d{2}\/\d{2}\/\d{2}$/', $fecha)) {
            continue;
        }
        if (!preg_match('/^\d{2}:\d{2}$/', $hora)) {
            continue;
        }
        if (!preg_match('/^-?\d+$/', $departamento)) {
            continue;
        }

        $registros[] = [
            'idBiometrico' => $idBiometrico,
            'cedula' => $cedula,
            'fecha' => $fecha,
            'hora' => $hora,
            'departamento' => (int)$departamento,
        ];
    }

    return $registros;
}

function agruparAsistencias(array $registros): array
{
    $agrupados = [];
    foreach ($registros as $registro) {
        $clave = $registro['cedula'] . '|' . $registro['fecha'];
        $agrupados[$clave][] = $registro;
    }
    return $agrupados;
}

function ordenarHoras(array &$registros): void
{
    usort($registros, function (array $a, array $b) {
        [$hA, $mA] = explode(':', $a['hora']);
        [$hB, $mB] = explode(':', $b['hora']);
        return ((int)$hA * 60 + (int)$mA) - ((int)$hB * 60 + (int)$mB);
    });
}

function calcularEstado(?string $entrada, ?string $salida): string
{
    if (!$entrada && !$salida) {
        return 'SIN_MARCAS';
    }
    if ($entrada && !$salida) {
        return 'SOLO_ENTRADA';
    }
    if (!$entrada && $salida) {
        return 'SOLO_SALIDA';
    }
    return 'COMPLETA';
}

function analizarGrupo(array $registros): array
{
    ordenarHoras($registros);
    $entrada = $registros[0]['hora'] ?? null;
    $salida = $registros[count($registros) - 1]['hora'] ?? null;
    $estado = calcularEstado($entrada, $salida);
    return [
        'entrada' => $entrada,
        'salida' => $salida,
        'estado' => $estado,
        'marcas' => array_map(fn($r) => $r['hora'], $registros),
        'departamento' => $registros[0]['departamento'] ?? null,
    ];
}

function obtenerEmpleadosPorCedula(PDO $conexion): array
{
    $query = 'SELECT e.nombre, e.apellido, e.codigo_empleado, e.cedula_empleado, d.nombre_departamento, emp.nombre AS nombre_empresa
              FROM Empleados e
              LEFT JOIN departamento d ON e.id_departamento = d.id_departamento
              LEFT JOIN Empresa emp ON d.id_empresa = emp.id_empresa';
    $stmt = $conexion->query($query);
    $empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $porCedula = [];
    foreach ($empleados as $emp) {
        $llave = trim((string)($emp['cedula_empleado'] ?? ''));
        if ($llave !== '') {
            $porCedula[$llave] = $emp;
        }
    }
    return $porCedula;
}

$formato = obtenerFormato();
$cedulaFiltro = obtenerCedula();
$fechaDesde = obtenerFechaDesde();
$fechaHasta = obtenerFechaHasta();
$registros = leerArchivoSubido();

try {
    $conexion = obtenerConexionPdo();
    $empleadosPorCedula = obtenerEmpleadosPorCedula($conexion);

    if (empty($registros)) {
        respuestaError('Debe subir un archivo TXT válido con las asistencias.');
    }

    if ($cedulaFiltro === '') {
        respuestaError('Debe seleccionar un empleado.');
    }

    // Filtrar registros por cedula y fechas
    $registrosFiltrados = array_filter($registros, function($reg) use ($cedulaFiltro, $fechaDesde, $fechaHasta) {
        return $reg['cedula'] === $cedulaFiltro && fechaEnRango($reg['fecha'], $fechaDesde, $fechaHasta);
    });

    if (empty($registrosFiltrados)) {
        respuestaError('No hay asistencias para el empleado y rango de fechas seleccionado.');
    }

    $agrupados = agruparAsistencias($registrosFiltrados);
    $asistencias = [];
    foreach ($agrupados as $clave => $registrosGrupo) {
        [$cedula, $fecha] = explode('|', $clave);
        $analisis = analizarGrupo($registrosGrupo);
        $empleado = $empleadosPorCedula[$cedula] ?? null;

        $asistencias[] = [
            'cedula' => $cedula,
            'fecha' => $fecha,
            'entrada' => $analisis['entrada'],
            'salida' => $analisis['salida'],
            'estado' => $analisis['estado'],
            'departamento' => $analisis['departamento'],
            'nombre' => $empleado['nombre'] ?? 'DESCONOCIDO',
            'apellido' => $empleado['apellido'] ?? '',
            'codigo_empleado' => $empleado['codigo_empleado'] ?? '',
            'nombre_departamento' => $empleado['nombre_departamento'] ?? 'N/A',
            'nombre_empresa' => $empleado['nombre_empresa'] ?? 'N/A',
        ];
    }

    $nombreArchivo = 'asistencia_' . $cedulaFiltro . '_' . date('Ymd_His');

    if ($formato === 'pdf') {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '.pdf"');
        echo "%PDF-1.4\n";
        echo "1 0 obj\n";
        echo "<<\n";
        echo "/Type /Catalog\n";
        echo "/Pages 2 0 R\n";
        echo ">>\n";
        echo "endobj\n";
        echo "2 0 obj\n";
        echo "<<\n";
        echo "/Type /Pages\n";
        echo "/Kids [3 0 R]\n";
        echo "/Count 1\n";
        echo ">>\n";
        echo "endobj\n";
        echo "3 0 obj\n";
        echo "<<\n";
        echo "/Type /Page\n";
        echo "/Parent 2 0 R\n";
        echo "/MediaBox [0 0 612 792]\n";
        echo "/Contents 4 0 R\n";
        echo "/Resources << /Font << /F1 5 0 R >> >>\n";
        echo ">>\n";
        echo "endobj\n";
        echo "4 0 obj\n";
        echo "<<\n";
        echo "/Length 200\n";
        echo ">>\n";
        echo "stream\n";
        echo "BT\n";
        echo "/F1 12 Tf\n";
        echo "50 750 Td\n";
        echo "(ASISTENCIA DE EMPLEADO) Tj\n";
        echo "ET\n";
        echo "endstream\n";
        echo "endobj\n";
        echo "5 0 obj\n";
        echo "<<\n";
        echo "/Type /Font\n";
        echo "/Subtype /Type1\n";
        echo "/BaseFont /Helvetica\n";
        echo ">>\n";
        echo "endobj\n";
        echo "xref\n";
        echo "0 6\n";
        echo "0000000000 65535 f \n";
        echo "0000000009 00000 n \n";
        echo "0000000058 00000 n \n";
        echo "0000000115 00000 n \n";
        echo "0000000274 00000 n \n";
        echo "0000000410 00000 n \n";
        echo "trailer\n";
        echo "<<\n";
        echo "/Size 6\n";
        echo "/Root 1 0 R\n";
        echo ">>\n";
        echo "startxref\n";
        echo "456\n";
        echo "%%EOF\n";
        exit();
    }

    if ($formato === 'excel') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '.csv"');
        echo "Cedula,Nombre,Apellido,Codigo,Empresa,Departamento,Fecha,Entrada,Salida,Estado\n";
        foreach ($asistencias as $item) {
            echo implode(',', [
                $item['cedula'],
                $item['nombre'],
                $item['apellido'],
                $item['codigo_empleado'],
                $item['nombre_empresa'],
                $item['departamento'],
                $item['fecha'],
                $item['entrada'] ?? '',
                $item['salida'] ?? '',
                $item['estado'],
            ]) . "\n";
        }
        exit();
    }

    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '.txt"');

    $empleado = $empleadosPorCedula[$cedulaFiltro] ?? null;
    $nombreCompleto = $empleado ? $empleado['nombre'] . ' ' . $empleado['apellido'] : 'DESCONOCIDO';

    echo "ASISTENCIA DE EMPLEADO\n";
    echo "=====================\n";
    echo "Empleado: $nombreCompleto\n";
    echo "Cedula: $cedulaFiltro\n";
    echo "Rango: " . ($fechaDesde ?? 'N/A') . " - " . ($fechaHasta ?? 'N/A') . "\n";
    echo "Archivo origen: " . ($_FILES['asistenciaTxt']['name'] ?? 'entrada.txt') . "\n";
    echo "Fecha de generacion: " . date('Y-m-d H:i:s') . "\n";
    echo "Registros filtrados: " . count($registrosFiltrados) . "\n";
    echo "Asistencias: " . count($asistencias) . "\n\n";

    echo "DETALLE DE MARCAS\n";
    foreach ($registrosFiltrados as $reg) {
        echo $reg['idBiometrico'] . ' ' . $reg['cedula'] . ' ' . $reg['fecha'] . ' ' . $reg['hora'] . ' ' . $reg['departamento'] . "\n";
    }

    echo "\nRESUMEN DIARIO\n";
    echo "FECHA\tENTRADA\tSALIDA\tESTADO\n";
    foreach ($asistencias as $asis) {
        echo $asis['fecha'] . "\t" . ($asis['entrada'] ?? '') . "\t" . ($asis['salida'] ?? '') . "\t" . $asis['estado'] . "\n";
    }
    exit();
} catch (Throwable $e) {
    respuestaError('Error al generar nomina: ' . $e->getMessage());
}
?>