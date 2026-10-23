<?php
require_once '../config.php';
require_once __DIR__ . '/idefy-prompts.php';
header('Content-Type: application/json; charset=utf-8');
$userId = requireAuth();
@set_time_limit(180);

$input       = json_decode(file_get_contents('php://input'), true) ?: [];
$id          = intval($input['id'] ?? 0);
$instruction = idefyClean($input['instruction'] ?? '', 2500);
if (!$id || $instruction === '') idefyFail(400, 'Indica a ideia e o que queres alterar.');

try {
    $db = getDBConnection();
    $q = $db->prepare('SELECT id,category,title,content,mode,flow FROM ideas WHERE id=? AND user_id=?');
    $q->execute([$id, $userId]);
    $old = $q->fetch();
    if (!$old) idefyFail(404, 'Ideia não encontrada.');

    $mode = (($old['mode'] ?? 'simple') === 'full') ? 'full' : 'simple';
    $flow = $old['flow'] ?? 'develop';
    $theme = idefyDetectTheme($old['category'], $old['title']);

    // Se a ideia já tem a casca Idefy, o modelo recebe a estrutura (HTML); senão, só o texto.
    $fragment = idefyExtractFragment($old['content'], 28000);
    $prompt = idefyRefactorPrompt([
        'title' => $old['title'], 'category' => $old['category'], 'mode' => $mode, 'theme' => $theme,
        'format' => $fragment !== null ? 'html' : 'texto',
        'content' => $fragment !== null ? $fragment : idefyPlainText($old['content'], 18000),
        'request' => $instruction,
    ]);

    $frag = idefyProducePlan($prompt, $mode);
    if ($frag === null) idefyFail(502, 'O Idefy não conseguiu refatorar esta ideia.');

    $frag  = idefyResolveImages($frag);
    $title = idefyTitleFrom($frag, $old['title']);
    $html  = idefyWrap($frag, $theme, $mode);

    idefyEnsureProgressTable($db);
    $db->beginTransaction();
    $ins = $db->prepare('INSERT INTO ideas (user_id,category,title,content,mode,flow,created_at) VALUES (?,?,?,?,?,?,NOW())');
    $ins->execute([$userId, $old['category'], $title, $html, $mode, $flow]);
    $newId = (int)$db->lastInsertId('ideas_id_seq');
    $del = $db->prepare('DELETE FROM ideas WHERE id=? AND user_id=?');
    $del->execute([$id, $userId]);
    $dp = $db->prepare('DELETE FROM idea_progress WHERE idea_id=? AND user_id=?');
    $dp->execute([$id, $userId]);
    $db->commit();

    echo json_encode(['success' => true, 'idea' => [
        'id' => $newId, 'category' => $old['category'], 'title' => $title, 'content' => $html,
        'mode' => $mode, 'flow' => $flow, 'theme' => $theme, 'saved' => false,
        'created_at' => date('Y-m-d H:i:s'),
    ]], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log('Erro refactor-idea: ' . $e->getMessage());
    idefyFail(500, 'Erro ao substituir a ideia na base de dados.');
}
?>
