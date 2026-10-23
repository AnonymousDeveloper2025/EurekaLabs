<?php
require_once '../config.php';
require_once __DIR__ . '/idefy-prompts.php';
header('Content-Type: application/json; charset=utf-8');
$userId = requireAuth();

$in     = json_decode(file_get_contents('php://input'), true) ?: [];
$ideaId = intval($in['idea_id'] ?? 0);
if (!$ideaId) idefyFail(400, 'Ideia inválida.');

$day     = min(365, max(1, intval($in['current_day'] ?? 1)));
$status  = in_array($in['status'] ?? 'active', ['active', 'completed', 'paused'], true) ? $in['status'] : 'active';
$percent = min(100, max(0, intval($in['progress_percent'] ?? 0)));

// notas: o iframe envia um objeto {step-N: "texto"} serializado; aceita também texto simples
$notes = $in['notes'] ?? '';
if (is_array($notes)) $notes = json_encode($notes, JSON_UNESCAPED_UNICODE);
$notes = idefyClean($notes, 30000);
$voice = idefyClean($in['voice_transcript'] ?? '', 20000);

// ids de passos e subpassos concluídos (step-N e step-N.M), validados e sem repetidos
$ids = [];
if (is_array($in['completed_steps'] ?? null)) {
    foreach (array_slice($in['completed_steps'], 0, 800) as $s) {
        $s = (string)$s;
        if (preg_match('/^[A-Za-z0-9._-]{1,60}$/', $s)) $ids[$s] = true;
    }
}
$ids = array_map('strval', array_keys($ids));

$stats = is_array($in['stats'] ?? null) ? json_encode($in['stats'], JSON_UNESCAPED_UNICODE) : '{}';
if ($stats === false || strlen($stats) > 20000) $stats = '{}';

try {
    $db = getDBConnection();
    idefyEnsureProgressTable($db);

    $own = $db->prepare('SELECT id FROM ideas WHERE id=? AND user_id=?');
    $own->execute([$ideaId, $userId]);
    if (!$own->fetch()) idefyFail(404, 'Ideia não encontrada.');

    $sql = 'INSERT INTO idea_progress (idea_id,user_id,current_day,status,notes,voice_transcript,completed_steps,progress_percent,stats,updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,NOW())
            ON CONFLICT (idea_id,user_id) DO UPDATE SET
              current_day=EXCLUDED.current_day, status=EXCLUDED.status, notes=EXCLUDED.notes,
              voice_transcript=EXCLUDED.voice_transcript, completed_steps=EXCLUDED.completed_steps,
              progress_percent=EXCLUDED.progress_percent, stats=EXCLUDED.stats, updated_at=NOW()';
    $db->prepare($sql)->execute([$ideaId, $userId, $day, $status, $notes, $voice, json_encode($ids), $percent, $stats]);

    $q = $db->prepare('SELECT * FROM idea_progress WHERE idea_id=? AND user_id=?');
    $q->execute([$ideaId, $userId]);
    echo json_encode(['success' => true, 'progress' => idefyFormatProgress($q->fetch())], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('Erro save-progress: ' . $e->getMessage());
    idefyFail(500, 'Não foi possível guardar o progresso.');
}
?>
