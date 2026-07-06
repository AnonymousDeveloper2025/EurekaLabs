<?php
/**
 * EUREKA LABS - CONFIGURAÇÃO FINAL
 * Versão: 3.0 - Com Gemini 2.5 Flash (FUNCIONA!)
 */

// 1. Configurações de Base de Dados (PostgreSQL)
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: 'password');
define('DB_NAME', getenv('DB_NAME') ?: 'idefy_db');

// 2. Configurações da API Gemini (Google - DIRECTAMENTE)
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');
define('GEMINI_API_BASE', 'https://generativelanguage.googleapis.com/v1beta/models');
// ✅ MODELO CONFIRMADO QUE FUNCIONA:
define('GEMINI_MODEL', 'gemini-2.5-flash');

// 3. Middleware de CORS
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

$allowed_origins = [
    'https://anonymousdeveloper2025.github.io',
    'http://eurekalabs.great-site.net',
    'https://eurekalabs.great-site.net'
];

if (in_array($origin, $allowed_origins) || preg_match('/github\.io$/', parse_url($origin, PHP_URL_HOST) ?? '') || preg_match('/great-site\.net$/', parse_url($origin, PHP_URL_HOST) ?? '')) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header("Access-Control-Allow-Origin: *");
}

// ✅ CORRIGIDO: Removido 'Access-Control-Allow-Credentials: true'.
// Combinar "Allow-Origin: *" com "Allow-Credentials: true" é PROIBIDO pelo browser
// e faz bloquear a resposta inteira (aparece como "Failed to fetch" no fetch()).
// A app usa token JWT manual (não cookies), por isso este header nem é necessário.
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Origin, Accept');
header('Access-Control-Max-Age: 3600');

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
 * Chamada à API Gemini (Google - DIRECTAMENTE)
 */
function callGeminiAPI($prompt) {
    $apiKey = GEMINI_API_KEY;
    
    if (empty($apiKey)) {
        error_log("Erro: GEMINI_API_KEY não está configurada!");
        return null;
    }
    
    // Endpoint do Gemini com modelo que FUNCIONA
    $url = GEMINI_API_BASE . '/' . GEMINI_MODEL . ':generateContent?key=' . urlencode($apiKey);
    
    $data = [
        'contents' => [
            [
                'parts' => [
                    [
                        'text' => $prompt
                    ]
                ]
            ]
        ],
        'generationConfig' => [
            'maxOutputTokens' => 4000,
            'temperature' => 0.7
        ]
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
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
        error_log("Erro API Gemini (HTTP $httpCode): " . $response);
        return null;
    }
    
    $responseData = json_decode($response, true);
    
    // Gemini retorna formato: { "candidates": [ { "content": { "parts": [ { "text": "..." } ] } } ] }
    if (!$responseData || !isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
        error_log("Resposta Gemini inválida: " . json_encode($responseData));
        return null;
    }
    
    // Converter para formato compatível
    return [
        'choices' => [
            [
                'message' => [
                    'content' => $responseData['candidates'][0]['content']['parts'][0]['text']
                ]
            ]
        ]
    ];
}

// Auth Helpers
function isValidEmail($email) { 
    return filter_var($email, FILTER_VALIDATE_EMAIL); 
}

function hashPassword($password) { 
    return password_hash($password, PASSWORD_BCRYPT); 
}

function verifyPassword($password, $hash) { 
    return password_verify($password, $hash); 
}

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
