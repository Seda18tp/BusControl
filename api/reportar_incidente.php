<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db/conexion.php';

if (!$pdo) {
    echo json_encode(['status' => 'error', 'message' => 'Sin conexión a la base de datos']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = trim($_POST['tipo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $busId = !empty($_POST['bus_id']) ? $_POST['bus_id'] : null;
    $rutaId = !empty($_POST['ruta_id']) ? $_POST['ruta_id'] : null;
    $conductorId = !empty($_POST['conductor_id']) ? $_POST['conductor_id'] : null;

    if (!empty($tipo) && !empty($descripcion)) {
        try {
            $stmt = $pdo->prepare('INSERT INTO incidentes (tipo, descripcion, "busId", "rutaId", "conductorId", estado, "fechaReporte") 
                                   VALUES (?, ?, ?, ?, ?, \'activo\', NOW())');
            $stmt->execute([$tipo, $descripcion, $busId, $rutaId, $conductorId]);

            echo json_encode(['status' => 'success', 'message' => 'Incidente registrado correctamente']);
        } catch (PDOException $e) {
            echo json_encode([
                'status' => 'error', 
                'message' => 'Error al guardar en BD: ' . $e->getMessage()
            ]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Todos los campos son requeridos']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
}
?>