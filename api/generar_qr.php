<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

session_start();
require_once __DIR__ . '/../db/conexion.php';

// Intentar leer de la sesión, o de la petición GET/POST como respaldo seguro
$usuarioId = $_SESSION['usuario_id'] ?? $_SESSION['id'] ?? $_GET['usuario_id'] ?? $_POST['usuario_id'] ?? null;

if (!$usuarioId) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Sesión no detectada'
    ]);
    exit;
}

if (!$pdo) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Sin conexión a la base de datos'
    ]);
    exit;
}

try {
    // 1. VERIFICAR ESTADO DE PAGO DEL ESTUDIANTE
    $stmtPago = $pdo->prepare('
        SELECT estado, "validoHasta" as "validoHasta" 
        FROM pagos 
        WHERE ("usuarioId" = ?) 
        ORDER BY id DESC LIMIT 1
    ');
    $stmtPago->execute([$usuarioId, $usuarioId]);
    $pago = $stmtPago->fetch(PDO::FETCH_ASSOC);

    $estadoPago = strtolower(trim($pago['estado'] ?? 'vencido'));
    $estaAlDia = in_array($estadoPago, ['al_dia', 'al dia', 'aldia', 'pagado', 'activo', '1']);

    if (!$estaAlDia) {
        echo json_encode([
            'status' => 'bloqueado',
            'message' => 'Pago mensual pendiente o vencido.'
        ]);
        exit;
    }

    // 2. CONTAR PASES CONSUMIDOS HOY EN ASISTENCIAS
    $asistenciasHoy = 0;
    try {
        $stmtAsistencias = $pdo->prepare('
            SELECT COUNT(*) 
            FROM asistencias 
            WHERE ("estudianteId" = ? OR "usuarioId" = ?) 
              AND DATE("fechaAbordaje") = CURRENT_DATE
        ');
        $stmtAsistencias->execute([$usuarioId, $usuarioId, $usuarioId, $usuarioId]);
        $asistenciasHoy = intval($stmtAsistencias->fetchColumn() ?: 0);
    } catch (PDOException $ex) {
        $asistenciasHoy = 0;
    }

    if ($asistenciasHoy >= 2) {
        echo json_encode([
            'status' => 'limite_alcanzado',
            'message' => 'Viajes del día completados'
        ]);
        exit;
    }

    // 3. GENERAR TOKEN Y URL DE QR
    $fechaHoy = date('Y-m-d');
    $secretoServidor = "CONTROLBUS_SECRET_KEY_2026";
    $viajeNumero = $asistenciasHoy + 1;
    $tokenRaw = "BUSCTRL-EST-" . $usuarioId . "-" . $fechaHoy . "-V" . $viajeNumero . "-" . $secretoServidor;
    $tokenQR = hash('sha256', $tokenRaw);

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
    echo json_encode([
        'status' => 'error',
        'message' => 'Error en BD: ' . $e->getMessage()
    ]);
}
?>