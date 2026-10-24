-- Dados aditivos para o cadastro administrativo de usuários e vínculo 1:1 com atletas.
-- Atletas já existentes permanecem sem vínculo até serem associados manualmente.
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS cpf VARCHAR(11);
ALTER TABLE usuarios DROP CONSTRAINT IF EXISTS usuarios_cpf_format_check;
ALTER TABLE usuarios ADD CONSTRAINT usuarios_cpf_format_check
    CHECK (cpf IS NULL OR cpf ~ '^[0-9]{11}$');
CREATE UNIQUE INDEX IF NOT EXISTS usuarios_cpf_unique
    ON usuarios (cpf) WHERE cpf IS NOT NULL;

ALTER TABLE atletas ADD COLUMN IF NOT EXISTS usuario_id BIGINT;
ALTER TABLE atletas DROP CONSTRAINT IF EXISTS atletas_usuario_id_fkey;
ALTER TABLE atletas ADD CONSTRAINT atletas_usuario_id_fkey
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT;
CREATE UNIQUE INDEX IF NOT EXISTS atletas_usuario_id_unique
    ON atletas (usuario_id) WHERE usuario_id IS NOT NULL;
