<?php
/**
 * GERAR TOKEN DE TESTE
 * Garante que existe um utilizador REAL de teste na base de dados
 * e gera um token JWT válido para esse utilizador (evita erro de foreign key).
 */

require_once 'config.php';

header('Content-Type: application/json');

$testEmail = 'teste@eurekalabs.com';

try {
    $conn = getDBConnection();

    // 1. Ver se o utilizador de teste já existe
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$testEmail]);
    $existing = $stmt->fetch();

    if ($existing) {
        $userId = $existing['id'];
        $status = 'Utilizador de teste já existia';
    } else {
        // 2. Criar utilizador de teste real
        $stmt = $conn->prepare("
            INSERT INTO users (name, email, password, created_at)
            VALUES (?, ?, ?, NOW())
            RETURNING id
        ");
        $stmt->execute(['Utilizador Teste', $testEmail, hashPassword('teste12345')]);
        $result = $stmt->fetch();
        $userId = $result['id'];
        $status = 'Utilizador de teste criado agora';
    }

    // 3. Gerar token para o utilizador REAL
    $testToken = generateToken($userId);
    $revalidatedUserId = validateToken($testToken);

    echo json_encode([
        'success' => true,
        'status' => $status,
        'user_id' => (int) $userId,
        'token' => $testToken,
        'self_test' => $revalidatedUserId == $userId
            ? '✅ Token válido e utilizador existe na base de dados'
            : '❌ Algo está mal na validação do token',
        'instrucoes' => 'Copia o valor de "token" e cola na caixa de teste em questions_TESTE.html'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao preparar utilizador de teste: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>
