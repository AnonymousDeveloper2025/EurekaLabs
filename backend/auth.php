<?php
/**
 * JWT VALIDATION & AUTH HELPERS
 * Eureka Labs Elite
 */

function validateToken($token) {
    $jwt_secret = getenv('JWT_SECRET') ?: 'eureka_labs_elite_secret_2026';
    
    if (!$token || empty($token)) {
        return null;
    }
    
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        error_log("Token format inválido: não tem 3 partes");
        return null;
    }
    
    list($headerEnc, $payloadEnc, $sigEnc) = $parts;
    
    // Função auxiliar para decode base64url
    $base64UrlDecode = function($str) {
        $padding = 4 - (strlen($str) % 4);
        if ($padding !== 4) {
            $str .= str_repeat('=', $padding);
        }
        return base64_decode(strtr($str, '-_', '+/'));
    };
    
    // Verificar assinatura
    $signatureInput = "$headerEnc.$payloadEnc";
    $expectedSigBinary = hash_hmac('sha256', $signatureInput, $jwt_secret, true);
    $expectedSigUrl = rtrim(strtr(base64_encode($expectedSigBinary), '+/', '-_'), '=');
    
    if (!hash_equals($expectedSigUrl, $sigEnc)) {
        error_log("Assinatura JWT inválida");
        return null;
    }
    
    // Decodificar payload
    try {
        $payloadJson = $base64UrlDecode($payloadEnc);
        $payload = json_decode($payloadJson, true);
        
        if (!$payload || !isset($payload['userId'])) {
            error_log("Payload JWT não contém userId");
            return null;
        }
        
        return $payload['userId'];
    } catch (Exception $e) {
        error_log("Erro ao decodificar JWT: " . $e->getMessage());
        return null;
    }
}

function getAuthUserId() {
    // Tentar pegar do header Authorization
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    
    if (preg_match('/Bearer\s+(\S+)/', $authHeader, $matches)) {
        $userId = validateToken($matches[1]);
        if ($userId) {
            return $userId;
        }
    }
    
    // Fallback: tentar do input JSON (para compatibilidade com cliente)
    $input = json_decode(file_get_contents('php://input'), true);
    if (!empty($input['token'])) {
        $userId = validateToken($input['token']);
        if ($userId) {
            return $userId;
        }
    }
    
    return null;
}

function requireAuth() {
    $userId = getAuthUserId();
    if (!$userId) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Não autorizado. Token inválido ou expirado.'
        ]);
        exit;
    }
    return $userId;
}

function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function generateToken($userId) {
    $jwt_secret = getenv('JWT_SECRET') ?: 'eureka_labs_elite_secret_2026';
    
    $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
    $payload = json_encode([
        'userId' => $userId,
        'iat' => time(),
        'exp' => time() + (24 * 60 * 60) // 24 horas
    ]);
    
    // Encode base64url
    $headerEnc = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');
    $payloadEnc = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    
    // Sign
    $signatureInput = "$headerEnc.$payloadEnc";
    $signature = hash_hmac('sha256', $signatureInput, $jwt_secret, true);
    $signatureEnc = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    
    return "$headerEnc.$payloadEnc.$signatureEnc";
}
?>
