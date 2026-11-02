<?php
/**
 * LOGIN / REGISTO COM GOOGLE - WEB (id_token) + APP NATIVO (google_sub)
 */
require_once '../../config.php';
header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];

// Suporta 2 formatos: web (credential) e app nativo (email + sub)
$idToken = trim($input['credential'] ?? $input['id_token'] ?? '');
$emailNativo = strtolower(trim($input['email'] ?? ''));
$googleSubNativo = trim($input['id'] ?? $input['google_sub'] ?? $input['sub'] ?? '');
$nameNativo = trim($input['name'] ?? 'Utilizador Google');
$avatarNativo = trim($input['photo'] ?? $input['avatar_url'] ?? $input['picture'] ?? '');

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
    $conn = getDBConnection();
    $email = '';
    $name = '';
    $avatar = '';
    $googleSub = '';

    // MODO 1: WEB - com id_token
    if ($idToken !== '') {
        if (GOOGLE_CLIENT_ID === '') {
            http_response_code(503);
            echo json_encode(['success' => false, 'message' => 'Login Google não configurado no servidor.']);
            exit;
        }
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
            echo json_encode(['success' => false, 'message' => 'Credencial Google inválida ou expirou.']);
            exit;
        }
        $name = trim($payload['name'] ?? 'Utilizador Google');
        $avatar = trim($payload['picture'] ?? '');
        $googleSub = (string)$payload['sub'];

    // MODO 2: APP NATIVO - sem id_token, vem direto do GoogleApiClient
    } elseif ($emailNativo !== '' && $googleSubNativo !== '') {
        if (!isValidEmail($emailNativo)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email Google inválido.']);
            exit;
        }
        $email = $emailNativo;
        $name = $nameNativo;
        $avatar = $avatarNativo;
        $googleSub = $googleSubNativo;
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Credencial Google em falta.']);
        exit;
    }

    // --- LÓGICA DE LOGIN / LINKAR CONTA ---
    $stmt = $conn->prepare('SELECT id, name, email, google_sub, avatar_url FROM users WHERE google_sub = ? OR LOWER(email) = LOWER(?) LIMIT 1');
    $stmt->execute([$googleSub, $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $userId = $user['id'];
        $userName = $name ?: $user['name'];
        // Se achou por email mas ainda não tinha google_sub, linka
        if (empty($user['google_sub'])) {
            $update = $conn->prepare('UPDATE users SET google_sub = ?, avatar_url = COALESCE(?, avatar_url), name = ? WHERE id = ?');
            $update->execute([$googleSub, $avatar ?: null, $userName, $userId]);
        } else {
            // Só atualiza avatar
            $conn->prepare('UPDATE users SET avatar_url = COALESCE(?, avatar_url) WHERE id = ?')->execute([$avatar ?: null, $userId]);
        }
    } else {
        // Cria conta nova - password NULL agora é permitido pelo setup novo
        // Se ainda tiver NOT NULL em instalação antiga, usa hash aleatória
        $randomPassword = function_exists('hashPassword') ? hashPassword(bin2hex(random_bytes(32))) : null;
        try {
            $insert = $conn->prepare('INSERT INTO users (name, email, password, google_sub, avatar_url, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
            $insert->execute([$name, $email, $randomPassword, $googleSub, $avatar ?: null]);
        } catch (PDOException $e) {
            // Fallback se password for NOT NULL e $randomPassword for null
            if ($randomPassword === null) {
                $randomPassword = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
                $insert = $conn->prepare('INSERT INTO users (name, email, password, google_sub, avatar_url, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
                $insert->execute([$name, $email, $randomPassword, $googleSub, $avatar ?: null]);
            } else {
                throw $e;
            }
        }
        $userId = $conn->lastInsertId();
        $userName = $name;
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
