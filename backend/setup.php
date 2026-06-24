<?php
require_once 'config.php';

try {
    $pdo = getDBConnection();
    
    // Tabela de Utilizadores
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id SERIAL PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Tabela de Inventário (Ideias)
    $pdo->exec("CREATE TABLE IF NOT EXISTS inventory (
        id SERIAL PRIMARY KEY,
        user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
        category VARCHAR(50) NOT NULL,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        image_url TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    echo json_encode(["success" => true, "message" => "Tabelas criadas com sucesso no PostgreSQL!"]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Erro ao criar tabelas: " . $e->getMessage()]);
}
?>
