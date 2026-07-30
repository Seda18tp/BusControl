<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

if (file_exists('../db/conexion.php')) {
require_once __DIR__ . '/../db/conexion.php';
} else {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Sin conexión a BD']);
    exit;
}

// 1. ACTUALIZAR UBICACIÓN (Petición POST del Conductor)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $busId = isset($_POST['bus_id']) ? intval($_POST['bus_id']) : 1;
    $lat = $_POST['lat'] ?? null;
    $lng = $_POST['lng'] ?? null;

    if ($lat !== null && $lng !== null) {
        // Actualizar coordenadas y cambiar el estado a 'en_ruta'
        $stmt = $pdo->prepare('UPDATE buses SET latitud = ?, longitud = ?, estado = 'en_ruta', "ultimaActualizacion" = NOW() WHERE id = ?');
        $stmt->execute([$lat, $lng, $busId]);

        ob_end_clean();
        echo json_encode([
            'status' => 'success', 
            'latitud' => $lat, 
            'longitud' => $lng, 
            'estado' => 'en_ruta'
        ]);
        exit;
    }
}

// 2. CONSULTAR UBICACIÓN (Petición GET de Estudiante y Administrador)
$stmt = $pdo->prepare('SELECT b.id, b.placa, b.latitud, b.longitud, b.estado, b."ultimaActualizacion", r.nombre as "rutaNombre"
                       FROM buses b 
                       LEFT JOIN rutas r ON b."rutaId" = r.id 
                       WHERE b.id = 1');
$stmt->execute();
$bus = $stmt->fetch(PDO::FETCH_ASSOC);

ob_end_clean();
if ($bus && $bus['latitud'] !== null && $bus['longitud'] !== null) {
    echo json_encode($bus);
} else {
    echo json_encode([
        'estado' => 'inactivo', 
        'latitud' => null, 
        'longitud' => null
    ]);
}
?>