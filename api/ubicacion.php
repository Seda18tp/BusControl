<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../db/conexion.php';

if (!$pdo) {
    echo json_encode(['status' => 'error', 'message' => 'Sin conexión a BD']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // RECIBIR Y GUARDAR GPS DEL CONDUCTOR
    $busId = isset($_POST['bus_id']) ? intval($_POST['bus_id']) : 1;
    $lat = $_POST['lat'] ?? $_POST['latitud'] ?? null;
    $lng = $_POST['lng'] ?? $_POST['longitud'] ?? null;
    $velocidad = $_POST['velocidad'] ?? 0;

    if ($lat !== null && $lng !== null) {
        try {
            $stmt = $pdo->prepare('UPDATE buses 
                                   SET latitud = ?, longitud = ?, velocidad = ?, estado = \'en_ruta\', "ultimaActualizacion" = NOW() 
                                   WHERE id = ?');
            $stmt->execute([$lat, $lng, $velocidad, $busId]);

            echo json_encode(['status' => 'success', 'lat' => floatval($lat), 'lng' => floatval($lng)]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            exit;
        }
    }
} else if ($method === 'GET') {
    // CONSULTAR GPS PARA ADMIN Y ESTUDIANTE
    try {
        $stmt = $pdo->query('SELECT b.id, b.placa, b.latitud, b.longitud, b.velocidad, b.estado, r.nombre as "rutaNombre"
                             FROM buses b 
                             LEFT JOIN rutas r ON b."rutaId" = r.id OR b.rutaid = r.id
                             WHERE b.latitud IS NOT NULL AND b.longitud IS NOT NULL 
                             ORDER BY b.id DESC LIMIT 1');
        $bus = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($bus && $bus['latitud'] !== null) {
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
                'estado' => $bus['estado'] ?? 'en_ruta',
                'rutaNombre' => $bus['rutaNombre'] ?? $bus['rutanombre'] ?? 'Ruta Principal'
            ]);
        } else {
            echo json_encode(['status' => 'empty', 'lat' => null, 'lng' => null]);
        }
        exit;
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}
?>