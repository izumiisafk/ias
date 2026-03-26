<?php
require_once 'env_loader.php';

$host     = getenv('DB_HOST') ?: 'db.pnbrkfpqrigmsluzhbff.supabase.co';
$port     = getenv('DB_PORT') ?: '5432';
$dbname   = getenv('DB_NAME') ?: 'postgres';
$username = getenv('DB_USER') ?: 'postgres';
$password = getenv('DB_PASSWORD') ?: '';

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
    $conn = new PDO($dsn, $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Add compatibility for fetch_assoc() style
    // (We will still need to refactor function calls)
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
