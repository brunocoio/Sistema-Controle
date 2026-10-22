CREATE TABLE IF NOT EXISTS eventos (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    tipo VARCHAR(20) NOT NULL CHECK (tipo IN ('jogo', 'treino', 'amistoso', 'compromisso')),
    categoria_id BIGINT,
    titulo VARCHAR(120) NOT NULL,
    adversario VARCHAR(120),
    inicio TIMESTAMPTZ NOT NULL,
    fim TIMESTAMPTZ,
    local VARCHAR(180),
    status VARCHAR(20) NOT NULL DEFAULT 'agendado' CHECK (status IN ('agendado', 'concluido', 'cancelado')),
    placar_nos SMALLINT CHECK (placar_nos IS NULL OR placar_nos >= 0),
    placar_adversario SMALLINT CHECK (placar_adversario IS NULL OR placar_adversario >= 0),
    atleta_destaque_id BIGINT,
    release_ativo BOOLEAN NOT NULL DEFAULT FALSE,
    release_texto TEXT,
    release_imagem BYTEA,
    criado_por BIGINT,
    atualizado_por BIGINT,
    criado_em TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (fim IS NULL OR fim > inicio),
    CHECK (tipo IN ('jogo', 'amistoso') OR (placar_nos IS NULL AND placar_adversario IS NULL AND atleta_destaque_id IS NULL))
);

CREATE INDEX IF NOT EXISTS eventos_inicio_status_idx ON eventos (inicio, status);
CREATE INDEX IF NOT EXISTS eventos_tipo_inicio_idx ON eventos (tipo, inicio);

CREATE TABLE IF NOT EXISTS evento_presencas (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    evento_id BIGINT NOT NULL REFERENCES eventos(id) ON DELETE CASCADE,
    atleta_id BIGINT NOT NULL CHECK (atleta_id > 0),
    resposta VARCHAR(16) NOT NULL CHECK (resposta IN ('confirmado', 'indisponivel')),
    atualizado_em TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (evento_id, atleta_id)
);

CREATE INDEX IF NOT EXISTS evento_presencas_atleta_idx ON evento_presencas (atleta_id, evento_id);

ALTER TABLE eventos
    ADD COLUMN IF NOT EXISTS categoria_id BIGINT,
    DROP CONSTRAINT IF EXISTS eventos_atleta_destaque_id_fkey,
    DROP CONSTRAINT IF EXISTS eventos_categoria_id_fkey,
    DROP CONSTRAINT IF EXISTS eventos_criado_por_fkey,
    DROP CONSTRAINT IF EXISTS eventos_atualizado_por_fkey;
ALTER TABLE evento_presencas
    DROP CONSTRAINT IF EXISTS evento_presencas_atleta_id_fkey;
CREATE INDEX IF NOT EXISTS eventos_categoria_inicio_idx ON eventos (categoria_id, inicio);
