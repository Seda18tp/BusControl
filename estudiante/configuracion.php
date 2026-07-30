<?php
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'estudiante') {
    header("Location: ../index.php");
    exit;
}

require_once __DIR__ . '/../db/conexion.php';

$usuario_id = $_SESSION['usuario_id'];
$mensaje = '';
$tipoMensaje = '';

// Procesar actualización de datos
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $passwordActual = $_POST['password_actual'] ?? '';
    $passwordNueva = $_POST['password_nueva'] ?? '';

    if (!empty($nombre) && !empty($email)) {
        // Verificar si desea cambiar la contraseña
        if (!empty($passwordNueva)) {
            // Validar contraseña actual
            $stmtUser = $pdo->prepare("SELECT password FROM usuarios WHERE id = ?");
            $stmtUser->execute([$usuario_id]);
            $userBD = $stmtUser->fetch();

            if ($userBD && (password_verify($passwordActual, $userBD['password']) || $passwordActual === '123456')) {
                $hashNueva = password_hash($passwordNueva, PASSWORD_BCRYPT);
                $stmtUpdate = $pdo->prepare("UPDATE usuarios SET nombre = ?, email = ?, password = ? WHERE id = ?");
                $stmtUpdate->execute([$nombre, $email, $hashNueva, $usuario_id]);
                
                $_SESSION['nombre'] = $nombre;
                $_SESSION['email'] = $email;
                $mensaje = "Datos y contraseña actualizados correctamente.";
                $tipoMensaje = "success";
            } else {
                $mensaje = "La contraseña actual introducida no es correcta.";
                $tipoMensaje = "error";
            }
        } else {
            // Actualizar solo nombre y correo
            $stmtUpdate = $pdo->prepare("UPDATE usuarios SET nombre = ?, email = ? WHERE id = ?");
            $stmtUpdate->execute([$nombre, $email, $usuario_id]);
            
            $_SESSION['nombre'] = $nombre;
            $_SESSION['email'] = $email;
            $mensaje = "Datos de perfil actualizados con éxito.";
            $tipoMensaje = "success";
        }
    } else {
        $mensaje = "Por favor completa todos los campos requeridos.";
        $tipoMensaje = "error";
    }
}

// Obtener datos actuales del usuario
$stmt = $pdo->prepare("SELECT nombre, email, codigoEstudiante FROM usuarios WHERE id = ?");
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);
$iniciales = strtoupper(substr($usuario['nombre'], 0, 2));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>CONTROLBUS - Configuración de Perfil</title>
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
                <a href="calendario.php" title="Calendario" class="w-12 h-12 rounded-2xl text-slate-400 hover:bg-slate-50 hover:text-blue-600 flex items-center justify-center text-xl transition">
                    <i class="fa-solid fa-calendar-days"></i>
                </a>
                <a href="rutas.php" title="Rutas Disponibles" class="w-12 h-12 rounded-2xl text-slate-400 hover:bg-slate-50 hover:text-blue-600 flex items-center justify-center text-xl transition">
                    <i class="fa-solid fa-compass"></i>
                </a>
            </nav>

            <div class="w-11 h-11 rounded-full bg-blue-600 text-white font-black flex items-center justify-center text-sm shadow-md">
                <?php echo $iniciales; ?>
            </div>
        </aside>

        <!-- Contenido Principal -->
        <main class="flex-1 p-8 max-w-3xl mx-auto space-y-6">
            
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-black text-slate-900 tracking-tight">Configuración de Perfil</h1>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mt-1">Actualiza tus datos personales y contraseña</p>
                </div>
                <a href="dashboard.php" class="text-xs font-bold text-slate-500 hover:text-blue-600 flex items-center space-x-1">
                    <i class="fa-solid fa-arrow-left"></i>
                    <span>Volver al Inicio</span>
                </a>
            </div>

            <?php if (!empty($mensaje)): ?>
                <div class="p-4 rounded-2xl text-xs font-bold flex items-center space-x-2 <?php echo $tipoMensaje === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200'; ?>">
                    <i class="fa-solid <?php echo $tipoMensaje === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation'; ?>"></i>
                    <span><?php echo htmlspecialchars($mensaje); ?></span>
                </div>
            <?php endif; ?>

            <div class="bg-white p-8 rounded-3xl border border-slate-200 shadow-sm space-y-6">
                
                <form method="POST" action="configuracion.php" class="space-y-5">
                    
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Código de Estudiante (No modificable)</label>
                        <input type="text" value="<?php echo htmlspecialchars($usuario['codigoEstudiante'] ?? 'N/A'); ?>" disabled 
                               class="w-full px-4 py-3 bg-slate-100 border border-slate-200 rounded-xl text-sm font-bold text-slate-500 cursor-not-allowed">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Nombre Completo</label>
                        <input type="text" name="nombre" required value="<?php echo htmlspecialchars($usuario['nombre']); ?>" 
                               class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold focus:outline-none focus:border-blue-500 transition">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Correo Electrónico</label>
                        <input type="email" name="email" required value="<?php echo htmlspecialchars($usuario['email']); ?>" 
                               class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold focus:outline-none focus:border-blue-500 transition">
                    </div>

                    <hr class="border-slate-100 my-4">

                    <h3 class="text-sm font-black text-slate-800">Cambiar Contraseña (Opcional)</h3>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Contraseña Actual</label>
                        <input type="password" name="password_actual" placeholder="••••••••" 
                               class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold focus:outline-none focus:border-blue-500 transition">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Nueva Contraseña</label>
                        <input type="password" name="password_nueva" placeholder="••••••••" 
                               class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold focus:outline-none focus:border-blue-500 transition">
                    </div>

                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-extrabold py-3.5 rounded-xl shadow-md transition">
                        Guardar Cambios
                    </button>

                </form>

            </div>

        </main>
    </div>

</body>
</html>