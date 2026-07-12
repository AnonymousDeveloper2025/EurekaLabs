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

    // ✅ CORRIGIDO: esta tabela chamava-se "inventory", mas TODOS os
    // endpoints (get-inventory.php, get-idea.php, delete-idea.php,
    // generate-idea.php, save-idea.php, get-stats.php) fazem SELECT/INSERT
    // na tabela "ideas". Isso fazia com que, numa base de dados nova, todas
    // essas queries falhassem com "relation ideas does not exist".
    //
    // Se já tens uma base de dados em produção com a tabela "inventory"
    // criada (no Render), esta linha renomeia-a automaticamente e preserva
    // todos os dados existentes. Se a tabela "inventory" não existir (ex:
    // base de dados nova), a linha simplesmente não faz nada.
    $pdo->exec("ALTER TABLE IF EXISTS inventory RENAME TO ideas");

    // Tabela de Ideias
    $pdo->exec("CREATE TABLE IF NOT EXISTS ideas (
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

    // ✅ Garante que estas colunas existem mesmo numa tabela "ideas" já
    // criada anteriormente sem elas (ex: por uma versão antiga deste
    // ficheiro). Nunca falha nem apaga dados.
    $pdo->exec("ALTER TABLE ideas ADD COLUMN IF NOT EXISTS saved BOOLEAN DEFAULT FALSE");
    $pdo->exec("ALTER TABLE ideas ADD COLUMN IF NOT EXISTS pdf_generated BOOLEAN DEFAULT FALSE");
    $pdo->exec("ALTER TABLE ideas ADD COLUMN IF NOT EXISTS pdf_generated_at TIMESTAMP");

    echo json_encode(["success" => true, "message" => "Tabelas criadas/actualizadas com sucesso no PostgreSQL!"]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Erro ao criar tabelas: " . $e->getMessage()]);
}
?>
