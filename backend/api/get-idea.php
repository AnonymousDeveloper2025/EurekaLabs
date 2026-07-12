<?php
/**
 * GET IDEA — devolve uma ideia específica (com o content completo),
 * usada pelo result.html quando vem do inventory.html (?id=X)
 */

require_once '../config.php';
header('Content-Type: application/json');

$userId = requireAuth();

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
    exit;
}

try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT id, category, title, content, saved, created_at FROM ideas WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
    $idea = $stmt->fetch();

    if (!$idea) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Ideia não encontrada.']);
        exit;
    }

    echo json_encode(['success' => true, 'idea' => $idea]);
} catch (Exception $e) {
    error_log("Erro get-idea: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao obter a ideia.']);
}
?>
