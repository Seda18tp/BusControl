<?php
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

require_once __DIR__ . '/../db/conexion.php';

$nombreAdmin = $_SESSION['nombre'];
$iniciales = strtoupper(substr($nombreAdmin, 0, 2));

// Consultar todas las rutas con su bus y conductor asignado
$stmtRutas = $pdo->query("SELECT r.id as rutaId, r.nombre as nombreRuta, r.horarioTurno, 
                                 b.placa, u.nombre as nombreConductor, b.estado as estadoBus 
                          FROM rutas r 
                          LEFT JOIN buses b ON b.rutaId = r.id 
                          LEFT JOIN usuarios u ON b.conductorId = u.id 
                          ORDER BY r.id ASC");
$rutas = $stmtRutas->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BUSCONTROL - Gestión de Rutas y Horarios</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/estilos.css">
</head>
<body class="bg-slate-100 font-sans text-slate-800 antialiased">

    <!-- Header / Barra Superior -->
    <header class="bg-white border-b border-slate-200 px-6 py-3 flex items-center justify-between sticky top-0 z-20">
        <div class="flex items-center space-x-3">
            <div class="bg-blue-600 text-white p-2 rounded-xl flex items-center justify-center shadow-md">
                <i class="fa-solid fa-graduation-cap text-lg"></i>
            </div>
            <span class="font-black text-slate-800 tracking-tight text-xl">BUSCONTROL</span>
        </div>
        <div class="flex items-center space-x-4">
            <span class="text-xs font-bold text-slate-600">Admin: <strong><?php echo htmlspecialchars($nombreAdmin); ?></strong></span>
        </div>
    </header>

    <div class="flex min-h-[calc(100vh-61px)]">
        
        <!-- Sidebar Izquierdo -->
        <aside class="w-20 bg-white border-r border-slate-200 flex flex-col justify-between items-center py-6 relative z-30">
            <nav class="flex flex-col space-y-5 w-full items-center">
                <a href="dashboard.php" title="Panel Principal" class="w-12 h-12 rounded-2xl text-slate-400 hover:bg-slate-50 hover:text-slate-600 flex items-center justify-center text-xl transition">
                    <i class="fa-solid fa-house"></i>
                </a>
            </nav>

            <div class="relative">
                <button id="btn-perfil-admin" onclick="toggleMenuAdmin()" class="w-11 h-11 rounded-full bg-blue-600 hover:bg-blue-700 text-white font-black flex items-center justify-center text-sm shadow-md transition focus:outline-none">
                    <?php echo $iniciales; ?>
                </button>

                <div id="dropdown-perfil-admin" class="hidden absolute bottom-0 left-16 ml-3 w-48 bg-white border border-slate-200 rounded-2xl shadow-xl py-2 z-50">
                    <div class="px-4 py-2 border-b border-slate-100">
                        <p class="text-xs font-extrabold text-slate-800"><?php echo htmlspecialchars($nombreAdmin); ?></p>
                        <p class="text-[10px] text-slate-400">Administrador</p>
                    </div>
                    <a href="configuracion.php" class="flex items-center space-x-2 px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50 hover:text-blue-600 transition">
                        <i class="fa-solid fa-sliders w-4"></i>
                        <span>Configuración</span>
                    </a>
                    <a href="../logout.php" class="flex items-center space-x-2 px-4 py-2.5 text-xs font-bold text-red-600 hover:bg-red-50 transition border-t border-slate-100">
                        <i class="fa-solid fa-right-from-bracket w-4"></i>
                        <span>Cerrar Sesión</span>
                    </a>
                </div>
            </div>
        </aside>

        <!-- Contenido Principal -->
        <main class="flex-1 p-8 max-w-6xl mx-auto space-y-6">
            
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-black text-slate-900 tracking-tight">Detalle de Rutas y Paradas</h1>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mt-1">Supervisión de recorridos y asignación de unidades</p>
                </div>
            </div>

            <div class="space-y-6">
                <?php foreach ($rutas as $ruta): ?>
                    <?php
                    // Obtener paradas ordenadas de cada ruta
                    $stmtParadas = $pdo->prepare("SELECT nombre, orden, horaEstimada, latitud, longitud FROM paradas WHERE rutaId = ? ORDER BY orden ASC");
                    $stmtParadas->execute([$ruta['rutaId']]);
                    $paradas = $stmtParadas->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    
                    <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-4">
                        <div class="flex flex-col md:flex-row md:items-center justify-between border-b border-slate-100 pb-4 gap-4">
                            <div class="flex items-center space-x-3">
                                <div class="w-12 h-12 rounded-2xl bg-blue-100 text-blue-600 flex items-center justify-center text-xl font-bold shrink-0">
                                    <i class="fa-solid fa-route"></i>
                                </div>
                                <div>
                                    <h2 class="text-xl font-black text-slate-800"><?php echo htmlspecialchars($ruta['nombreRuta']); ?></h2>
                                    <p class="text-xs text-slate-400 font-semibold">Turno: <?php echo htmlspecialchars($ruta['horarioTurno']); ?></p>
                                </div>
                            </div>

                            <div class="flex items-center space-x-6 text-xs font-bold text-slate-600">
                                <div>
                                    <span class="text-slate-400 block text-[10px] uppercase">Vehículo</span>
                                    <span><?php echo htmlspecialchars($ruta['placa'] ?: 'Sin Asignar'); ?></span>
                                </div>
                                <div>
                                    <span class="text-slate-400 block text-[10px] uppercase">Conductor</span>
                                    <span><?php echo htmlspecialchars($ruta['nombreConductor'] ?: 'Sin Asignar'); ?></span>
                                </div>
                                <div>
                                    <?php if ($ruta['estadoBus'] === 'en_ruta'): ?>
                                        <span class="bg-emerald-100 text-emerald-800 px-3 py-1 rounded-full text-[11px] font-bold">En Servicio</span>
                                    <?php else: ?>
                                        <span class="bg-slate-100 text-slate-600 px-3 py-1 rounded-full text-[11px] font-bold">Fuera de Servicio</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Secuencia de Paradas Registradas -->
                        <div>
                            <h4 class="text-xs font-black text-slate-400 uppercase tracking-wider mb-3">Secuencia de Paradas (<?php echo count($paradas); ?>)</h4>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                <?php foreach ($paradas as $parada): ?>
                                    <div class="p-3 bg-slate-50 border border-slate-100 rounded-2xl flex items-start space-x-3">
                                        <span class="w-7 h-7 rounded-full bg-blue-600 text-white font-black text-xs flex items-center justify-center shrink-0 mt-0.5">
                                            <?php echo $parada['orden']; ?>
                                        </span>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-xs font-extrabold text-slate-800 truncate"><?php echo htmlspecialchars($parada['nombre']); ?></p>
                                            <p class="text-[10px] text-slate-400 font-bold">Est.: <?php echo date('h:i A', strtotime($parada['horaEstimada'])); ?></p>
                                            <?php if ($parada['latitud'] && $parada['longitud']): ?>
                                                <p class="text-[9px] text-emerald-600 font-semibold mt-0.5">
                                                    GPS: <?php echo round($parada['latitud'], 4); ?>, <?php echo round($parada['longitud'], 4); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>

        </main>
    </div>

    <script>
        function toggleMenuAdmin() {
            document.getElementById('dropdown-perfil-admin').classList.toggle('hidden');
        }
    </script>
</body>
</html>