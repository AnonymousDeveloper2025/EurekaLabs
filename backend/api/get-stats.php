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

header('Content-Type: application/json');

$userId = $_GET['userId'] ?? null;

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Utilizador não identificado']);
    exit;
}

$conn = getDBConnection();

// Contar ideias
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM ideas WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$ideasCount = $result->fetch_assoc()['count'];

// Contar PDFs gerados
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM ideas WHERE user_id = ? AND pdf_generated = 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$pdfsCount = $result->fetch_assoc()['count'];

echo json_encode([
    'success' => true,
    'ideas_count' => $ideasCount,
    'pdfs_count' => $pdfsCount
]);

$stmt->close();
$conn->close();
?>