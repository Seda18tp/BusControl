<?php
// db/conexion.php

$host = getenv('DB_HOST') ?: 'aws-0-sa-east-1.pooler.supabase.com';
$port = getenv('DB_PORT') ?: '5432';
$db   = getenv('DB_NAME') ?: 'postgres';
$user = getenv('DB_USER') ?: 'postgres.xxxx';
$pass = getenv('DB_PASS') ?: 'cxAP#z87Nz$knbS';

try {
    // Conexión PDO a PostgreSQL (Supabase)
    $dsn = "pgsql:host=$host;port=$port;dbname=$db;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Error de conexión con la base de datos Supabase: " . $e->getMessage());
}
?>
