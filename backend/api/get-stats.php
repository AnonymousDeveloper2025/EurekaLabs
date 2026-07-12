<?php
/**
 * GET STATS — EUREKA LABS
 * ✅ CORRIGIDO: usava a API do mysqli sobre uma ligação PDO (Fatal Error
 * sempre) e confiava num "userId" vindo da query string (qualquer pessoa
 * podia ver as estatísticas de outro utilizador). Agora usa PDO e o JWT
 * (requireAuth).
 */

require_once '../config.php';
header('Content-Type: application/json');

$userId = requireAuth();

try {
    $conn = getDBConnection();

    $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM ideas WHERE user_id = ?");
    $stmt->execute([$userId]);
    $ideasCount = (int) ($stmt->fetch()['count'] ?? 0);

    $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM ideas WHERE user_id = ? AND pdf_generated = TRUE");
    $stmt->execute([$userId]);
    $pdfsCount = (int) ($stmt->fetch()['count'] ?? 0);

    $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM ideas WHERE user_id = ? AND saved = TRUE");
    $stmt->execute([$userId]);
    $savedCount = (int) ($stmt->fetch()['count'] ?? 0);

    echo json_encode([
        'success' => true,
        'ideas_count' => $ideasCount,
        'pdfs_count' => $pdfsCount,
        'saved_count' => $savedCount
    ]);
} catch (Exception $e) {
    error_log("Erro get-stats: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao obter estatísticas.']);
}
?>
