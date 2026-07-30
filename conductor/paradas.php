<?php
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'conductor') { header("Location: ../index.php"); exit; }

require_once __DIR__ . '/../db/conexion.php';

$conductorId = $_SESSION['usuario_id'];
$iniciales = strtoupper(substr($_SESSION['nombre'], 0, 2));

$stmt = $pdo->prepare("SELECT p.nombre, p.orden, p.horaEstimada, r.nombre as nombreRuta 
                       FROM buses b 
                       JOIN paradas p ON b.rutaId = p.rutaId 
                       JOIN rutas r ON b.rutaId = r.id 
                       WHERE b.conductorId = ? ORDER BY p.orden ASC");
$stmt->execute([$conductorId]);
$paradas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>BUSCONTROL - Lista de Paradas</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/estilos.css">
</head>
<body class="bg-slate-100 font-sans text-slate-800">

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
            <a href="dashboard.php" class="text-slate-400 hover:text-blue-600 text-xl"><i class="fa-solid fa-bus"></i></a>
            <a href="paradas.php" class="bg-blue-50 text-blue-600 p-3 rounded-2xl text-xl"><i class="fa-solid fa-route"></i></a>
            <a href="incidentes.php" class="text-slate-400 hover:text-blue-600 text-xl"><i class="fa-solid fa-triangle-exclamation"></i></a>
        </aside>

        <main class="flex-1 p-8 max-w-4xl mx-auto space-y-6">
            <h1 class="text-3xl font-black text-slate-900">Paradas de la Ruta Asignada</h1>
            <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-3">
                <?php foreach($paradas as $p): ?>
                <div class="p-4 bg-slate-50 border rounded-2xl flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <span class="w-8 h-8 rounded-full bg-blue-600 text-white font-black text-xs flex items-center justify-center"><?php echo $p['orden']; ?></span>
                        <span class="font-bold text-slate-800"><?php echo htmlspecialchars($p['nombre']); ?></span>
                    </div>
                    <span class="text-xs font-bold text-slate-500">Hora Programada: <?php echo date('h:i A', strtotime($p['horaEstimada'])); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>
</body>
</html>