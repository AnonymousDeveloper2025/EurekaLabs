<?php
/**
 * GENERATE IDEA — EUREKA LABS
 * ✅ CORRIGIDOS vários bugs graves nesta versão:
 * 1) require_once '../../config.php' — caminho errado (este ficheiro está
 *    em api/, só precisa de subir UM nível: '../config.php'). Com dois
 *    níveis, o PHP nunca encontrava o config.php e este endpoint falhava
 *    sempre com Fatal Error.
 * 2) Lia "user_id" do corpo do pedido — mas o frontend nunca envia isso
 *    (usa o cabeçalho Authorization com o token JWT). Por isso $userId
 *    era sempre null e a resposta era sempre "Utilizador não autenticado".
 *    Agora usa requireAuth(), como todos os outros endpoints.
 * 3) Lia a resposta do Gemini na forma $response['candidates'][0]... mas
 *    callGeminiAPI() (em config.php) já devolve noutro formato:
 *    $response['choices'][0]['message']['content']. Isto fazia com que
 *    o endpoint respondesse sempre "Erro ao processar com o IDEFY",
 *    mesmo quando o Gemini respondia bem.
 * 4) A resposta não incluía "category", "saved" nem "created_at", que o
 *    result.html precisa para mostrar a imagem certa e o estado do botão
 *    Guardar.
 */

require_once '../config.php';

$userId = requireAuth();

$input = json_decode(file_get_contents('php://input'), true);

$topic = trim($input['topic'] ?? '');
$mode = $input['mode'] ?? 'simple';
$category = $input['category'] ?? 'geral';
$answers = $input['answers'] ?? [];

if (empty($topic)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Indica sobre o que queres uma ideia.']);
    exit;
}

$answersText = !empty($answers) ? "Preferências: " . implode(', ', $answers) : "";

$systemPersona = "Tu és o IDEFY, assistente de elite do Eureka Labs. 
Responde APENAS com HTML e CSS inline. 
Design: Modern Dark, Glassmorphism, Cores #3b82f6 e #8b5cf6. 
Inclui animações fade-in e tabelas se for plano completo.";

$prompt = "$systemPersona TAREFA: Gera um " . ($mode === 'full' ? "PLANO COMPLETO" : "IDEIA SIMPLES") . " sobre: '$topic'. $answersText";

$response = callGeminiAPI($prompt);

if (!$response || !isset($response['choices'][0]['message']['content'])) {
    http_response_code(502);
    echo json_encode(['success' => false, 'message' => 'Erro ao processar com o IDEFY. Tenta novamente.']);
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
        $ideaId = $conn->lastInsertId('ideas_id_seq');
        echo json_encode([
            'success' => true,
            'idea' => [
                'id' => (int) $ideaId,
                'title' => $title,
                'content' => $htmlContent,
                'category' => $category,
                'mode' => $mode,
                'saved' => false,
                'created_at' => date('Y-m-d H:i:s')
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao guardar a ideia.']);
    }
} catch (Exception $e) {
    error_log("Erro generate-idea: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro no servidor.']);
}
?>
