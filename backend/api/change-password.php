<?php
/**
 * CHANGE PASSWORD — EUREKA LABS
 * ✅ CORRIGIDO: usava a API do mysqli (bind_param, close, get_result) sobre
 * uma ligação PDO — dava sempre Fatal Error. Também confiava num "userId"
 * enviado directamente pelo cliente, o que permitia a qualquer pessoa mudar
 * a password de OUTRO utilizador só adivinhando o id. Agora usa PDO e exige
 * o JWT (requireAuth) + a password actual para confirmar a identidade.
 */

require_once '../config.php'; // já trata CORS e o pedido OPTIONS — não duplicar aqui
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$userId = requireAuth();

$input = json_decode(file_get_contents('php://input'), true);
$currentPassword = $input['currentPassword'] ?? '';
$newPassword = $input['newPassword'] ?? '';

if (empty($currentPassword) || empty($newPassword)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Preenche a palavra-passe actual e a nova palavra-passe.']);
    exit;
}

if (strlen($newPassword) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A nova palavra-passe deve ter pelo menos 6 caracteres.']);
    exit;
}

try {
    $conn = getDBConnection();

    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user || !verifyPassword($currentPassword, $user['password'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'A palavra-passe actual está incorrecta.']);
        exit;
    }

    $hashedPassword = hashPassword($newPassword);
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->execute([$hashedPassword, $userId]);

    echo json_encode(['success' => true, 'message' => 'Palavra-passe alterada com sucesso.']);
} catch (Exception $e) {
    error_log("Erro change-password: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao alterar a palavra-passe.']);
}
?>
