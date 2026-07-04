<?php
/**
 * DISCOVER AVAILABLE FREE GEMINI MODELS
 * Descobre quais modelos funcionam COM A TUA CHAVE GRATUITA
 */

header('Content-Type: application/json');

$geminiApiKey = getenv('GEMINI_API_KEY') ?: '';

if (empty($geminiApiKey)) {
    http_response_code(400);
    die(json_encode([
        'success' => false,
        'message' => 'GEMINI_API_KEY não configurada',
        'hint' => 'Adiciona em Render → Settings → Environment Variables'
    ]));
}

// Lista de modelos GRATUITOS conhecidos do Google Studio
$modelsToTest = [
    'gemini-pro',
    'gemini-pro-vision',
    'gemini-1.5-flash',
    'gemini-1.5-pro',
    'gemini-1.5-pro-vision',
    'gemini-2.0-flash-exp',
    'text-embedding-004'
];

echo json_encode([
    'message' => 'Testando modelos gratuitos disponíveis...',
    'api_key_length' => strlen($geminiApiKey),
    'models_to_test' => $modelsToTest
]) . "\n\n";

$workingModels = [];
$failedModels = [];

foreach ($modelsToTest as $model) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($geminiApiKey);
    
    $testData = [
        'contents' => [[
            'parts' => [['text' => 'teste']]
        ]],
        'generationConfig' => [
            'maxOutputTokens' => 100,
            'temperature' => 0.7
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($testData),
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $responseData = json_decode($response, true);
    
    if ($httpCode === 200) {
        $workingModels[] = [
            'model' => $model,
            'status' => '✅ FUNCIONA',
            'http_code' => 200
        ];
        echo "✅ $model - FUNCIONA\n";
    } else {
        $errorMsg = $responseData['error']['message'] ?? 'Unknown error';
        $failedModels[] = [
            'model' => $model,
            'status' => '❌ ERRO',
            'http_code' => $httpCode,
            'error' => $errorMsg
        ];
        echo "❌ $model - HTTP $httpCode\n";
    }
}

echo "\n\n=== RESULTADO FINAL ===\n\n";

echo json_encode([
    'success' => count($workingModels) > 0,
    'working_models' => $workingModels,
    'failed_models' => $failedModels,
    'recommendation' => count($workingModels) > 0 ? 'Use: ' . $workingModels[0]['model'] : 'Nenhum modelo disponível!',
    'hint' => 'Copia o modelo recomendado para backend/config.php na linha: define("GEMINI_MODEL", "...")'
], JSON_PRETTY_PRINT);
?>
