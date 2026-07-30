<?php
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'conductor') {
    header("Location: ../index.php");
    exit;
}

require_once __DIR__ . '/../db/conexion.php';

$conductorId = $_SESSION['usuario_id'];
$nombreConductor = $_SESSION['nombre'];
$iniciales = strtoupper(substr($nombreConductor, 0, 2));

// Obtener la unidad (Bus) asignada a este conductor y su ruta
$stmtBus = $pdo->prepare('SELECT b.id as "busId", b.placa, r.id as "rutaId", r.nombre as "nombreRuta" 
                         FROM buses b 
                         JOIN rutas r ON b."rutaId" = r.id 
                         WHERE b."conductorId" = ? LIMIT 1');
$stmtBus->execute([$conductorId]);
$bus = $stmtBus->fetch(PDO::FETCH_ASSOC);

$busId = $bus['busId'] ?? 1;
$placa = $bus['placa'] ?? 'BUS-001';
$rutaId = $bus['rutaId'] ?? 1;
$nombreRuta = $bus['nombreRuta'] ?? 'Ruta A (AM)';

$stmtParadas = $pdo->prepare('SELECT id, nombre, orden, "horaEstimada", latitud, longitud FROM paradas WHERE "rutaId" = ? ORDER BY orden ASC');
$stmtParadas->execute([$rutaId]);
$paradas = $stmtParadas->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BUSCONTROL - Panel del Conductor</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="../css/estilos.css">
    <script src="https://unpkg.com/html5-qrcode"></script>
    <style>
        #mapa { height: 340px; width: 100%; border-radius: 1rem; z-index: 1; }
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
            <span class="text-xs font-bold text-slate-600">Conductor: <strong><?php echo htmlspecialchars($nombreConductor); ?></strong></span>
        </div>
    </header>

    <div class="flex min-h-[calc(100vh-61px)]">
        
        <!-- Sidebar Izquierdo -->
        <aside class="w-20 bg-white border-r border-slate-200 flex flex-col justify-between items-center py-6 relative z-30">
            <nav class="flex flex-col space-y-5 w-full items-center">
                <a href="dashboard.php" title="Ruta Actual" class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl shadow-sm">
                    <i class="fa-solid fa-bus"></i>
                </a>
                <a href="paradas.php" title="Lista de Paradas" class="w-12 h-12 rounded-2xl text-slate-400 hover:bg-slate-50 hover:text-slate-600 flex items-center justify-center text-xl transition">
                    <i class="fa-solid fa-route"></i>
                </a>
                <a href="incidentes.php" title="Incidentes" class="w-12 h-12 rounded-2xl text-slate-400 hover:bg-slate-50 hover:text-slate-600 flex items-center justify-center text-xl transition">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </a>
            </nav>

            <div class="relative">
                <button id="btn-perfil-cond" onclick="toggleMenuConductor()" class="w-11 h-11 rounded-full bg-blue-600 hover:bg-blue-700 text-white font-black flex items-center justify-center text-sm shadow-md transition focus:outline-none">
                    <?php echo $iniciales; ?>
                </button>

                <div id="dropdown-perfil-cond" class="hidden absolute bottom-0 left-16 ml-3 w-48 bg-white border border-slate-200 rounded-2xl shadow-xl py-2 z-50">
                    <div class="px-4 py-2 border-b border-slate-100">
                        <p class="text-xs font-extrabold text-slate-800"><?php echo htmlspecialchars($nombreConductor); ?></p>
                        <p class="text-[10px] text-slate-400">Conductor</p>
                    </div>
                    <a href="configuracion.php" class="flex items-center space-x-2 px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50 hover:text-blue-600 transition">
                        <i class="fa-solid fa-user-gear w-4"></i>
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
            
            <!-- Encabezado de Ruta y Vehículo -->
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-200 space-y-4">
                <div class="flex justify-between items-center border-b border-slate-100 pb-4">
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase">Ruta Actual</p>
                        <h2 class="text-xl font-black text-slate-900"><?php echo htmlspecialchars($nombreRuta); ?></h2>
                    </div>
                    <div class="text-right">
                        <p class="text-xs font-bold text-slate-400 uppercase">Vehículo Asignado</p>
                        <h2 class="text-xl font-black text-slate-900"><?php echo htmlspecialchars($placa); ?></h2>
                    </div>
                </div>

                <div class="relative">
                    <div id="mapa"></div>

                    <!-- Panel Flotante de Paradas Dinámicas -->
                    <div class="absolute top-3 right-3 bg-white/95 backdrop-blur-sm p-4 rounded-2xl shadow-lg border border-slate-100 z-[1000] w-72 max-h-72 overflow-y-auto space-y-3">
                        <h4 class="text-xs font-black text-slate-400 uppercase border-b pb-1">Progreso del Recorrido</h4>
                        
                        <div id="lista-paradas" class="space-y-2">
                            <?php foreach ($paradas as $index => $parada): ?>
                                <div id="parada-card-<?php echo $parada['id']; ?>" class="flex items-start space-x-3 p-2 rounded-xl bg-slate-50 border border-slate-100 transition-all">
                                    <div id="parada-icon-<?php echo $parada['id']; ?>" class="w-5 h-5 rounded-full bg-slate-300 text-white flex items-center justify-center text-[10px] shrink-0 mt-0.5 font-black">
                                        <?php echo $parada['orden']; ?>
                                    </div>
                                    <div>
                                        <p class="text-xs font-black text-slate-800 uppercase"><?php echo htmlspecialchars($parada['nombre']); ?></p>
                                        <p id="parada-status-<?php echo $parada['id']; ?>" class="text-[10px] font-bold text-slate-400">
                                            Est.: <?php echo date('h:i A', strtotime($parada['horaEstimada'])); ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Fila Inferior: Escáner QR de Pasajeros y Acciones -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                
                <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-200 space-y-4">
                    <h3 class="text-lg font-black text-slate-900">Escanear Pasajero</h3>
                    
                    <div id="reader-container" class="bg-slate-50 border-2 border-dashed border-blue-400 rounded-2xl p-4 text-center min-h-[200px] flex flex-col items-center justify-center">
                        <div id="qr-reader" class="w-full max-w-xs mx-auto"></div>
                        <button id="btn-iniciar-camara" onclick="iniciarCamaraQR()" class="bg-blue-600 hover:bg-blue-700 text-white font-extrabold px-4 py-2.5 rounded-xl text-xs shadow transition mt-2">
                            <i class="fa-solid fa-camera mr-2"></i> Activar Cámara Lector
                        </button>
                    </div>

                    <div id="resultado-qr" class="hidden p-3 rounded-xl text-xs font-bold text-center"></div>
                </div>

                <div class="space-y-4 flex flex-col justify-between">
                    <button onclick="abrirModalIncidente()" class="w-full bg-white hover:bg-red-50 text-red-600 font-extrabold py-4 px-6 rounded-3xl border border-red-200 shadow-sm flex items-center justify-center space-x-3 transition">
                        <span class="w-8 h-8 rounded-xl bg-red-100 flex items-center justify-center text-sm">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </span>
                        <span>Reportar Incidente / Retraso</span>
                    </button>

                    <!-- Indicador de Transmisión Automática Fija -->
                    <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-200 text-center space-y-3">
                        <div class="w-full bg-emerald-50 border border-emerald-200 text-emerald-700 font-black py-4 px-6 rounded-2xl shadow-sm flex items-center justify-center space-x-3">
                            <span class="relative flex h-3 w-3">
                              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                              <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                            </span>
                            <span class="text-sm">TRANSMITIENDO GPS EN VIVO</span>
                        </div>
                    </div>
                </div>

            </div>

        </main>
    </div>

    <!-- Modal para Reporte de Incidentes -->
    <div id="modal-incidente" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl p-6 w-full max-w-md space-y-4 shadow-2xl">
            <h3 class="text-lg font-black text-slate-900">Reportar Incidente en Ruta</h3>
            <form id="form-incidente" onsubmit="guardarIncidente(event)" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Tipo de Incidente</label>
                    <select id="tipo-incidente" class="w-full px-4 py-3 bg-slate-50 border rounded-xl text-sm font-semibold">
                        <option value="mecanico">Falla Mecánica</option>
                        <option value="trafico">Congestión / Tráfico Alto</option>
                        <option value="emergencia">Emergencia</option>
                        <option value="otro">Otro</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Descripción corta</label>
                    <textarea id="desc-incidente" required rows="3" placeholder="Describe brevemente la situación..." class="w-full px-4 py-3 bg-slate-50 border rounded-xl text-sm font-semibold"></textarea>
                </div>
                <div class="flex space-x-3">
                    <button type="button" onclick="cerrarModalIncidente()" class="w-1/2 py-3 bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold rounded-xl text-xs">Cancelar</button>
                    <button type="submit" class="w-1/2 py-3 bg-red-600 hover:bg-red-700 text-white font-extrabold rounded-xl text-xs shadow">Enviar Alerta</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        let map = null;
        let busMarker = null;
        let watchId = null;
        let wakeLock = null;
        let html5QrCode = null;
        
        const busId = <?php echo $busId; ?>;
        const paradasBD = <?php echo json_encode($paradas); ?>;

        let ultimaLat = null;
        let ultimaLng = null;
        let ultimoTiempo = null;

        const busIcon = L.divIcon({
            className: 'custom-driver-icon',
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

        // Evitar que la pantalla se apague (Screen Wake Lock)
        async function solicitarWakeLock() {
            try {
                if ('wakeLock' in navigator) {
                    wakeLock = await navigator.wakeLock.request('screen');
                    console.log('Screen Wake Lock activo');
                }
            } catch (err) {
                console.warn(`Wake Lock no disponible: ${err.message}`);
            }
        }

        // FUNCIONES DE AUTO-INICIO AUTOMÁTICO AL CARGAR LA PÁGINA
        function autoIniciarTransmision() {
            solicitarWakeLock();

            if ("geolocation" in navigator) {
                // 1. Obtener primera coordenada de inmediato para actualizar BD rápido
                navigator.geolocation.getCurrentPosition(
                    pos => procesarNuevaUbicacion(pos),
                    err => console.error("Error al obtener posición inicial:", err.message),
                    { enableHighAccuracy: true, timeout: 10000 }
                );

                // 2. Mantener escucha continua en tiempo real
                watchId = navigator.geolocation.watchPosition(
                    pos => procesarNuevaUbicacion(pos),
                    err => console.error("Error en rastreo continuo:", err.message),
                    { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 }
                );
            } else {
                alert("Tu navegador no soporta geolocalización.");
            }
        }

        function procesarNuevaUbicacion(pos) {
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;
            const ahora = Date.now();
            let velocidadKmH = 0;

            if (pos.coords.speed !== null && pos.coords.speed > 0) {
                velocidadKmH = pos.coords.speed * 3.6;
            } else if (ultimaLat !== null && ultimaLng !== null && ultimoTiempo !== null) {
                const distMetros = calcularDistanciaMetros(ultimaLat, ultimaLng, lat, lng);
                const tiempoSegundos = (ahora - ultimoTiempo) / 1000;
                if (tiempoSegundos > 0) {
                    velocidadKmH = (distMetros / tiempoSegundos) * 3.6;
                }
            }

            ultimaLat = lat;
            ultimaLng = lng;
            ultimoTiempo = ahora;

            const posArray = [lat, lng];

            if (!busMarker) {
                busMarker = L.marker(posArray, { icon: busIcon }).addTo(map);
            } else {
                busMarker.setLatLng(posArray);
            }
            map.setView(posArray, 16);

            // Enviar posición a la base de datos
            enviarUbicacionBD(lat, lng, velocidadKmH);
            
            // Verificar cercanía a paradas
            verificarProximidadParadas(lat, lng);
        }

        // EJECUTAR AUTOINICIO TAN PRONTO EL DOCUMENTO ESTÉ LISTO
        window.addEventListener('DOMContentLoaded', autoIniciarTransmision);

        // Re-solicitar Wake Lock si la pestaña vuelve a ser visible
        document.addEventListener('visibilitychange', async () => {
            if (document.visibilityState === 'visible') {
                await solicitarWakeLock();
            }
        });

        function enviarUbicacionBD(lat, lng, velocidad) {
            const formData = new FormData();
            formData.append('bus_id', busId);
            formData.append('lat', lat);
            formData.append('lng', lng);
            formData.append('velocidad', velocidad.toFixed(2));

            fetch('../api/ubicacion.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                console.log("GPS actualizado automáticamente:", data);
            })
            .catch(err => console.error("Error conectando con API:", err));
        }

        function verificarProximidadParadas(busLat, busLng) {
            if (!paradasBD || !Array.isArray(paradasBD)) return;

            const latBusNum = parseFloat(busLat);
            const lngBusNum = parseFloat(busLng);

            paradasBD.forEach(parada => {
                if (parada.latitud !== null && parada.longitud !== null) {
                    const paradaLatNum = parseFloat(parada.latitud);
                    const paradaLngNum = parseFloat(parada.longitud);

                    const dist = calcularDistanciaMetros(latBusNum, lngBusNum, paradaLatNum, paradaLngNum);

                    if (dist < 150) {
                        const card = document.getElementById(`parada-card-${parada.id}`);
                        const icon = document.getElementById(`parada-icon-${parada.id}`);
                        const status = document.getElementById(`parada-status-${parada.id}`);

                        if (card) {
                            card.className = "flex items-start space-x-3 p-2 rounded-xl bg-emerald-50 border border-emerald-200 transition-all shadow-sm";
                            icon.className = "w-5 h-5 rounded-full bg-emerald-500 text-white flex items-center justify-center text-[10px] shrink-0 mt-0.5 font-black";
                            icon.innerHTML = '<i class="fa-solid fa-check"></i>';
                            
                            if (status) {
                                status.className = "text-[10px] font-bold text-emerald-600";
                                status.innerText = "✓ Confirmada / Llegó";
                            }
                        }
                    }
                }
            });
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

        function iniciarCamaraQR() {
            document.getElementById('btn-iniciar-camara').classList.add('hidden');
            html5QrCode = new Html5Qrcode("qr-reader");

            html5QrCode.start(
                { facingMode: "environment" },
                { fps: 10, qrbox: { width: 200, height: 200 } },
                (qrText) => {
                    validarQRServidor(qrText);
                    html5QrCode.stop();
                    document.getElementById('btn-iniciar-camara').classList.remove('hidden');
                },
                (errorMessage) => { }
            ).catch(err => alert("Error accediendo a la cámara: " + err));
        }

        function validarQRServidor(token) {
            const resDiv = document.getElementById('resultado-qr');
            resDiv.classList.remove('hidden');
            resDiv.className = "p-3 rounded-xl text-xs font-bold text-center bg-blue-100 text-blue-700";
            resDiv.innerText = "Validando pase de abordaje...";

            const formData = new FormData();
            formData.append('token', token);

            fetch('../api/validar_qr.php', { method: 'POST', body: formData })
            .then(res => res.text())
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if(data.status === 'success') {
                        resDiv.className = "p-3 rounded-xl text-xs font-bold text-center bg-emerald-100 text-emerald-800 border border-emerald-300";
                        resDiv.innerText = "✓ " + data.message + ": " + data.estudiante;
                    } else {
                        resDiv.className = "p-3 rounded-xl text-xs font-bold text-center bg-red-100 text-red-800 border border-red-300";
                        resDiv.innerText = "✕ " + data.message;
                    }
                } catch (e) {
                    resDiv.className = "p-3 rounded-xl text-xs font-bold text-center bg-amber-100 text-amber-800";
                    resDiv.innerText = "Error en el servidor al validar el QR.";
                }
            })
            .catch(err => console.error("Error en petición HTTP:", err));
        }

        function toggleMenuConductor() {
            document.getElementById('dropdown-perfil-cond').classList.toggle('hidden');
        }

        function abrirModalIncidente() { document.getElementById('modal-incidente').classList.remove('hidden'); }
        function cerrarModalIncidente() { document.getElementById('modal-incidente').classList.add('hidden'); }

        function guardarIncidente(e) {
            e.preventDefault();
            const tipo = document.getElementById('tipo-incidente').value;
            const desc = document.getElementById('desc-incidente').value;

            const formData = new FormData();
            formData.append('tipo', tipo);
            formData.append('descripcion', desc);

            fetch('../api/reportar_incidente.php', { method: 'POST', body: formData })
            .then(res => res.text())
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if(data.status === 'success') {
                        alert("Incidente reportado correctamente a los estudiantes y administración.");
                        cerrarModalIncidente();
                        document.getElementById('desc-incidente').value = '';
                    } else {
                        alert("Error: " + data.message);
                    }
                } catch(e) {
                    console.error("Error al reportar:", text);
                }
            });
        }
    </script>
</body>
</html>