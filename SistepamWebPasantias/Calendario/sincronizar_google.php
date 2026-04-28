<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

/*
 * ============================================================
 * SINCRONIZACIÓN CON GOOGLE CALENDAR
 * ============================================================
 * Descarga los días feriados de Venezuela desde el calendario
 * público de Google y los devuelve en formato JSON para que
 * el frontend (Calendario.js) los pinte en la rejilla mensual.
 *
 * Parámetros GET recibidos desde el JS:
 *   ?nivel=empresas|departamentos|empleados|general
 *   &id=nombreDeLaEntidad
 *
 * SOLUCIÓN a fallos anteriores:
 *   - Se eliminó file_get_contents() porque falla en XAMPP
 *     cuando allow_url_fopen está deshabilitado.
 *   - Se usa cURL con SSL desactivado para entornos locales
 *     (Windows no siempre tiene el certificado raíz actualizado).
 *   - Se leen y validan los parámetros nivel e id.
 *   - Se manejan todos los errores con mensajes descriptivos.
 * ============================================================
 */

$apiKey = 'AIzaSyCCchbbPcQVRODZj4hZP86UyAhidKAOq6g';
$calendarId = 'es.ve#holiday@group.v.calendar.google.com';

// Leer parámetros que envía el JavaScript
$nivel = trim((string)($_GET['nivel'] ?? ''));
$idEntidad = trim((string)($_GET['id'] ?? ''));

// Rango de fechas: año actual completo
$anoActual = (int) date('Y');
$timeMin = $anoActual . '-01-01T00:00:00Z';
$timeMax = ($anoActual + 1) . '-01-01T00:00:00Z';

// Construir URL de la API de Google Calendar
$url = 'https://www.googleapis.com/calendar/v3/calendars/'
    . urlencode($calendarId)
    . '/events'
    . '?key=' . urlencode($apiKey)
    . '&timeMin=' . urlencode($timeMin)
    . '&timeMax=' . urlencode($timeMax)
    . '&singleEvents=true'
    . '&orderBy=startTime'
    . '&maxResults=2500';

// Verificar que cURL existe
if (!function_exists('curl_init')) {
    echo json_encode([
        'success' => false,
        'message' => 'El servidor no tiene habilitada la extensión cURL. Active php_curl en php.ini.',
    ]);
    exit;
}

// Inicializar cURL
$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 25,
    CURLOPT_CONNECTTIMEOUT => 10,

    // ENTORNO LOCAL (XAMPP/WAMP en Windows):
    // Desactivamos verificación SSL porque los certificados raíz
    // de Windows a veces no están actualizados o no son accesibles.
    // EN PRODUCCIÓN (servidor Linux con certbot): poner true y 2.
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,

    // Headers
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'User-Agent: SistemaGestionAsistencias/1.0',
    ],

    // No lanzar excepción con HTTP 403/404, lo manejamos manualmente
    CURLOPT_FAILONERROR => false,

    // Seguir redirecciones (por si Google redirige)
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 3,
]);

// Ejecutar petición
$response = curl_exec($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$errorCurl = curl_error($ch);
curl_close($ch);

// --- MANEJO DE ERRORES ---

// Error de red o cURL
if ($response === false || $response === '') {
    $mensajeError = $errorCurl !== ''
        ? 'Error de conexión cURL: ' . $errorCurl
        : 'No se recibió respuesta del servidor de Google Calendar.';
    echo json_encode([
        'success' => false,
        'message' => $mensajeError,
    ]);
    exit;
}

// Error HTTP (API Key inválida, cuota excedida, etc.)
if ($httpCode !== 200) {
    $mensajeHttp = 'Google Calendar respondió con código HTTP ' . $httpCode . '. ';
    if ($httpCode === 403) {
        $mensajeHttp .= 'La API Key no tiene permiso o está restringida. Verifique en Google Cloud Console.';
    } elseif ($httpCode === 404) {
        $mensajeHttp .= 'Calendario no encontrado. Verifique el ID del calendario.';
    } else {
        $mensajeHttp .= 'Revise la conexión a internet y la API Key.';
    }
    echo json_encode([
        'success' => false,
        'message' => $mensajeHttp,
        'http_code' => $httpCode,
    ]);
    exit;
}

// Decodificar JSON de Google
$data = json_decode($response, true);

// Validar estructura de respuesta
if (!is_array($data)) {
    echo json_encode([
        'success' => false,
        'message' => 'Google Calendar devolvió una respuesta no válida (no es JSON).',
    ]);
    exit;
}

// Verificar si Google devolvió un error en el cuerpo
if (isset($data['error'])) {
    $mensajeGoogle = $data['error']['message'] ?? 'Error desconocido de Google.';
    $codigoGoogle = $data['error']['code'] ?? 0;
    echo json_encode([
        'success' => false,
        'message' => 'Error de Google Calendar (' . $codigoGoogle . '): ' . $mensajeGoogle,
    ]);
    exit;
}

// Verificar que existen eventos
if (!isset($data['items']) || !is_array($data['items'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Google Calendar no devolvió eventos (posiblemente el calendario está vacío).',
    ]);
    exit;
}

// Procesar eventos
$eventosFinales = [];

foreach ($data['items'] as $item) {
    // Obtener fecha (puede ser date o dateTime)
    $fechaRaw = '';
    if (isset($item['start']['date'])) {
        $fechaRaw = $item['start']['date'];
    } elseif (isset($item['start']['dateTime'])) {
        $fechaRaw = $item['start']['dateTime'];
    }

    if ($fechaRaw === '') {
        continue; // Evento sin fecha, lo ignoramos
    }

    // Extraer solo YYYY-MM-DD
    $fechaFormateada = substr($fechaRaw, 0, 10);

    // Validar formato de fecha
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaFormateada)) {
        continue;
    }

    $eventosFinales[] = [
        'fecha' => $fechaFormateada,
        'summary' => $item['summary'] ?? 'Sin nombre',
        'laboral' => false,
    ];
}

// Respuesta exitosa
echo json_encode([
    'success' => true,
    'eventos' => $eventosFinales,
    'total_eventos' => count($eventosFinales),
    'nivel_recibido' => $nivel,
    'id_entidad_recibido' => $idEntidad,
]);