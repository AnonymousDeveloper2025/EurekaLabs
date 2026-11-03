<?php
/**
 * GENERATE PDF — EUREKA LABS / IDEFY
 *
 * Gerador de PDF temático com mPDF.
 * Instalação: composer install --no-dev --optimize-autoloader
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
$input = json_decode(file_get_contents('php://input'), true);
$ideaId = (int)($input['id'] ?? 0);

if ($ideaId <= 0) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'ID da ideia em falta.']);
    exit;
}

try {
    $conn = getDBConnection();
    $stmt = $conn->prepare(
        'SELECT id, title, content, category FROM ideas WHERE id = ? AND user_id = ?'
    );
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

/** Temas definidos por palavra-chave/categoria. As fontes escolhidas existem no mPDF. */
function getPdfTheme(string $category): array
{
    $themes = [
        'tecnologia' => [
            'bg' => '#eef4ff', 'accent' => '#2563eb', 'dark' => '#163b82',
            'font' => 'dejavusans', 'border' => 'solid', 'watermark' => 'TECNOLOGIA',
        ],
        'negocio' => [
            'bg' => '#edf7f3', 'accent' => '#0d7669', 'dark' => '#075e54',
            'font' => 'dejavusans', 'border' => 'double', 'watermark' => 'NEGÓCIO',
        ],
        'criatividade' => [
            'bg' => '#fff0f8', 'accent' => '#db2777', 'dark' => '#9d174d',
            'font' => 'dejavuserif', 'border' => 'dashed', 'watermark' => 'CRIATIVIDADE',
        ],
        'aventura' => [
            'bg' => '#f2f8e9', 'accent' => '#4d7c0f', 'dark' => '#365314',
            'font' => 'dejavusans', 'border' => 'solid', 'watermark' => 'AVENTURA',
        ],
        'culinaria' => [
            'bg' => '#fff5e3', 'accent' => '#c26c08', 'dark' => '#8a4b08',
            'font' => 'dejavuserif', 'border' => 'double', 'watermark' => 'CULINÁRIA',
        ],
        'bem-estar' => [
            'bg' => '#f5f1ff', 'accent' => '#6d28d9', 'dark' => '#4c1d95',
            'font' => 'dejavuserif', 'border' => 'dashed', 'watermark' => 'BEM-ESTAR',
        ],
        'viagem' => [
            'bg' => '#eaf8fb', 'accent' => '#08849b', 'dark' => '#075e70',
            'font' => 'dejavusans', 'border' => 'solid', 'watermark' => 'VIAGEM',
        ],
        'geral' => [
            'bg' => '#f1f2f8', 'accent' => '#4f46e5', 'dark' => '#3730a3',
            'font' => 'dejavusans', 'border' => 'solid', 'watermark' => 'IDEIA',
        ],
    ];

    $key = function_exists('mb_strtolower')
        ? mb_strtolower(trim($category), 'UTF-8')
        : strtolower(trim($category));
    if (isset($themes[$key])) {
        return $themes[$key];
    }

    $fallback = array_values($themes);
    return $fallback[abs((int)crc32($key)) % count($fallback)];
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Baixa imagens remotas uma única vez e transforma-as em data URI.
 * Assim o PDF não depende de allow_url_fopen durante a renderização do mPDF.
 */
function imageAsDataUri(string $url, array &$cache, int &$count, int $max = 6): ?string
{
    if ($count >= $max || !preg_match('#^https?://#i', $url)) {
        return null;
    }
    if (array_key_exists($url, $cache)) {
        return $cache[$url] ?: null;
    }

    $cache[$url] = false;
    $context = stream_context_create([
        'http' => ['timeout' => 8, 'follow_location' => 0, 'user_agent' => 'Idefy-PDF/1.0'],
        'https' => ['timeout' => 8, 'follow_location' => 0, 'user_agent' => 'Idefy-PDF/1.0'],
    ]);
    $data = @file_get_contents($url, false, $context, 0, 5 * 1024 * 1024 + 1);
    if ($data === false || strlen($data) > 5 * 1024 * 1024) {
        return null;
    }
    $info = @getimagesizefromstring($data);
    $mime = $info['mime'] ?? '';
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!$info || !in_array($mime, $allowed, true)) {
        return null;
    }

    $count++;
    $cache[$url] = 'data:' . $mime . ';base64,' . base64_encode($data);
    return $cache[$url];
}

/** Sanitiza HTML do utilizador, preservando estrutura e formatação útil. */
function cleanRichHtml(string $html): string
{
    $html = preg_replace('/<(script|style|iframe|object|embed|form)\b[^>]*>.*?<\/\1>/is', '', $html) ?? '';
    libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->loadHTML('<?xml encoding="UTF-8"><div id="pdf-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $root = $dom->getElementById('pdf-root');
    if (!$root) {
        return nl2br(e(strip_tags($html)));
    }

    $allowed = [
        'p','br','div','section','article','h1','h2','h3','h4','h5','strong','b','em','i',
        'u','s','blockquote','ul','ol','li','a','img','table','thead','tbody','tfoot','tr','th','td','hr',
    ];
    $out = '';
    $walk = function (DOMNode $node) use (&$walk, &$out, $allowed): void {
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $out .= e(preg_replace('/\s+/u', ' ', $child->nodeValue ?? '') ?? '');
                continue;
            }
            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }
            $tag = strtolower($child->nodeName);
            if (!in_array($tag, $allowed, true)) {
                $walk($child);
                continue;
            }
            if ($tag === 'img') {
                $src = trim($child->getAttribute('src'));
                if (preg_match('#^https?://#i', $src)) {
                    $out .= '<img data-pdf-src="' . e($src) . '" alt="' . e($child->getAttribute('alt')) . '">';
                }
                continue;
            }
            if ($tag === 'a') {
                $href = trim($child->getAttribute('href'));
                $href = preg_match('#^https?://#i', $href) ? $href : '#';
                $out .= '<a href="' . e($href) . '">';
                $walk($child);
                $out .= '</a>';
                continue;
            }
            $out .= '<' . $tag . '>';
            $walk($child);
            $out .= '</' . $tag . '>';
        }
    };
    $walk($root);
    return $out;
}

function replaceRemoteImages(string $html, array &$cache, int &$count): string
{
    return preg_replace_callback('/<img\s+data-pdf-src="([^"]*)"[^>]*>/i', function ($m) use (&$cache, &$count) {
        $src = imageAsDataUri(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'), $cache, $count);
        return $src ? '<img src="' . e($src) . '" alt="imagem">' : '';
    }, $html) ?? '';
}

$theme = getPdfTheme((string)($idea['category'] ?? 'geral'));
$imageCache = [];
$imageCount = 0;
$bodyHtml = replaceRemoteImages(cleanRichHtml((string)$idea['content']), $imageCache, $imageCount);

// Mantém a mesma paleta em todos os elementos, incluindo listas, tabelas e citações.
$css = <<<CSS
@page {
  sheet-size: A4;
  margin: 27mm 21mm 23mm 21mm;
  background-color: {$theme['bg']};
}
body {
  font-family: {$theme['font']};
  color: #202124;
  font-size: 10.8pt;
  line-height: 1.55;
  background-color: {$theme['bg']};
}
.page-frame { position: fixed; top: 8mm; left: 8mm; right: 8mm; bottom: 8mm; border: 0.55mm solid {$theme['accent']}; }
.page-frame.double { border: 0.75mm double {$theme['accent']}; }
.page-frame.dashed { border: 0.55mm dashed {$theme['accent']}; }
.header-title { color: {$theme['accent']}; font-size: 19pt; font-weight: bold; text-align: center; letter-spacing: .2mm; }
.header-subtitle { color: #6b7280; font-size: 8.5pt; text-align: center; margin-top: 1mm; }
h1,h2,h3,h4,h5 { color: {$theme['dark']}; page-break-after: avoid; line-height: 1.25; }
h1 { font-size: 17pt; border-bottom: 0.35mm solid {$theme['accent']}; padding-bottom: 2mm; }
h2 { font-size: 14pt; margin-top: 7mm; }
h3 { font-size: 12pt; margin-top: 5mm; }
p { margin: 0 0 3.5mm 0; text-align: justify; }
blockquote { border-left: 1.5mm solid {$theme['accent']}; background-color: #ffffffaa; padding: 3mm 4mm; margin: 4mm 0; color: #4b5563; font-style: italic; }
ul,ol { margin-top: 2mm; margin-bottom: 4mm; padding-left: 8mm; }
li { margin-bottom: 1.5mm; }
li::marker { color: {$theme['accent']}; font-weight: bold; }
table { width: 100%; border-collapse: collapse; margin: 4mm 0; page-break-inside: avoid; }
th { background-color: {$theme['accent']}; color: #ffffff; font-weight: bold; }
th,td { border: 0.25mm solid {$theme['accent']}; padding: 2mm; vertical-align: top; }
img { max-width: 100%; height: auto; margin: 3mm auto; }
hr { border: 0; border-top: 0.3mm solid {$theme['accent']}; margin: 5mm 0; }
a { color: {$theme['dark']}; text-decoration: underline; }
.meta { color: #6b7280; font-size: 8.5pt; text-align: center; margin-top: 7mm; }
CSS;

try {
    $tempDir = sys_get_temp_dir() . '/idefy-mpdf';
    if (!is_dir($tempDir)) {
        @mkdir($tempDir, 0775, true);
    }
    $mpdf = new Mpdf([
        'mode' => 'utf-8', 'format' => 'A4', 'tempDir' => $tempDir,
        'margin_left' => 21, 'margin_right' => 21, 'margin_top' => 27, 'margin_bottom' => 23,
        'margin_header' => 8, 'margin_footer' => 8, 'default_font' => $theme['font'],
        'autoScriptToLang' => true, 'autoLangToFont' => true, 'showImageErrors' => false,
    ]);
    $mpdf->SetTitle((string)$idea['title']);
    $mpdf->SetAuthor('Eureka Labs - Idefy');
    $mpdf->SetSubject('Ideia gerada no Idefy');
    $mpdf->SetKeywords((string)($idea['category'] ?? 'ideia') . ', Idefy, Eureka Labs');
    $mpdf->SetWatermarkText($theme['watermark'], 0.055);
    $mpdf->showWatermarkText = true;
    $mpdf->SetHTMLHeader('<div class="header-title">Eureka Labs · Idefy</div><div class="header-subtitle">Gerador de Ideias</div>');
    $mpdf->SetHTMLFooter('<div style="text-align:center;color:' . $theme['accent'] . ';font-size:8.5pt">Página {PAGENO} de {nbpg} · © 2026 Eureka Labs</div>');
    $mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);
    $frameClass = 'page-frame ' . $theme['border'];
    $document = '<div class="' . e($frameClass) . '"></div>';
    $document .= '<h1>' . e((string)$idea['title']) . '</h1>';
    $document .= $bodyHtml !== '' ? $bodyHtml : '<p>Sem conteúdo disponível.</p>';
    $document .= '<div class="meta">Gerado em ' . e(date('d/m/Y H:i')) . '</div>';
    $mpdf->WriteHTML($document, \Mpdf\HTMLParserMode::HTML_BODY);

    try {
        $stmt = $conn->prepare('UPDATE ideas SET pdf_generated = TRUE, pdf_generated_at = NOW() WHERE id = ? AND user_id = ?');
        $stmt->execute([$ideaId, $userId]);
    } catch (Throwable $e) {
        error_log('Erro generate-pdf (marcar pdf_generated): ' . $e->getMessage());
    }

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
