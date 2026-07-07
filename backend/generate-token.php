<?php
/**
 * GERAR TOKEN DE TESTE
 * Cria um token JWT válido (assinado com o mesmo JWT_SECRET do servidor)
 * para usares na página de teste, sem risco de colar algo inválido.
 */

require_once 'config.php';

header('Content-Type: application/json');

// Cria um token para um utilizador de teste (id=999)
$testToken = generateToken(999);

// Confirma imediatamente que o próprio servidor consegue validar o token que acabou de criar
$revalidatedUserId = validateToken($testToken);

echo json_encode([
    'success' => true,
    'token' => $testToken,
    'self_test' => $revalidatedUserId === 999
        ? '✅ O servidor conseguiu validar o próprio token (tudo bem com JWT_SECRET)'
        : '❌ O servidor NÃO conseguiu validar o seu próprio token — algo está mal na função validateToken()',
    'instrucoes' => 'Copia o valor de "token" e cola na caixa de teste em questions_TESTE.html'
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
