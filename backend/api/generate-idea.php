<?php
require_once '../../config.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$userId = $input['user_id'] ?? null;
$topic = $input['topic'] ?? '';
$mode = $input['mode'] ?? 'simple';
$category = $input['category'] ?? 'Geral';
$answers = $input['answers'] ?? [];

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Utilizador não autenticado']);
    exit;
}

$answersText = !empty($answers) ? "Preferências adicionais: " . implode(', ', $answers) : "";

$systemPersona = "Tu és o IDEFY, o assistente de inteligência artificial ultra-avançado do Eureka Labs. 
Tu respondes APENAS com código HTML e CSS inline (dentro de uma div contentora). 
Usa cores: #3b82f6 (azul), #8b5cf6 (roxo), #ffffff (branco). 
Usa bordas arredondadas (30px+), glassmorphism e animações CSS.";

if ($mode === 'full') {
    $prompt = "$systemPersona TAREFA: Gera um PLANO COMPLETO DE FÉRIAS sobre: '$topic'. $answersText";
} else {
    $prompt = "$systemPersona TAREFA: Gera uma IDEIA SIMPLES sobre: '$topic'. $answersText";
}

$response = callGeminiAPI($prompt);

if (!isset($response['candidates'][0]['content']['parts'][0]['text'])) {
    echo json_encode(['success' => false, 'message' => 'Erro ao processar a ideia.']);
    exit;
}

$htmlContent = $response['candidates'][0]['content']['parts'][0]['text'];
$htmlContent = preg_replace('/^```html\s*|```$/i', '', trim($htmlContent));

$title = "Ideia para " . $topic;
if (preg_match('/<h[1-2][^>]*>(.*?)<\/h[1-2]>/i', $htmlContent, $matches)) {
    $title = strip_tags($matches[1]);
}

try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("INSERT INTO ideas (user_id, category, title, content, created_at) VALUES (?, ?, ?, ?, NOW())");
    
    if ($stmt->execute([$userId, $category, $title, $htmlContent])) {
        echo json_encode([
            'success' => true,
            'idea' => [
                'id' => $conn->lastInsertId(),
                'title' => $title,
                'content' => $htmlContent,
                'mode' => $mode
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao guardar no inventário']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no servidor: ' . $e->getMessage()]);
}
?>
