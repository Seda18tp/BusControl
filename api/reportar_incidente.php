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
    $busId = $_POST['bus_id'] ?? null;
    $rutaId = $_POST['ruta_id'] ?? null;
    $conductorId = $_POST['conductor_id'] ?? null;

    if (!empty($tipo) && !empty($descripcion)) {
        try {
            // Insertar el incidente registrando el estado activo
            $stmt = $pdo->prepare('INSERT INTO incidentes (tipo, descripcion, "busId", "rutaId", "conductorId", estado, fecha) 
                                   VALUES (?, ?, ?, ?, ?, \'activo\', NOW())');
            $stmt->execute([$tipo, $descripcion, $busId, $rutaId, $conductorId]);

            echo json_encode(['status' => 'success', 'message' => 'Incidente registrado correctamente']);
        } catch (PDOException $e) {
            // Respaldo por si las columnas en Supabase no llevan camelCase
            try {
                $stmt = $pdo->prepare('INSERT INTO incidentes (tipo, descripcion, busid, rutaid, conductorid, estado, fecha) 
                                       VALUES (?, ?, ?, ?, ?, \'activo\', NOW())');
                $stmt->execute([$tipo, $descripcion, $busId, $rutaId, $conductorId]);

                echo json_encode(['status' => 'success', 'message' => 'Incidente registrado correctamente']);
            } catch (PDOException $ex) {
                echo json_encode(['status' => 'error', 'message' => 'Error al guardar en BD: ' . $ex->getMessage()]);
            }
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Todos los campos son requeridos']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
}
?>