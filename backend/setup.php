<?php
/**
 * SETUP DATABASE - EUREKA LABS ELITE
 * Cria todas as tabelas necessárias no PostgreSQL
 */

require_once 'config.php';

header('Content-Type: application/json');

try {
    $pdo = getDBConnection();
    
    // 1. Tabela de Utilizadores
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id SERIAL PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // 2. Tabela de Ideias (CORRIGIDO: era "inventory")
    $pdo->exec("CREATE TABLE IF NOT EXISTS ideas (
        id SERIAL PRIMARY KEY,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        category VARCHAR(50) NOT NULL DEFAULT 'Geral',
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        image_url TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // 3. Criar índices para melhor performance
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ideas_user_id ON ideas(user_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ideas_category ON ideas(category)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ideas_created_at ON ideas(created_at DESC)");
    
    // 4. Tabela de Auditoria (Opcional, mas recomendado)
    $pdo->exec("CREATE TABLE IF NOT EXISTS audit_log (
        id SERIAL PRIMARY KEY,
        user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
        action VARCHAR(50) NOT NULL,
        details TEXT,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    echo json_encode([
        "success" => true,
        "message" => "✅ Tabelas criadas com sucesso no PostgreSQL!",
        "tables_created" => [
            "users",
            "ideas",
            "audit_log"
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Setup Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "❌ Erro ao criar tabelas: " . $e->getMessage()
    ]);
}
?>
