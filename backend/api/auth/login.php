<?php
require_once '../../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

// Validações
if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Email e palavra-passe são obrigatórios']);
    exit;
}

$conn = getDBConnection();

// Procurar utilizador
$stmt = $conn->prepare("SELECT id, name, email, password FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Email ou palavra-passe incorretos']);
    exit;
}

$user = $result->fetch_assoc();

// Verificar palavra-passe
if (!verifyPassword($password, $user['password'])) {
    echo json_encode(['success' => false, 'message' => 'Email ou palavra-passe incorretos']);
    exit;
}

// Gerar token
$token = generateToken($user['id']);

echo json_encode([
    'success' => true,
    'message' => 'Login realizado com sucesso',
    'user' => [
        'id' => $user['id'],
        'name' => $user['name'],
        'email' => $user['email']
    ],
    'token' => $token
]);

$stmt->close();
$conn->close();
?>
