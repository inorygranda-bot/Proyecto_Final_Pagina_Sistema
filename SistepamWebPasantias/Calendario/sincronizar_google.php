<?php
header('Content-Type: application/json');

$apiKey = 'AIzaSyCCchbbPcQVRODZj4hZP86UyAhidKAOq6g'; 

$calendarId = 'es.ve#holiday@group.v.calendar.google.com';

$timeMin = '2026-01-01T00:00:00Z';
$timeMax = '2026-12-31T23:59:59Z';

$url = "https://www.googleapis.com/calendar/v3/calendars/" . urlencode($calendarId) . "/events?key=" . $apiKey . "&timeMin=" . $timeMin . "&timeMax=" . $timeMax . "&singleEvents=true&orderBy=startTime";

$response = @file_get_contents($url);

if ($response === FALSE) {
    echo json_encode([
        "success" => false, 
        "message" => "Error al conectar con Google. Revisa tu API Key o conexión a internet."
    ]);
    exit;
}

$data = json_decode($response, true);
$eventosFinales = [];

if (isset($data['items'])) {
    foreach ($data['items'] as $item) {

        $fechaRaw = isset($item['start']['date']) ? $item['start']['date'] : $item['start']['dateTime'];
        $fechaFormateada = substr($fechaRaw, 0, 10); 
        
        $eventosFinales[] = [
            "fecha" => $fechaFormateada,
            "summary" => $item['summary'],
            "laboral" => false 
        ];
    }
}

echo json_encode([
    "success" => true,
    "eventos" => $eventosFinales
]);