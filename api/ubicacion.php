<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

// Comprobar la conexión con la ruta absoluta correcta
if (file_exists(__DIR__ . '/../db/conexion.php')) {
    require_once __DIR__ . '/../db/conexion.php';
} else {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Sin conexión a BD']);
    exit;
}

if (!$pdo) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Sin conexión a BD']);
    exit;
}

// 1. ACTUALIZAR UBICACIÓN (Petición POST del Conductor)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $busId = isset($_POST['bus_id']) ? intval($_POST['bus_id']) : 1;
    $lat = $_POST['lat'] ?? $_POST['latitud'] ?? null;
    $lng = $_POST['lng'] ?? $_POST['longitud'] ?? null;
    $velocidad = $_POST['velocidad'] ?? 0;

    if ($lat !== null && $lng !== null) {
        try {
            // Se corrigieron las comillas simples de 'en_ruta' usando escapes \'en_ruta\'
            $stmt = $pdo->prepare('UPDATE buses 
                                   SET latitud = ?, longitud = ?, velocidad = ?, estado = \'en_ruta\', "ultimaActualizacion" = NOW() 
                                   WHERE id = ?');
            $stmt->execute([$lat, $lng, $velocidad, $busId]);

            ob_end_clean();
            echo json_encode([
                'status' => 'success', 
                'lat' => floatval($lat),
                'lng' => floatval($lng),
                'latitud' => floatval($lat), 
                'longitud' => floatval($lng), 
                'estado' => 'en_ruta'
            ]);
            exit;
        } catch (PDOException $e) {
            // Respaldo por si ultimaActualizacion está en minúsculas en PostgreSQL
            try {
                $stmt = $pdo->prepare('UPDATE buses 
                                       SET latitud = ?, longitud = ?, velocidad = ?, estado = \'en_ruta\', ultimaactualizacion = NOW() 
                                       WHERE id = ?');
                $stmt->execute([$lat, $lng, $velocidad, $busId]);

                ob_end_clean();
                echo json_encode([
                    'status' => 'success', 
                    'lat' => floatval($lat),
                    'lng' => floatval($lng),
                    'latitud' => floatval($lat), 
                    'longitud' => floatval($lng), 
                    'estado' => 'en_ruta'
                ]);
                exit;
            } catch (PDOException $ex) {
                ob_end_clean();
                echo json_encode(['status' => 'error', 'message' => $ex->getMessage()]);
                exit;
            }
        }
    }
}

// 2. CONSULTAR UBICACIÓN (Petición GET de Estudiante y Administrador)
try {
    $busIdConsulta = isset($_GET['bus_id']) ? intval($_GET['bus_id']) : null;

    if ($busIdConsulta) {
        $stmt = $pdo->prepare('SELECT b.id, b.placa, b.latitud, b.longitud, b.velocidad, b.estado, b."ultimaActualizacion", r.nombre as "rutaNombre"
                               FROM buses b 
                               LEFT JOIN rutas r ON b."rutaId" = r.id 
                               WHERE b.id = ?');
        $stmt->execute([$busIdConsulta]);
    } else {
        // Si no se especifica ID, trae el bus con actualización más reciente que tenga coordenadas
        $stmt = $pdo->query('SELECT b.id, b.placa, b.latitud, b.longitud, b.velocidad, b.estado, b."ultimaActualizacion", r.nombre as "rutaNombre"
                             FROM buses b 
                             LEFT JOIN rutas r ON b."rutaId" = r.id OR b.rutaid = r.id
                             WHERE b.latitud IS NOT NULL AND b.longitud IS NOT NULL
                             ORDER BY b.id DESC LIMIT 1');
    }

    $bus = $stmt->fetch(PDO::FETCH_ASSOC);

    ob_end_clean();
    if ($bus && $bus['latitud'] !== null && $bus['longitud'] !== null) {
        $lat = floatval($bus['latitud']);
        $lng = floatval($bus['longitud']);

        echo json_encode([
            'status' => 'success',
            'id' => $bus['id'],
            'placa' => $bus['placa'],
            'lat' => $lat,
            'lng' => $lng,
            'latitud' => $lat,
            'longitud' => $lng,
            'velocidad' => $bus['velocidad'] ?? 0,
            'estado' => $bus['estado'],
            'rutaNombre' => $bus['rutaNombre'] ?? $bus['rutanombre'] ?? 'Ruta Sin Nombre',
            'ultimaActualizacion' => $bus['ultimaActualizacion'] ?? $bus['ultimaactualizacion'] ?? null
        ]);
    } else {
        echo json_encode([
            'status' => 'empty',
            'estado' => 'inactivo', 
            'lat' => null,
            'lng' => null,
            'latitud' => null, 
            'longitud' => null
        ]);
    }
} catch (PDOException $e) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>