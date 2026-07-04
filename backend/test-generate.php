<?php
/**
 * TEST GENERATE-IDEA ENDPOINT
 * Testa se o endpoint está a funcionar correctamente
 */

header('Content-Type: application/json');

$apiKey = getenv('GEMINI_API_KEY') ?: '';

if (empty($apiKey)) {
    die(json_encode(['success' => false, 'message' => 'API Key não configurada']));
}

echo "🧪 Testando Generate-Idea Endpoint\n\n";

// Simular um pedido como se viesse do frontend
$testPayload = [
    'topic' => 'Como começar um negócio online',
    'mode' => 'simple',
    'category' => 'Negócios',
    'answers' => []
];

echo "Payload de teste:\n";
echo json_encode($testPayload, JSON_PRETTY_PRINT) . "\n\n";

// Precisamos de um token válido para testar
// Vamos criar um token de teste
require_once 'config.php';

// Gerar token de teste
$testUserId = 999;
$testToken = generateToken($testUserId);

echo "Token de teste gerado: " . substr($testToken, 0, 20) . "...\n\n";

// Testar o endpoint
$url = 'http://localhost/backend/api/generate-idea.php'; // Ou o URL do Render

echo "Testando endpoint em modo local...\n";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $testToken
    ],
    CURLOPT_POSTFIELDS => json_encode($testPayload),
    CURLOPT_TIMEOUT => 60
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Response Code: $httpCode\n\n";
echo "Response:\n";
echo json_encode(json_decode($response, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
