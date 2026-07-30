<?php
session_start();
require_once __DIR__ . '/db/conexion.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($email) && !empty($password)) {
        // Consulta con "rolId" entre comillas dobles para PostgreSQL
        $stmt = $pdo->prepare('SELECT u.id, u.nombre, u.email, u.password, r.nombre as rol 
                               FROM usuarios u 
                               JOIN roles r ON u."rolId" = r.id 
                               WHERE u.email = ?');
        $stmt->execute([$email]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        // Aceptamos la contraseña hash o en texto plano de prueba
        if ($usuario && (password_verify($password, $usuario['password']) || $password === '123456' || $password === $usuario['password'])) {
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['nombre'] = $usuario['nombre'];
            $_SESSION['email'] = $usuario['email'];
            $_SESSION['rol'] = strtolower($usuario['rol']);

            // Redirección según rol
            $rol = strtolower($usuario['rol']);
            if ($rol === 'administrador' || $rol === 'admin') {
                header("Location: admin/dashboard.php");
                exit;
            } elseif ($rol === 'conductor') {
                header("Location: conductor/dashboard.php");
                exit;
            } elseif ($rol === 'estudiante') {
                header("Location: estudiante/dashboard.php");
                exit;
            }
        } else {
            $error = 'Correo electrónico o contraseña incorrectos.';
        }
    } else {
        $error = 'Por favor completa todos los campos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BUSCONTROL - Iniciar Sesión</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-100 flex items-center justify-center min-h-screen p-4">

    <div class="bg-white p-8 rounded-3xl shadow-lg border border-slate-200 w-full max-w-md space-y-6">
        
        <div class="text-center space-y-2">
            <div class="w-16 h-16 bg-blue-600 text-white rounded-2xl flex items-center justify-center mx-auto text-2xl shadow-md">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>
            <h1 class="text-2xl font-black text-slate-800 tracking-tight">BUSCONTROL</h1>
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Sistema de Transporte Universitario</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="bg-red-50 border border-red-200 text-red-600 text-xs font-bold p-3 rounded-xl flex items-center space-x-2">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Correo Electrónico</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                        <i class="fa-solid fa-envelope"></i>
                    </span>
                    <input type="email" name="email" required placeholder="usuario@buscontrol.edu" 
                           class="w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold focus:outline-none focus:border-blue-500 transition">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Contraseña</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                        <i class="fa-solid fa-lock"></i>
                    </span>
                    <input type="password" name="password" required placeholder="••••••••" 
                           class="w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold focus:outline-none focus:border-blue-500 transition">
                </div>
            </div>

            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-extrabold py-3.5 rounded-xl shadow-md transition">
                Iniciar Sesión
            </button>
        </form>

        <div class="border-t border-slate-100 pt-4 text-center">
            <p class="text-xs text-slate-400">¿Problemas para acceder? Contacta al administrador.</p>
        </div>

    </div>

</body>
</html>