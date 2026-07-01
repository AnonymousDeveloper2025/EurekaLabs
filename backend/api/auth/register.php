<?php
require_once '../../config.php';

// Headers já estão no config.php, mas reforçamos se necessário
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$name = trim($input['name'] ?? '');
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

// Validações Essenciais
if (empty($name) || empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Todos os campos são obrigatórios']);
    exit;
}

if (!isValidEmail($email)) {
    echo json_encode(['success' => false, 'message' => 'Email inválido']);
    exit;
}

if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Palavra-passe deve ter pelo menos 6 caracteres']);
    exit;
}

try {
    $conn = getDBConnection();

    // Verificar se o email já existe
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Este email já está registado']);
        exit;
    }

    // Criar novo utilizador
    $hashedPassword = hashPassword($password);
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, created_at) VALUES (?, ?, ?, NOW())");
    
    if ($stmt->execute([$name, $email, $hashedPassword])) {
        $userId = $conn->lastInsertId();
        $token = generateToken($userId);
        
        echo json_encode([
            'success' => true,
            'message' => 'Registo realizado com sucesso',
            'user' => [
                'id' => $userId,
                'name' => $name,
                'email' => $email
            ],
            'token' => $token
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao registar utilizador']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no servidor: ' . $e->getMessage()]);
}
?>
