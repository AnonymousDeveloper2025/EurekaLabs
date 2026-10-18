<?php
require_once '../config.php';
require_once __DIR__ . '/idefy-prompts.php';
header('Content-Type: application/json; charset=utf-8');
$userId = requireAuth();
@set_time_limit(180);

$input    = json_decode(file_get_contents('php://input'), true) ?: [];
$topic    = idefyClean($input['topic'] ?? '', 600);
$mode     = (($input['mode'] ?? 'simple') === 'full') ? 'full' : 'simple';
$category = idefyClean($input['category'] ?? 'geral', 60) ?: 'geral';
$flow     = (($input['flow'] ?? 'develop') === 'enhance') ? 'enhance' : 'develop';
$answers  = $input['answers'] ?? [];

// O Idefy só cria depois de o utilizador responder: sem respostas não há geração.
$clean = [];
if (is_array($answers)) {
    foreach (array_slice(array_values($answers), 0, 15) as $a) {
        if (is_array($a)) $a = implode(', ', array_map('strval', array_filter($a, 'is_scalar')));
        $a = idefyClean($a, 1500);
        if ($a !== '') $clean[] = $a;
    }
}
if ($topic === '' || !$clean) idefyFail(400, 'Responde primeiro às perguntas do Idefy.');

$theme  = idefyDetectTheme($category, $topic);
$prompt = idefyPlanPrompt([
    'topic' => $topic, 'category' => $category, 'mode' => $mode,
    'flow' => $flow, 'answers' => $clean, 'theme' => $theme,
]);

$fragment = idefyProducePlan($prompt, $mode);
if ($fragment === null) idefyFail(502, 'Erro ao processar com o Idefy.');

$fragment = idefyResolveImages($fragment);
$title    = idefyTitleFrom($fragment, 'Ideia para ' . mb_substr($topic, 0, 120));
$html     = idefyWrap($fragment, $theme, $mode);

try {
    $db = getDBConnection();
    $stmt = $db->prepare('INSERT INTO ideas (user_id,category,title,content,mode,flow,created_at) VALUES (?,?,?,?,?,?,NOW())');
    $stmt->execute([$userId, $category, $title, $html, $mode, $flow]);
    $id = $db->lastInsertId('ideas_id_seq');
    echo json_encode(['success' => true, 'idea' => [
        'id' => (int)$id, 'title' => $title, 'content' => $html, 'category' => $category,
        'mode' => $mode, 'flow' => $flow, 'theme' => $theme, 'saved' => false,
        'created_at' => date('Y-m-d H:i:s'),
    ]], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('Erro generate-idea: ' . $e->getMessage());
    idefyFail(500, 'Erro ao guardar a ideia.');
}
?>
