<?php
header('Content-Type: text/html; charset=utf-8');

// Cole o seu token recém-gerado aqui
$token = trim("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");

$url = "https://api.github.com/user";

$options = [
    "http" => [
        "header"  => "Authorization: Bearer $token\r\nUser-Agent: PHP-Token-Test\r\n",
        "method"  => "GET",
        "ignore_errors" => true
    ]
];

$context  = stream_context_create($options);
$response = file_get_contents($url, false, $context);
$httpCode = $http_response_header[0] ?? 'HTTP/1.1 000 Unknown';

if (strpos($httpCode, "200") !== false) {
    $data = json_decode($response, true);
    echo "<h3>✅ Token VÁLIDO!</h3>";
    echo "Autenticado como: <b>" . htmlspecialchars($data['login']) . "</b><br>";
    echo "Nome da conta: <b>" . htmlspecialchars($data['name'] ?? 'Não definido') . "</b>";
} else {
    echo "<h3>❌ Token INVÁLIDO ou EXPIRADO ($httpCode)</h3>";
    echo "Resposta do GitHub:<br><pre>" . htmlspecialchars($response) . "</pre>";
}
?>
