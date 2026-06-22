<?php
require_once '../../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$userId = $input['userId'] ?? null;
$category = $input['category'] ?? '';
$answers = $input['answers'] ?? [];

if (!$userId || empty($category)) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

// Construir o prompt para o Gemini com System Instruction de alto nível
$categoryNames = [
    'vida-pessoal' => 'Vida Pessoal',
    'futuro' => 'Planos para o Futuro',
    'escola' => 'Escola & Estudo',
    'historia' => 'História & Cultura',
    'aventura' => 'Aventura & Lazer',
    'familia' => 'Família & Amigos',
    'criatividade' => 'Criatividade',
    'empreendedorismo' => 'Empreendedorismo',
    'saude' => 'Saúde & Bem-estar'
];

$categoryName = $categoryNames[$category] ?? 'Férias';
$answersText = implode(' | ', $answers);

// SYSTEM INSTRUCTION DE ALTO NÍVEL
$systemInstruction = "Tu és um especialista em design de experiências e gerador de ideias criativas para férias. 
A tua tarefa é criar planos de férias detalhados, estruturados e altamente personalizados.

DIRETRIZES CRÍTICAS:
1. ESTRUTURA: Organiza a resposta em secções claras com títulos bem definidos
2. CRIATIVIDADE: Gera ideias únicas e fora do comum, evitando clichés
3. PRATICIDADE: Cada ideia deve ser viável e executável
4. PERSONALIZAÇÃO: Adapta completamente à categoria e às respostas do utilizador
5. PROFISSIONALISMO: Tom inspirador, motivador e profissional
6. DETALHE: Inclui informações específicas, horários, orçamentos estimados
7. AÇÃO: Termina com um call-to-action motivador

FORMATO OBRIGATÓRIO:
- Começa com um título atrativo (máx 50 caracteres)
- Inclui um subtítulo inspirador
- Estrutura em 5-7 secções principais
- Usa formatação clara com separadores
- Inclui dicas profissionais e recursos
- Termina com um resumo executivo";

$prompt = "$systemInstruction

CONTEXTO DO UTILIZADOR:
Categoria: $categoryName
Preferências: $answersText

Agora, cria um plano de férias EXCEPCIONAL e DETALHADO para este utilizador. Sê criativo, específico e inspirador.";

// Chamar a API Gemini com o prompt avançado
$response = callGeminiAPI($prompt);

if (!isset($response['candidates'][0]['content']['parts'][0]['text'])) {
    echo json_encode(['success' => false, 'message' => 'Erro ao gerar ideia']);
    exit;
}

$ideaContent = $response['candidates'][0]['content']['parts'][0]['text'];

// Extrair título (primeira linha)
$lines = explode("\n", $ideaContent);
$title = trim($lines[0]);
if (strpos($title, '#') === 0) {
    $title = trim(str_replace('#', '', $title));
}

// Guardar na base de dados
$conn = getDBConnection();

$stmt = $conn->prepare("INSERT INTO ideas (user_id, category, title, content, created_at) VALUES (?, ?, ?, ?, NOW())");
$stmt->bind_param("isss", $userId, $category, $title, $ideaContent);

if ($stmt->execute()) {
    $ideaId = $stmt->insert_id;
    
    echo json_encode([
        'success' => true,
        'message' => 'Ideia gerada com sucesso',
        'idea' => [
            'id' => $ideaId,
            'title' => $title,
            'content' => $ideaContent,
            'category' => $category
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao guardar ideia']);
}

$stmt->close();
$conn->close();
?>
