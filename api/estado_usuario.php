<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db/conexion.php';

session_start();
$usuario_id = $_GET['id'] ?? ($_SESSION['usuario_id'] ?? null);

if (!$usuario_id) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$stmt = $pdo->prepare('SELECT id, nombre, email, "estadoPago", "codigoEstudiante" FROM usuarios WHERE id = ?');
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch();

echo json_encode($usuario);
?>