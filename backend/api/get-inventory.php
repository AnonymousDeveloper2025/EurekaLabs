<?php
/**
 * GET INVENTORY — EUREKA LABS
 * ✅ CORRIGIDO: usava a API do mysqli (bind_param/get_result/close) sobre
 * uma ligação PDO — Fatal Error sempre. Também confiava no "userId" da
 * query string (qualquer pessoa podia ver o inventário de outro
 * utilizador). Agora usa PDO e o JWT (requireAuth), como o resto da API.
 *
 * Mantém o filtro "saved = TRUE" — o inventário mostra só as ideias que
 * o utilizador marcou explicitamente como guardadas (botão "Guardar" em
 * result.html), não todas as que alguma vez gerou.
 */

require_once '../config.php';
header('Content-Type: application/json');

$userId = requireAuth();

try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT id, title, content, category, created_at, pdf_generated FROM ideas WHERE user_id = ? AND saved = TRUE ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    $ideas = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'ideas' => $ideas
    ]);
} catch (Exception $e) {
    error_log("Erro get-inventory: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao carregar o inventário.']);
}
?>
