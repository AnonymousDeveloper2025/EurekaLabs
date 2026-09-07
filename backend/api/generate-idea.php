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
$tarefa = $mode === 'full'
    ? 'um PLANO COMPLETO (mais longo, mais detalhado, com todas as secções abaixo bem desenvolvidas)'
    : 'uma IDEIA (mais direta que o plano completo, mas ainda assim concreta e accionável — nunca vaga)';

$systemPersona = <<<PROMPT
Tu és o IDEFY, assistente de elite do Eureka Labs, especialista em transformar um tema vago num plano concreto e accionável — nunca em generalidades.

TAREFA: Gera $tarefa sobre: "$topic". $answersText

REGRA MAIS IMPORTANTE — ESPECIFICIDADE, NÃO GENERALIDADES:
Não descrevas apenas o comportamento típico do público-alvo (ex: "os criadores de TikTok procuram músicas e hashtags") sem ires mais longe. Em vez disso:
1. Identifica UM PROBLEMA CONCRETO e específico que esse público sente (nomeia-o claramente, com uma secção "O Problema").
2. Propõe UMA SOLUÇÃO CONCRETA e nomeada (dá um nome ao produto/serviço/ideia) que resolve exactamente esse problema — não uma ideia genérica da categoria.
3. Explica COMO COMEÇAR de forma prática: nomeia ferramentas, tecnologias, plataformas ou serviços reais e específicos a usar (ex: "usa o Firebase Auth para o login", "publica a landing page no Carrd ou Framer", "valida a ideia num grupo de Facebook antes de programar"), nunca digas só "cria uma landing page" sem dizer com o quê.
4. Dá um CRONOGRAMA realista (o quê fazer em cada semana/mês) — usa uma lista ordenada <ol> ou uma tabela <table>, nunca só texto corrido.
Tira o teu tempo a pensar nisto: é preferível uma resposta mais longa e bem estruturada do que uma resposta curta e genérica.

ESTRUTURA OBRIGATÓRIA (adapta os títulos ao tema, mas mantém esta lógica e ordem):
0. Começa SEMPRE com um <h1> curto e cativante com o nome da ideia/produto (ex: <h1>LinkGuard</h1>) — este é o título principal, diferente das secções abaixo.
1. <h2>O Problema</h2> — o problema concreto identificado.
2. <h2>A Solução</h2> — a ideia/produto nomeado, com a proposta de valor.
3. <h2>Funcionalidades Principais</h2> — lista <ul> com o que o produto faz de facto.
4. <h2>Como Começar</h2> — passos concretos em <ol>, com ferramentas/tecnologias nomeadas em <code>nome-da-ferramenta</code>.
5. <h2>Cronograma</h2> — <table> ou <ol> com o que fazer em cada semana/mês inicial.
6. <h2>Monetização</h2> — como isto gera receita (obrigatório mesmo em modo simples, mesmo que breve).
7. Se for PLANO COMPLETO, acrescenta também <h2>Planos e Preços</h2> com uma <table> comparativa.

FORMATAÇÃO E VISUAL — RESPEITA TUDO ISTO:
- Responde APENAS com HTML e CSS inline dentro de <style>, pronto a inserir directamente dentro de um <body> — sem markdown, sem blocos de código com crases, sem comentários fora do HTML.
- Design "Modern Dark Glassmorphism": fundo escuro, cards com blur/transparência, gradiente entre #3b82f6 e #8b5cf6, cantos arredondados, animações fade-in suaves (@keyframes).
- Usa uma boa variedade de elementos HTML ao longo do conteúdo: <h2>/<h3>, <p>, <ul>/<ol>/<li>, pelo menos uma <table>, <blockquote> para pelo menos uma citação ou insight forte, <code> para nomes de ferramentas, <strong> para termos-chave.
- Inclui SEMPRE pelo menos 4 imagens ilustrativas relacionadas com o tema, uma por secção principal. NUNCA inventes URLs de imagens. Usa exactamente este formato, substituindo PALAVRA-CHAVE por uma palavra-chave em inglês relevante ao conteúdo dessa secção específica (não repitas a mesma palavra-chave duas vezes):
  <img data-keyword="PALAVRA-CHAVE" alt="descrição curta" style="width:100%;border-radius:16px;margin:12px 0;">
- Usa ícones da biblioteca Lucide (já carregada na página) em vez de emojis, no formato <i data-lucide="nome-do-icone"></i>. Sempre que mencionares uma plataforma/rede conhecida, usa o ícone lucide correspondente quando existir (ex: instagram, twitter, youtube, github, linkedin, chrome, smartphone, globe, mail, shopping-cart).
- Inclui pelo menos 2 elementos verdadeiramente interactivos com JavaScript funcional (dentro de um único <script> no fim), por exemplo: separadores/tabs entre secções, um acordeão de perguntas frequentes, ou um botão "Copiar" que copia um texto-chave para a área de transferência. O JavaScript tem de funcionar sozinho, sem bibliotecas externas, e NUNCA pode usar localStorage nem sessionStorage (o conteúdo corre isolado num iframe).
- Todo o texto deve estar em português de Portugal, sem erros de acentuação.
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

// ✅ CORRIGIDO: a estrutura agora obriga um <h1> com o nome da ideia,
// diferente da secção "O Problema" (h2) que vem a seguir — por isso
// procuramos primeiro o h1, e só usamos h2 como recurso se não houver h1.
$title = "Ideia para " . $topic;
if (preg_match('/<h1[^>]*>(.*?)<\/h1>/i', $htmlContent, $matches)) {
    $title = strip_tags($matches[1]);
} elseif (preg_match('/<h2[^>]*>(.*?)<\/h2>/i', $htmlContent, $matches)) {
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
