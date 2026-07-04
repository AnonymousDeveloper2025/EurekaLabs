<?php
/**
 * FINAL TEST - Gemini 2.5 Flash
 * Testa se a API Gemini 2.5 Flash funciona correctamente
 */

header('Content-Type: application/json');

$geminiApiKey = getenv('GEMINI_API_KEY') ?: '';

if (empty($geminiApiKey)) {
    http_response_code(400);
    die(json_encode([
        'success' => false,
        'message' => 'GEMINI_API_KEY não configurada'
    ]));
}

$model = 'gemini-2.5-flash';
$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . urlencode($geminiApiKey);

$testPrompt = "Responde em português com um parágrafo curto: O que é o Eureka Labs?";

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

echo "🧪 Testando Gemini 2.5 Flash...\n\n";

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
            'message' => '✅✅✅ GEMINI 2.5 FLASH FUNCIONA PERFEITAMENTE!',
            'model' => 'gemini-2.5-flash',
            'test_response' => $responseData['candidates'][0]['content']['parts'][0]['text'],
            'instructions' => [
                'Usar config_FINAL_FUNCIONAL.php como backend/config.php',
                'O modelo gemini-2.5-flash está confirmado',
                'A API Gemini está a funcionar corretamente'
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Resposta Gemini inválida',
            'raw_response' => $responseData
        ], JSON_PRETTY_PRINT);
    }
} else {
    http_response_code($httpCode);
    echo json_encode([
        'success' => false,
        'message' => "Erro na API Gemini (HTTP $httpCode)",
        'error' => $curlError ?: 'Sem detalhes',
        'raw_response' => $response
    ], JSON_PRETTY_PRINT);
}
?>
