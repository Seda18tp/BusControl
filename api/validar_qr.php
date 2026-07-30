<?php
// Iniciar buffer de salida para evitar que Warnings/Notice corrompan el JSON
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

session_start();
header('Content-Type: application/json; charset=utf-8');

// Incluir conexión a la base de datos
if (file_exists('../db/conexion.php')) {
    require_once '../db/conexion.php';
} else {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'No se encuentra db/conexion.php']);
    exit;
}

// Validar que la sesión sea de conductor
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'conductor') {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Acceso denegado']);
    exit;
}

$conductorId = $_SESSION['usuario_id'];
$tokenEscaneado = trim($_POST['token'] ?? '');

if (empty($tokenEscaneado)) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Código no proporcionado']);
    exit;
}

try {
    // 1. Buscar el token en la tabla tokens_qr
    $stmt = $pdo->prepare("SELECT t.id, t.estudianteId, t.usado, t.expiraEn, u.nombre 
                           FROM tokens_qr t 
                           JOIN usuarios u ON t.estudianteId = u.id 
                           WHERE t.token = ?");
    $stmt->execute([$tokenEscaneado]);
    $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tokenData) {
        ob_end_clean();
        echo json_encode(['status' => 'invalid', 'message' => 'Código QR Inválido o Inexistente']);
        exit;
    }

    // 2. Verificar si el token ya fue quemado/usado
    if ($tokenData['usado'] == 1) {
        ob_end_clean();
        echo json_encode(['status' => 'used', 'message' => 'Este QR YA FUE USADO']);
        exit;
    }

    // 3. Verificar si el token ya expiró
    if ($tokenData['expiraEn'] < date('Y-m-d H:i:s')) {
        ob_end_clean();
        echo json_encode(['status' => 'expired', 'message' => 'El código QR ha expirado']);
        exit;
    }

    // 4. PASO CLAVE: Quemar/Inactivar el token en la BD (usado = 1)
    $stmtUsado = $pdo->prepare("UPDATE tokens_qr SET usado = 1 WHERE id = ?");
    $stmtUsado->execute([$tokenData['id']]);

    // 5. Garantizar un registro activo en la tabla 'viajes' para evitar conflicto de Llave Foránea (FK)
    $stmtViaje = $pdo->prepare("SELECT id FROM viajes WHERE conductorId = ? AND estado = 'activo' ORDER BY id DESC LIMIT 1");
    $stmtViaje->execute([$conductorId]);
    $viaje = $stmtViaje->fetch(PDO::FETCH_ASSOC);

    if (!$viaje) {
        // Crear un viaje inicial si no existe
        $stmtNuevoViaje = $pdo->prepare("INSERT INTO viajes (busId, rutaId, conductorId, estado) VALUES (1, 1, ?, 'activo')");
        $stmtNuevoViaje->execute([$conductorId]);
        $viajeId = $pdo->lastInsertId();
    } else {
        $viajeId = $viaje['id'];
    }

    // 6. Registrar la asistencia/abordaje en la BD
    $stmtAsistencia = $pdo->prepare("INSERT INTO asistencias (estudianteId, viajeId, fechaAbordaje) VALUES (?, ?, NOW())");
    $stmtAsistencia->execute([$tokenData['estudianteId'], $viajeId]);

    // Limpiar cualquier HTML accidental y enviar JSON limpio
    ob_end_clean();
    echo json_encode([
        'status' => 'success',
        'message' => 'Abordaje Autorizado',
        'estudiante' => $tokenData['nombre']
    ]);
    exit;

} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Error BD: ' . $e->getMessage()]);
    exit;
}
?>