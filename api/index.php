<?php
// Enrutador global para Vercel Serverless PHP

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . '/..' . $uri;

// Si la ruta solicitada es la raíz '/', cargar el index.php principal de la raíz
if ($uri === '/' || $uri === '') {
    require __DIR__ . '/../index.php';
    exit;
}

// Si la ruta corresponde a un archivo PHP existente en alguna carpeta, incluirlo
if (file_exists($file) && is_file($file) && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
    require $file;
    exit;
}

// Para archivos estáticos (CSS, JS, imágenes), intentar servirlos o incluir el index principal
if (file_exists($file) && is_file($file)) {
    return false; // Permite a Vercel servir archivos estáticos directamente
}

// Si no encuentra el archivo, intentar cargar index.php de la raíz o mostrar error
if (file_exists(__DIR__ . '/../index.php')) {
    require __DIR__ . '/../index.php';
} else {
    http_response_code(404);
    echo "Página no encontrada";
}
?>