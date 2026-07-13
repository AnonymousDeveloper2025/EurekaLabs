<?php
require_once '../../config.php';

// Headers CORS já definidos no config.php, mas reforçamos aqui para segurança extra
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = ['https://anonymousdeveloper2025.github.io', 'http://eurekalabs.great-site.net', 'https://eurekalabs.great-site.net'];

if (in_array($origin, $allowed)) {
    header("Access-Control-Allow-Origin: $origin");
}

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

$answersText = !empty($answers) ? "Preferências: " . implode(', ', $answers) : "";

$systemPersona = "Tu és o IDEFY, assistente de elite do Eureka Labs. 
Responde APENAS com HTML e CSS inline. 
Design: Modern Dark, Glassmorphism, Cores #3b82f6 e #8b5cf6. 
Inclui animações fade-in e tabelas se for plano completo.";

$prompt = "$systemPersona TAREFA: Gera um " . ($mode === 'full' ? "PLANO COMPLETO" : "IDEIA SIMPLES") . " sobre: '$topic'. $answersText";

$response = callGeminiAPI($prompt);

if (!isset($response['candidates'][0]['content']['parts'][0]['text'])) {
    echo json_encode(['success' => false, 'message' => 'Erro ao processar com o IDEFY.']);
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
