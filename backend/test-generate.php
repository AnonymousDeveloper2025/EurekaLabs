<?php
/**
 * TEST GENERATE-IDEA ENDPOINT - CORRIGIDO
 * SEM output antes dos headers!
 */

// PRIMEIRO: require_once (sem echo antes!)
require_once 'config.php';

// Agora sim podemos fazer echo
header('Content-Type: application/json');

$apiKey = getenv('GEMINI_API_KEY') ?: '';

if (empty($apiKey)) {
    echo json_encode(['success' => false, 'message' => 'API Key não configurada']);
    exit;
}

// Testar se consegue fazer uma ideia simples
$testPayload = [
    'topic' => 'Como começar um negócio online',
    'mode' => 'simple',
    'category' => 'Negócios',
    'answers' => []
];

// Gerar token de teste
$testUserId = 999;
$testToken = generateToken($testUserId);

// Tentar chamar a API Gemini directamente (simular o que generate-idea.php faz)
$testPrompt = "Tu és o IDEFY. Gera uma ideia sobre: " . $testPayload['topic'];

$geminiUrl = GEMINI_API_BASE . '/' . GEMINI_MODEL . ':generateContent?key=' . urlencode($apiKey);

$geminiData = [
    'contents' => [
        [
            'parts' => [
                ['text' => $testPrompt]
            ]
        ]
    ],
    'generationConfig' => [
        'maxOutputTokens' => 1000,
        'temperature' => 0.7
    ]
];

$ch = curl_init($geminiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($geminiData),
    CURLOPT_TIMEOUT => 30
]);

$geminiResponse = curl_exec($ch);
$geminiHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($geminiHttpCode === 200) {
    $geminiData = json_decode($geminiResponse, true);
    
    if (isset($geminiData['candidates'][0]['content']['parts'][0]['text'])) {
        echo json_encode([
            'success' => true,
            'message' => '✅ Teste completo com sucesso!',
            'test' => [
                'model' => GEMINI_MODEL,
                'prompt' => $testPrompt,
                'response' => $geminiData['candidates'][0]['content']['parts'][0]['text'],
                'token' => substr($testToken, 0, 20) . '...'
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Resposta Gemini inválida',
            'details' => $geminiData
        ], JSON_PRETTY_PRINT);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => "Erro ao chamar Gemini (HTTP $geminiHttpCode)",
        'error' => json_decode($geminiResponse, true)
    ], JSON_PRETTY_PRINT);
}
?>
