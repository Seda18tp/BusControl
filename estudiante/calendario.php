<?php
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'estudiante') { header("Location: ../index.php"); exit; }
require_once '../db/conexion.php';

$rutas = $pdo->query("SELECT r.nombre, r.horarioTurno, p.nombre as parada, p.horaEstimada 
                      FROM rutas r 
                      JOIN paradas p ON r.id = p.rutaId 
                      ORDER BY r.nombre, p.orden")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>CONTROLBUS - Horarios de Rutas</title>
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
            <a href="notificaciones.php" class="text-slate-400 hover:text-blue-600 text-xl"><i class="fa-solid fa-bell"></i></a>
            <a href="calendario.php" class="bg-blue-50 text-blue-600 p-3 rounded-2xl text-xl"><i class="fa-solid fa-calendar-days"></i></a>
            <a href="rutas.php" class="text-slate-400 hover:text-blue-600 text-xl"><i class="fa-solid fa-compass"></i></a>
            <a href="configuracion.php" class="text-slate-400 hover:text-blue-600 text-xl"><i class="fa-solid fa-gear"></i></a>
        </aside>

        <main class="flex-1 p-8 max-w-5xl mx-auto space-y-6">
            <h1 class="text-3xl font-black text-slate-900">Horarios y Cronograma de Rutas</h1>
            
            <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-xs font-bold text-slate-400 border-b">
                            <th class="pb-3">RUTA</th>
                            <th class="pb-3">TURNO</th>
                            <th class="pb-3">PARADA PROGRAMADA</th>
                            <th class="pb-3 text-right">HORA ESTIMADA</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y text-sm font-semibold">
                        <?php foreach($rutas as $r): ?>
                        <tr>
                            <td class="py-4 text-blue-600 font-bold"><?php echo htmlspecialchars($r['nombre']); ?></td>
                            <td class="py-4 text-slate-500"><?php echo htmlspecialchars($r['horarioTurno']); ?></td>
                            <td class="py-4 text-slate-800"><?php echo htmlspecialchars($r['parada']); ?></td>
                            <td class="py-4 text-right font-mono font-bold"><?php echo date('h:i A', strtotime($r['horaEstimada'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>