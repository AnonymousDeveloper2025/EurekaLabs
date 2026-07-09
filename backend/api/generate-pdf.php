<?php
/**
 * GENERATE PDF — EUREKA LABS (VERSÃO DEFINITIVA)
 * Gera um PDF legível a partir de uma ideia já guardada na base de dados,
 * usando FPDF. Esta é a única versão a usar — substitui tanto
 * generate-pdf_FINAL.php como generate-pdf_CORRIGIDO.php (ambos podem ser
 * apagados da tua pasta de downloads).
 */

require_once '../config.php'; // já trata CORS e o pedido OPTIONS (preflight) — não duplicar aqui

// ✅ Autenticação real via JWT — nunca confiar num userId enviado pelo cliente
$userId = requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$ideaId = intval($input['id'] ?? 0);

if (!$ideaId) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID da ideia em falta.']);
    exit;
}

// ✅ Busca a ideia REAL da base de dados — nunca confiar em conteúdo vindo do
// cliente. Isto também confirma que a ideia pertence a este utilizador.
try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT id, title, content, category FROM ideas WHERE id = ? AND user_id = ?");
    $stmt->execute([$ideaId, $userId]);
    $idea = $stmt->fetch();
} catch (Exception $e) {
    error_log("Erro generate-pdf (fetch idea): " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Erro ao obter a ideia.']);
    exit;
}

if (!$idea) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Ideia não encontrada.']);
    exit;
}

/**
 * Converte o HTML da ideia (com <style>, botões onclick, badges, etc.) em
 * texto limpo e legível. O FPDF não interpreta HTML — escrever o HTML em
 * bruto mostraria as tags, o CSS e os atributos onclick como texto visível.
 * Títulos (h1-h3) ficam marcados com "== Título ==" para se destacarem no
 * corpo do texto, já que o FPDF usa uma única fonte fixa no corpo.
 */
function eureka_html_para_texto($html) {
    $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html);
    $html = preg_replace('/<button\b[^>]*>.*?<\/button>/is', '', $html);

    // Marca títulos com == destaque == antes de remover as tags
    $html = preg_replace('/<(h[1-3])[^>]*>(.*?)<\/\1>/is', "\n\n== $2 ==\n", $html);
    $html = preg_replace('/<\/(p|div|li)>/i', "</$1>\n", $html);
    $html = preg_replace('/<(li)[^>]*>/i', "\n- ", $html);
    $html = preg_replace('/<br\s*\/?>/i', "\n", $html);

    $text = strip_tags($html);
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    $text = preg_replace('/[ \t]+/', ' ', $text);
    $text = preg_replace('/\n{3,}/', "\n\n", $text);
    return trim($text);
}

/**
 * O FPDF standard só suporta Windows-1252 (não Unicode/emoji). Removemos
 * emojis (senão apareceriam como "?" ou caracteres estranhos) e depois
 * convertemos acentos portugueses correctamente.
 */
function eureka_preparar_para_fpdf($text) {
    $text = preg_replace('/[\x{1F000}-\x{1FFFF}\x{2600}-\x{27BF}\x{2190}-\x{21FF}\x{2B00}-\x{2BFF}]/u', '', $text);
    return @iconv('UTF-8', 'windows-1252//TRANSLIT', $text) ?: $text;
}

$textoLimpo = eureka_html_para_texto($idea['content']);

require_once '../vendor/fpdf/fpdf.php';

class PDF extends FPDF {
    function Header() {
        $this->SetFont('Arial', 'B', 20);
        $this->SetTextColor(59, 130, 246);
        $this->Cell(0, 10, eureka_preparar_para_fpdf('Eureka Labs - Idefy'), 0, 1, 'C');
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(150, 150, 150);
        $this->Cell(0, 5, eureka_preparar_para_fpdf('Gerado pelo assistente IDEFY'), 0, 1, 'C');
        $this->Ln(5);
    }
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();

$pdf->SetFont('Arial', 'B', 16);
$pdf->SetTextColor(139, 92, 246);
$pdf->MultiCell(0, 8, eureka_preparar_para_fpdf($idea['title']));
$pdf->Ln(3);

// ✅ Imagem ilustrativa: tenta Unsplash primeiro (se UNSPLASH_API_KEY estiver
// configurada); se falhar ou não estiver configurada, usa Picsum como
// fallback fiável — assim há sempre uma imagem, nunca falha.
$imageUrl = getUnsplashImage($idea['title']);
if (!$imageUrl) {
    $seed = preg_replace('/[^a-zA-Z0-9]/', '', $idea['category'] ?? 'ideas') ?: 'ideas';
    $imageUrl = "https://picsum.photos/seed/{$seed}/900/500";
}

$imagePath = sys_get_temp_dir() . '/idefy_' . uniqid() . '.jpg';
$imgData = @file_get_contents($imageUrl);
if ($imgData !== false) {
    file_put_contents($imagePath, $imgData);
    if (file_exists($imagePath)) {
        $pdf->Image($imagePath, 10, $pdf->GetY(), 190);
        $pdf->Ln(75);
        @unlink($imagePath);
    }
}

$pdf->SetFont('Arial', '', 11);
$pdf->SetTextColor(20, 20, 20);
$pdf->MultiCell(0, 6, eureka_preparar_para_fpdf($textoLimpo));

$pdf->Ln(8);
$pdf->SetFont('Arial', 'I', 9);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0, 5, eureka_preparar_para_fpdf('Gerado em: ' . date('d/m/Y H:i')), 0, 1, 'C');
$pdf->Cell(0, 5, '(c) ' . date('Y') . ' Eureka Labs - Todos os direitos reservados', 0, 1, 'C');

// ✅ Regista que o PDF foi gerado — nunca bloqueia o download se as colunas
// ainda não existirem (ver nota SQL no fim do ficheiro para as criar).
try {
    $stmt = $conn->prepare("UPDATE ideas SET pdf_generated = TRUE, pdf_generated_at = NOW() WHERE id = ? AND user_id = ?");
    $stmt->execute([$ideaId, $userId]);
} catch (Exception $e) {
    error_log("Aviso (não crítico): não foi possível marcar pdf_generated — " . $e->getMessage());
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="ideia-' . $ideaId . '.pdf"');
$pdf->Output('D', 'ideia-' . $ideaId . '.pdf');
exit;

/*
NOTA OPCIONAL: para o campo "pdf_generated" funcionar de verdade (em vez de
cair sempre no catch acima em silêncio), corre este SQL uma vez — podes
colá-lo directamente no backend/setup.php ou correr manualmente na BD:

ALTER TABLE ideas ADD COLUMN IF NOT EXISTS pdf_generated BOOLEAN DEFAULT FALSE;
ALTER TABLE ideas ADD COLUMN IF NOT EXISTS pdf_generated_at TIMESTAMP;
*/
?>
