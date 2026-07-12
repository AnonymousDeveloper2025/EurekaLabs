<?php
/**
 * SAVE IDEA — EUREKA LABS
 * ✅ CORRIGIDO: usava a API do mysqli sobre uma ligação PDO (Fatal Error
 * sempre) e confiava num "userId" enviado pelo cliente. Agora usa PDO e o
 * JWT (requireAuth).
 *
 * Nota: o generate-idea.php já guarda a ideia na base de dados assim que é
 * gerada — este endpoint serve para o utilizador marcar explicitamente uma
 * ideia como "guardada" (botão "Guardar" em result.html), distinguindo-a
 * das ideias apenas geradas.
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
$ideaId = intval($input['id'] ?? 0);

if (!$ideaId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID da ideia em falta.']);
    exit;
}

try {
    $conn = getDBConnection();

    $stmt = $conn->prepare("UPDATE ideas SET saved = TRUE WHERE id = ? AND user_id = ?");
    $stmt->execute([$ideaId, $userId]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Ideia guardada com sucesso.']);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Ideia não encontrada.']);
    }
} catch (Exception $e) {
    error_log("Erro save-idea: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao guardar a ideia.']);
}
?>
