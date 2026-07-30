<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (file_exists('../db/conexion.php')) {
    require_once '../db/conexion.php';
} else {
    echo json_encode(['status' => 'error', 'message' => 'Sin conexion BD']);
    exit;
}

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'conductor') {
    echo json_encode(['status' => 'error', 'message' => 'Acceso no autorizado']);
    exit;
}

$conductorId = $_SESSION['usuario_id'];
$tipo = $_POST['tipo'] ?? 'otro';
$desc = trim($_POST['descripcion'] ?? '');

if (empty($desc)) {
    echo json_encode(['status' => 'error', 'message' => 'Ingresa una descripción']);
    exit;
}

// Buscar o crear un viaje activo para asociar el incidente
$stmtViaje = $pdo->prepare("SELECT id FROM viajes WHERE conductorId = ? AND estado = 'activo' ORDER BY id DESC LIMIT 1");
$stmtViaje->execute([$conductorId]);
$viaje = $stmtViaje->fetch(PDO::FETCH_ASSOC);

if (!$viaje) {
    // Si no hay viaje abierto, insertar uno activo por defecto
    $stmtNuevoViaje = $pdo->prepare("INSERT INTO viajes (busId, rutaId, conductorId, estado) VALUES (1, 1, ?, 'activo')");
    $stmtNuevoViaje->execute([$conductorId]);
    $viajeId = $pdo->lastInsertId();
} else {
    $viajeId = $viaje['id'];
}

// Insertar en la tabla incidentes
$stmtInsert = $pdo->prepare("INSERT INTO incidentes (viajeId, conductorId, descripcion, tipo, fechaReporte) VALUES (?, ?, ?, ?, NOW())");
$stmtInsert->execute([$viajeId, $conductorId, $desc, $tipo]);

echo json_encode([
    'status' => 'success',
    'message' => 'Incidente reportado exitosamente'
]);
?>