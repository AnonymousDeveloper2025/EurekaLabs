<?php
require_once __DIR__. '/../config.php'; // FIX: Sobe só 1 pasta pra achar o config.php

header('Content-Type: application/json');

// Bloqueia GET/OPTIONS. Só aceita POST. Igual ao register.php
if ($_SERVER['REQUEST_METHOD']!== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$userId = $input['user_id']?? null;
$topic = trim($input['topic']?? '');
$mode = $input['mode']?? 'simple';
$category = $input['category']?? 'Geral';
$answers = $input['answers']?? [];

// Validações Essenciais - igual ao register
if (empty($userId)) {
    echo json_encode(['success' => false, 'message' => 'Utilizador não autenticado']);
    exit;
}

if (empty($topic)) {
    echo json_encode(['success' => false, 'message' => 'Tópico é obrigatório']);
    exit;
}

$answersText =!empty($answers)? "Preferências: ". implode(', ', $answers) : "";

$systemPersona = "Tu és o IDEFY, assistente de elite do Eureka Labs.
Responde APENAS com HTML e CSS inline.
Design: Modern Dark, Glassmorphism, Cores #3b82f6 e #8b5cf6.
Inclui animações fade-in e tabelas se for plano completo.";

$prompt = "$systemPersona TAREFA: Gera um ". ($mode === 'full'? "PLANO COMPLETO" : "IDEIA SIMPLES"). " sobre: '$topic'. $answersText";

$response = callGeminiAPI($prompt);

// Debug melhorado pra saber o erro real do Gemini
if (isset($response['error'])) {
    echo json_encode(['success' => false, 'message' => 'Erro Gemini: '. $response['error']['message']?? json_encode($response['error'])]);
    exit;
}

if (!isset($response['candidates'][0]['content']['parts'][0]['text'])) {
    echo json_encode(['success' => false, 'message' => 'Erro ao processar com o IDEFY. Resposta vazia da API.']);
    exit;
}

$htmlContent = $response['candidates'][0]['content']['parts'][0]['text'];
// Remove ```html e ``` caso o Gemini mande
$htmlContent = preg_replace('/^```(?:html)?\s*|\s*```$/s', '', trim($htmlContent));

$title = "Ideia para ". $topic;
if (preg_match('/<h[1-2][^>]*>(.*?)<\/h[1-2]>/i', $htmlContent, $matches)) {
    $title = strip_tags($matches[1]);
}

try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("INSERT INTO ideas (user_id, category, title, content, created_at) VALUES (?,?, NOW())");

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
    echo json_encode(['success' => false, 'message' => 'Erro no servidor: '. $e->getMessage()]);
}
?>
