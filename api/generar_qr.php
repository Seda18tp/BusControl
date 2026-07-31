<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

session_start();
require_once __DIR__ . '/../db/conexion.php';

$usuarioId = $_SESSION['usuario_id'] ?? $_SESSION['id'] ?? $_GET['usuario_id'] ?? $_POST['usuario_id'] ?? null;

if (!$usuarioId) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión no detectada']);
    exit;
}

if (!$pdo) {
    echo json_encode(['status' => 'error', 'message' => 'Sin conexión a BD']);
    exit;
}

try {
    // 1. VERIFICAR PAGO AL DÍA
    $stmtPago = $pdo->prepare('
        SELECT estado, "validoHasta" as "validoHasta" 
        FROM pagos 
        WHERE ("usuarioId" = ?) 
        ORDER BY id DESC LIMIT 1
    ');
    $stmtPago->execute([$usuarioId]);
    $pago = $stmtPago->fetch(PDO::FETCH_ASSOC);

    $estadoPago = strtolower(trim($pago['estado'] ?? 'vencido'));
    $estaAlDia = in_array($estadoPago, ['al_dia', 'al dia', 'aldia', 'pagado', 'activo', '1']);

    if (!$estaAlDia) {
        echo json_encode(['status' => 'bloqueado', 'message' => 'Pago mensual pendiente o vencido.']);
        exit;
    }

    // 2. CONTAR PASES CONSUMIDOS HOY (Límite de 2 viajes)
    $asistenciasHoy = 0;
    try {
        $stmtAsistencias = $pdo->prepare('
            SELECT COUNT(*) 
            FROM asistencias 
            WHERE ("estudianteId" = ?) 
              AND DATE("fechaAbordaje") = CURRENT_DATE
        ');
        $stmtAsistencias->execute([$usuarioId]);
        $asistenciasHoy = intval($stmtAsistencias->fetchColumn() ?: 0);
    } catch (PDOException $ex) {
        $asistenciasHoy = 0;
    }

    if ($asistenciasHoy >= 2) {
        echo json_encode(['status' => 'limite_alcanzado', 'message' => 'Viajes del día completados']);
        exit;
    }

    // 3. GENERAR TOKEN ÚNICO
    $fechaHoy = date('Y-m-d');
    $secretoServidor = "CONTROLBUS_SECRET_KEY_2026";
    $viajeNumero = $asistenciasHoy + 1;
    $tokenRaw = "BUSCTRL-EST-" . $usuarioId . "-" . $fechaHoy . "-V" . $viajeNumero . "-" . $secretoServidor;
    $tokenQR = hash('sha256', $tokenRaw);

    // 4. GUARDAR EN TU TABLA CON TUS COLUMNAS EXACTAS ("estudianteId", "creadoEn", "expiraEn")
    try {
        // Expiración al final del día actual
        $stmtToken = $pdo->prepare('
            INSERT INTO "tokens_qr" (token, usado, "estudianteId", "creadoEn", "expiraEn") 
            VALUES (?, 0, ?, NOW(), CURRENT_DATE + INTERVAL \'1 day\')
            ON CONFLICT ("estudianteId") DO UPDATE 
            SET token = EXCLUDED.token, usado = 0, "creadoEn" = NOW(), "expiraEn" = CURRENT_DATE + INTERVAL \'1 day\'
        ');
        $stmtToken->execute([$tokenQR, $usuarioId]);
    } catch (PDOException $e) {
        // Respaldo sin ON CONFLICT por si la restricción UNIQUE no está en estudianteId
        try {
            $stmtToken = $pdo->prepare('
                INSERT INTO tokens_qr (token, usado, "estudianteId", "creadoEn", "expiraEn") 
                VALUES (?, 0, ?, NOW(), CURRENT_DATE + INTERVAL \'1 day\')
            ');
            $stmtToken->execute([$tokenQR, $usuarioId]);
        } catch (PDOException $ex) {}
    }

    $qrContenido = urlencode($tokenQR);
    $qrUrl = "https://quickchart.io/qr?text={$qrContenido}&size=200&margin=1";
    $textoPase = ($viajeNumero === 1) ? "Pase 1 de 2 (Ida)" : "Pase 2 de 2 (Vuelta)";

    echo json_encode([
        'status' => 'success',
        'token' => $tokenQR,
        'qr_url' => $qrUrl,
        'mensaje' => $textoPase
    ]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error en BD: ' . $e->getMessage()]);
}
?>