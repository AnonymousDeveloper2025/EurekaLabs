<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}
require_once '../config.php';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="ideia-' . time() . '.pdf"');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$userId = $input['userId'] ?? null;
$idea = $input['idea'] ?? null;

if (!$userId || !$idea) {
    http_response_code(400);
    exit;
}

// Incluir FPDF
require_once '../vendor/fpdf/fpdf.php';

class PDF extends FPDF {
    public function Header() {
        $this->SetFont('Arial', 'B', 20);
        $this->SetTextColor(59, 130, 246); // Azul
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
$pdf->SetTextColor(139, 92, 246); // Roxo
$pdf->Cell(0, 10, $idea['title'], 0, 1, 'L');

$pdf->SetFont('Arial', '', 11);
$pdf->SetTextColor(0, 0, 0);
$pdf->MultiCell(0, 5, $idea['content']);

// Tentar obter imagem do Unsplash
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

// Footer
$pdf->Ln(10);
$pdf->SetFont('Arial', 'I', 9);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0, 5, 'Gerado em: ' . date('d/m/Y H:i'), 0, 1, 'C');
$pdf->Cell(0, 5, '© 2026 ∀nonymous Developer - Todos direitos reservados', 0, 1, 'C');

// Guardar na base de dados que foi gerado um PDF
$conn = getDBConnection();
$stmt = $conn->prepare("UPDATE ideas SET pdf_generated = 1, pdf_generated_at = NOW() WHERE id = ? AND user_id = ?");
$ideaId = $idea['id'];
$stmt->bind_param("ii", $ideaId, $userId);
$stmt->execute();
$stmt->close();
$conn->close();

// Output PDF
$pdf->Output('D', 'ideia-' . time() . '.pdf');
?>