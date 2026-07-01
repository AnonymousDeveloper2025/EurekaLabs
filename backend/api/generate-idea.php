<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}
require_once '../../config.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$userId = $input['user_id'] ?? null;
$topic = $input['topic'] ?? '';
$mode = $input['mode'] ?? 'simple'; // 'simple' ou 'full'
$category = $input['category'] ?? 'Geral';
$answers = $input['answers'] ?? [];

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Utilizador não autenticado']);
    exit;
}

$answersText = !empty($answers) ? "Preferências adicionais: " . implode(', ', $answers) : "";

// DEFINIÇÃO DO PERSONA IDEFY
$systemPersona = "Tu és o IDEFY, o assistente de inteligência artificial ultra-avançado do Eureka Labs. 
Tu não és um chatbot comum. Tu és criativo, futurista, inspirador e direto. 
Tu não usas frases genéricas como 'Aqui está o que encontrei' ou 'Espero que ajude'. 
Tu respondes como se estivesses a entregar um projeto de elite.

FORMATO DE RESPOSTA:
Tu deves responder APENAS com código HTML e CSS inline (dentro de uma div contentora). 
Usa um design moderno, dark, com gradientes, animações CSS e emojis. 
Se for um 'plano completo', inclui uma tabela de calendário detalhada.
Se for uma 'ideia simples', foca numa apresentação visual impactante e curta.

REGRAS VISUAIS:
- Usa cores: #3b82f6 (azul), #8b5cf6 (roxo), #ffffff (branco).
- Usa bordas arredondadas (30px+), sombras suaves e glassmorphism.
- Inclui botões decorativos (sem link) para 'Passo 1', 'Passo 2', etc.
- Adiciona animações de fade-in para os elementos.";

if ($mode === 'full') {
    $prompt = "$systemPersona

TAREFA: Gera um PLANO COMPLETO DE FÉRIAS detalhado sobre: '$topic'.
$answersText

O plano deve incluir:
1. Um título épico.
2. Um calendário dia-a-dia formatado em HTML.
3. Lista de itens necessários.
4. Dicas de 'Expert'.
5. Formatação visual deslumbrante.";
} else {
    $prompt = "$systemPersona

TAREFA: Gera uma IDEIA SIMPLES E IMPACTANTE sobre: '$topic'.
$answersText

A ideia deve ser:
1. Um título curto e forte.
2. Descrição visual e inspiradora.
3. 3 passos rápidos para começar.
4. Formatação HTML moderna e minimalista.";
}

// Chamar a API Gemini
$response = callGeminiAPI($prompt);

if (!isset($response['candidates'][0]['content']['parts'][0]['text'])) {
    echo json_encode(['success' => false, 'message' => 'O IDEFY encontrou um problema ao processar a tua ideia.']);
    exit;
}

$htmlContent = $response['candidates'][0]['content']['parts'][0]['text'];

// Limpar possíveis tags markdown de código se o Gemini as colocar
$htmlContent = preg_replace('/^```html\s*|```$/i', '', trim($htmlContent));

// Extrair um título simples para a base de dados
$title = "Ideia para " . $topic;
if (preg_match('/<h[1-2][^>]*>(.*?)<\/h[1-2]>/i', $htmlContent, $matches)) {
    $title = strip_tags($matches[1]);
}

// Guardar na base de dados
$conn = getDBConnection();
$stmt = $conn->prepare("INSERT INTO ideas (user_id, category, title, content, created_at) VALUES (?, ?, ?, ?, NOW())");
$stmt->bind_param("isss", $userId, $category, $title, $htmlContent);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'idea' => [
            'id' => $stmt->insert_id,
            'title' => $title,
            'content' => $htmlContent,
            'mode' => $mode
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao guardar no inventário']);
}

$stmt->close();
$conn->close();
?>
