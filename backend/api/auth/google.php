<?php
/**
 * LOGIN / REGISTO COM GOOGLE IDENTITY SERVICES
 * O Client ID deve estar em GOOGLE_CLIENT_ID no backend/.env.
 */
require_once '../../config.php';
header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$idToken = trim($input['credential'] ?? $input['id_token'] ?? '');
if ($idToken === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Credencial Google em falta.']);
    exit;
}
if (GOOGLE_CLIENT_ID === '') {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'O login Google ainda não foi configurado no servidor.']);
    exit;
}

function googleTokenInfo($token) {
    $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode($token);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status !== 200 || !$body) return null;
    $payload = json_decode($body, true);
    return is_array($payload) ? $payload : null;
}

try {
    $payload = googleTokenInfo($idToken);
    $issuer = $payload['iss'] ?? '';
    $audience = $payload['aud'] ?? '';
    $email = strtolower(trim($payload['email'] ?? ''));
    $expiresAt = (int)($payload['exp'] ?? 0);
    $verified = filter_var($payload['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);

    if (!$payload || !hash_equals(GOOGLE_CLIENT_ID, $audience) ||
        !in_array($issuer, ['accounts.google.com', 'https://accounts.google.com'], true) ||
        $expiresAt < time() || !$verified || !isValidEmail($email) || empty($payload['sub'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'A credencial Google é inválida ou expirou.']);
        exit;
    }

    $name = trim($payload['name'] ?? 'Utilizador Google');
    $avatar = trim($payload['picture'] ?? '');
    $googleSub = (string)$payload['sub'];
    $conn = getDBConnection();

    $stmt = $conn->prepare('SELECT id, name, email, google_sub, avatar_url FROM users WHERE google_sub = ? OR LOWER(email) = ? LIMIT 1');
    $stmt->execute([$googleSub, $email]);
    $user = $stmt->fetch();

    if ($user) {
        if (empty($user['google_sub'])) {
            $update = $conn->prepare('UPDATE users SET google_sub = ?, avatar_url = ?, name = ? WHERE id = ?');
            $update->execute([$googleSub, $avatar ?: null, $name ?: $user['name'], $user['id']]);
        }
        $userId = $user['id'];
        $userName = $name ?: $user['name'];
    } else {
        // A coluna password é obrigatória nas instalações existentes; esta hash
        // aleatória impede login por password numa conta criada só via Google.
        $randomPassword = hashPassword(bin2hex(random_bytes(32)));
        $insert = $conn->prepare('INSERT INTO users (name, email, password, google_sub, avatar_url, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
        $insert->execute([$name ?: 'Utilizador Google', $email, $randomPassword, $googleSub, $avatar ?: null]);
        $userId = $conn->lastInsertId();
        $userName = $name ?: 'Utilizador Google';
    }

    echo json_encode([
        'success' => true,
        'message' => 'Entrada com Google concluída.',
        'user' => ['id' => $userId, 'name' => $userName, 'email' => $email, 'avatar_url' => $avatar],
        'token' => generateToken($userId)
    ]);
} catch (Throwable $e) {
    error_log('Erro Google Auth: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Não foi possível concluir a entrada com Google.']);
}
?>
