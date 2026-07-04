<?php
/**
 * TEST GEMINI API CONNECTION
 * Usa isto para testar se a API Gemini está a funcionar
 * 
 * Acesso: https://teu-dominio/backend/test-gemini.php
 */

header('Content-Type: application/json');

// Simular as definições
$geminiApiKey = getenv('GEMINI_API_KEY') ?: 'AQ.Ab8RN6KcD3HUnIaGSuZr7Dtd7XRya2bzL1MW3mgsEznRYXH0jg';
$geminiApiBase = 'https://generativelanguage.googleapis.com/v1beta/models';
$geminiModel = 'gemini-1.5-flash';

// Verificar se temos a chave
if (empty($geminiApiKey)) {
    http_response_code(400);
    die(json_encode([
        'success' => false,
        'message' => 'GEMINI_API_KEY não está configurada!',
        'hint' => 'Adiciona no Render → Settings → Environment Variables'
    ]));
}

// Fazer teste simples
$testPrompt = "Responde em português com um parágrafo curto: O que é o Eureka Labs?";

$url = $geminiApiBase . '/' . $geminiModel . ':generateContent?key=' . urlencode($geminiApiKey);

$data = [
    'contents' => [
        [
            'parts' => [
                ['text' => $testPrompt]
            ]
        ]
    ],
    'generationConfig' => [
        'maxOutputTokens' => 500,
        'temperature' => 0.7
    ]
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($httpCode === 200) {
    $responseData = json_decode($response, true);
    
    if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => '✅ Gemini API está a funcionar!',
            'test_response' => $responseData['candidates'][0]['content']['parts'][0]['text'],
            'model' => $geminiModel,
            'api_base' => $geminiApiBase
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Resposta Gemini inválida',
            'raw_response' => $responseData
        ]);
    }
} else {
    http_response_code($httpCode);
    echo json_encode([
        'success' => false,
        'message' => "Erro na API Gemini (HTTP $httpCode)",
        'error' => $curlError ?: 'Sem detalhes',
        'raw_response' => $response,
        'debug_info' => [
            'url' => $url,
            'model' => $geminiModel,
            'api_key_present' => !empty($geminiApiKey),
            'api_key_length' => strlen($geminiApiKey) . ' caracteres'
        ]
    ]);
}
?>
