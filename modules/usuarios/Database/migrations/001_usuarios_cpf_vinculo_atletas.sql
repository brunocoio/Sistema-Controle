-- Dados aditivos para o cadastro administrativo de usuários e vínculo 1:1 com atletas.
-- Atletas já existentes permanecem sem vínculo até serem associados manualmente.
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS cpf VARCHAR(11);
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS funcoes JSONB NOT NULL DEFAULT '[]'::jsonb;
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS senha_inicial_pendente BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE usuarios DROP CONSTRAINT IF EXISTS usuarios_funcoes_array_check;
ALTER TABLE usuarios ADD CONSTRAINT usuarios_funcoes_array_check
    CHECK (jsonb_typeof(funcoes) = 'array');
UPDATE usuarios SET funcoes = jsonb_build_array(perfil)
WHERE jsonb_array_length(funcoes) = 0 AND perfil IN ('staff', 'atleta');
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
