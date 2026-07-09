<?php
/**
 * GET INVENTORY - Lista as ideias do utilizador autenticado
 * EUREKA LABS
 */

require_once '../config.php'; // ✅ 1 nível acima (backend/api/ → backend/)
header('Content-Type: application/json');

$userId = requireAuth();

$page = max(1, intval($_GET['page'] ?? 1));
$limit = min(50, max(1, intval($_GET['limit'] ?? 10)));
$offset = ($page - 1) * $limit;
$category = trim($_GET['category'] ?? '');
$search = trim($_GET['search'] ?? '');

try {
    $conn = getDBConnection();

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

    $stmt = $conn->prepare("
        SELECT id, category, title, created_at
        FROM ideas
        WHERE $whereClause
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute(array_merge($params, [$limit, $offset]));
    $ideas = $stmt->fetchAll();

    $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM ideas WHERE $whereClause");
    $countStmt->execute($params);
    $total = (int) ($countStmt->fetch()['total'] ?? 0);

    echo json_encode([
        'success' => true,
        'ideas' => $ideas,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => (int) ceil($total / $limit)
        ]
    ]);
} catch (Exception $e) {
    error_log("Erro get-inventory: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao recuperar ideias.']);
}
?>
