<?php
// Permitir CORS y respuestas JSON limpias
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

require_once __DIR__ . '/../db/conexion.php';

if (!$pdo) {
    echo json_encode(['status' => 'error', 'message' => 'Sin conexion a BD']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// 1. RECIBIR GPS DEL CONDUCTOR (POST)
if ($method === 'POST') {
    $busId = !empty($_POST['bus_id']) ? intval($_POST['bus_id']) : 1;
    $lat = $_POST['lat'] ?? $_POST['latitud'] ?? null;
    $lng = $_POST['lng'] ?? $_POST['longitud'] ?? null;
    $velocidad = $_POST['velocidad'] ?? 0;

    if ($lat !== null && $lng !== null) {
        try {
            // Actualización directa en Supabase
            $stmt = $pdo->prepare('UPDATE buses 
                                   SET latitud = ?, longitud = ?, velocidad = ?, estado = \'en_ruta\' 
                                   WHERE id = ?');
            $stmt->execute([$lat, $lng, $velocidad, $busId]);

            echo json_encode(['status' => 'success', 'lat' => floatval($lat), 'lng' => floatval($lng)]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            exit;
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Coordenadas nulas']);
        exit;
    }
} 

// 2. RETORNAR GPS A ESTUDIANTE Y ADMIN (GET)
if ($method === 'GET') {
    try {
        // Consulta sin filtros restrictivos de fecha/hora para evitar desfaces de zona horaria de Vercel/Supabase
        $stmt = $pdo->query('SELECT id, placa, latitud, longitud, velocidad, estado 
                             FROM buses 
                             WHERE latitud IS NOT NULL AND longitud IS NOT NULL 
                             ORDER BY id DESC LIMIT 1');
        $bus = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($bus && $bus['latitud'] !== null && $bus['longitud'] !== null) {
            $lat = floatval($bus['latitud']);
            $lng = floatval($bus['longitud']);

            echo json_encode([
                'status' => 'success',
                'id' => $bus['id'],
                'placa' => $bus['placa'] ?? 'BUS-001',
                'lat' => $lat,
                'lng' => $lng,
                'latitud' => $lat,
                'longitud' => $lng,
                'velocidad' => $bus['velocidad'] ?? 0,
                'estado' => $bus['estado'] ?? 'en_ruta'
            ]);
        } else {
            echo json_encode(['status' => 'empty', 'message' => 'No hay coordenadas en BD']);
        }
        exit;
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}
?>