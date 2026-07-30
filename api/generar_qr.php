<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

session_start();
header('Content-Type: application/json; charset=utf-8');

if (file_exists('../db/conexion.php')) {
    require_once '../db/conexion.php';
} else {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Sin conexión a BD']);
    exit;
}

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'estudiante') {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$estudianteId = $_SESSION['usuario_id'];

try {
    // 1. Verificar estado de pago del estudiante
    $stmtPago = $pdo->prepare("SELECT estado, validoHasta FROM pagos WHERE usuarioId = ? ORDER BY id DESC LIMIT 1");
    $stmtPago->execute([$estudianteId]);
    $pago = $stmtPago->fetch(PDO::FETCH_ASSOC);

    if (!$pago || $pago['estado'] !== 'al_dia' || $pago['validoHasta'] < date('Y-m-d')) {
        ob_end_clean();
        echo json_encode([
            'status' => 'bloqueado',
            'message' => 'Pago mensual vencido. Acceso inhabilitado.'
        ]);
        exit;
    }

    // 2. Verificar si ya tiene un token VÁLIDO Y NO USADO generado hoy
    $stmtActivo = $pdo->prepare("SELECT token, expiraEn FROM tokens_qr 
                                 WHERE estudianteId = ? 
                                 AND DATE(creadoEn) = CURDATE() 
                                 AND usado = 0 
                                 AND expiraEn > NOW() 
                                 ORDER BY id DESC LIMIT 1");
    $stmtActivo->execute([$estudianteId]);
    $tokenActivo = $stmtActivo->fetch(PDO::FETCH_ASSOC);

    if ($tokenActivo) {
        // Si aún tiene un token vigente no usado, le entregamos ese mismo para no gastar su segundo cupo
        $urlQR = "https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=" . $tokenActivo['token'];
        ob_end_clean();
        echo json_encode([
            'status' => 'success',
            'token' => $tokenActivo['token'],
            'qr_url' => $urlQR,
            'mensaje' => 'Pase activo'
        ]);
        exit;
    }

    // 3. Contar cuántos tokens HA USADO O GENERADO hoy el estudiante
    $stmtConteo = $pdo->prepare("SELECT COUNT(*) as totalHoy FROM tokens_qr 
                                 WHERE estudianteId = ? 
                                 AND DATE(creadoEn) = CURDATE()");
    $stmtConteo->execute([$estudianteId]);
    $conteo = $stmtConteo->fetch(PDO::FETCH_ASSOC);
    $totalHoy = intval($conteo['totalHoy'] ?? 0);

    // 4. Validar el límite de máximo 2 tokens diarios (1 Ida y 1 Vuelta)
    if ($totalHoy >= 2) {
        ob_end_clean();
        echo json_encode([
            'status' => 'limite_alcanzado',
            'message' => 'Has consumido tus 2 pases de abordaje de hoy (Ida y Vuelta).'
        ]);
        exit;
    }

    // 5. Generar Nuevo Token Único (Pase 1 de 2 o Pase 2 de 2)
    $tokenAleatorio = bin2hex(random_bytes(16));
    $expiraEn = date('Y-m-d H:i:s', strtotime('+15 minutes')); // Válido por 15 min para abordar

    $stmtInsert = $pdo->prepare("INSERT INTO tokens_qr (estudianteId, token, expiraEn, usado) VALUES (?, ?, ?, 0)");
    $stmtInsert->execute([$estudianteId, $tokenAleatorio, $expiraEn]);

    $urlQR = "https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=" . $tokenAleatorio;
    $numPase = $totalHoy + 1; // 1 para Ida, 2 para Vuelta

    ob_end_clean();
    echo json_encode([
        'status' => 'success',
        'token' => $tokenAleatorio,
        'qr_url' => $urlQR,
        'mensaje' => "Pase {$numPase} de 2 generado exitosamente."
    ]);
    exit;

} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Error en BD: ' . $e->getMessage()]);
    exit;
}
?>