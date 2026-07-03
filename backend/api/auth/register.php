<?php
/**
 * REGISTER - EUREKA LABS ELITE
 */

require_once '../../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$name = trim($input['name'] ?? '');
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if (empty($name) || empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Preenche todos os campos obrigatórios.']);
    exit;
}

try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Este email já está registado.']);
        exit;
    }

    $hashedPassword = hashPassword($password);
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, created_at) VALUES (?, ?, ?, NOW())");
    
    if ($stmt->execute([$name, $email, $hashedPassword])) {
        $userId = $conn->lastInsertId();
        $token = generateToken($userId);
        
        echo json_encode([
            'success' => true,
            'message' => 'Bem-vindo ao Eureka Labs!',
            'user' => ['id' => $userId, 'name' => $name, 'email' => $email],
            'token' => $token
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao criar conta.']);
    }
} catch (Exception $e) {
    error_log("Erro Register: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro no servidor.']);
}
?>
