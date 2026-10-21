CREATE TABLE IF NOT EXISTS usuarios (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    email VARCHAR(254) NOT NULL,
    email_normalizado VARCHAR(254) GENERATED ALWAYS AS (lower(email)) STORED,
    password_hash VARCHAR(255) NOT NULL,
    perfil VARCHAR(20) NOT NULL CHECK (perfil IN ('admin', 'staff', 'atleta')),
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    criado_em TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS usuarios_email_normalizado_unique
    ON usuarios (email_normalizado);

CREATE TABLE IF NOT EXISTS autenticacao_tentativas (
    chave_hash CHAR(64) PRIMARY KEY,
    tentativas SMALLINT NOT NULL DEFAULT 0 CHECK (tentativas >= 0),
    inicio_janela TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    bloqueado_ate TIMESTAMPTZ NULL
);

CREATE INDEX IF NOT EXISTS autenticacao_tentativas_bloqueado_idx
    ON autenticacao_tentativas (bloqueado_ate)
    WHERE bloqueado_ate IS NOT NULL;
CREATE TABLE IF NOT EXISTS app_sessions (
    session_id VARCHAR(128) PRIMARY KEY,
    payload TEXT NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL
);

CREATE INDEX IF NOT EXISTS app_sessions_expires_at_idx ON app_sessions (expires_at);