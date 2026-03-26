<?php
/** @var PDO $conn Database connection object */
$conn     = null;
$db_error = '';

require_once __DIR__ . '/env_loader.php';

$host     = getenv('DB_HOST') ?: 'aws-1-ap-south-1.pooler.supabase.com';
$port     = getenv('DB_PORT') ?: '5432';
$dbname   = getenv('DB_NAME') ?: 'postgres';
$username = getenv('DB_USER') ?: 'postgres.pnbrkfpqrigmsluzhbff';
$password = getenv('DB_PASSWORD') ?: '';

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_PERSISTENT         => true, // Faster reconnects
        PDO::ATTR_TIMEOUT            => 5,    // Connection timeout
    ];
    $conn = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    $db_error = $e->getMessage();
}
?>
