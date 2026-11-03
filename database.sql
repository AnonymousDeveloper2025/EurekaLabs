-- Eureka Labs / Idefy — PostgreSQL (Neon)
-- O backend usa PDO PostgreSQL; execute este ficheiro numa base PostgreSQL.

CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255),
    google_sub VARCHAR(255),
    avatar_url TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS users_google_sub_unique
    ON users (google_sub) WHERE google_sub IS NOT NULL;

CREATE TABLE IF NOT EXISTS ideas (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    category VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    image_url TEXT,
    saved BOOLEAN NOT NULL DEFAULT FALSE,
    pdf_generated BOOLEAN NOT NULL DEFAULT FALSE,
    pdf_generated_at TIMESTAMP NULL,
    mode VARCHAR(20) NOT NULL DEFAULT 'simple',
    flow VARCHAR(20) NOT NULL DEFAULT 'develop',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS idea_progress (
    id SERIAL PRIMARY KEY,
    idea_id INTEGER NOT NULL REFERENCES ideas(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    current_day INTEGER NOT NULL DEFAULT 1,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    notes TEXT NOT NULL DEFAULT '',
    voice_transcript TEXT NOT NULL DEFAULT '',
    completed_steps JSONB NOT NULL DEFAULT '[]'::jsonb,
    progress_percent INTEGER NOT NULL DEFAULT 0,
    stats JSONB NOT NULL DEFAULT '{}'::jsonb,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (idea_id, user_id)
);

CREATE INDEX IF NOT EXISTS ideas_user_created_idx
    ON ideas (user_id, created_at DESC);

CREATE INDEX IF NOT EXISTS progress_user_idx
    ON idea_progress (user_id, updated_at DESC);
