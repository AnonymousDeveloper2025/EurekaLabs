<?php
/**
 * GENERATE PDF — EUREKA LABS
 *
 * ✅ CORRIGIDO: Fundo colorido em todas as páginas + lMargin
 */

require_once '../config.php'; // já trata CORS e OPTIONS — não duplicar

if ($_SERVER['REQUEST_METHOD']!== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$userId = requireAuth();

$input = json_decode(file_get_contents('php://input'), true);
$ideaId = intval($input['id']?? 0);

if (!$ideaId) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID da ideia em falta.']);
    exit;
}

try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT id, title, content, category FROM ideas WHERE id =? AND user_id =?");
    $stmt->execute([$ideaId, $userId]);
    $idea = $stmt->fetch();

    if (!$idea) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Ideia não encontrada.']);
        exit;
    }
} catch (Exception $e) {
    error_log("Erro generate-pdf (buscar ideia): ". $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Erro ao carregar a ideia.']);
    exit;
}

require_once '../vendor/fpdf/fpdf.php';

function utf8ToPdf($text) {
    $converted = @iconv('UTF-8', 'CP1252//TRANSLIT', $text);
    return $converted!== false? $converted : preg_replace('/[^\x20-\x7E]/', '', $text);
}

function getPdfTheme($category) {
    $themes = [
        'tecnologia' => ['bg' => [233, 240, 250], 'accent' => [37, 99, 235], 'font' => 'Courier', 'border' => 'solid'],
        'negocio' => ['bg' => [231, 240, 237], 'accent' => [13, 118, 105], 'font' => 'Arial', 'border' => 'double'],
        'criatividade' => ['bg' => [250, 235, 245], 'accent' => [219, 39, 119], 'font' => 'Times', 'border' => 'dashed'],
        'aventura' => ['bg' => [237, 243, 227], 'accent' => [77, 124, 15], 'font' => 'Arial', 'border' => 'solid'],
        'culinaria' => ['bg' => [253, 240, 220], 'accent' => [194, 108, 8], 'font' => 'Times', 'border' => 'double'],
        'bem-estar' => ['bg' => [239, 236, 250], 'accent' => [109, 40, 217], 'font' => 'Times', 'border' => 'dashed'],
        'viagem' => ['bg' => [224, 242, 246], 'accent' => [8, 132, 155], 'font' => 'Arial', 'border' => 'solid'],
        'geral' => ['bg' => [235, 236, 245], 'accent' => [79, 70, 229], 'font' => 'Arial', 'border' => 'solid'],
    ];
    $key = strtolower(trim($category));
    if (isset($themes[$key])) return $themes[$key];

    $fallback = array_values($themes);
    return $fallback[crc32($key) % count($fallback)];
}

class ThemedPDF extends FPDF {
    public $theme;
    public $contentMargin = 20; // Margem pública
    private $margin = 15;

    function __construct() {
        parent::__construct();
        $this->SetAutoPageBreak(true, 24);
    }

    public function Header() {
        // PINTA O FUNDO EM TODA PÁGINA
        $this->SetFillColor($this->theme['bg'][0], $this->theme['bg'][1], $this->theme['bg'][2]);
        $this->Rect(0, 0, $this->GetPageWidth(), $this->GetPageHeight(), 'F');

        [$r, $g, $b] = $this->theme['accent'];
        $this->SetDrawColor($r, $g, $b);
        $m = $this->margin - 5;
        if ($this->theme['border'] === 'double') {
            $this->SetLineWidth(0.9);
            $this->Rect($m, $m, 210 - 2 * $m, 297 - 2 * $m);
            $this->SetLineWidth(0.3);
            $this->Rect($m + 2.2, $m + 2.2, 210 - 2 * ($m + 2.2), 297 - 2 * ($m + 2.2));
        } elseif ($this->theme['border'] === 'dashed') {
            $this->SetLineWidth(0.6);
            $this->dashedRect($m, $m, 210 - 2 * $m, 297 - 2 * $m, 3, 2);
        } else {
            $this->SetLineWidth(0.7);
            $this->Rect($m, $m, 210 - 2 * $m, 297 - 2 * $m);
        }
        $this->SetLineWidth(0.2);

        $this->SetY($this->margin + 2);
        $this->SetFont($this->theme['font'], 'B', 19);
        $this->SetTextColor($r, $g, $b);
        $this->Cell(0, 10, 'Eureka Labs - Idefy', 0, 1, 'C');
        $this->SetFont($this->theme['font'], 'I', 9);
        $this->SetTextColor(90, 90, 90);
        $this->Cell(0, 5, utf8ToPdf('Gerador de Ideias'), 0, 1, 'C');
        $this->Ln(4);
    }

    public function Footer() {
        $this->SetY(-20);
        [$r, $g, $b] = $this->theme['accent'];
        $this->SetFont($this->theme['font'], 'I', 8);
        $this->SetTextColor($r, $g, $b);
        $this->Cell(0, 10, utf8ToPdf('Página '. $this->PageNo()), 0, 0, 'C');
    }

    public function dashedRect($x, $y, $w, $h, $dash, $gap) {
        $this->dashedLine($x, $y, $x + $w, $y, $dash, $gap);
        $this->dashedLine($x + $w, $y, $x + $w, $y + $h, $dash, $gap);
        $this->dashedLine($x + $w, $y + $h, $x, $y + $h, $dash, $gap);
        $this->dashedLine($x, $y + $h, $x, $y, $dash, $gap);
    }

    public function dashedLine($x1, $y1, $x2, $y2, $dash, $gap) {
        $dist = sqrt(($x2 - $x1) ** 2 + ($y2 - $y1) ** 2);
        if ($dist < 0.01) return;
        $steps = (int) floor($dist / ($dash + $gap));
        $dx = ($x2 - $x1) / $dist;
        $dy = ($y2 - $y1) / $dist;
        $x = $x1; $y = $y1;
        for ($i = 0; $i < $steps; $i++) {
            $xEnd = $x + $dx * $dash;
            $yEnd = $y + $dy * $dash;
            $this->Line($x, $y, $xEnd, $yEnd);
            $x = $xEnd + $dx * $gap;
            $y = $yEnd + $dy * $gap;
        }
    }
}

$theme = getPdfTheme($idea['category']?? 'geral');

$rawContent = $idea['content'];
$rawContent = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $rawContent);
$rawContent = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $rawContent);

libxml_use_internal_errors(true);
$dom = new DOMDocument();
$dom->loadHTML('<?xml encoding="utf-8"?><div>'. $rawContent. '</div>');
libxml_clear_errors();
$rootDiv = $dom->getElementsByTagName('div')->item(0);

$blockTags = ['h1', 'h2', 'h3', 'h4', 'p', 'div', 'ul', 'ol', 'li', 'table', 'tr', 'td', 'th', 'section', 'article', 'blockquote'];
$imageCache = [];
$imagesInserted = 0;
$MAX_IMAGES = 6;

function insertPdfImage($pdf, $url, &$imageCache, &$imagesInserted, $maxImages) {
    if ($imagesInserted >= $maxImages) return;
    try {
        if (!isset($imageCache[$url])) {
            $data = @file_get_contents($url);
            if (!$data) { $imageCache[$url] = false; return; }
            $path = sys_get_temp_dir(). '/idefy_'. md5($url). '.jpg';
            file_put_contents($path, $data);
            $imageCache[$url] = $path;
        }
        $path = $imageCache[$url];
        if (!$path ||!file_exists($path)) return;

        $usableWidth = $pdf->GetPageWidth() - 2 * $pdf->contentMargin;
        $displayHeight = $usableWidth * 0.6;
        $dims = @getimagesize($path);
        if ($dims && $dims[0] > 0) {
            $displayHeight = $usableWidth * ($dims[1] / $dims[0]);
        }
        if ($pdf->GetY() + $displayHeight > 270) $pdf->AddPage();
        $pdf->Ln(3);
        $pdf->Image($path, ($pdf->GetPageWidth() - $usableWidth) / 2, $pdf->GetY(), $usableWidth, $displayHeight);
        $pdf->SetY($pdf->GetY() + $displayHeight + 4);
        $imagesInserted++;
    } catch (Exception $e) {
        error_log("Erro generate-pdf (imagem $url): ". $e->getMessage());
    }
}

function renderNode($pdf, DOMNode $node, $theme, &$imageCache, &$imagesInserted, $maxImages) {
    global $blockTags;
    foreach ($node->childNodes as $child) {
        if ($child->nodeType!== XML_ELEMENT_NODE) continue;
        $tag = strtolower($child->nodeName);

        if ($tag === 'img') {
            $src = $child->getAttribute('src');
            if ($src) insertPdfImage($pdf, $src, $imageCache, $imagesInserted, $maxImages);
            continue;
        }

        $text = trim(preg_replace('/\s+/', ' ', $child->textContent));
        if ($text === '') continue;

        [$r, $g, $b] = $theme['accent'];

        if (in_array($tag, ['h1', 'h2', 'h3', 'h4'])) {
            $pdf->Ln(4);
            $pdf->SetFillColor($r, $g, $b);
            $pdf->Rect($pdf->GetX(), $pdf->GetY() + 2, 3.2, 3.2, 'F');
            $pdf->SetX($pdf->GetX() + 6);
            $pdf->SetFont($theme['font'], 'B', $tag === 'h1'? 16 : ($tag === 'h2'? 14 : 12));
            $pdf->SetTextColor($r, $g, $b);
            $pdf->MultiCell(0, 7, utf8ToPdf($text));
            $pdf->SetFont($theme['font'], '', 11);
            $pdf->SetTextColor(30, 30, 30);
            $pdf->Ln(1);
        } elseif ($tag === 'blockquote' || $tag === 'q') {
            $startY = $pdf->GetY();
            $pdf->SetX($pdf->contentMargin + 8);
            $pdf->SetFont($theme['font'], 'I', 11);
            $pdf->SetTextColor(70, 70, 70);
            $pdf->MultiCell(0, 6, utf8ToPdf('" '. $text. ' "'));
            $endY = $pdf->GetY();
            $pdf->SetDrawColor($r, $g, $b);
            $pdf->SetLineWidth(1);
            $pdf->Line($pdf->contentMargin + 4, $startY + 1, $pdf->contentMargin + 4, $endY - 1);
            $pdf->SetLineWidth(0.2);
            $pdf->SetFont($theme['font'], '', 11);
            $pdf->SetTextColor(30, 30, 30);
            $pdf->Ln(2);
        } elseif ($tag === 'li') {
            $pdf->SetFillColor($r, $g, $b);
            $bx = $pdf->GetX() + 2;
            $by = $pdf->GetY() + 2.3;
            $pdf->Rect($bx, $by, 1.8, 1.8, 'F');
            $pdf->SetX($pdf->GetX() + 7);
            $pdf->MultiCell(0, 5.5, utf8ToPdf($text));
        } else {
            $hasBlockChild = false;
            foreach ($child->childNodes as $grandchild) {
                if ($grandchild->nodeType === XML_ELEMENT_NODE && (in_array(strtolower($grandchild->nodeName), $blockTags) || strtolower($grandchild->nodeName) === 'img')) {
                    $hasBlockChild = true;
                    break;
                }
            }
            if ($hasBlockChild) {
                renderNode($pdf, $child, $theme, $imageCache, $imagesInserted, $maxImages);
            } elseif (in_array($tag, ['p', 'span', 'strong', 'em', 'b', 'i', 'td', 'th'])) {
                $pdf->MultiCell(0, 5.5, utf8ToPdf($text));
                $pdf->Ln(1);
            }
        }
    }
}

$pdf = new ThemedPDF();
$pdf->theme = $theme;
$pdf->SetMargins(20, 10, 20);
$pdf->SetAutoPageBreak(true, 24);
$pdf->AddPage();

// Define a cor de texto e fill padrão pra não vir branco
$pdf->SetFillColor($theme['bg'][0], $theme['bg'][1], $theme['bg'][2]);
$pdf->SetTextColor(30, 30, 30);

[$ra, $ga, $ba] = $theme['accent'];
$pdf->SetFont($theme['font'], 'B', 18);
$pdf->SetTextColor($ra, $ga, $ba);
$pdf->MultiCell(0, 8, utf8ToPdf($idea['title']));
$pdf->Ln(3);

$pdf->SetFont($theme['font'], '', 11);
$pdf->SetTextColor(30, 30, 30);

if ($rootDiv) {
    renderNode($pdf, $rootDiv, $theme, $imageCache, $imagesInserted, $MAX_IMAGES);
} else {
    $plain = trim(preg_replace('/\s+/', ' ', strip_tags($rawContent)));
    $pdf->MultiCell(0, 5.5, utf8ToPdf($plain));
}

foreach ($imageCache as $path) {
    if ($path && file_exists($path)) @unlink($path);
}

$pdf->Ln(8);
$pdf->SetFont($theme['font'], 'I', 9);
$pdf->SetTextColor(90, 90, 90);
$pdf->Cell(0, 5, utf8ToPdf('Gerado em: '. date('d/m/Y H:i')), 0, 1, 'C');
$pdf->Cell(0, 5, utf8ToPdf('© 2026 Eureka Labs - Todos os direitos reservados'), 0, 1, 'C');

try {
    $stmt = $conn->prepare("UPDATE ideas SET pdf_generated = TRUE, pdf_generated_at = NOW() WHERE id =? AND user_id =?");
    $stmt->execute([$ideaId, $userId]);
} catch (Exception $e) {
    error_log("Erro generate-pdf (marcar pdf_generated): ". $e->getMessage());
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="ideia-'. $ideaId. '.pdf"');
$pdf->Output('D', 'ideia-'. $ideaId. '.pdf');
?>
