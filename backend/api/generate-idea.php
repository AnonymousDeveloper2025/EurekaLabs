<?php
/**
 * GENERATE IDEA - EUREKA LABS ELITE v3.0
 * Versão Final Funcional com Gemini 2.5 Flash
 */

// ✅ PRIMEIRO: Headers (sem output antes!)
header('Content-Type: application/json');

// ✅ SEGUNDO: Requires
// ✅ CORRIGIDO: generate-idea.php está em backend/api/, só 1 nível abaixo de backend/config.php
// (o require_once '../../config.php' estava errado e causava Fatal Error no servidor)
require_once '../config.php';

// ✅ TERCEIRO: Processamento

// 1. Validar autenticação
$userId = getAuthUserId();
if (!$userId) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Não autorizado. Token inválido ou expirado.'
    ]);
    exit;
}

// 2. Pegar input
$input = json_decode(file_get_contents('php://input'), true);

$topic = trim($input['topic'] ?? '');
$mode = $input['mode'] ?? 'simple';
$category = trim($input['category'] ?? 'Geral');
$answers = $input['answers'] ?? [];

// 3. Validar input
if (empty($topic)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Sobre o que queres a ideia? O tópico é obrigatório.'
    ]);
    exit;
}

if (strlen($topic) > 500) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'O tópico é demasiado longo. Máximo 500 caracteres.'
    ]);
    exit;
}

// 4. Construir prompt
$answersText = !empty($answers) ? "Preferências do utilizador: " . implode(', ', array_filter($answers)) : "";

$systemPersona = "Tu és o IDEFY, o assistente de elite do Eureka Labs. 
Responde APENAS com código HTML e CSS inline (dentro de uma div com max-width: 800px). 
NÃO uses blocos de código markdown ou triple backticks. 
Design: Modern Dark, Glassmorphism, Azul (#3b82f6) e Roxo (#8b5cf6).
Responde em português.
Estrutura bem com headings, parágrafos e listas.";

$prompt = "$systemPersona

TAREFA: Gera um " . ($mode === 'full' ? "PLANO COMPLETO (estruturado em secções)" : "IDEIA SIMPLES (concisa e directa)") . " sobre: '$topic'. 

$answersText

Responde APENAS com HTML/CSS válido, sem markdown, sem explicações adicionais. Usa estilos inline para cores e fontes.";

// 5. Chamar API Gemini
$response = callGeminiAPI($prompt);

if (!$response || !isset($response['choices'][0]['message']['content'])) {
    error_log("Gemini API Error: " . json_encode($response));
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'message' => 'O IDEFY não respondeu. Verifica a tua ligação ou tenta mais tarde.'
    ]);
    exit;
}

$htmlContent = $response['choices'][0]['message']['content'];

// 6. Limpar markdown se existir
$htmlContent = preg_replace('/^```html\s*|```\s*$/i', '', trim($htmlContent));

// 7. Extrair título
$title = "Ideia para " . substr($topic, 0, 50);
if (preg_match('/<h[1-2][^>]*>(.*?)<\/h[1-2]>/i', $htmlContent, $matches)) {
    $title = strip_tags($matches[1]);
}

// 8. Guardar no banco de dados
try {
    $conn = getDBConnection();
    
    // Usar RETURNING para PostgreSQL
    $stmt = $conn->prepare("
        INSERT INTO ideas (user_id, category, title, content, created_at) 
        VALUES (?, ?, ?, ?, NOW()) 
        RETURNING id
    ");
    
    if ($stmt->execute([$userId, $category, $title, $htmlContent])) {
        $result = $stmt->fetch();
        $ideaId = $result['id'] ?? null;
        
        if ($ideaId) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'idea' => [
                    'id' => (int) $ideaId,
                    'title' => $title,
                    'content' => $htmlContent,
                    'category' => $category,
                    'mode' => $mode,
                    'created_at' => date('Y-m-d H:i:s')
                ]
            ]);
        } else {
            throw new Exception('Não foi possível recuperar o ID da ideia');
        }
    } else {
        throw new Exception('Erro ao executar INSERT');
    }
    
} catch (Exception $e) {
    error_log("Database Error (generate-idea): " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao guardar a ideia. Tenta novamente mais tarde.',
        'error' => $e->getMessage()
    ]);
}
?>
