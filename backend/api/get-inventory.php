<?php
/**
 * GET INVENTORY - EUREKA LABS ELITE
 */

require_once '../config.php';

header('Content-Type: application/json');

$userId = $_GET['userId'] ?? null;

if (!$userId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Utilizador não identificado']);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Buscar ideias salvas
    $stmt = $conn->prepare("SELECT id, title, content, category, created_at FROM ideas WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    $ideas = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'inventory' => $ideas
    ]);

} catch (Exception $e) {
    error_log("Erro Inventory: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao carregar o inventário']);
}
?>
