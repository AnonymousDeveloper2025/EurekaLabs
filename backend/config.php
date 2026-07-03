<?php
/**
 * EUREKA LABS - CONFIGURAÇÃO ELITE & CORS MIDDLEWARE
 */

// 1. Configurações de Base de Dados (PostgreSQL)
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: 'password');
define('DB_NAME', getenv('DB_NAME') ?: 'idefy_db');

// 2. Configurações da API Manus (Gemini)
define('MANUS_API_BASE', getenv('OPENAI_API_BASE') ?: 'https://api.manus.im/v1');
define('MANUS_API_KEY', getenv('OPENAI_API_KEY') ?: '');
define('MANUS_MODEL', 'gemini-3-flash-preview');

// 3. Middleware de CORS Definitivo
// Capturar a origem do pedido
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// Lista de domínios permitidos
$allowed_origins = [
    'https://anonymousdeveloper2025.github.io',
    'http://eurekalabs.great-site.net',
    'https://eurekalabs.great-site.net'
];

// Se for um dos nossos domínios, permitimos explicitamente
if (in_array($origin, $allowed_origins) || preg_match('/github\.io$/', parse_url($origin, PHP_URL_HOST) ?? '') || preg_match('/great-site\.net$/', parse_url($origin, PHP_URL_HOST) ?? '')) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    // Fallback para evitar bloqueio total em ambientes de teste
    header("Access-Control-Allow-Origin: *");
}

header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Origin, Accept');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 3600');

// Lidar com Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/**
 * Conexão à base de dados PostgreSQL usando PDO
 */
function getDBConnection() {
    try {
        $dbUrl = DB_HOST;
        if (strpos($dbUrl, 'postgresql://') === 0 || strpos($dbUrl, 'postgres://') === 0) {
            $db = parse_url($dbUrl);
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
        error_log("Erro BD: " . $e->getMessage());
        header('Content-Type: application/json');
        die(json_encode(['success' => false, 'message' => 'Erro na conexão com a base de dados.']));
    }
}

/**
 * Chamada à API Gemini via Proxy Manus (OpenAI Compatible)
 */
function callGeminiAPI($prompt) {
    $url = MANUS_API_BASE . '/chat/completions';
    
    $data = [
        'model' => MANUS_MODEL,
        'messages' => [
            ['role' => 'user', 'content' => $prompt]
        ],
        'max_tokens' => 4000
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . MANUS_API_KEY
        ],
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_TIMEOUT => 60
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        error_log("Erro cURL: " . curl_error($ch));
        curl_close($ch);
        return null;
    }
    
    curl_close($ch);
    
    if ($httpCode !== 200) {
        error_log("Erro API Manus (HTTP $httpCode): " . $response);
        return null;
    }
    
    return json_decode($response, true);
}

// Auth Helpers
function isValidEmail($email) { return filter_var($email, FILTER_VALIDATE_EMAIL); }
function hashPassword($password) { return password_hash($password, PASSWORD_BCRYPT); }
function verifyPassword($password, $hash) { return password_verify($password, $hash); }
function generateToken($userId) {
    $jwt_secret = getenv('JWT_SECRET') ?: 'eureka_labs_elite_secret_2026';
    $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
    $payload = json_encode(['userId' => $userId, 'iat' => time()]);
    $hE = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');
    $pE = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    $sig = hash_hmac('sha256', "$hE.$pE", $jwt_secret, true);
    $sE = rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');
    return "$hE.$pE.$sE";
}
?>
