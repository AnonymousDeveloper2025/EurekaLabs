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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$userId = $input['userId'] ?? null;

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Utilizador não identificado']);
    exit;
}

$conn = getDBConnection();

// Eliminar ideias do utilizador
$stmt = $conn->prepare("DELETE FROM ideas WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->close();

// Eliminar utilizador
$stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Conta eliminada com sucesso']);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao eliminar conta']);
}

$stmt->close();
$conn->close();
?>