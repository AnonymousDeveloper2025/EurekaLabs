<?php
/**
 * GET INVENTORY - EUREKA LABS ELITE
 */

require_once '../config.php';

header('Content-Type: application/json');

$userId = $_GET['userId'] ?? null;

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Utilizador não autenticado']);
    exit;
}

try {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("SELECT id, category, title, content, created_at FROM ideas WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    $ideas = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'inventory' => $ideas
    ]);

} catch (Exception $e) {
    error_log("Erro Inventory: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao carregar o inventário']);
}
?>
