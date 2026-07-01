<?php
session_start();

// 1. CORS: Libera teu front do GitHub + Great-Site
$allowed_origins = [
    'https://anonymousdeveloper2025.github.io',
    'https://eurekalabs.great-site.net',
    'http://eurekalabs.great-site.net'
];

if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
    header("Access-Control-Allow-Origin: ". $_SERVER['HTTP_ORIGIN']);
    header('Access-Control-Allow-Credentials: true');
}

header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Responde o preflight OPTIONS e para aqui
if ($_SERVER['REQUEST_METHOD']=== 'OPTIONS') {
    http_response_code(200);
    exit();
}

header('Content-Type: application/json; charset=UTF-8');

// 2. Conexão DB - POSTGRES do Render via DATABASE_URL
function getDBConnection() {
    $databaseUrl = $_ENV['DATABASE_URL']?? ''; // Render cria essa var sozinho

    if (empty($databaseUrl)) {
        echo json_encode(['success' => false, 'message' => 'Erro DB: DATABASE_URL não definida no Render']);
        exit();
    }

    $db = parse_url($databaseUrl);
    $host = $db['host'];
    $port = $db['port']?? 5432;
    $dbname = ltrim($db['path'], '/');
    $user = $db['user'];
    $pass = $db['pass'];

    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require"; // Obrigatório no Render
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    try {
        return new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erro DB: '. $e->getMessage()]);
        exit();
    }
}

// 3. Auth Helpers
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

function generateToken($userId) {
    return bin2hex(random_bytes(32))."_".$userId;
}

// 4. Gemini API - MODELO: gemini-1.5-flash-latest = 1500 requests/dia grátis
function callGeminiAPI($prompt) {
    $apiKey = $_ENV['GEMINI_API_KEY']?? '';

    if (empty($apiKey)) {
        return ['error' => 'GEMINI_API_KEY não definida no Render > Environment'];
    }

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=". $apiKey;

    $payload = [
        "contents" => [
            ["role" => "user", "parts" => [["text" => $prompt]]]
        ],
        "generationConfig" => [
            "temperature" => 0.7,
            "maxOutputTokens" => 8192
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 45);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        return ['error' => 'CURL: '. curl_error($ch)];
    }

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($response, true);
    if ($http_code!== 200 && isset($decoded['error'])) {
        return ['error' => $decoded['error']['message']?? 'Erro HTTP '. $http_code];
    }

    return $decoded;
}
?>
