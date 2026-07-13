<?php
/**
 * GENERATE PDF — EUREKA LABS
 * ✅ CORRIGIDO:
 * 1) Usava a API do mysqli (bind_param/close) sobre uma ligação PDO —
 *    Fatal Error sempre que era chamado, depois de já ter começado a
 *    enviar o PDF (por isso o download vinha corrompido/vazio).
 * 2) Confiava no "userId" enviado pelo cliente — qualquer pessoa podia
 *    gerar/marcar PDFs de outro utilizador. Agora usa requireAuth().
 * 3) O result.html manda só { id }, não { userId, idea } — o endpoint
 *    agora vai buscar a ideia à base de dados pelo id (mais seguro: não
 *    dá para pedir um PDF de conteúdo inventado no próprio pedido).
 * 4) Removidos os cabeçalhos CORS duplicados (o config.php já trata
 *    disso) — a combinação "Allow-Origin: *" + "Allow-Credentials: true"
 *    que aqui estava é proibida pelos browsers e bloqueava o download
 *    sempre que a origem não era uma das reconhecidas.
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

// Incluir FPDF
require_once '../vendor/fpdf/fpdf.php';

class PDF extends FPDF {
    public function Header() {
        $this->SetFont('Arial', 'B', 20);
        $this->SetTextColor(59, 130, 246);
        $this->Cell(0, 10, 'Eureka Labs - Idefy', 0, 1, 'C');
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(150, 150, 150);
        $this->Cell(0, 5, 'Gerador de Ideias para as Ferias', 0, 1, 'C');
        $this->Ln(5);
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 10, 'Pagina ' . $this->PageNo(), 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);
$pdf->SetTextColor(139, 92, 246);
$pdf->Cell(0, 10, $idea['title'], 0, 1, 'L');

$pdf->SetFont('Arial', '', 11);
$pdf->SetTextColor(0, 0, 0);
// O conteúdo é guardado em HTML — remove as tags para o PDF (texto simples)
$plainContent = trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode($idea['content']))));
$pdf->MultiCell(0, 5, $plainContent);

$imageUrl = getUnsplashImage($idea['title']);
if ($imageUrl) {
    $pdf->Ln(5);
    $imagePath = sys_get_temp_dir() . '/idefy_' . time() . '.jpg';
    file_put_contents($imagePath, file_get_contents($imageUrl));

    if (file_exists($imagePath)) {
        $pdf->Image($imagePath, 10, $pdf->GetY(), 190);
        unlink($imagePath);
    }
}

$pdf->Ln(10);
$pdf->SetFont('Arial', 'I', 9);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0, 5, 'Gerado em: ' . date('d/m/Y H:i'), 0, 1, 'C');
$pdf->Cell(0, 5, '© 2026 Anonymous Developer - Todos direitos reservados', 0, 1, 'C');

// Marca na base de dados que o PDF foi gerado (PDO, não mysqli)
try {
    $stmt = $conn->prepare("UPDATE ideas SET pdf_generated = TRUE, pdf_generated_at = NOW() WHERE id = ? AND user_id = ?");
    $stmt->execute([$ideaId, $userId]);
} catch (Exception $e) {
    error_log("Erro generate-pdf (marcar pdf_generated): " . $e->getMessage());
    // não bloqueia o download por causa disto
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="ideia-' . $ideaId . '.pdf"');
$pdf->Output('D', 'ideia-' . $ideaId . '.pdf');
?>
