<?php
require_once '../../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$name = trim($input['name'] ?? '');
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

// Validações
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

$conn = getDBConnection();

// Verificar se o email já existe
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Este email já está registado']);
    exit;
}

// Criar novo utilizador
$hashedPassword = hashPassword($password);
$stmt = $conn->prepare("INSERT INTO users (name, email, password, created_at) VALUES (?, ?, ?, NOW())");
$stmt->bind_param("sss", $name, $email, $hashedPassword);

if ($stmt->execute()) {
    $userId = $stmt->insert_id;
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

$stmt->close();
$conn->close();
?>
