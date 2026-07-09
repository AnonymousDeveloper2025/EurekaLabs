<?php
/**
 * DELETE IDEA — elimina uma ideia do utilizador autenticado.
 * A confirmação acontece no frontend (result.html); este endpoint
 * confia que, ao ser chamado, o utilizador já confirmou.
 */

require_once '../config.php';
header('Content-Type: application/json');

$userId = requireAuth();

$input = json_decode(file_get_contents('php://input'), true);
$id = intval($input['id'] ?? 0);

if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
    exit;
}

try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("DELETE FROM ideas WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Ideia eliminada com sucesso.']);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Ideia não encontrada ou já eliminada.']);
    }
} catch (Exception $e) {
    error_log("Erro delete-idea: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao eliminar a ideia.']);
}
?>
