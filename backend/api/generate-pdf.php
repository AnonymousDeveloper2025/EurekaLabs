<?php
/**
 * IDEFY PDF MAKER — Eureka Labs
 *
 * Renderização editorial do conteúdo semântico do Idefy.
 * O HTML da aplicação contém uma interface interactiva; este endpoint extrai apenas o
 * conteúdo do main.idf-main e transforma-o numa publicação PDF navegável.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Mpdf\Mpdf;
use Mpdf\Output\Destination;

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$userId = requireAuth();
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$ideaId = (int)($input['id'] ?? 0);

if ($ideaId <= 0) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'ID da ideia em falta.']);
    exit;
}

try {
    $conn = getDBConnection();
    $stmt = $conn->prepare('SELECT id, title, content, category FROM ideas WHERE id = ? AND user_id = ?');
    $stmt->execute([$ideaId, $userId]);
    $idea = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$idea) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Ideia não encontrada.']);
        exit;
    }
} catch (Throwable $e) {
    error_log('Erro generate-pdf (buscar ideia): ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Erro ao carregar a ideia.']);
    exit;
}

function pdfText(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * 150 palavras-chave distribuídas por 10 direcções artísticas. O tema é escolhido
 * pelo texto inteiro da ideia e não apenas pela categoria gravada na base de dados.
 */
function pdfThemes(): array
{
    $base = [
        'game' => [
            'keywords' => ['jogo','jogos','game','gaming','gamer','videojogo','videogame','arcade','rpg','aventura','missão','missao','personagem','nível','nivel','boss','pixel','fantasia','terror','sobrevivência','sobrevivencia','multiplayer','consola','console','play','steam','unity'],
            'label' => 'Jogo & mundos imersivos', 'font' => 'dejavusanscondensed', 'bg' => '#10172b', 'paper' => '#18223c', 'ink' => '#eaf1ff', 'muted' => '#a8b6d8', 'accent' => '#7c5cff', 'accent2' => '#22d3ee', 'gold' => '#fbbf24', 'line' => '#5064a0', 'border' => 'neon', 'icon' => '◆', 'watermark' => 'PLAY', 'pattern' => 'grid',
        ],
        'tech' => [
            'keywords' => ['tecnologia','software','aplicação','aplicacao','app','programação','programacao','código','codigo','ia','inteligência','inteligencia','artificial','startup','website','site','plataforma','automação','automacao','robô','robo','saas','digital','cibersegurança','ciberseguranca','dados'],
            'label' => 'Tecnologia & produto digital', 'font' => 'dejavusans', 'bg' => '#edf5ff', 'paper' => '#ffffff', 'ink' => '#17243d', 'muted' => '#63718a', 'accent' => '#1769e0', 'accent2' => '#00a8a8', 'gold' => '#e19a19', 'line' => '#a9c7ed', 'border' => 'circuit', 'icon' => '⌁', 'watermark' => 'BUILD', 'pattern' => 'circuit',
        ],
        'business' => [
            'keywords' => ['negócio','negocio','empresa','empreendedor','empreender','vendas','venda','marketing','finanças','financas','investimento','investir','loja','cliente','lucro','monetização','monetizacao','e-commerce','freelance','carreira','trabalho','emprego','poupança','poupanca','dinheiro','rendimento','mercado','marca','negociar'],
            'label' => 'Negócio & crescimento', 'font' => 'dejavusans', 'bg' => '#f4f8f4', 'paper' => '#ffffff', 'ink' => '#1e3029', 'muted' => '#687a71', 'accent' => '#17795d', 'accent2' => '#b88925', 'gold' => '#d49a24', 'line' => '#afd3c2', 'border' => 'double', 'icon' => '◆', 'watermark' => 'GROW', 'pattern' => 'waves',
        ],
        'study' => [
            'keywords' => ['estudo','estudar','aprendizagem','aprender','escola','universidade','exame','curso','idioma','língua','lingua','aula','educação','educacao','livro','leitura','tese','formação','formacao','faculdade','professor','matéria','materia','conhecimento','biblioteca'],
            'label' => 'Estudo & conhecimento', 'font' => 'dejavuserif', 'bg' => '#fffaf0', 'paper' => '#fffdf7', 'ink' => '#342d26', 'muted' => '#82766b', 'accent' => '#9a4e2d', 'accent2' => '#355c7d', 'gold' => '#c68c27', 'line' => '#e3cba9', 'border' => 'notebook', 'icon' => '✦', 'watermark' => 'FOCUS', 'pattern' => 'paper',
        ],
        'wellness' => [
            'keywords' => ['saúde','saude','fitness','treino','ginásio','ginasio','dieta','nutrição','nutricao','bem-estar','bem estar','mental','meditação','meditacao','sono','corrida','yoga','desporto','esporte','emagrecer','terapia','calma','hábitos','habitos','autocuidado'],
            'label' => 'Bem-estar & equilíbrio', 'font' => 'dejavuserif', 'bg' => '#f5f4ff', 'paper' => '#ffffff', 'ink' => '#2d2942', 'muted' => '#77728d', 'accent' => '#7257b8', 'accent2' => '#d17c8b', 'gold' => '#b88925', 'line' => '#d4c9ee', 'border' => 'soft', 'icon' => '◌', 'watermark' => 'BALANCE', 'pattern' => 'organic',
        ],
        'creative' => [
            'keywords' => ['arte','design','música','musica','escrever','criatividade','criativo','fotografia','vídeo','video','pintura','desenho','podcast','moda','cinema','ilustração','ilustracao','teatro','dança','danca','poesia','conteúdo','conteudo','criação','criacao','estúdio','estudio'],
            'label' => 'Criatividade & expressão', 'font' => 'dejavuserif', 'bg' => '#fff1f7', 'paper' => '#fffafe', 'ink' => '#3b2535', 'muted' => '#896c7d', 'accent' => '#d33878', 'accent2' => '#7554c7', 'gold' => '#e39a28', 'line' => '#edb6d0', 'border' => 'brush', 'icon' => '✦', 'watermark' => 'CREATE', 'pattern' => 'dots',
        ],
        'food' => [
            'keywords' => ['cozinha','culinária','culinaria','comida','restaurante','jantar','bolo','pastelaria','gastronomia','bebida','café','cafe','receita','padaria','chef','ingrediente','alimento','menu','doce','salada','vegan','vegano','agricultura','horta','comer'],
            'label' => 'Gastronomia & sabor', 'font' => 'dejavuserif', 'bg' => '#fff6e7', 'paper' => '#fffdf8', 'ink' => '#4a2d1e', 'muted' => '#91715e', 'accent' => '#c56822', 'accent2' => '#57815b', 'gold' => '#d99928', 'line' => '#edc497', 'border' => 'tile', 'icon' => '●', 'watermark' => 'TASTE', 'pattern' => 'checks',
        ],
        'travel' => [
            'keywords' => ['viagem','viajar','turismo','férias','ferias','roteiro','passeio','aventura','mochila','destino','trilho','praia','montanha','cidade','hotel','voo','avião','aviao','turista','explorar','mapa','cultura','expedição','expedicao','campismo'],
            'label' => 'Viagem & descoberta', 'font' => 'dejavusans', 'bg' => '#eaf8fb', 'paper' => '#fbffff', 'ink' => '#183b45', 'muted' => '#66838b', 'accent' => '#087f95', 'accent2' => '#de8f32', 'gold' => '#c38a27', 'line' => '#abd8df', 'border' => 'map', 'icon' => '⌖', 'watermark' => 'EXPLORE', 'pattern' => 'contour',
        ],
        'nature' => [
            'keywords' => ['natureza','ambiente','sustentável','sustentavel','ecologia','ecológico','ecologico','clima','reciclagem','reciclar','energia','solar','água','agua','floresta','mar','animais','animal','jardim','plantas','planta','rural','agro','permacultura','verde','conservação'],
            'label' => 'Natureza & futuro', 'font' => 'dejavusans', 'bg' => '#edf8ef', 'paper' => '#fbfffb', 'ink' => '#1d3b2a', 'muted' => '#698471', 'accent' => '#34794e', 'accent2' => '#8a9b3f', 'gold' => '#c0932b', 'line' => '#b4d8b9', 'border' => 'leaf', 'icon' => '❧', 'watermark' => 'GROW', 'pattern' => 'leaf',
        ],
        'social' => [
            'keywords' => ['comunidade','evento','festa','amigos','família','familia','casamento','voluntariado','associação','associacao','relacionamento','casal','social','networking','aniversário','aniversario','pessoas','bairro','ajuda','grupo','crianças','criancas','juventude','cidadania','cultura'],
            'label' => 'Pessoas & comunidade', 'font' => 'dejavusans', 'bg' => '#fff3ef', 'paper' => '#fffdfc', 'ink' => '#452b2b', 'muted' => '#8d7370', 'accent' => '#c45158', 'accent2' => '#4267a8', 'gold' => '#c5912b', 'line' => '#e8baba', 'border' => 'ribbon', 'icon' => '♥', 'watermark' => 'TOGETHER', 'pattern' => 'confetti',
        ],
    ];
    return $base + ['default' => [
        'keywords' => [], 'label' => 'Ideia em destaque', 'font' => 'dejavusans', 'bg' => '#f2f3fb', 'paper' => '#ffffff', 'ink' => '#22243a', 'muted' => '#73758b', 'accent' => '#5146a8', 'accent2' => '#239b9b', 'gold' => '#ba8522', 'line' => '#c7c8e5', 'border' => 'classic', 'icon' => '◆', 'watermark' => 'IDEA', 'pattern' => 'aurora',
    ]];
}

function pdfTheme(string $category, string $title, string $content): array
{
    $themes = pdfThemes();
    $haystack = mb_strtolower($category . ' ' . $title . ' ' . strip_tags($content), 'UTF-8');
    $best = 'default';
    $bestScore = 0;
    foreach ($themes as $name => $theme) {
        $score = 0;
        foreach ($theme['keywords'] as $keyword) {
            if (mb_strpos($haystack, $keyword) !== false) {
                $score += (mb_strpos(mb_strtolower($category, 'UTF-8'), $keyword) !== false ? 6 : 1);
                if (mb_strpos(mb_strtolower($title, 'UTF-8'), $keyword) !== false) $score += 3;
            }
        }
        if ($score > $bestScore) {
            $best = $name;
            $bestScore = $score;
        }
    }
    $selected = $themes[$best];
    $selected['key'] = $best;
    $selected['score'] = $bestScore;
    return $selected;
}

function pdfImageData(string $url, array &$cache, int &$count, int $max = 6): ?string
{
    if ($count >= $max || !preg_match('#^https?://#i', $url)) return null;
    if (array_key_exists($url, $cache)) return $cache[$url] ?: null;
    $cache[$url] = false;
    $context = stream_context_create(['http' => ['timeout' => 8, 'follow_location' => 0, 'user_agent' => 'Idefy-PDF/2.0'], 'https' => ['timeout' => 8, 'follow_location' => 0, 'user_agent' => 'Idefy-PDF/2.0']]);
    $data = @file_get_contents($url, false, $context, 0, 5 * 1024 * 1024 + 1);
    if ($data === false || strlen($data) > 5 * 1024 * 1024) return null;
    $info = @getimagesizefromstring($data);
    $mime = $info['mime'] ?? '';
    if (!$info || !in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) return null;
    $count++;
    return $cache[$url] = 'data:' . $mime . ';base64,' . base64_encode($data);
}

function pdfSourceContent(string $html): string
{
    // Retira o menu, progresso, botões e o iframe da aplicação; guarda só o artigo.
    if (preg_match('/<main\b[^>]*class=["\'][^"\']*idf-main[^"\']*["\'][^>]*>(.*?)<\/main>/is', $html, $match)) {
        return $match[1];
    }
    return preg_replace('/<(script|style|noscript|template|nav|aside)\b[^>]*>.*?<\/\1>/is', '', $html) ?? $html;
}

/** Limpa HTML e recolhe um índice e uma lista de referências clicáveis. */
function pdfCleanHtml(string $html, array &$toc, array &$links, array &$imageCache, int &$imageCount): string
{
    $html = pdfSourceContent($html);
    $html = preg_replace('/<(script|style|noscript|template|form|iframe|object|embed)\b[^>]*>.*?<\/\1>/is', '', $html) ?? '';
    libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->loadHTML('<?xml encoding="UTF-8"><div id="pdf-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $root = $dom->getElementById('pdf-root');
    if (!$root) return '<p>Sem conteúdo disponível.</p>';

    $allowed = ['p','br','div','section','article','h1','h2','h3','h4','h5','strong','b','em','i','u','s','small','mark','blockquote','ul','ol','li','a','img','figure','figcaption','table','caption','thead','tbody','tfoot','tr','th','td','hr','pre','code','sup','sub','dl','dt','dd','span'];
    $out = '';
    $headingIndex = 0;
    $skipFirstH1 = true;
    $walk = function (DOMNode $node) use (&$walk, &$out, &$toc, &$links, &$imageCache, &$imageCount, &$headingIndex, &$skipFirstH1, $allowed): void {
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $text = preg_replace('/\s+/u', ' ', $child->nodeValue ?? '') ?? '';
                $out .= pdfText($text);
                continue;
            }
            if ($child->nodeType !== XML_ELEMENT_NODE) continue;
            $tag = strtolower($child->nodeName);
            if (!in_array($tag, $allowed, true)) {
                $walk($child);
                continue;
            }
            if ($tag === 'h1' && $skipFirstH1) {
                $skipFirstH1 = false;
                continue;
            }
            if (in_array($tag, ['h2', 'h3', 'h4'], true)) {
                $label = trim(preg_replace('/\s+/u', ' ', $child->textContent ?? '') ?? '');
                if ($label !== '') {
                    $headingIndex++;
                    $anchor = 'sec-' . $headingIndex;
                    $toc[] = ['label' => $label, 'level' => (int)substr($tag, 1), 'anchor' => $anchor];
                    $out .= '<' . $tag . ' id="' . $anchor . '">';
                    $walk($child);
                    $out .= '</' . $tag . '>';
                    continue;
                }
            }
            if ($tag === 'img') {
                $src = trim($child->getAttribute('src'));
                $data = pdfImageData($src, $imageCache, $imageCount);
                if ($data) {
                    $out .= '<img src="' . pdfText($data) . '" alt="' . pdfText($child->getAttribute('alt')) . '">';
                }
                continue;
            }
            if ($tag === 'a') {
                $href = trim($child->getAttribute('href'));
                $valid = preg_match('#^(https?://|mailto:)#i', $href);
                if ($valid) {
                    $links[$href] = trim(preg_replace('/\s+/u', ' ', $child->textContent ?? '') ?? '') ?: $href;
                    $out .= '<a href="' . pdfText($href) . '">';
                    $walk($child);
                    $out .= '</a>';
                } else {
                    $walk($child);
                }
                continue;
            }
            $out .= '<' . $tag . '>';
            $walk($child);
            $out .= '</' . $tag . '>';
        }
    };
    $walk($root);
    return $out !== '' ? $out : '<p>Sem conteúdo disponível.</p>';
}

$theme = pdfTheme((string)($idea['category'] ?? ''), (string)$idea['title'], (string)$idea['content']);
$toc = [];
$links = [];
$imageCache = [];
$imageCount = 0;
$bodyHtml = pdfCleanHtml((string)$idea['content'], $toc, $links, $imageCache, $imageCount);
$generatedAt = date('d/m/Y H:i');
$coverSummary = trim(preg_replace('/\s+/u', ' ', strip_tags($bodyHtml)) ?? '');
$coverSummary = mb_substr($coverSummary, 0, 360, 'UTF-8');

$tocHtml = '<div class="toc-card"><div class="toc-kicker">MAPA DA IDEIA</div><div class="toc-heading">Índice</div><div class="toc-rule"></div>';
if ($toc) {
    foreach ($toc as $item) {
        $indent = max(0, $item['level'] - 2) * 7;
        $tocHtml .= '<div class="toc-row" style="padding-left:' . $indent . 'mm"><span class="toc-bullet">' . pdfText($theme['icon']) . '</span><a href="#' . pdfText($item['anchor']) . '">' . pdfText($item['label']) . '</a></div>';
    }
} else {
    $tocHtml .= '<div class="toc-row"><span class="toc-bullet">' . pdfText($theme['icon']) . '</span><span>Conteúdo principal</span></div>';
}
$tocHtml .= '</div>';

$referenceHtml = '';
if ($links) {
    $referenceHtml = '<section class="references"><div class="section-label">REFERÊNCIAS & LINKS</div><h2>Fontes para continuar</h2><p class="muted">Os links abaixo permanecem clicáveis no PDF.</p><ol>';
    foreach (array_slice($links, 0, 30, true) as $href => $label) {
        $referenceHtml .= '<li><a href="' . pdfText($href) . '">' . pdfText($label) . '</a><br><span class="url">' . pdfText($href) . '</span></li>';
    }
    $referenceHtml .= '</ol></section>';
}

$css = <<<CSS
@page { sheet-size: A4; margin: 28mm 19mm 24mm 19mm; background-color: {$theme['bg']}; }
body { font-family: {$theme['font']}; font-size: 10.5pt; line-height: 1.52; color: {$theme['ink']}; background-color: {$theme['bg']}; }
.page-frame { position: fixed; top: 8mm; left: 8mm; right: 8mm; bottom: 8mm; border: 0.55mm solid {$theme['line']}; border-radius: 5mm; }
.page-frame.double { border: 0.8mm double {$theme['accent']}; }
.page-frame.neon { border: 0.55mm solid {$theme['accent']}; }
.page-frame.notebook { border: 0.4mm solid {$theme['accent']}; border-radius: 1mm; }
.page-frame.soft { border: 0.6mm solid {$theme['line']}; border-radius: 10mm; }
.page-frame.brush { border: 1.1mm solid {$theme['accent']}; border-radius: 3mm; }
.page-frame.tile { border: 0.75mm solid {$theme['accent']}; border-radius: 4mm; }
.page-frame.map { border: 0.5mm dashed {$theme['accent']}; border-radius: 2mm; }
.page-frame.leaf { border: 0.55mm solid {$theme['accent']}; border-radius: 9mm 2mm 9mm 2mm; }
.page-frame.ribbon { border: 0.7mm solid {$theme['accent']}; border-radius: 5mm 1mm 5mm 1mm; }
.page-frame.circuit { border: 0.45mm solid {$theme['accent']}; border-radius: 1.5mm; }
.page-frame.classic { border: 0.55mm solid {$theme['accent']}; border-radius: 4mm; }
.page-corner { position: fixed; top: 8mm; right: 8mm; width: 22mm; height: 22mm; background-color: {$theme['accent']}; opacity: .12; border-radius: 0 0 0 22mm; }
.header-brand { color: {$theme['accent']}; font-size: 9pt; font-weight: bold; letter-spacing: 1.2mm; text-align: left; }
.header-theme { color: {$theme['muted']}; font-size: 7.5pt; text-align: right; }
.cover { page-break-after: always; min-height: 235mm; padding: 16mm 5mm 6mm 5mm; }
.cover-top { color: {$theme['accent']}; font-size: 9pt; font-weight: bold; letter-spacing: 1.4mm; text-transform: uppercase; }
.cover-mark { color: {$theme['accent2']}; font-size: 25pt; margin-top: 12mm; }
.cover-watermark { position: absolute; top: 57mm; left: 14mm; color: {$theme['accent']}; opacity: .10; font-size: 62pt; font-weight: bold; letter-spacing: 3mm; }
.cover h1 { position: relative; color: {$theme['ink']}; font-size: 35pt; line-height: 1.08; margin: 29mm 0 7mm 0; border: 0; padding: 0; }
.cover-subtitle { color: {$theme['muted']}; font-size: 13pt; line-height: 1.4; margin-bottom: 13mm; }
.cover-chip { display: inline-block; color: {$theme['accent']}; background-color: {$theme['paper']}; border: 0.35mm solid {$theme['line']}; border-radius: 8mm; padding: 2.5mm 5mm; font-size: 8.5pt; font-weight: bold; }
.cover-description { margin-top: 16mm; padding: 6mm; color: {$theme['ink']}; background-color: {$theme['paper']}; border-left: 1.5mm solid {$theme['accent']}; border-radius: 0 5mm 5mm 0; }
.cover-footer { position: absolute; bottom: 10mm; color: {$theme['muted']}; font-size: 8.5pt; }
.toc-card { page-break-after: always; padding: 10mm 7mm; background-color: {$theme['paper']}; border: 0.55mm solid {$theme['line']}; border-radius: 7mm; }
.toc-kicker,.section-label { color: {$theme['accent']}; font-size: 8pt; font-weight: bold; letter-spacing: 1.1mm; }
.toc-heading { color: {$theme['ink']}; font-size: 26pt; font-weight: bold; margin: 3mm 0; }
.toc-rule { border-top: 0.7mm solid {$theme['accent']}; margin: 4mm 0 8mm 0; }
.toc-row { padding: 3mm 0; border-bottom: 0.2mm solid {$theme['line']}; font-size: 11pt; }
.toc-row a { color: {$theme['ink']}; text-decoration: none; }
.toc-bullet { color: {$theme['accent2']}; font-size: 10pt; display: inline-block; width: 8mm; }
h1,h2,h3,h4,h5 { page-break-after: avoid; line-height: 1.2; color: {$theme['ink']}; }
h1 { font-size: 23pt; color: {$theme['accent']}; border-bottom: 0.65mm solid {$theme['accent']}; padding-bottom: 3mm; }
h2 { color: {$theme['accent']}; font-size: 17pt; margin-top: 10mm; border-left: 2mm solid {$theme['accent']}; padding-left: 4mm; }
h3 { color: {$theme['accent2']}; font-size: 13pt; margin-top: 7mm; }
h4 { color: {$theme['gold']}; font-size: 11pt; }
p { margin: 0 0 4mm 0; text-align: justify; }
.lead { font-size: 13pt; color: {$theme['muted']}; }
.muted { color: {$theme['muted']}; }
blockquote { border-left: 1.8mm solid {$theme['accent2']}; background-color: {$theme['paper']}; padding: 4mm 5mm; margin: 5mm 0; color: {$theme['muted']}; font-style: italic; border-radius: 0 5mm 5mm 0; }
ul,ol { margin-top: 2mm; margin-bottom: 5mm; padding-left: 8mm; }
li { margin-bottom: 2mm; }
li::marker { color: {$theme['accent']}; font-weight: bold; }
mark { background-color: {$theme['gold']}; color: {$theme['ink']}; padding: 0 1mm; }
code,pre { color: {$theme['accent']}; background-color: {$theme['paper']}; }
figure { margin: 6mm 0; padding: 3mm; background-color: {$theme['paper']}; border: 0.45mm solid {$theme['line']}; border-radius: 6mm; page-break-inside: avoid; }
img { max-width: 100%; height: auto; margin: 1mm auto; border-radius: 4mm; }
figcaption { color: {$theme['muted']}; text-align: center; font-size: 8.5pt; padding-top: 2mm; }
table { width: 100%; border-collapse: separate; border-spacing: 0; margin: 6mm 0; background-color: {$theme['paper']}; border: 0.55mm solid {$theme['accent']}; border-radius: 5mm; overflow: hidden; page-break-inside: avoid; }
caption { color: {$theme['accent']}; text-align: left; font-size: 9pt; font-weight: bold; padding: 3mm 2mm; }
th { background-color: {$theme['accent']}; color: #ffffff; font-weight: bold; padding: 3mm 2.5mm; text-align: left; }
td { color: {$theme['ink']}; padding: 2.8mm 2.5mm; border-bottom: 0.25mm solid {$theme['line']}; vertical-align: top; }
tr:nth-child(even) td { background-color: {$theme['bg']}; }
tr:last-child td { border-bottom: 0; }
hr { border: 0; border-top: 0.35mm solid {$theme['line']}; margin: 7mm 0; }
a { color: {$theme['accent']}; text-decoration: underline; }
.references { page-break-before: always; }
.references h2 { margin-top: 3mm; }
.references li { margin-bottom: 4mm; }
.url { color: {$theme['muted']}; font-size: 7.5pt; }
.meta { color: {$theme['muted']}; text-align: center; font-size: 8pt; margin-top: 10mm; }
CSS;

try {
    $tempDir = sys_get_temp_dir() . '/idefy-mpdf';
    if (!is_dir($tempDir)) @mkdir($tempDir, 0775, true);
    $mpdf = new Mpdf([
        'mode' => 'utf-8', 'format' => 'A4', 'tempDir' => $tempDir,
        'margin_left' => 19, 'margin_right' => 19, 'margin_top' => 28, 'margin_bottom' => 24,
        'margin_header' => 8, 'margin_footer' => 8, 'default_font' => $theme['font'],
        'autoScriptToLang' => true, 'autoLangToFont' => true, 'showImageErrors' => false,
    ]);
    $mpdf->SetTitle((string)$idea['title']);
    $mpdf->SetAuthor('Idefy PDF maker - Eureka Labs');
    $mpdf->SetSubject('Publicação profissional da ideia');
    $mpdf->SetKeywords($theme['label'] . ', Idefy, Eureka Labs, PDF');
    $mpdf->showWatermarkText = false;
    $mpdf->SetHTMLHeader('<table style="border:0;background:transparent;margin:0"><tr><td style="border:0;padding:0;color:' . $theme['accent'] . ';font-size:9pt;font-weight:bold;letter-spacing:1mm">IDEFY · EUREKA LABS</td><td style="border:0;padding:0;color:' . $theme['muted'] . ';font-size:8pt;text-align:right">' . pdfText($theme['label']) . '</td></tr></table>');
    $mpdf->SetHTMLFooter('<div style="text-align:center;color:' . $theme['muted'] . ';font-size:8pt">Gerado por Idefy PDF maker - Eureka Labs em ' . pdfText($generatedAt) . ' · Página {PAGENO} de {nbpg}</div>');
    $mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);

    $frame = '<div class="page-frame ' . pdfText($theme['border']) . '"></div><div class="page-corner"></div>';
    $cover = '<section class="cover"><div class="cover-top">' . pdfText($theme['label']) . ' · PUBLICAÇÃO IDEFY</div><div class="cover-mark">' . pdfText($theme['icon']) . '</div><div class="cover-watermark">' . pdfText($theme['watermark']) . '</div><h1>' . pdfText((string)$idea['title']) . '</h1><p class="cover-subtitle">Uma ideia transformada num mapa claro de decisão, execução e próximos passos.</p><span class="cover-chip">' . pdfText($theme['icon'] . '  Tema: ' . $theme['label']) . '</span><div class="cover-description"><strong>Visão geral</strong><br>' . pdfText($coverSummary !== '' ? $coverSummary : 'Uma publicação preparada pelo Idefy para transformar intenção em acção.') . '</div><div class="cover-footer">Idefy PDF maker<br>Eureka Labs · ' . pdfText($generatedAt) . '</div></section>';
    $document = $frame . $cover . $tocHtml . '<div class="content">' . $bodyHtml . $referenceHtml . '<div class="meta">Gerado por Idefy PDF maker - Eureka Labs em ' . pdfText($generatedAt) . '</div></div>';
    $mpdf->WriteHTML($document, \Mpdf\HTMLParserMode::HTML_BODY);

    $stmt = $conn->prepare('UPDATE ideas SET pdf_generated = TRUE, pdf_generated_at = NOW() WHERE id = ? AND user_id = ?');
    $stmt->execute([$ideaId, $userId]);

    $filename = 'ideia-' . $ideaId . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $mpdf->Output($filename, Destination::DOWNLOAD);
} catch (Throwable $e) {
    error_log('Erro generate-pdf (mPDF): ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Não foi possível gerar o PDF. Verifique a instalação do mPDF.']);
}
