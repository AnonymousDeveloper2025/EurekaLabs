<?php
/**
 * DELETE ACCOUNT — EUREKA LABS
 * ✅ CORRIGIDO: usava a API do mysqli sobre uma ligação PDO (Fatal Error
 * sempre) e confiava num "userId" enviado pelo cliente (qualquer pessoa
 * podia apagar a conta de outro utilizador). Agora usa PDO, exige o JWT
 * (requireAuth) e confirma a password antes de apagar — ação irreversível.
 */

require_once '../config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$userId = requireAuth();

$input = json_decode(file_get_contents('php://input'), true);
$password = $input['password'] ?? '';

if (empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Confirma a tua palavra-passe para eliminar a conta.']);
    exit;
}

try {
    $conn = getDBConnection();

    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user || !verifyPassword($password, $user['password'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Palavra-passe incorrecta.']);
        exit;
    }

    // ON DELETE CASCADE já trata das ideias associadas, mas eliminamos
    // explicitamente por segurança/clareza.
    $conn->beginTransaction();
    $stmt = $conn->prepare("DELETE FROM ideas WHERE user_id = ?");
    $stmt->execute([$userId]);

    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Conta eliminada com sucesso.']);
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Erro delete-account: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao eliminar a conta.']);
}
?>
