<?php
/**
 * TEST GENERATE-IDEA ENDPOINT - COM FALLBACK DE MODELOS
 */

require_once 'config.php';
header('Content-Type: application/json');

$apiKey = getenv('GEMINI_API_KEY')?: '';

if (empty($apiKey)) {
    echo json_encode(['success' => false, 'message' => 'API Key não configurada']);
    exit;
}

// --- LISTA DE MODELOS PARA TENTAR (NA ORDEM) ---
// Peguei da tua lista. Ele vai tentar de cima pra baixo.
$modelsToTry = [
    // 1. Latest (mais estáveis e baratos)
    'models/gemini-flash-latest',
    'models/gemini-flash-lite-latest',
    'models/gemini-pro-latest',

    // 2. Gemini 2.5 / 3.5 (os principais)
    'models/gemini-2.5-flash',
    'models/gemini-2.5-flash-lite',
    'models/gemini-3.5-flash',
    'models/gemini-3.5-flash-lite',
    'models/gemini-2.5-pro',

    // 3. Gemma (fallback se Gemini falhar - tu pediste)
    'models/gemma-4-31b-it',
    'models/gemma-4-26b-a4b-it',

    // 4. Previews mais novos (último recurso)
    'models/gemini-3-flash-preview',
    'models/gemini-3.1-flash-lite',
];

$testPrompt = "Tu és o IDEFY. Gera uma ideia sobre: Como começar um negócio online. Responde em 2 frases.";

function callGemini($model, $prompt, $apiKey) {
    // Se for Gemma, a API às vezes espera formato diferente
    // mas o endpoint generateContent funciona igual
    $geminiUrl = 'https://generativelanguage.googleapis.com/v1/'. $model. ':generateContent?key='. urlencode($apiKey);

    $geminiData = [
        'contents' => [
            ['parts' => [['text' => $prompt]]]
        ],
        'generationConfig' => [
            'maxOutputTokens' => 1000,
            'temperature' => 0.7
        ]
    ];

    // IF especial para modelos Gemma - eles gostam de temperatura menor
    if (strpos($model, 'gemma')!== false) {
        $geminiData['generationConfig']['temperature'] = 0.6;
    }

    $ch = curl_init($geminiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($geminiData),
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    return [$httpCode, $response, $curlError];
}

$lastError = null;
$successResponse = null;
$usedModel = null;

foreach ($modelsToTry as $model) {
    list($httpCode, $response, $curlError) = callGemini($model, $testPrompt, $apiKey);

    if ($curlError) {
        $lastError = "cURL erro no $model: $curlError";
        continue; // tenta o próximo
    }

    if ($httpCode === 200) {
        $data = json_decode($response, true);
        // IF para verificar se tem texto válido
        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            $successResponse = $data['candidates'][0]['content']['parts'][0]['text'];
            $usedModel = $model;
            break; // SUCESSO! para o loop
        } else {
            $lastError = "Resposta inválida do $model: ". substr($response, 0, 500);
        }
    } else {
        // ELSE: se deu 404, 429, 500, etc, tenta o próximo
        $errorData = json_decode($response, true);
        $msg = $errorData['error']['message']?? $response;
        $lastError = "HTTP $httpCode no $model: $msg";

        // Se for erro de quota, não adianta tentar outros Gemini, vai logo pros Gemma
        if ($httpCode == 429) {
            // continua tentando
        }
    }
}

// --- RESULTADO FINAL ---
if ($successResponse) {
    echo json_encode([
        'success' => true,
        'message' => '✅ SUCESSO com fallback!',
        'test' => [
            'model_usado' => $usedModel,
            'modelos_tentados' => $modelsToTry,
            'prompt' => $testPrompt,
            'response' => $successResponse
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'success' => false,
        'message' => '❌ Todos os modelos falharam',
        'ultimo_erro' => $lastError,
        'modelos_tentados' => $modelsToTry
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>
