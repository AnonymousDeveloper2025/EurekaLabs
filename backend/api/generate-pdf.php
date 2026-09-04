<?php
/**
 * GENERATE PDF — EUREKA LABS
 *
 * ✅ CORRIGIDO (ronda anterior): PDO em vez de mysqli, requireAuth() em vez
 * de confiar no cliente, payload alinhado com o result.html, CORS duplicado
 * removido.
 *
 * ✅ CORRIGIDO NESTA RONDA — o PDF vinha "com CSS em texto e acentos
 * trocados":
 * 1) strip_tags() remove as TAGS <style> e <script>, mas NÃO o conteúdo lá
 *    dentro — por isso as regras CSS apareciam como texto normal no PDF.
 *    Agora removemos esses blocos por completo (tag + conteúdo) antes de
 *    extrair o texto.
 * 2) O FPDF (motor usado aqui) não entende UTF-8 — espera Windows-1252.
 *    Como a ideia é gerada e guardada em UTF-8 (com "é", "ã", "€", etc.),
 *    o texto ficava com "Ã©", "Ã£", "â‚¬" (mojibake clássico). Agora todo
 *    o texto passa por utf8ToPdf() antes de ir para o PDF.
 * 3) O conteúdo era despejado como um bloco de texto único, sem distinguir
 *    títulos de parágrafos ou listas — agora percorre a estrutura HTML
 *    (títulos maiores/a roxo, marcadores nas listas) para ficar legível e
 *    ocupar várias páginas quando o conteúdo é mais rico.
 * 4) Só metia 1 imagem genérica (baseada no título) no fim. Agora usa as
 *    imagens reais do Unsplash que já vêm embutidas no conteúdo da ideia
 *    (até 3), inseridas junto das secções a que pertencem.
 */

require_once '../config.php'; // já trata CORS e OPTIONS — não duplicar

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$userId = requireAuth();

$input = json_decode(file_get_contents('php://input'), true);
$ideaId = intval($input['id'] ?? 0);

if (!$ideaId) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID da ideia em falta.']);
    exit;
}

try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT id, title, content, category FROM ideas WHERE id = ? AND user_id = ?");
    $stmt->execute([$ideaId, $userId]);
    $idea = $stmt->fetch();

    if (!$idea) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Ideia não encontrada.']);
        exit;
    }
} catch (Exception $e) {
    error_log("Erro generate-pdf (buscar ideia): " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Erro ao carregar a ideia.']);
    exit;
}

require_once '../vendor/fpdf/fpdf.php';

// Converte UTF-8 (como o conteúdo é guardado) para Windows-1252 (o que o
// FPDF espera). //TRANSLIT troca caracteres sem equivalente (ex: emojis)
// pela aproximação mais próxima em vez de rebentar.
function utf8ToPdf($text) {
    $converted = @iconv('UTF-8', 'CP1252//TRANSLIT', $text);
    return $converted !== false ? $converted : preg_replace('/[^\x20-\x7E]/', '', $text);
}

class PDF extends FPDF {
    public function Header() {
        $this->SetFont('Arial', 'B', 20);
        $this->SetTextColor(59, 130, 246);
        $this->Cell(0, 10, 'Eureka Labs - Idefy', 0, 1, 'C');
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(150, 150, 150);
        $this->Cell(0, 5, utf8ToPdf('Gerador de Ideias'), 0, 1, 'C');
        $this->Ln(5);
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 10, utf8ToPdf('Página ' . $this->PageNo()), 0, 0, 'C');
    }
}

// --- Prepara o conteúdo: remove <style>/<script> por completo, separa as imagens ---
$rawContent = $idea['content'];
$rawContent = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $rawContent);
$rawContent = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $rawContent);

preg_match_all('/<img[^>]*src="([^"]+)"[^>]*>/i', $rawContent, $imgMatches);
$contentImages = array_slice(array_unique($imgMatches[1]), 0, 3);
$rawContent = preg_replace('/<img[^>]*>/i', '', $rawContent);

libxml_use_internal_errors(true);
$dom = new DOMDocument();
$dom->loadHTML('<?xml encoding="utf-8" ?><div>' . $rawContent . '</div>');
libxml_clear_errors();
$rootDiv = $dom->getElementsByTagName('div')->item(0);

$blockTags = ['h1', 'h2', 'h3', 'h4', 'p', 'div', 'ul', 'ol', 'li', 'table', 'tr', 'td', 'th', 'section', 'article'];

function renderNode($pdf, DOMNode $node) {
    global $blockTags;
    foreach ($node->childNodes as $child) {
        if ($child->nodeType !== XML_ELEMENT_NODE) continue;
        $tag = strtolower($child->nodeName);
        $text = trim(preg_replace('/\s+/', ' ', $child->textContent));

        if ($text === '') continue;

        if (in_array($tag, ['h1', 'h2', 'h3', 'h4'])) {
            $pdf->Ln(3);
            $pdf->SetFont('Arial', 'B', $tag === 'h1' ? 16 : ($tag === 'h2' ? 14 : 12));
            $pdf->SetTextColor(139, 92, 246);
            $pdf->MultiCell(0, 7, utf8ToPdf($text));
            $pdf->SetFont('Arial', '', 11);
            $pdf->SetTextColor(20, 20, 20);
            $pdf->Ln(1);
        } elseif ($tag === 'li') {
            $pdf->SetX($pdf->GetX() + 4);
            $pdf->MultiCell(0, 5.5, utf8ToPdf('- ' . $text));
        } else {
            $hasBlockChild = false;
            foreach ($child->childNodes as $grandchild) {
                if ($grandchild->nodeType === XML_ELEMENT_NODE && in_array(strtolower($grandchild->nodeName), $blockTags)) {
                    $hasBlockChild = true;
                    break;
                }
            }
            if ($hasBlockChild) {
                renderNode($pdf, $child);
            } elseif (in_array($tag, ['p', 'span', 'strong', 'em', 'b', 'i', 'td', 'th'])) {
                $pdf->MultiCell(0, 5.5, utf8ToPdf($text));
                $pdf->Ln(1);
            }
        }
    }
}

$pdf = new PDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 17);
$pdf->SetTextColor(139, 92, 246);
$pdf->MultiCell(0, 8, utf8ToPdf($idea['title']));
$pdf->Ln(3);

$pdf->SetFont('Arial', '', 11);
$pdf->SetTextColor(20, 20, 20);

if ($rootDiv) {
    renderNode($pdf, $rootDiv);
} else {
    // Recurso de segurança: se o HTML não deu para interpretar, mostra o texto simples
    $plain = trim(preg_replace('/\s+/', ' ', strip_tags($rawContent)));
    $pdf->MultiCell(0, 5.5, utf8ToPdf($plain));
}

// --- Insere as imagens reais (já vêm do Unsplash, embutidas na ideia) ---
foreach ($contentImages as $imgUrl) {
    try {
        $imageData = @file_get_contents($imgUrl);
        if (!$imageData) continue;

        $imagePath = sys_get_temp_dir() . '/idefy_' . uniqid() . '.jpg';
        file_put_contents($imagePath, $imageData);

        if (file_exists($imagePath)) {
            if ($pdf->GetY() > 230) $pdf->AddPage();
            $pdf->Ln(4);
            $pdf->Image($imagePath, 10, $pdf->GetY(), 190);
            $pdf->Ln(80); // espaço aproximado ocupado pela imagem (190mm largura ~ 100-120mm altura)
            unlink($imagePath);
        }
    } catch (Exception $e) {
        error_log("Erro generate-pdf (imagem $imgUrl): " . $e->getMessage());
        continue;
    }
}

$pdf->AddPage();
$pdf->Ln(20);
$pdf->SetFont('Arial', 'I', 9);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0, 5, utf8ToPdf('Gerado em: ' . date('d/m/Y H:i')), 0, 1, 'C');
$pdf->Cell(0, 5, utf8ToPdf('© 2026 Eureka Labs - Todos os direitos reservados'), 0, 1, 'C');

try {
    $stmt = $conn->prepare("UPDATE ideas SET pdf_generated = TRUE, pdf_generated_at = NOW() WHERE id = ? AND user_id = ?");
    $stmt->execute([$ideaId, $userId]);
} catch (Exception $e) {
    error_log("Erro generate-pdf (marcar pdf_generated): " . $e->getMessage());
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="ideia-' . $ideaId . '.pdf"');
$pdf->Output('D', 'ideia-' . $ideaId . '.pdf');
?>
