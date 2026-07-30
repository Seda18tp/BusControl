<?php
session_start();

// Verificar que el usuario tenga sesión activa y sea administrador
if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol'] !== 'admin' && ($_SESSION['rolid'] ?? 0) != 1)) {
    header("Location: ../index.php");
    exit;
}

require_once __DIR__ . '/../db/conexion.php';

$adminId = $_SESSION['usuario_id'];
$nombreAdmin = $_SESSION['nombre'] ?? 'Admin';
$iniciales = strtoupper(substr($nombreAdmin, 0, 2));

// 1. Conteo Total de Estudiantes
$stmtTotalEst = $pdo->query('SELECT COUNT(*) FROM usuarios WHERE "rolId" = 3 OR rolid = 3');
$totalEstudiantes = $stmtTotalEst->fetchColumn() ?: 0;

// 2. Conteo de Pagos (Al día vs Pendientes)
$stmtPagosStats = $pdo->query("SELECT 
    SUM(CASE WHEN estado = 'al_dia' THEN 1 ELSE 0 END) as pagados,
    SUM(CASE WHEN estado != 'al_dia' THEN 1 ELSE 0 END) as pendientes
    FROM pagos");
$pagosStats = $stmtPagosStats->fetch(PDO::FETCH_ASSOC);
$pagosPagados = $pagosStats['pagados'] ?: 0;
$pagosPendientes = $pagosStats['pendientes'] ?: 0;

// 3. Conteo de Buses Activos
$stmtBuses = $pdo->query('SELECT COUNT(*) as total, 
    SUM(CASE WHEN estado = \'en_ruta\' OR "ultimaActualizacion" >= NOW() - INTERVAL \'1 hour\' THEN 1 ELSE 0 END) as activos 
    FROM buses');
$busesData = $stmtBuses->fetch(PDO::FETCH_ASSOC);
$busesActivos = $busesData['activos'] ?: 0;
$busesTotal = $busesData['total'] ?: 0;

// 4. Estadísticas de abordaje por Ruta
$rutasStats = [];
try {
    $stmtRutasStats = $pdo->query('SELECT r.nombre as "rutaNombre", COUNT(a.id) as "totalAbordajes" 
                                    FROM rutas r 
                                    LEFT JOIN viajes v ON v."rutaId" = r.id 
                                    LEFT JOIN asistencias a ON a."viajeId" = v.id AND DATE(a."fechaAbordaje") = CURRENT_DATE 
                                    GROUP BY r.id, r.nombre');
    $rutasStats = $stmtRutasStats->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Respaldo seguro por si las tablas dependientes no existen
    $stmtRutasSimple = $pdo->query('SELECT nombre as "rutaNombre", 0 as "totalAbordajes" FROM rutas');
    $rutasStats = $stmtRutasSimple->fetchAll(PDO::FETCH_ASSOC);
}

// 5. Historial Reciente de Pagos
$stmtHistorialPagos = $pdo->query('SELECT p.id, u.nombre as estudiante, u."codigoEstudiante", p.monto, p."fechaPago", p.estado 
                                    FROM pagos p 
                                    JOIN usuarios u ON p."usuarioId" = u.id 
                                    ORDER BY p."fechaPago" DESC LIMIT 5');
$historialPagos = $stmtHistorialPagos->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BUSCONTROL - Panel de Administración</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
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
                <a href="dashboard.php" title="Panel Principal" class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl shadow-sm">
                    <i class="fa-solid fa-house"></i>
                </a>
                <a href="pagos.php" title="Gestión de Pagos" class="w-12 h-12 rounded-2xl text-slate-400 hover:bg-slate-50 hover:text-slate-600 flex items-center justify-center text-xl transition">
                    <i class="fa-solid fa-credit-card"></i>
                </a>
                <a href="notificaciones.php" title="Alertas e Incidentes" class="w-12 h-12 rounded-2xl text-slate-400 hover:bg-slate-50 hover:text-slate-600 flex items-center justify-center text-xl transition relative">
                    <i class="fa-solid fa-bell"></i>
                </a>
            </nav>

            <!-- Menú Perfil (Burbuja Flotante) -->
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
        <main class="flex-1 p-8 max-w-7xl mx-auto space-y-6">
            
            <!-- Tarjetas de Métricas -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                
                <!-- Card 1: Total Estudiantes -->
                <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-3">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Estudiantes Registrados</p>
                    <div class="flex items-baseline justify-between">
                        <h2 class="text-3xl font-black text-slate-900"><?php echo number_format($totalEstudiantes); ?></h2>
                        <span class="text-xs font-bold text-blue-600 bg-blue-50 px-2.5 py-1 rounded-full"><i class="fa-solid fa-users mr-1"></i> Total</span>
                    </div>
                    <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                        <div class="bg-blue-600 h-full w-full rounded-full"></div>
                    </div>
                </div>

                <!-- Card 2: Resumen de Pagos Mensuales -->
                <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-3">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Pagos Mensuales</p>
                    <div class="space-y-1.5">
                        <div class="flex justify-between text-xs font-bold">
                            <span class="text-emerald-600">● Al día</span>
                            <span class="text-slate-800"><?php echo number_format($pagosPagados); ?></span>
                        </div>
                        <div class="flex justify-between text-xs font-bold">
                            <span class="text-amber-500">● Pendientes / Mora</span>
                            <span class="text-slate-800"><?php echo number_format($pagosPendientes); ?></span>
                        </div>
                    </div>
                    <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden flex">
                        <?php 
                        $totalP = ($pagosPagados + $pagosPendientes) ?: 1;
                        $pctPagados = ($pagosPagados / $totalP) * 100;
                        ?>
                        <div class="bg-emerald-500 h-full rounded-l-full" style="width: <?php echo $pctPagados; ?>%"></div>
                        <div class="bg-amber-400 h-full rounded-r-full" style="width: <?php echo 100 - $pctPagados; ?>%"></div>
                    </div>
                </div>

                <!-- Card 3: Flota Activa -->
                <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-3">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Flota en Servicio</p>
                    <div class="flex items-baseline justify-between">
                        <h2 class="text-3xl font-black text-slate-900"><?php echo $busesActivos; ?> <span class="text-lg font-bold text-slate-400">/ <?php echo $busesTotal; ?></span></h2>
                        <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-2.5 py-1 rounded-full"><i class="fa-solid fa-bus mr-1"></i> En ruta</span>
                    </div>
                    <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                        <?php $pctBuses = $busesTotal > 0 ? ($busesActivos / $busesTotal) * 100 : 0; ?>
                        <div class="bg-blue-600 h-full rounded-full" style="width: <?php echo $pctBuses; ?>%"></div>
                    </div>
                </div>

            </div>

            <!-- Fila Central: Mapa + Estadística -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- Mapa de Monitoreo -->
                <div class="lg:col-span-2 bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-4">
                    <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                        <h3 class="font-extrabold text-slate-800 text-lg">Estado de Flota en Vivo</h3>
                        <span class="text-xs bg-blue-100 text-blue-800 font-bold px-3 py-1 rounded-full">Monitoreo GPS</span>
                    </div>
                    <div id="mapa" class="h-80 w-full rounded-2xl"></div>
                </div>

                <!-- Estadística por Ruta -->
                <div class="lg:col-span-1 bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-4 flex flex-col justify-between">
                    <div>
                        <h3 class="font-extrabold text-slate-800 text-lg border-b border-slate-100 pb-3">Abordajes Hoy por Ruta</h3>
                        <div class="space-y-4 mt-4">
                            <?php if (empty($rutasStats)): ?>
                                <p class="text-xs text-slate-400 font-medium">No hay registros de abordajes hoy.</p>
                            <?php else: ?>
                                <?php foreach ($rutasStats as $rs): ?>
                                    <?php 
                                        $rutaNombre = $rs['rutaNombre'] ?? $rs['rutanombre'] ?? 'Ruta Sin Nombre';
                                        $totalAbordajes = $rs['totalAbordajes'] ?? $rs['totalabordajes'] ?? 0;
                                    ?>
                                    <div class="p-3 bg-slate-50 border border-slate-100 rounded-2xl space-y-1">
                                        <div class="flex justify-between items-center">
                                            <span class="text-xs font-black text-slate-800"><?php echo htmlspecialchars($rutaNombre); ?></span>
                                            <span class="text-xs font-bold bg-blue-100 text-blue-700 px-2.5 py-0.5 rounded-full"><?php echo number_format($totalAbordajes); ?> pasajeros</span>
                                        </div>
                                        <p class="text-[10px] text-slate-400 font-medium">Asistencias confirmadas el día de hoy</p>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <a href="rutas.php" class="w-full py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-extrabold text-xs rounded-xl text-center block transition">
                        Ver Detalle de Rutas y Horarios
                    </a>
                </div>

            </div>

            <!-- Fila Inferior: Historial de Pagos -->
            <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-4">
                <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                    <h3 class="font-extrabold text-slate-800 text-lg">Últimos Pagos Registrados</h3>
                    <a href="pagos.php" class="text-xs font-bold text-blue-600 hover:underline">Ver Todos los Pagos →</a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-100 text-[11px] font-black text-slate-400 uppercase tracking-wider">
                                <th class="py-3 px-4">Estudiante</th>
                                <th class="py-3 px-4">Código</th>
                                <th class="py-3 px-4">Monto</th>
                                <th class="py-3 px-4">Fecha de Pago</th>
                                <th class="py-3 px-4 text-right">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs font-semibold">
                            <?php foreach ($historialPagos as $pago): ?>
                                <?php 
                                    $codigoEst = $pago['codigoEstudiante'] ?? $pago['codigoestudiante'] ?? 'N/A';
                                    $fechaPago = $pago['fechaPago'] ?? $pago['fechapago'] ?? null;
                                ?>
                                <tr class="hover:bg-slate-50/50 transition">
                                    <td class="py-3.5 px-4 font-extrabold text-slate-800"><?php echo htmlspecialchars($pago['estudiante']); ?></td>
                                    <td class="py-3.5 px-4 text-slate-500"><?php echo htmlspecialchars($codigoEst ?: 'N/A'); ?></td>
                                    <td class="py-3.5 px-4 font-bold text-slate-800">$<?php echo number_format($pago['monto'], 2); ?></td>
                                    <td class="py-3.5 px-4 text-slate-500"><?php echo $fechaPago ? date('d/m/Y H:i', strtotime($fechaPago)) : 'N/A'; ?></td>
                                    <td class="py-3.5 px-4 text-right">
                                        <?php if ($pago['estado'] === 'al_dia'): ?>
                                            <span class="bg-emerald-100 text-emerald-800 px-3 py-1 rounded-full font-bold text-[10px]">PAGADO / AL DÍA</span>
                                        <?php else: ?>
                                            <span class="bg-red-100 text-red-800 px-3 py-1 rounded-full font-bold text-[10px]">PENDIENTE / EN MORA</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </main> 
    </div>

    <!-- Scripts Leaflet -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        let map = null;
        let busMarker = null;
        let primeraCargaAdmin = true;

        const busIcon = L.divIcon({
            className: 'custom-admin-bus-icon',
            html: `<div style="background-color: #2563eb; color: white; width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 3px solid white; box-shadow: 0 4px 10px rgba(0,0,0,0.3);">
                    <i class="fa-solid fa-bus text-sm"></i>
                   </div>`,
            iconSize: [38, 38],
            iconAnchor: [19, 19]
        });

        function initMap() {
            map = L.map('mapa', { zoomControl: false }).setView([4.6097, -74.0817], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(map);
            L.control.zoom({ position: 'bottomright' }).addTo(map);
        }

        initMap();

        function actualizarGPSGlobal() {
            fetch('../api/ubicacion.php')
                .then(res => res.json())
                .then(data => {
                    if (data.latitud && data.longitud) {
                        const lat = parseFloat(data.latitud);
                        const lng = parseFloat(data.longitud);
                        const pos = [lat, lng];

                        if (!busMarker) {
                            busMarker = L.marker(pos, { icon: busIcon }).addTo(map);
                        } else {
                            busMarker.setLatLng(pos);
                        }

                        if (primeraCargaAdmin || data.estado === 'en_ruta') {
                            map.setView(pos, 16);
                            primeraCargaAdmin = false;
                        }
                    }
                })
                .catch(err => console.error("Error consultando GPS Admin:", err));
        }

        function toggleMenuAdmin() {
            document.getElementById('dropdown-perfil-admin').classList.toggle('hidden');
        }

        setInterval(actualizarGPSGlobal, 3000);
        actualizarGPSGlobal();
    </script>
</body>
</html>