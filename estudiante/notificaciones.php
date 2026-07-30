<?php
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'estudiante') { header("Location: ../index.php"); exit; }
require_once __DIR__ . '/../db/conexion.php';

// Consultar avisos del sistema e incidentes reportados por conductores
$stmt = $pdo->query('SELECT i.descripcion, i.tipo, i."fechaReporte", u.nombre as conductor 
                     FROM incidentes i 
                     JOIN usuarios u ON i."conductorId" = u.id 
                     ORDER BY i."fechaReporte" DESC LIMIT 10');
$alertas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>CONTROLBUS - Notificaciones</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/estilos.css">
</head>
<body class="bg-slate-100">
    <header class="bg-white border-b border-slate-200 px-6 py-3 flex items-center justify-between sticky top-0 z-20">
        <div class="flex items-center space-x-3">
            <div class="bg-blue-600 text-white p-2 rounded-xl flex items-center justify-center shadow-md">
                <i class="fa-solid fa-graduation-cap text-lg"></i>
            </div>
            <span class="font-black text-slate-800 tracking-tight text-xl">BUSCONTROL</span>
        </div>
    </header>
    <div class="flex min-h-screen">
        <aside class="w-20 bg-white border-r flex flex-col items-center py-6 space-y-5">
            <a href="dashboard.php" class="text-slate-400 hover:text-blue-600 text-xl"><i class="fa-solid fa-house"></i></a>
            <a href="pagos.php" class="text-slate-400 hover:text-blue-600 text-xl"><i class="fa-solid fa-credit-card"></i></a>
            <a href="notificaciones.php" class="bg-blue-50 text-blue-600 p-3 rounded-2xl text-xl"><i class="fa-solid fa-bell"></i></a>
            <a href="calendario.php" class="text-slate-400 hover:text-blue-600 text-xl"><i class="fa-solid fa-calendar-days"></i></a>
            <a href="rutas.php" class="text-slate-400 hover:text-blue-600 text-xl"><i class="fa-solid fa-compass"></i></a>
        </aside>

        <main class="flex-1 p-8 max-w-4xl mx-auto space-y-6">
            <h1 class="text-3xl font-black text-slate-900">Alertas y Notificaciones de Ruta</h1>
            
            <div class="space-y-4">
                <?php foreach($alertas as $a): ?>
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-start space-x-4">
                    <div class="p-3 bg-amber-100 text-amber-600 rounded-xl text-xl">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <div>
                        <div class="flex items-center space-x-2">
                            <h3 class="font-bold text-slate-800 uppercase text-sm">Alerta de <?php echo htmlspecialchars($a['tipo']); ?></h3>
                            <span class="text-xs text-slate-400"><?php echo date('d/m/Y H:i', strtotime($a['fechaReporte'])); ?></span>
                        </div>
                        <p class="text-sm text-slate-600 mt-1"><?php echo htmlspecialchars($a['descripcion']); ?></p>
                        <span class="text-xs text-slate-400 font-semibold mt-2 block">Reportado por Conductor: <?php echo htmlspecialchars($a['conductor']); ?></span>
                    </div>
                </div>
                <?php endforeach; if(empty($alertas)): ?>
                <div class="bg-white p-8 rounded-2xl text-center text-slate-400 font-bold">No hay alertas registradas por los conductores en este momento.</div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>