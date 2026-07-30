<?php
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'estudiante') {
    header("Location: ../index.php");
    exit;
}

require_once __DIR__ . '/../db/conexion.php';

$usuario_id = $_SESSION['usuario_id'];
$nombre_usuario = $_SESSION['nombre'];
$iniciales = strtoupper(substr($nombre_usuario, 0, 2));

// Consultar todas las rutas y sus paradas
$stmtRutas = $pdo->query('SELECT r.id, r.nombre, r."horarioTurno" FROM rutas r ORDER BY r.id ASC');
$rutas = $stmtRutas->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>CONTROLBUS - Rutas Disponibles</title>
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
        <!-- Sidebar Izquierdo -->
        <aside class="w-20 bg-white border-r border-slate-200 flex flex-col justify-between items-center py-6">
            <nav class="flex flex-col space-y-5 w-full items-center">
                <a href="dashboard.php" title="Inicio" class="w-12 h-12 rounded-2xl text-slate-400 hover:bg-slate-50 hover:text-blue-600 flex items-center justify-center text-xl transition">
                    <i class="fa-solid fa-house"></i>
                </a>
                <a href="pagos.php" title="Mis Pagos" class="w-12 h-12 rounded-2xl text-slate-400 hover:bg-slate-50 hover:text-blue-600 flex items-center justify-center text-xl transition">
                    <i class="fa-solid fa-credit-card"></i>
                </a>
                <a href="notificaciones.php" title="Notificaciones" class="w-12 h-12 rounded-2xl text-slate-400 hover:bg-slate-50 hover:text-blue-600 flex items-center justify-center text-xl transition">
                    <i class="fa-solid fa-bell"></i>
                </a>
                <a href="rutas.php" title="Rutas Disponibles" class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl shadow-sm">
                    <i class="fa-solid fa-compass"></i>
                </a>
            </nav>

            <div class="w-11 h-11 rounded-full bg-blue-600 text-white font-black flex items-center justify-center text-sm shadow-md">
                <?php echo $iniciales; ?>
            </div>
        </aside>

        <!-- Contenido Principal -->
        <main class="flex-1 p-8 max-w-5xl mx-auto space-y-6">
            
            <div>
                <h1 class="text-3xl font-black text-slate-900 tracking-tight">Rutas y Trazados Disponibles</h1>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mt-1">Conoce los recorridos y paradas de las unidades</p>
            </div>

            <div class="space-y-6">
                <?php foreach ($rutas as $ruta): ?>
                    <?php
                    // Cargar paradas de la ruta actual
                    $stmtParadas = $pdo->prepare('SELECT nombre, orden, "horaEstimada" FROM paradas WHERE "rutaId" = ? ORDER BY orden ASC');
                    $stmtParadas->execute([$ruta['id']]);
                    $paradas = $stmtParadas->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    
                    <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-4">
                        <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 rounded-2xl bg-blue-100 text-blue-600 flex items-center justify-center font-bold">
                                    <i class="fa-solid fa-route"></i>
                                </div>
                                <div>
                                    <h2 class="text-lg font-black text-slate-800"><?php echo htmlspecialchars($ruta['nombre']); ?></h2>
                                    <p class="text-xs text-slate-400 font-semibold">Turno: <?php echo htmlspecialchars($ruta['horarioTurno']); ?></p>
                                </div>
                            </div>
                            <span class="text-xs font-extrabold bg-emerald-100 text-emerald-800 px-3 py-1 rounded-full">Activa</span>
                        </div>

                        <!-- Secuencia de Paradas -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 pt-2">
                            <?php foreach ($paradas as $parada): ?>
                                <div class="p-3 bg-slate-50 border border-slate-100 rounded-2xl flex items-center space-x-3">
                                    <span class="w-7 h-7 rounded-full bg-blue-600 text-white font-extrabold text-xs flex items-center justify-center shrink-0">
                                        <?php echo $parada['orden']; ?>
                                    </span>
                                    <div>
                                        <p class="text-xs font-extrabold text-slate-800"><?php echo htmlspecialchars($parada['nombre']); ?></p>
                                        <p class="text-[10px] text-slate-400 font-bold">Est.: <?php echo date('h:i A', strtotime($parada['horaEstimada'])); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        </main>
    </div>

</body>
</html>