<?php
session_start();

// Verificar que el usuario tenga sesión activa y sea estudiante
if (!isset($_SESSION['usuario_id']) || (strtolower($_SESSION['rol'] ?? '') !== 'estudiante' && ($_SESSION['rolid'] ?? $_SESSION['rolId'] ?? 0) != 3)) {
    header("Location: ../index.php");
    exit;
}

require_once __DIR__ . '/../db/conexion.php';

$usuario_id = $_SESSION['usuario_id'];
$nombre_usuario = $_SESSION['nombre'] ?? 'Estudiante';
$iniciales = strtoupper(substr($nombre_usuario, 0, 2));

// Estado de Pago (Soporte Case-Safe para PostgreSQL)
$stmtPago = $pdo->prepare('SELECT estado, "validoHasta" as "validoHasta" FROM pagos WHERE "usuarioId" = ? ORDER BY id DESC LIMIT 1');
$stmtPago->execute([$usuario_id]);
$pago = $stmtPago->fetch(PDO::FETCH_ASSOC);

$estadoPago = $pago['estado'] ?? 'vencido';
$validoHasta = $pago['validoHasta'] ?? $pago['validohasta'] ?? null;

// Cargar paradas activas con latitud y longitud
$stmtParadas = $pdo->prepare('SELECT id, nombre, orden, "horaEstimada" as "horaEstimada", latitud, longitud FROM paradas WHERE "rutaId" = 1 ORDER BY orden ASC');
$stmtParadas->execute();
$paradas = $stmtParadas->fetchAll(PDO::FETCH_ASSOC);

// Token QR Diario
$fechaHoy = date('Y-m-d');
$secretoServidor = "CONTROLBUS_SECRET_KEY_2026";
$tokenQR = hash('sha256', $usuario_id . $fechaHoy . $secretoServidor);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CONTROLBUS - Panel del Estudiante</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="../css/estilos.css">
    <style>
        #mapa { height: 350px; width: 100%; border-radius: 1rem; z-index: 1; }
    </style>
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
            <span class="text-xs font-bold text-slate-600">Hola, <strong><?php echo htmlspecialchars($nombre_usuario); ?></strong></span>
        </div>
    </header>

    <div class="flex min-h-[calc(100vh-61px)]">
        
        <!-- Sidebar Izquierdo -->
        <aside class="w-20 bg-white border-r border-slate-200 flex flex-col justify-between items-center py-6 relative z-30">
            <nav class="flex flex-col space-y-5 w-full items-center">
                <a href="dashboard.php" title="Inicio" class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl shadow-sm">
                    <i class="fa-solid fa-house"></i>
                </a>
                <a href="pagos.php" title="Mis Pagos" class="w-12 h-12 rounded-2xl text-slate-400 hover:bg-slate-50 hover:text-slate-600 flex items-center justify-center text-xl transition">
                    <i class="fa-solid fa-credit-card"></i>
                </a>
                <a href="notificaciones.php" title="Notificaciones" class="w-12 h-12 rounded-2xl text-slate-400 hover:bg-slate-50 hover:text-slate-600 flex items-center justify-center text-xl transition relative">
                    <i class="fa-solid fa-bell"></i>
                </a>
                <a href="calendario.php" title="Calendario" class="w-12 h-12 rounded-2xl text-slate-400 hover:bg-slate-50 hover:text-slate-600 flex items-center justify-center text-xl transition">
                    <i class="fa-solid fa-calendar-days"></i>
                </a>
                <a href="rutas.php" title="Rutas Disponibles" class="w-12 h-12 rounded-2xl text-slate-400 hover:bg-slate-50 hover:text-slate-600 flex items-center justify-center text-xl transition">
                    <i class="fa-solid fa-compass"></i>
                </a>
            </nav>

            <!-- Menú Perfil Flotante -->
            <div class="relative">
                <button id="btn-perfil" onclick="togglePerfilMenu()" class="w-11 h-11 rounded-full bg-blue-600 hover:bg-blue-700 text-white font-black flex items-center justify-center text-sm shadow-md transition focus:outline-none">
                    <?php echo $iniciales; ?>
                </button>

                <div id="dropdown-perfil" class="hidden absolute bottom-0 left-16 ml-3 w-48 bg-white border border-slate-200 rounded-2xl shadow-xl py-2 z-50">
                    <div class="px-4 py-2 border-b border-slate-100">
                        <p class="text-xs font-extrabold text-slate-800"><?php echo htmlspecialchars($nombre_usuario); ?></p>
                        <p class="text-[10px] text-slate-400">Estudiante</p>
                    </div>
                    <a href="configuracion.php" class="flex items-center space-x-2 px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50 hover:text-blue-600 transition">
                        <i class="fa-solid fa-gear w-4"></i>
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
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-200 space-y-4">
                        <div class="flex justify-between items-center">
                            <h2 class="font-extrabold text-slate-800 text-lg">Rastreo de Bus en Vivo (Ruta A)</h2>
                            <span id="badge-estado-bus" class="text-xs bg-emerald-100 text-emerald-800 font-bold px-3 py-1 rounded-full animate-pulse">
                                ● Bus transmitiendo
                            </span>
                        </div>

                        <div class="relative">
                            <!-- Contenedor del Mapa -->
                            <div id="mapa"></div>

                            <!-- Cartel Flotante del Mapa -->
                            <div class="absolute top-3 right-3 bg-white/95 backdrop-blur-sm p-3 rounded-2xl shadow-lg border border-slate-100 z-[1000] flex items-center space-x-3 min-w-[210px]">
                                <div id="cartel-estado-bus" class="bg-emerald-500 text-white px-3 py-2 rounded-xl text-xs font-bold text-center transition-all duration-300">
                                    <span id="txt-estado-sub">En Movimiento</span>
                                    <div id="placa-bus" class="text-sm font-black">BUS-001</div>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase">Siguiente Parada</p>
                                    <p id="proxima-parada" class="font-black text-slate-800 text-xs uppercase">BIBLIOTECA</p>
                                    <p class="text-[11px] font-semibold text-slate-500">Llegada est.: <span id="tiempo-llegada" class="text-slate-800 font-bold">4 min</span></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Estado del Pago Mensual -->
                    <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex items-center space-x-4">
                        <div id="icon-pago" class="w-12 h-12 rounded-2xl <?php echo $estadoPago === 'al_dia' ? 'bg-emerald-100 text-emerald-600' : 'bg-red-100 text-red-600'; ?> flex items-center justify-center text-xl shrink-0">
                            <i class="fa-solid <?php echo $estadoPago === 'al_dia' ? 'fa-credit-card' : 'fa-triangle-exclamation'; ?>"></i>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">PAGO MENSUAL</p>
                            <p class="text-lg font-black <?php echo $estadoPago === 'al_dia' ? 'text-emerald-600' : 'text-red-600'; ?>">
                                <?php echo $estadoPago === 'al_dia' ? 'AL DÍA' : 'EXPIRADO'; ?>
                            </p>
                            <p class="text-xs text-slate-400 font-medium">
                                <?php echo ($estadoPago === 'al_dia' && $validoHasta) ? '(Válido hasta ' . date('d/m/Y', strtotime($validoHasta)) . ')' : '(Pago requerido para abordar)'; ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Columna Derecha: Código QR para Abordaje -->
                <div class="lg:col-span-1">
                    <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-200 text-center flex flex-col items-center justify-between h-full space-y-6">
                        <div>
                            <h3 class="text-lg font-black text-slate-800">Código QR para Abordaje</h3>
                            <p class="text-xs text-slate-400 mt-1">Generado de forma segura</p>
                        </div>

                        <div class="relative p-6 bg-white border border-slate-200 rounded-3xl shadow-sm my-auto w-full flex items-center justify-center min-h-[220px]">
                            <!-- Esquinas decorativas de escáner -->
                            <div class="absolute top-2 left-2 w-4 h-4 border-t-2 border-l-2 border-slate-800 rounded-tl"></div>
                            <div class="absolute top-2 right-2 w-4 h-4 border-t-2 border-r-2 border-slate-800 rounded-tr"></div>
                            <div class="absolute bottom-2 left-2 w-4 h-4 border-b-2 border-l-2 border-slate-800 rounded-bl"></div>
                            <div class="absolute bottom-2 right-2 w-4 h-4 border-b-2 border-r-2 border-slate-800 rounded-br"></div>

                            <!-- Contenedor dinámico de QR -->
                            <div id="contenedor-qr-estudiante">
                                <div class="animate-pulse flex flex-col items-center space-y-2">
                                    <div class="w-36 h-36 bg-slate-200 rounded-xl"></div>
                                    <span class="text-xs font-bold text-slate-400">Generando pase único...</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center space-x-2">
                            <?php if ($estadoPago === 'al_dia'): ?>
                                <span class="text-sm font-bold text-slate-700">Válido para hoy</span>
                                <i class="fa-solid fa-circle-check text-emerald-500 text-lg"></i>
                            <?php else: ?>
                                <span class="text-sm font-bold text-red-600">Inhabilitado</span>
                                <i class="fa-solid fa-circle-xmark text-red-500 text-lg"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        let map = null;
        let busMarker = null;
        let primeraCarga = true;
        let anteriorLatEstudiante = null;
        let anteriorLngEstudiante = null;

        const paradasBD = <?php echo json_encode($paradas); ?>;

        const busIcon = L.divIcon({
            className: 'custom-bus-icon',
            html: `<div style="background-color: #2563eb; color: white; width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 3px solid white; box-shadow: 0 4px 10px rgba(0,0,0,0.25);">
                    <i class="fa-solid fa-bus text-sm"></i>
                   </div>`,
            iconSize: [38, 38],
            iconAnchor: [19, 19]
        });

        function obtenerQRDinamico() {
            fetch('../api/generar_qr.php')
                .then(res => res.json())
                .then(data => {
                    const container = document.getElementById('contenedor-qr-estudiante');
                    if (!container) return;

                    if (data.status === 'success') {
                        container.innerHTML = `
                            <div class="space-y-2">
                                <img id="img-qr" src="${data.qr_url}" alt="QR Abordaje" class="w-44 h-44 mx-auto">
                                <span class="inline-block bg-blue-50 text-blue-700 text-[11px] font-extrabold px-3 py-1 rounded-full border border-blue-200">
                                    ${data.mensaje || 'Pase Diario'}
                                </span>
                            </div>`;
                    } else if (data.status === 'limite_alcanzado') {
                        container.innerHTML = `
                            <div class="w-44 h-44 bg-amber-50 border border-amber-200 rounded-2xl flex flex-col items-center justify-center space-y-2 p-4 text-amber-700">
                                <i class="fa-solid fa-circle-check text-3xl text-amber-500"></i>
                                <span class="text-[11px] font-extrabold text-center">Viajes del día completados</span>
                                <span class="text-[9px] text-amber-600 text-center">Consumiste tus 2 pases (Ida y Vuelta).</span>
                            </div>`;
                    } else {
                        container.innerHTML = `
                            <div class="w-44 h-44 bg-slate-100 rounded-2xl flex flex-col items-center justify-center space-y-2 p-4 text-slate-400">
                                <i class="fa-solid fa-lock text-3xl text-red-400"></i>
                                <span class="text-[11px] font-bold text-slate-500">Pase Bloqueado</span>
                                <span class="text-[9px] text-slate-400 text-center">${data.message || 'Pago requerido'}</span>
                            </div>`;
                    }
                })
                .catch(err => console.error("Error al obtener QR:", err));
        }

        window.addEventListener('DOMContentLoaded', () => {
            obtenerQRDinamico();
        });

        function initMap() {
            map = L.map('mapa', { zoomControl: false }).setView([4.6097, -74.0817], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(map);
            L.control.zoom({ position: 'bottomright' }).addTo(map);
        }

        initMap();

        function actualizarUbicacion() {
            fetch('../api/ubicacion.php')
                .then(res => res.json())
                .then(data => {
                    const rawLat = data.latitud ?? data.lat;
                    const rawLng = data.longitud ?? data.lng;

                    if (rawLat !== null && rawLng !== null && rawLat !== undefined && rawLng !== undefined) {
                        const lat = parseFloat(rawLat);
                        const lng = parseFloat(rawLng);
                        const pos = [lat, lng];

                        if (data.placa) {
                            const elPlaca = document.getElementById('placa-bus');
                            if (elPlaca) elPlaca.innerText = data.placa;
                        }

                        if (!busMarker) {
                            busMarker = L.marker(pos, { icon: busIcon }).addTo(map);
                        } else {
                            busMarker.setLatLng(pos);
                        }

                        if (primeraCarga || data.estado === 'en_ruta') {
                            map.setView(pos, 16);
                            primeraCarga = false;
                        }

                        const estaEnParada = verificarLlegadaEstudiante(lat, lng);

                        if (!estaEnParada) {
                            let estaEnMovimiento = false;

                            if (data.estado === 'en_ruta') {
                                estaEnMovimiento = true;
                            } else if (anteriorLatEstudiante !== null && anteriorLngEstudiante !== null) {
                                const distAvance = calcularDistanciaMetros(anteriorLatEstudiante, anteriorLngEstudiante, lat, lng);
                                if (distAvance > 5) {
                                    estaEnMovimiento = true;
                                }
                            }

                            actualizarBadgeEstado(estaEnMovimiento, data.placa || 'BUS-001');
                        }

                        anteriorLatEstudiante = lat;
                        anteriorLngEstudiante = lng;
                    }
                })
                .catch(err => console.error("Error GPS Estudiante:", err));
        }

        function verificarLlegadaEstudiante(busLat, busLng) {
            if (!paradasBD || !Array.isArray(paradasBD) || paradasBD.length === 0) return false;

            let paradaEncontrada = null;
            let siguienteParada = null;

            for (let i = 0; i < paradasBD.length; i++) {
                const parada = paradasBD[i];
                if (parada.latitud !== null && parada.longitud !== null) {
                    const pLat = parseFloat(parada.latitud);
                    const pLng = parseFloat(parada.longitud);

                    const dist = calcularDistanciaMetros(busLat, busLng, pLat, pLng);

                    if (dist < 150) {
                        paradaEncontrada = parada;
                        if (i + 1 < paradasBD.length) {
                            siguienteParada = paradasBD[i + 1];
                        }
                        break;
                    }
                }
            }

            const cartel = document.getElementById('cartel-estado-bus');
            const txtProximaParada = document.getElementById('proxima-parada');

            if (paradaEncontrada) {
                if (cartel) {
                    cartel.className = "bg-emerald-500 text-white px-3 py-2 rounded-xl text-xs font-bold text-center animate-bounce shadow-md";
                    cartel.innerHTML = `
                        <span class="text-[10px] uppercase font-bold tracking-wider opacity-90">¡Llegó a!</span>
                        <div class="text-xs font-black uppercase leading-tight mt-0.5">${paradaEncontrada.nombre}</div>
                    `;
                }

                if (txtProximaParada) {
                    txtProximaParada.innerText = siguienteParada ? siguienteParada.nombre : "FIN DEL RECORRIDO";
                }

                return true;
            }

            return false;
        }

        function actualizarBadgeEstado(enMovimiento, placa) {
            const cartel = document.getElementById('cartel-estado-bus');
            if (!cartel) return;

            if (enMovimiento) {
                cartel.className = "bg-emerald-500 text-white px-3 py-2 rounded-xl text-xs font-bold text-center transition-all duration-300";
                cartel.innerHTML = `
                    <span>En Movimiento</span>
                    <div id="placa-bus" class="text-sm font-black">${placa}</div>
                `;
            } else {
                cartel.className = "bg-amber-500 text-white px-3 py-2 rounded-xl text-xs font-bold text-center transition-all duration-300";
                cartel.innerHTML = `
                    <span>Detenido</span>
                    <div id="placa-bus" class="text-sm font-black">${placa}</div>
                `;
            }
        }

        function calcularDistanciaMetros(lat1, lon1, lat2, lon2) {
            const R = 6371000;
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLon = (lon2 - lon1) * Math.PI / 180;
            const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                      Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                      Math.sin(dLon / 2) * Math.sin(dLon / 2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
            return R * c;
        }

        function togglePerfilMenu() {
            const menu = document.getElementById('dropdown-perfil');
            if (menu) menu.classList.toggle('hidden');
        }

        setInterval(actualizarUbicacion, 3000);
        actualizarUbicacion();
    </script>
</body>
</html>