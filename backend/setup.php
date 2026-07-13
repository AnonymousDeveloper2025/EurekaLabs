<?php
require_once 'config.php';

/**
 * Corre uma instrução SQL e ignora "já existe" (a tabela/coluna já está
 * criada — objectivo alcançado). Isto torna o setup.php seguro para correr
 * várias vezes seguidas, incluindo quando dois pedidos chegam quase ao
 * mesmo tempo (ex: o browser tenta novamente porque o Render demorou a
 * acordar) — o que antes causava "relation ideas already exists".
 */
function runSafe(PDO $pdo, string $sql, array $etapas = []): void {
    try {
        $pdo->exec($sql);
    } catch (PDOException $e) {
        $sqlstate = $e->getCode();
        if (!$sqlstate && isset($e->errorInfo[0])) {
            $sqlstate = $e->errorInfo[0];
        }
        // 42P07 = duplicate_table no PostgreSQL, 42701 = duplicate_column
        if ($sqlstate === '42P07' || $sqlstate === '42701') {
            return; // já existe — nada a fazer, não é um erro real
        }
        throw $e;
    }
}

try {
    $pdo = getDBConnection();

    runSafe($pdo, "CREATE TABLE IF NOT EXISTS users (
        id SERIAL PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Renomeia a tabela antiga "inventory" para "ideas", só se "inventory"
    // ainda existir (preserva dados de instalações antigas). Se já foi
    // renomeada antes, isto não faz nada.
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
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    runSafe($pdo, "ALTER TABLE ideas ADD COLUMN IF NOT EXISTS saved BOOLEAN DEFAULT FALSE");
    runSafe($pdo, "ALTER TABLE ideas ADD COLUMN IF NOT EXISTS pdf_generated BOOLEAN DEFAULT FALSE");
    runSafe($pdo, "ALTER TABLE ideas ADD COLUMN IF NOT EXISTS pdf_generated_at TIMESTAMP");

    echo json_encode(["success" => true, "message" => "Tabelas criadas/actualizadas com sucesso no PostgreSQL!"]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Erro ao criar tabelas: " . $e->getMessage()]);
}
?>
