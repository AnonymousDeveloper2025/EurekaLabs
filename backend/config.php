<?php
// ============================================
// CONFIGURAÇÃO DO EUREKA LABS - RENDER VERSION
// ============================================

// Base de Dados (Variáveis de Ambiente do Render)
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: 'password');
define('DB_NAME', getenv('DB_NAME') ?: 'idefy_db');

// API Gemini
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent');

// Unsplash
define('UNSPLASH_API_KEY', getenv('UNSPLASH_API_KEY') ?: 'YOUR_UNSPLASH_API_KEY_HERE');
define('UNSPLASH_API_URL', 'https://api.unsplash.com');

// Frontend URL
define('FRONTEND_URL', getenv('FRONTEND_URL') ?: 'https://anonymousdeveloper2025.github.io');

// JWT Secret
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'your_super_secret_key_change_this');

// Headers CORS dinâmicos
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

// Resposta JSON
function jsonResponse($success, $message = '', $data = []) {
    return json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
}

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

// API Gemini Call
function callGeminiAPI($prompt) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => GEMINI_API_URL . '?key=' . GEMINI_API_KEY,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'contents' => [['parts' => [['text' => $prompt]]]]
        ])
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// Unsplash Image
function getUnsplashImage($query) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => UNSPLASH_API_URL . '/search/photos?query=' . urlencode($query) . '&client_id=' . UNSPLASH_API_KEY,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Accept-Version: v1']
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    return !empty($data['results']) ? $data['results'][0]['urls']['regular'] : null;
}

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
