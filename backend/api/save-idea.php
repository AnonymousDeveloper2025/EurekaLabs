<?php
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$userId = $input['userId'] ?? null;
$idea = $input['idea'] ?? null;

if (!$userId || !$idea) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

$conn = getDBConnection();

// Verificar se a ideia já existe
$ideaId = $idea['id'] ?? null;

if ($ideaId) {
    // Atualizar
    $stmt = $conn->prepare("UPDATE ideas SET title = ?, content = ?, saved = 1 WHERE id = ? AND user_id = ?");
    $title = $idea['title'];
    $content = $idea['content'];
    $stmt->bind_param("ssii", $title, $content, $ideaId, $userId);
} else {
    // Criar nova
    $stmt = $conn->prepare("INSERT INTO ideas (user_id, title, content, category, saved, created_at) VALUES (?, ?, ?, ?, 1, NOW())");
    $title = $idea['title'];
    $content = $idea['content'];
    $category = $idea['category'] ?? 'geral';
    $stmt->bind_param("isss", $userId, $title, $content, $category);
}

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Ideia guardada com sucesso'
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao guardar ideia']);
}

$stmt->close();
$conn->close();
?>
