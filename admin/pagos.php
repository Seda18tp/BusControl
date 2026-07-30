<?php
session_start();

// Verificar que el usuario tenga sesión activa y sea administrador
if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol'] !== 'admin' && ($_SESSION['rolid'] ?? 0) != 1)) { 
    header("Location: ../index.php"); 
    exit; 
}

require_once __DIR__ . '/../db/conexion.php';

$nombreAdmin = $_SESSION['nombre'] ?? 'Admin';
$iniciales = strtoupper(substr($nombreAdmin, 0, 2));

// Procesar actualización rápida de pago
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuarioId = $_POST['usuario_id'];
    $nuevoEstado = $_POST['estado'];
    $validoHasta = date('Y-m-d', strtotime('+30 days'));

    // Uso de comillas dobles en columnas camelCase para PostgreSQL/Supabase
    $stmt = $pdo->prepare('INSERT INTO pagos ("usuarioId", monto, "fechaPago", "validoHasta", estado) VALUES (?, 50000.00, NOW(), ?, ?)');
    $stmt->execute([$usuarioId, $validoHasta, $nuevoEstado]);
    $mensaje = "Estado de pago actualizado correctamente.";
}

// Obtener lista completa de estudiantes y su último pago (PostgreSQL Case-Safe)
$stmtEstudiantes = $pdo->query('SELECT u.id, u.nombre, u.email, u."codigoEstudiante", 
                                (SELECT estado FROM pagos WHERE "usuarioId" = u.id ORDER BY id DESC LIMIT 1) as "estadoPago",
                                (SELECT "validoHasta" FROM pagos WHERE "usuarioId" = u.id ORDER BY id DESC LIMIT 1) as "validoHasta"
                                FROM usuarios u WHERE u."rolId" = 3 OR u.rolid = 3');
$estudiantes = $stmtEstudiantes->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BUSCONTROL - Control de Pagos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/estilos.css">
</head>
<body class="bg-slate-100 font-sans text-slate-800 antialiased">

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
                <a href="pagos.php" title="Gestión de Pagos" class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl shadow-sm">
                    <i class="fa-solid fa-credit-card"></i>
                </a>
                <a href="notificaciones.php" title="Alertas e Incidentes" class="w-12 h-12 rounded-2xl text-slate-400 hover:bg-slate-50 hover:text-slate-600 flex items-center justify-center text-xl transition relative">
                    <i class="fa-solid fa-bell"></i>
                </a>
            </nav>
        </aside>

        <!-- Contenido Principal -->
        <main class="flex-1 p-8 max-w-6xl mx-auto space-y-6">
            <?php if (isset($mensaje)): ?>
                <div class="p-4 bg-emerald-100 border border-emerald-200 text-emerald-800 rounded-2xl text-xs font-bold">
                    <?php echo $mensaje; ?>
                </div>
            <?php endif; ?>

            <h1 class="text-3xl font-black text-slate-900">Gestión de Pagos de Estudiantes</h1>
            
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 text-xs font-black text-slate-400 uppercase">
                            <th class="py-3 px-4">Estudiante</th>
                            <th class="py-3 px-4">Código</th>
                            <th class="py-3 px-4">Estado Actual</th>
                            <th class="py-3 px-4">Válido Hasta</th>
                            <th class="py-3 px-4 text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs font-semibold">
                        <?php foreach($estudiantes as $est): ?>
                        <?php 
                            $codigo = $est['codigoEstudiante'] ?? $est['codigoestudiante'] ?? 'N/A';
                            $estadoPago = $est['estadoPago'] ?? $est['estadopago'] ?? 'vencido';
                            $validoHasta = $est['validoHasta'] ?? $est['validohasta'] ?? null;
                        ?>
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="py-3.5 px-4 font-black text-slate-800"><?php echo htmlspecialchars($est['nombre']); ?></td>
                            <td class="py-3.5 px-4 text-slate-500"><?php echo htmlspecialchars($codigo ?: 'N/A'); ?></td>
                            <td class="py-3.5 px-4">
                                <?php if($estadoPago === 'al_dia'): ?>
                                    <span class="bg-emerald-100 text-emerald-800 px-3 py-1 rounded-full font-bold text-[10px]">AL DÍA</span>
                                <?php else: ?>
                                    <span class="bg-red-100 text-red-800 px-3 py-1 rounded-full font-bold text-[10px]">EN MORA</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 text-slate-500"><?php echo $validoHasta ? date('d/m/Y', strtotime($validoHasta)) : 'N/A'; ?></td>
                            <td class="py-3.5 px-4 text-right">
                                <form method="POST" class="inline-flex space-x-2">
                                    <input type="hidden" name="usuario_id" value="<?php echo $est['id']; ?>">
                                    <input type="hidden" name="estado" value="al_dia">
                                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-xl font-bold text-[11px] transition">
                                        Marcar Pagado
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>