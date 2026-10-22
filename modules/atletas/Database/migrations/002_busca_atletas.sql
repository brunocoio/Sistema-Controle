-- Acelera buscas por trecho do nome ou apelido (ILIKE '%texto%').
-- Migration aditiva: não altera nem remove dados existentes.
CREATE EXTENSION IF NOT EXISTS pg_trgm;

CREATE INDEX IF NOT EXISTS atletas_nome_trgm_idx
    ON atletas USING GIN (nome gin_trgm_ops);

CREATE INDEX IF NOT EXISTS atletas_apelido_trgm_idx
    ON atletas USING GIN (apelido gin_trgm_ops)
    WHERE apelido IS NOT NULL;
