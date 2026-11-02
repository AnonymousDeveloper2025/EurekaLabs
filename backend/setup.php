<?php
require_once 'config.php';

/**
 * Executa SQL e ignora "já existe".
 * Seguro para correr várias vezes (Render acorda 2x ao mesmo tempo)
 */
function runSafe(PDO $pdo, string $sql): void {
    try {
        $pdo->exec($sql);
    } catch (PDOException $e) {
        $code = $e->getCode();
        if (!$code && isset($e->errorInfo[0])) {
            $code = $e->errorInfo[0];
        }
        // 42P07 = tabela já existe, 42701 = coluna já existe, 42710 = index/objeto já existe
        if (in_array($code, ['42P07', '42701', '42710'])) {
            return;
        }
        // 23505 às vezes vem quando o index unique já existe e não usou IF NOT EXISTS
        if (strpos($e->getMessage(), 'already exists') !== false) {
            return;
        }
        throw $e;
    }
}

try {
    $pdo = getDBConnection();

    // --- USERS ---
    // password NULLABLE para permitir conta só com Google
    runSafe($pdo, "CREATE TABLE IF NOT EXISTS users (
        id SERIAL PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255),
        google_sub VARCHAR(255),
        avatar_url TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Migrations para quem já tem a tabela antiga
    runSafe($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS password VARCHAR(255)");
    runSafe($pdo, "ALTER TABLE users ALTER COLUMN password DROP NOT NULL");
    runSafe($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS google_sub VARCHAR(255)");
    runSafe($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar_url TEXT");
    runSafe($pdo, "CREATE UNIQUE INDEX IF NOT EXISTS users_google_sub_unique ON users (google_sub) WHERE google_sub IS NOT NULL");

    // --- IDEAS ---
    // Renomeia inventory antiga se ainda existir
    runSafe($pdo, "ALTER TABLE IF EXISTS inventory RENAME TO ideas");

    runSafe($pdo, "CREATE TABLE IF NOT EXISTS ideas (
        id SERIAL PRIMARY KEY,
        user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
        category VARCHAR(50) NOT NULL,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        image_url TEXT,
        saved BOOLEAN DEFAULT FALSE,
        pdf_generated BOOLEAN DEFAULT FALSE,
        pdf_generated_at TIMESTAMP,
        mode VARCHAR(20) DEFAULT 'simple',
        flow VARCHAR(20) DEFAULT 'develop',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    runSafe($pdo, "ALTER TABLE ideas ADD COLUMN IF NOT EXISTS saved BOOLEAN DEFAULT FALSE");
    runSafe($pdo, "ALTER TABLE ideas ADD COLUMN IF NOT EXISTS pdf_generated BOOLEAN DEFAULT FALSE");
    runSafe($pdo, "ALTER TABLE ideas ADD COLUMN IF NOT EXISTS pdf_generated_at TIMESTAMP");
    runSafe($pdo, "ALTER TABLE ideas ADD COLUMN IF NOT EXISTS mode VARCHAR(20) DEFAULT 'simple'");
    runSafe($pdo, "ALTER TABLE ideas ADD COLUMN IF NOT EXISTS flow VARCHAR(20) DEFAULT 'develop'");
    runSafe($pdo, "ALTER TABLE ideas ADD COLUMN IF NOT EXISTS image_url TEXT");

    // --- PROGRESSO ---
    runSafe($pdo, "CREATE TABLE IF NOT EXISTS idea_progress (
        id SERIAL PRIMARY KEY,
        idea_id INTEGER NOT NULL REFERENCES ideas(id) ON DELETE CASCADE,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        current_day INTEGER DEFAULT 1,
        status VARCHAR(20) DEFAULT 'active',
        notes TEXT DEFAULT '',
        voice_transcript TEXT DEFAULT '',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (idea_id, user_id)
    )");

    echo json_encode(["success" => true, "message" => "Tabelas criadas/actualizadas com sucesso no PostgreSQL!"]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Erro ao criar tabelas: " . $e->getMessage()]);
}
?>
