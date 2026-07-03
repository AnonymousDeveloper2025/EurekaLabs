<?php
/**
 * LOGIN - EUREKA LABS ELITE
 */

require_once '../../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Preenche o email e a palavra-passe.']);
    exit;
}

try {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("SELECT id, name, email, password FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && verifyPassword($password, $user['password'])) {
        $token = generateToken($user['id']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Bem-vindo de volta!',
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email']
            ],
            'token' => $token
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Credenciais inválidas.']);
    }

} catch (Exception $e) {
    error_log("Erro Login: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro no servidor ao processar o login.']);
}
?>
