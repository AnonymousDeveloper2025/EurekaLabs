<?php
/**
 * GET STATS - EUREKA LABS ELITE
 */

require_once '../config.php';

// Headers CORS já definidos no config.php

header('Content-Type: application/json');

$userId = $_GET['userId'] ?? null;

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Utilizador não especificado']);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Contar ideias
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM ideas WHERE user_id = ?");
    $stmt->execute([$userId]);
    $ideasCount = $stmt->fetch()['total'];

    echo json_encode([
        'success' => true,
        'ideas_count' => (int)$ideasCount,
        'pdfs_count' => 0 
    ]);

} catch (Exception $e) {
    error_log("Erro Stats: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao carregar estatísticas']);
}
?>
