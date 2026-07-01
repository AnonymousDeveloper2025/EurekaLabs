<?php
// ============================================
// CONFIGURAÇÃO DO EUREKA LABS - ELITE VERSION
// ============================================

// Base de Dados (PostgreSQL)
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: 'password');
define('DB_NAME', getenv('DB_NAME') ?: 'idefy_db');

// API Gemini 1.5 Flash (Melhor resolução e velocidade)
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent');

// Frontend URL - CORS
$allowed_origins = [
    'https://anonymousdeveloper2025.github.io',
    'http://eurekalabs.great-site.net',
    'https://eurekalabs.great-site.net'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header("Access-Control-Allow-Origin: https://anonymousdeveloper2025.github.io");
}

header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

header('Content-Type: application/json');

// Conexão à base de dados (PostgreSQL via PDO)
function getDBConnection() {
    try {
        if (strpos(DB_HOST, 'postgresql://') === 0 || strpos(DB_HOST, 'postgres://') === 0) {
            $db = parse_url(DB_HOST);
            $host = $db['host'];
            $port = $db['port'] ?? 5432;
            $dbname = ltrim($db['path'], '/');
            $user = $db['user'];
            $pass = $db['pass'];
        } else {
            $host = DB_HOST;
            $port = 5432;
            $dbname = DB_NAME;
            $user = DB_USER;
            $pass = DB_PASS;
        }
        
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        die(json_encode(['success' => false, 'message' => 'Erro na conexão com a BD: ' . $e->getMessage()]));
    }
}

// API Gemini Call (v1.5 Flash)
function callGeminiAPI($prompt) {
    $url = GEMINI_API_URL . '?key=' . GEMINI_API_KEY;
    $data = [
        'contents' => [['parts' => [['text' => $prompt]]]]
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_TIMEOUT => 45 // Render timeout safe
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// JWT Secret
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'your_super_secret_key_change_this');

// Auth Helpers
function isValidEmail($email) { return filter_var($email, FILTER_VALIDATE_EMAIL); }
function hashPassword($password) { return password_hash($password, PASSWORD_BCRYPT); }
function verifyPassword($password, $hash) { return password_verify($password, $hash); }
function generateToken($userId) {
    $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
    $payload = json_encode(['userId' => $userId, 'iat' => time()]);
    $hE = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');
    $pE = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    $sig = hash_hmac('sha256', "$hE.$pE", JWT_SECRET, true);
    $sE = rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');
    return "$hE.$pE.$sE";
}
?>
