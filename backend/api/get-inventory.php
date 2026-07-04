<?php
/**
 * GET INVENTORY - Lista todas as ideias do utilizador
 * EUREKA LABS ELITE
 */

require_once '../../config.php';
require_once '../../auth.php';

header('Content-Type: application/json');

// 1. Validar autenticação
$userId = requireAuth();

// 2. Pegar parâmetros de query
$page = max(1, intval($_GET['page'] ?? 1));
$limit = min(50, max(1, intval($_GET['limit'] ?? 10)));
$offset = ($page - 1) * $limit;
$category = trim($_GET['category'] ?? '');
$search = trim($_GET['search'] ?? '');

try {
    $conn = getDBConnection();
    
    // 3. Construir query com filtros opcionais
    $whereConditions = ['user_id = ?'];
    $params = [$userId];
    
    if (!empty($category)) {
        $whereConditions[] = 'category = ?';
        $params[] = $category;
    }
    
    if (!empty($search)) {
        $whereConditions[] = '(title ILIKE ? OR content ILIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // 4. Buscar ideias (com paginação)
    $stmt = $conn->prepare("
        SELECT id, category, title, created_at 
        FROM ideas 
        WHERE $whereClause 
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?
    ");
    
    $stmt->execute(array_merge($params, [$limit, $offset]));
    $ideas = $stmt->fetchAll();
    
    // 5. Contar total
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM ideas 
        WHERE $whereClause
    ");
    
    $countStmt->execute($params);
    $totalResult = $countStmt->fetch();
    $total = (int) $totalResult['total'];
    
    // 6. Retornar resultado
    echo json_encode([
        'success' => true,
        'ideas' => $ideas,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error in get-inventory: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao recuperar ideias.'
    ]);
}
?>
