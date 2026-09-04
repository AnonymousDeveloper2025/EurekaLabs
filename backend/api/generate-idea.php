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

$answersText = !empty($answers) ? "Preferências do utilizador: " . implode(', ', $answers) . "." : "";

// ✅ CORRIGIDO: o prompt anterior era demasiado vago ("HTML e CSS inline,
// cores X e Y") — por isso o Gemini às vezes esquecia botões, animações,
// e usava imagens genéricas/sem relação com o tema. Agora o prompt pede
// explicitamente estrutura em secções, imagens por palavra-chave (que
// substituímos a seguir por fotos reais do Unsplash), ícones Lucide e
// pelo menos um elemento verdadeiramente interactivo com JavaScript.
$tarefa = $mode === 'full' ? 'um PLANO COMPLETO (mais detalhado, com várias secções)' : 'uma IDEIA SIMPLES (mais direta, mas ainda bem ilustrada)';

$systemPersona = <<<PROMPT
Tu és o IDEFY, assistente de elite do Eureka Labs, especialista em transformar ideias em páginas visuais ricas e interactivas.

TAREFA: Gera $tarefa sobre: "$topic". $answersText

Responde APENAS com HTML e CSS inline dentro de <style>, pronto a inserir directamente dentro de um <body> — sem markdown, sem blocos de código com crases, sem comentários fora do HTML.

REGRAS OBRIGATÓRIAS:
1. Design "Modern Dark Glassmorphism": fundo escuro, cards com blur/transparência, gradiente entre #3b82f6 e #8b5cf6, cantos arredondados, animações fade-in suaves ao longo da página (@keyframes).
2. Organiza o conteúdo em pelo menos 3 secções distintas com títulos claros em <h2> ou <h3> (ex: Visão Geral, Funcionalidades, Como Começar, Monetização — adapta ao tema).
3. Inclui SEMPRE pelo menos 4 imagens ilustrativas relacionadas com o tema. NUNCA inventes URLs de imagens. Usa exactamente este formato, substituindo PALAVRA-CHAVE por uma palavra-chave em inglês relevante ao conteúdo dessa secção específica (não repitas a mesma palavra-chave duas vezes):
   <img data-keyword="PALAVRA-CHAVE" alt="descrição curta" style="width:100%;border-radius:16px;margin:12px 0;">
4. Usa ícones da biblioteca Lucide (já carregada na página) em vez de emojis, no formato <i data-lucide="nome-do-icone"></i>. Sempre que mencionares uma plataforma/rede conhecida, usa o ícone lucide correspondente quando existir (ex: instagram, twitter, youtube, github, linkedin, chrome, smartphone, globe, mail, shopping-cart).
5. Inclui pelo menos 2 elementos verdadeiramente interactivos com JavaScript funcional (dentro de um único <script> no fim), por exemplo: separadores/tabs entre secções, um acordeão de perguntas frequentes, ou um botão "Copiar" que copia um texto-chave para a área de transferência. O JavaScript tem de funcionar sozinho, sem bibliotecas externas, e NUNCA pode usar localStorage nem sessionStorage (o conteúdo corre isolado num iframe).
6. Se for um PLANO COMPLETO, inclui também uma tabela comparativa (ex: planos/preços ou fases do plano).
7. Todo o texto deve estar em português de Portugal, sem erros de acentuação.
PROMPT;

$prompt = $systemPersona;

$response = callGeminiAPI($prompt);

if (!$response || !isset($response['choices'][0]['message']['content'])) {
    http_response_code(502);
    echo json_encode(['success' => false, 'message' => 'Erro ao processar com o IDEFY. Tenta novamente.']);
    exit;
}

$htmlContent = $response['choices'][0]['message']['content'];
$htmlContent = preg_replace('/^```html\s*|```$/i', '', trim($htmlContent));

// ✅ NOVO: troca cada <img data-keyword="..."> por uma foto real e
// relacionada do Unsplash. Isto resolve as "imagens sem nada a ver com o
// contexto" — em vez de a IA inventar uma URL de imagem (que não existe
// ou é genérica), ela escolhe só a palavra-chave, e nós vamos buscar a
// foto verdadeira. Limitado a 4 palavras-chave únicas para não gastar a
// quota da API do Unsplash de mais.
preg_match_all('/data-keyword="([^"]+)"/', $htmlContent, $kwMatches);
$uniqueKeywords = array_slice(array_unique($kwMatches[1]), 0, 4);
$imageCache = [];
foreach ($uniqueKeywords as $keyword) {
    $url = getUnsplashImage($keyword);
    $imageCache[$keyword] = $url ?: ('https://picsum.photos/seed/' . urlencode($keyword) . '/900/600');
}
$htmlContent = preg_replace_callback('/<img([^>]*)data-keyword="([^"]+)"([^>]*)>/i', function ($m) use ($imageCache) {
    $keyword = $m[2];
    $src = $imageCache[$keyword] ?? ('https://picsum.photos/seed/' . urlencode($keyword) . '/900/600');
    $attrs = $m[1] . $m[3];
    if (stripos($attrs, 'src=') !== false) {
        $attrs = preg_replace('/src="[^"]*"/i', 'src="' . $src . '"', $attrs);
    } else {
        $attrs = ' src="' . $src . '"' . $attrs;
    }
    return '<img' . $attrs . '>';
}, $htmlContent);

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
