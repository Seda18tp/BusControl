<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../db/conexion.php';

if (!$pdo) {
    echo json_encode(['status' => 'error', 'message' => 'Sin conexión a BD']);
    exit;
}

$token = trim($_POST['token'] ?? $_GET['token'] ?? '');

if (empty($token)) {
    echo json_encode(['status' => 'error', 'message' => 'Código QR no recibido']);
    exit;
}

try {
    // 1. BUSCAR EL TOKEN EN TU TABLA
    $stmtToken = $pdo->prepare('
        SELECT t.id as "tokenId", t."estudianteId", t.usado, u.nombre 
        FROM "tokens_qr" t 
        JOIN usuarios u ON t."estudianteId" = u.id 
        WHERE t.token = ? 
        LIMIT 1
    ');
    $stmtToken->execute([$token]);
    $resultado = $stmtToken->fetch(PDO::FETCH_ASSOC);

    $tokenId = $resultado['tokenId'] ?? null;
    $usuarioId = $resultado['estudianteId'] ?? null;
    $nombreEstudiante = $resultado['nombre'] ?? 'Estudiante';

    if (!$usuarioId) {
        echo json_encode(['status' => 'error', 'message' => 'Pase QR no encontrado o no registrado']);
        exit;
    }

    // 2. VERIFICAR ASISTENCIAS DEL DÍA
    $stmtConteo = $pdo->prepare('
        SELECT COUNT(*) 
        FROM asistencias 
        WHERE ("estudianteId" = ?) 
          AND DATE("fechaAbordaje") = CURRENT_DATE
    ');
    $stmtConteo->execute([$usuarioId]);
    $usosHoy = intval($stmtConteo->fetchColumn() ?: 0);

    if ($usosHoy >= 2) {
        echo json_encode(['status' => 'error', 'message' => 'El estudiante ya usó sus 2 pases de hoy']);
        exit;
    }

    // 3. REGISTRAR ASISTENCIA Y QUEMAR EL TOKEN
    try {
        $stmtInsert = $pdo->prepare('
            INSERT INTO asistencias ("estudianteId", "fechaAbordaje", estado) 
            VALUES (?, NOW(), \'confirmado\')
        ');
        $stmtInsert->execute([$usuarioId]);
    } catch (PDOException $ex) {
        $stmtInsert = $pdo->prepare('
            INSERT INTO asistencias (estudianteid, fechaabordaje, estado) 
            VALUES (?, NOW(), \'confirmado\')
        ');
        $stmtInsert->execute([$usuarioId]);
    }

    // Marcar en tokens_qr que fue usado
    if ($tokenId) {
        $stmtUpdate = $pdo->prepare('UPDATE "tokens_qr" SET usado = 1 WHERE id = ?');
        $stmtUpdate->execute([$tokenId]);
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Abordaje registrado',
        'estudiante' => $nombreEstudiante
    ]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error al validar: ' . $e->getMessage()]);
}
?>