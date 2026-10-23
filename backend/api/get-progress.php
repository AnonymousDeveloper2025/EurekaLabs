<?php
require_once '../config.php';
require_once __DIR__ . '/idefy-prompts.php';
header('Content-Type: application/json; charset=utf-8');
$userId = requireAuth();

$ideaId = intval($_GET['id'] ?? 0);
if (!$ideaId) idefyFail(400, 'Ideia inválida.');

try {
    $db = getDBConnection();
    idefyEnsureProgressTable($db);
    $q = $db->prepare('SELECT p.* FROM idea_progress p JOIN ideas i ON i.id=p.idea_id AND i.user_id=p.user_id WHERE p.idea_id=? AND p.user_id=?');
    $q->execute([$ideaId, $userId]);
    $row = $q->fetch();
    // progress = null quando ainda não há nada guardado (primeira abertura da ideia)
    echo json_encode(['success' => true, 'progress' => $row ? idefyFormatProgress($row) : null], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('Erro get-progress: ' . $e->getMessage());
    idefyFail(500, 'Não foi possível carregar o progresso.');
}
?>
