<?php
/**
 * VERIFY GEMINI API KEY
 * Verifica se a chave é válida
 */

header('Content-Type: application/json');

$geminiApiKey = getenv('GEMINI_API_KEY') ?: '';

echo "🔍 VERIFICANDO CHAVE GEMINI\n\n";

if (empty($geminiApiKey)) {
    die(json_encode(['success' => false, 'message' => 'Chave não configurada']));
}

echo "✓ Chave presente: " . strlen($geminiApiKey) . " caracteres\n";
echo "✓ Primeiros 10 caracteres: " . substr($geminiApiKey, 0, 10) . "...\n\n";

// Teste 1: Tentar listar modelos
echo "📋 TESTE 1: Listar modelos\n";
$url1 = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . urlencode($geminiApiKey);
$ch1 = curl_init($url1);
curl_setopt_array($ch1, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
$response1 = curl_exec($ch1);
$httpCode1 = curl_getinfo($ch1, CURLINFO_HTTP_CODE);
curl_close($ch1);

$data1 = json_decode($response1, true);

if ($httpCode1 === 200 && isset($data1['models'])) {
    echo "✅ SUCESSO - Encontrados " . count($data1['models']) . " modelos\n";
    foreach ($data1['models'] as $m) {
        echo "  - " . $m['name'] . "\n";
    }
} else {
    echo "❌ ERRO HTTP $httpCode1\n";
    if (isset($data1['error'])) {
        echo "   Error: " . $data1['error']['message'] . "\n";
    }
}

echo "\n";

// Teste 2: Tentar com versão diferente da API
echo "📋 TESTE 2: Tentar versão v1 (em vez de v1beta)\n";
$url2 = 'https://generativelanguage.googleapis.com/v1/models?key=' . urlencode($geminiApiKey);
$ch2 = curl_init($url2);
curl_setopt_array($ch2, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
$response2 = curl_exec($ch2);
$httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

$data2 = json_decode($response2, true);

if ($httpCode2 === 200 && isset($data2['models'])) {
    echo "✅ SUCESSO com v1 - Encontrados " . count($data2['models']) . " modelos\n";
    foreach ($data2['models'] as $m) {
        echo "  - " . $m['name'] . "\n";
    }
} else {
    echo "❌ ERRO HTTP $httpCode2\n";
    if (isset($data2['error'])) {
        echo "   Error: " . $data2['error']['message'] . "\n";
    }
}

echo "\n";

// Teste 3: Teste básico com gemini-1.5-pro (versão v1)
echo "📋 TESTE 3: Teste básico (generateContent com v1)\n";
$url3 = 'https://generativelanguage.googleapis.com/v1/models/gemini-1.5-pro:generateContent?key=' . urlencode($geminiApiKey);
$payload3 = json_encode([
    'contents' => [['parts' => [['text' => 'oi']]]]
]);
$ch3 = curl_init($url3);
curl_setopt_array($ch3, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload3,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT => 10
]);
$response3 = curl_exec($ch3);
$httpCode3 = curl_getinfo($ch3, CURLINFO_HTTP_CODE);
curl_close($ch3);

$data3 = json_decode($response3, true);

if ($httpCode3 === 200) {
    echo "✅ SUCESSO - API respondeu!\n";
} else {
    echo "❌ ERRO HTTP $httpCode3\n";
    if (isset($data3['error'])) {
        echo "   Error: " . $data3['error']['message'] . "\n";
    }
}

echo "\n\n=== RESUMO ===\n";
echo json_encode([
    'chave_comprimento' => strlen($geminiApiKey),
    'teste1_listarModelos_v1beta' => $httpCode1 === 200 ? 'OK' : "ERRO $httpCode1",
    'teste2_listarModelos_v1' => $httpCode2 === 200 ? 'OK' : "ERRO $httpCode2",
    'teste3_generateContent_v1' => $httpCode3 === 200 ? 'OK' : "ERRO $httpCode3",
    'recomendacao' => match(true) {
        $httpCode1 === 200 => 'Usar API v1beta',
        $httpCode2 === 200 => 'Usar API v1',
        $httpCode3 === 200 => 'API funciona com v1',
        default => 'Chave pode estar inválida ou expirada'
    }
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
