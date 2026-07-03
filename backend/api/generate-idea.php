<?php
/**
 * GENERATE IDEA - EUREKA LABS ELITE
 */

// config.php já trata CORS e headers globais
require_once '../../config.php';

// Garantir que a resposta é JSON mesmo em erros fatais
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$userId = $input['user_id'] ?? null;
$topic = $input['topic'] ?? '';
$mode = $input['mode'] ?? 'simple';
$category = $input['category'] ?? 'Geral';
$answers = $input['answers'] ?? [];

if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sessão expirada. Por favor, faz login novamente.']);
    exit;
}

if (empty($topic)) {
    echo json_encode(['success' => false, 'message' => 'Sobre o que queres a ideia? O tópico é obrigatório.']);
    exit;
}

$answersText = !empty($answers) ? "Preferências: " . implode(', ', $answers) : "";

$systemPersona = "Tu és o IDEFY, o assistente de elite do Eureka Labs. 
Responde APENAS com código HTML e CSS inline (dentro de uma div). 
NÃO uses blocos de código markdown. 
Design: Modern Dark, Glassmorphism, Azul (#3b82f6) e Roxo (#8b5cf6).";

$prompt = "$systemPersona TAREFA: Gera um " . ($mode === 'full' ? "PLANO COMPLETO" : "IDEIA SIMPLES") . " sobre: '$topic'. $answersText";

$response = callGeminiAPI($prompt);

if (!$response || !isset($response['choices'][0]['message']['content'])) {
    http_response_code(502);
    echo json_encode(['success' => false, 'message' => 'O IDEFY não respondeu. Verifica a tua ligação ou tenta mais tarde.']);
    exit;
}

$htmlContent = $response['choices'][0]['message']['content'];
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
        echo json_encode(['success' => false, 'message' => 'Erro ao guardar no inventário.']);
    }
} catch (Exception $e) {
    error_log("Erro SQL generate-idea: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno ao processar a ideia.']);
}
?>
