<?php
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$userId = $input['userId'] ?? null;
$newPassword = $input['newPassword'] ?? '';

if (!$userId || empty($newPassword)) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

if (strlen($newPassword) < 6) {
    echo json_encode(['success' => false, 'message' => 'Palavra-passe deve ter pelo menos 6 caracteres']);
    exit;
}

$conn = getDBConnection();

$hashedPassword = hashPassword($newPassword);
$stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
$stmt->bind_param("si", $hashedPassword, $userId);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Palavra-passe alterada com sucesso']);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao alterar palavra-passe']);
}

$stmt->close();
$conn->close();
?>
