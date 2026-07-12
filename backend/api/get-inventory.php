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

$stmt = $conn->prepare("SELECT id, title, content, category, created_at, pdf_generated FROM ideas WHERE user_id = ? AND saved = 1 ORDER BY created_at DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

$ideas = [];
while ($row = $result->fetch_assoc()) {
    $ideas[] = $row;
}

echo json_encode([
    'success' => true,
    'ideas' => $ideas
]);

$stmt->close();
$conn->close();
?>