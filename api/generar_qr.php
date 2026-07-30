<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

session_start();
require_once __DIR__ . '/../db/conexion.php';

// Verificar que haya sesión activa de estudiante
$usuarioId = $_SESSION['usuario_id'] ?? $_SESSION['id'] ?? null;

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
    // 1. VERIFICAR ESTADO DE PAGO DEL ESTUDIANTE (Soporte Case-Safe)
    $stmtPago = $pdo->prepare('
        SELECT estado, "validoHasta" as "validoHasta" 
        FROM pagos 
        WHERE ("usuarioId" = ?) 
        ORDER BY id DESC LIMIT 1
    ');
    $stmtPago->execute([$usuarioId]);
    $pago = $stmtPago->fetch(PDO::FETCH_ASSOC);

    $estadoPago = strtolower(trim($pago['estado'] ?? 'vencido'));

    // Aceptar cualquier variación común de "al día"
    $estaAlDia = in_array($estadoPago, ['al_dia', 'al dia', 'aldia', 'pagado', 'activo', '1']);

    if (!$estaAlDia) {
        echo json_encode([
            'status' => 'bloqueado',
            'message' => 'Pago mensual pendiente o vencido. Pago requerido para abordar.'
        ]);
        exit;
    }

    // 2. CONTAR CUÁNTOS PASES HA CONSUMIDO HOY EN ASISTENCIAS (Límite: 2 pases por día)
    $asistenciasHoy = 0;
    try {
        $stmtAsistencias = $pdo->prepare('
            SELECT COUNT(*) 
            FROM asistencias 
            WHERE ("estudianteId" = ?  OR "usuarioId" = ?) 
              AND DATE("fechaAbordaje") = CURRENT_DATE
        ');
        $stmtAsistencias->execute([$usuarioId, $usuarioId, $usuarioId, $usuarioId]);
        $asistenciasHoy = intval($stmtAsistencias->fetchColumn() ?: 0);
    } catch (PDOException $ex) {
        // En caso de que la tabla asistencias no exista aún o no tenga registros hoy
        $asistenciasHoy = 0;
    }

    if ($asistenciasHoy >= 2) {
        echo json_encode([
            'status' => 'limite_alcanzado',
            'message' => 'Límite alcanzado',
            'asistencias' => $asistenciasHoy
        ]);
        exit;
    }

    // 3. GENERAR TOKEN ÚNICO DIARIO DE ABORDAJE
    $fechaHoy = date('Y-m-d');
    $secretoServidor = "CONTROLBUS_SECRET_KEY_2026";
    // El token incluye el ID del usuario, la fecha del día y un identificador del viaje (1 o 2)
    $viajeNumero = $asistenciasHoy + 1;
    $tokenRaw = "BUSCTRL-EST-" . $usuarioId . "-" . $fechaHoy . "-V" . $viajeNumero . "-" . $secretoServidor;
    $tokenQR = hash('sha256', $tokenRaw);

    // Guardar o actualizar el token diario en la base de datos si existe tabla de tokens (Opcional)
    try {
        $stmtToken = $pdo->prepare('
            INSERT INTO tokens_qr ("usuarioId", token, "fechaCreacion", usos) 
            VALUES (?, ?, NOW(), ?) 
            ON CONFLICT ("usuarioId") DO UPDATE SET token = EXCLUDED.token, "fechaCreacion" = NOW()
        ');
        $stmtToken->execute([$usuarioId, $tokenQR, $asistenciasHoy]);
    } catch (PDOException $e) {
        // Si no existe la tabla tokens_qr, continúa normalmente generando el código visual
    }

    // 4. CONSTRUIR URL DEL CÓDIGO QR VISUAL (Usando QuickChart API segura y rápida)
    $qrContenido = urlencode($tokenQR);
    $qrUrl = "https://quickchart.io/qr?text={$qrContenido}&size=200&margin=1";

    $textoPase = ($viajeNumero === 1) ? "Pase 1 de 2 (Ida)" : "Pase 2 de 2 (Vuelta)";

    echo json_encode([
        'status' => 'success',
        'token' => $tokenQR,
        'qr_url' => $qrUrl,
        'mensaje' => $textoPase,
        'usos_restantes' => 2 - $asistenciasHoy
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Error de consulta en BD: ' . $e->getMessage()
    ]);
}
?>