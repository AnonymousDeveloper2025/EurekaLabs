<?php
/**
 * GENERATE IDEA - EUREKA LABS ELITE
 * Refatorado profissionalmente para usar o Proxy do Manus.
 */

require_once '../../config.php';

// A resposta é sempre JSON
header('Content-Type: application/json');

// Receber dados do front-end
$input = json_decode(file_get_contents('php://input'), true);

$userId = $input['user_id'] ?? null;
$topic = $input['topic'] ?? '';
$mode = $input['mode'] ?? 'simple';
$category = $input['category'] ?? 'Geral';
$answers = $input['answers'] ?? [];

// 1. Validação de Autenticação
if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Sessão expirada. Por favor, faz login novamente.']);
    exit;
}

// 2. Construção do Prompt Profissional
$answersText = !empty($answers) ? "Preferências do utilizador: " . implode(', ', $answers) : "";

$systemPersona = "Tu és o IDEFY, o assistente de inteligência artificial de elite do Eureka Labs. 
A tua missão é transformar uma ideia básica numa experiência visual deslumbrante.

FORMATO OBRIGATÓRIO:
- Responde APENAS com código HTML e CSS inline (dentro de uma div contentora).
- NÃO uses blocos de código markdown (```html).
- Usa um design Modern Dark com Glassmorphism.
- Cores principais: Azul (#3b82f6), Roxo (#8b5cf6), Branco (#ffffff).
- Inclui animações CSS fade-in e ícones (usa emojis ou SVG simples).
- Se o modo for 'full', cria um cronograma detalhado em tabela.

CONTEÚDO:";

if ($mode === 'full') {
    $prompt = "$systemPersona Gera um PLANO COMPLETO E DETALHADO sobre: '$topic'. $answersText. Inclui introdução, passos práticos, cronograma e dicas de expert.";
} else {
    $prompt = "$systemPersona Gera uma IDEIA SIMPLES E IMPACTANTE sobre: '$topic'. $answersText. Foca na criatividade e rapidez de execução.";
}

// 3. Chamada à API (via Proxy Manus)
$response = callGeminiAPI($prompt);

if (!$response || !isset($response['choices'][0]['message']['content'])) {
    echo json_encode(['success' => false, 'message' => 'O IDEFY está momentaneamente indisponível. Tenta novamente em segundos.']);
    exit;
}

$htmlContent = $response['choices'][0]['message']['content'];

// Limpeza de tags markdown se a IA insistir nelas
$htmlContent = preg_replace('/^```html\s*|```$/i', '', trim($htmlContent));

// 4. Extração do Título
$title = "Ideia para " . $topic;
if (preg_match('/<h[1-2][^>]*>(.*?)<\/h[1-2]>/i', $htmlContent, $matches)) {
    $title = strip_tags($matches[1]);
}

// 5. Persistência na Base de Dados (PostgreSQL)
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
        echo json_encode(['success' => false, 'message' => 'Não foi possível guardar a tua ideia no inventário.']);
    }
} catch (Exception $e) {
    error_log("Erro SQL generate-idea: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno ao processar a ideia.']);
}
?>
