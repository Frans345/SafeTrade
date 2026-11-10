<?php
$host = getenv('DB_HOST') ?: 'localhost';
$db   = getenv('DB_NAME') ?: 'safetrade';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: ''; 
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

//Session 30 minutes
$timeoutDuration = 1800; 

//User last activity
if (isset($_SESSION['user_id']) && isset($_SESSION['last_activity'])) {
   
    if ((time() - $_SESSION['last_activity']) > $timeoutDuration) {
        session_unset();     // Wipe all session variables
        session_destroy();   // Destroy the session completely
        header('Location: login.php?msg=session_expired'); 
        exit;
    }
}

//Update activity to now
if (isset($_SESSION['user_id'])) {
    $_SESSION['last_activity'] = time();
}
?>