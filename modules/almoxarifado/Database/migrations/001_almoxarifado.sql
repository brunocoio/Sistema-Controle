-- Módulo de almoxarifado. Cria tabelas próprias e preserva registros existentes.
CREATE TABLE IF NOT EXISTS almoxarifado_categorias (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nome VARCHAR(80) NOT NULL,
    grupo VARCHAR(16) NOT NULL CHECK (grupo IN ('equipamento','vestuario','outro')),
    modo_controle VARCHAR(12) NOT NULL CHECK (modo_controle IN ('unidade','quantidade')),
    ativa BOOLEAN NOT NULL DEFAULT TRUE,
    criado_por BIGINT REFERENCES usuarios(id) ON DELETE SET NULL,
    criado_em TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX IF NOT EXISTS almoxarifado_categorias_nome_unique ON almoxarifado_categorias (lower(nome));

CREATE TABLE IF NOT EXISTS almoxarifado_colecoes (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    ano_inicio SMALLINT NOT NULL CHECK (ano_inicio BETWEEN 1900 AND 2200),
    ano_fim SMALLINT NOT NULL CHECK (ano_fim BETWEEN 1900 AND 2200),
    cor_primaria VARCHAR(40) NOT NULL,
    cor_contraste VARCHAR(40) NOT NULL,
    tem_logo BOOLEAN NOT NULL DEFAULT FALSE,
    tem_nome BOOLEAN NOT NULL DEFAULT FALSE,
    tem_numero BOOLEAN NOT NULL DEFAULT FALSE,
    tem_patrocinio BOOLEAN NOT NULL DEFAULT FALSE,
    observacao VARCHAR(240),
    ativa BOOLEAN NOT NULL DEFAULT TRUE,
    criado_por BIGINT REFERENCES usuarios(id) ON DELETE SET NULL,
    criado_em TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (ano_fim >= ano_inicio),
    CHECK (lower(cor_primaria) <> lower(cor_contraste))
);
CREATE INDEX IF NOT EXISTS almoxarifado_colecoes_anos_idx ON almoxarifado_colecoes (ano_inicio, ano_fim, ativa);

CREATE TABLE IF NOT EXISTS almoxarifado_itens (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    categoria_id BIGINT NOT NULL REFERENCES almoxarifado_categorias(id) ON DELETE RESTRICT,
    colecao_id BIGINT REFERENCES almoxarifado_colecoes(id) ON DELETE SET NULL,
    nome VARCHAR(120) NOT NULL,
    modo_controle VARCHAR(12) NOT NULL CHECK (modo_controle IN ('unidade','quantidade')),
    codigo VARCHAR(48),
    quantidade INTEGER NOT NULL DEFAULT 1 CHECK (quantidade >= 0),
    unidade VARCHAR(24) NOT NULL DEFAULT 'unidade',
    temporada VARCHAR(24),
    cor VARCHAR(40),
    tamanho VARCHAR(24),
    numero SMALLINT CHECK (numero IS NULL OR numero BETWEEN 0 AND 99),
    nome_estampado VARCHAR(80),
    uso VARCHAR(16) CHECK (uso IS NULL OR uso IN ('treino','campeonato','outro')),
    funcao_treino VARCHAR(12) CHECK (funcao_treino IS NULL OR funcao_treino IN ('ataque','defesa')),
    propriedade VARCHAR(12) NOT NULL DEFAULT 'equipe' CHECK (propriedade IN ('equipe','atleta')),
    proprietario_id BIGINT REFERENCES usuarios(id) ON DELETE SET NULL,
    origem VARCHAR(12) NOT NULL DEFAULT 'legado' CHECK (origem IN ('compra','doacao','legado','atleta','outro')),
    doador VARCHAR(120),
    fornecedor VARCHAR(120),
    adquirido_em DATE,
    condicao VARCHAR(16) NOT NULL DEFAULT 'bom' CHECK (condicao IN ('novo','bom','reparo','inutilizado')),
    situacao VARCHAR(16) NOT NULL DEFAULT 'disponivel' CHECK (situacao IN ('disponivel','manutencao','baixado')),
    quantidade_manutencao INTEGER NOT NULL DEFAULT 0 CHECK (quantidade_manutencao >= 0),
    localizacao VARCHAR(100),
    observacao VARCHAR(240),
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    criado_por BIGINT REFERENCES usuarios(id) ON DELETE SET NULL,
    criado_em TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (quantidade_manutencao <= quantidade),
    CHECK ((modo_controle = 'unidade' AND quantidade = 1 AND codigo IS NOT NULL) OR modo_controle = 'quantidade'),
    CHECK ((propriedade = 'atleta' AND proprietario_id IS NOT NULL) OR propriedade = 'equipe')
);
CREATE UNIQUE INDEX IF NOT EXISTS almoxarifado_itens_codigo_unique ON almoxarifado_itens (lower(codigo)) WHERE codigo IS NOT NULL;
CREATE INDEX IF NOT EXISTS almoxarifado_itens_categoria_idx ON almoxarifado_itens (categoria_id, ativo, lower(nome));
CREATE INDEX IF NOT EXISTS almoxarifado_itens_colecao_idx ON almoxarifado_itens (colecao_id, temporada, cor);

CREATE TABLE IF NOT EXISTS almoxarifado_movimentos (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    item_id BIGINT NOT NULL REFERENCES almoxarifado_itens(id) ON DELETE RESTRICT,
    tipo VARCHAR(16) NOT NULL CHECK (tipo IN ('entrada','baixa','manutencao','retorno_manutencao','ajuste')),
    quantidade INTEGER NOT NULL CHECK (quantidade > 0),
    motivo VARCHAR(240) NOT NULL,
    origem VARCHAR(16) CHECK (origem IS NULL OR origem IN ('compra','doacao','legado','atleta','outro')),
    data_movimento TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    realizado_por BIGINT REFERENCES usuarios(id) ON DELETE SET NULL,
    criado_em TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS almoxarifado_movimentos_item_idx ON almoxarifado_movimentos (item_id, data_movimento DESC, id DESC);

CREATE TABLE IF NOT EXISTS almoxarifado_emprestimos (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    item_id BIGINT NOT NULL REFERENCES almoxarifado_itens(id) ON DELETE RESTRICT,
    usuario_id BIGINT NOT NULL REFERENCES usuarios(id) ON DELETE RESTRICT,
    quantidade INTEGER NOT NULL DEFAULT 1 CHECK (quantidade > 0),
    retirado_em TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    devolucao_prevista DATE,
    devolvido_em TIMESTAMPTZ,
    entregue_por BIGINT REFERENCES usuarios(id) ON DELETE SET NULL,
    recebido_por BIGINT REFERENCES usuarios(id) ON DELETE SET NULL,
    condicao_saida VARCHAR(16) NOT NULL CHECK (condicao_saida IN ('novo','bom','reparo')),
    condicao_retorno VARCHAR(16) CHECK (condicao_retorno IS NULL OR condicao_retorno IN ('novo','bom','reparo','inutilizado')),
    quantidade_devolvida INTEGER,
    quantidade_perdida INTEGER,
    quantidade_danificada INTEGER,
    observacao VARCHAR(240),
    status VARCHAR(12) NOT NULL DEFAULT 'aberto' CHECK (status IN ('aberto','devolvido')),
    CHECK ((status = 'aberto' AND devolvido_em IS NULL
            AND quantidade_devolvida IS NULL AND quantidade_perdida IS NULL AND quantidade_danificada IS NULL)
        OR (status = 'devolvido' AND devolvido_em IS NOT NULL
            AND quantidade_devolvida IS NOT NULL AND quantidade_perdida IS NOT NULL AND quantidade_danificada IS NOT NULL
            AND quantidade_devolvida + quantidade_perdida + quantidade_danificada = quantidade))
);
CREATE INDEX IF NOT EXISTS almoxarifado_emprestimos_usuario_idx ON almoxarifado_emprestimos (usuario_id, status, retirado_em DESC);
CREATE INDEX IF NOT EXISTS almoxarifado_emprestimos_item_idx ON almoxarifado_emprestimos (item_id, status);

CREATE TABLE IF NOT EXISTS almoxarifado_checklists (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    evento_id BIGINT NOT NULL REFERENCES eventos(id) ON DELETE RESTRICT,
    responsavel_id BIGINT NOT NULL REFERENCES usuarios(id) ON DELETE RESTRICT,
    criado_por BIGINT REFERENCES usuarios(id) ON DELETE SET NULL,
    aberto_em TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fechado_em TIMESTAMPTZ,
    conferido_por BIGINT REFERENCES usuarios(id) ON DELETE SET NULL,
    observacao VARCHAR(240),
    status VARCHAR(12) NOT NULL DEFAULT 'aberto' CHECK (status IN ('aberto','conferido')),
    CHECK ((status = 'aberto' AND fechado_em IS NULL) OR (status = 'conferido' AND fechado_em IS NOT NULL))
);
CREATE UNIQUE INDEX IF NOT EXISTS almoxarifado_checklists_evento_unique ON almoxarifado_checklists (evento_id);

CREATE TABLE IF NOT EXISTS almoxarifado_checklist_itens (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    checklist_id BIGINT NOT NULL REFERENCES almoxarifado_checklists(id) ON DELETE RESTRICT,
    item_id BIGINT NOT NULL REFERENCES almoxarifado_itens(id) ON DELETE RESTRICT,
    quantidade_levar INTEGER NOT NULL CHECK (quantidade_levar > 0),
    quantidade_devolvida INTEGER,
    quantidade_perdida INTEGER,
    quantidade_danificada INTEGER,
    observacao VARCHAR(240),
    UNIQUE (checklist_id, item_id),
    CHECK (quantidade_devolvida IS NULL OR quantidade_devolvida >= 0),
    CHECK (quantidade_perdida IS NULL OR quantidade_perdida >= 0),
    CHECK (quantidade_danificada IS NULL OR quantidade_danificada >= 0),
    CHECK (quantidade_devolvida IS NULL OR quantidade_perdida IS NULL OR quantidade_danificada IS NULL
        OR quantidade_devolvida + quantidade_perdida + quantidade_danificada = quantidade_levar)
);
CREATE INDEX IF NOT EXISTS almoxarifado_checklist_itens_checklist_idx ON almoxarifado_checklist_itens (checklist_id, item_id);

INSERT INTO almoxarifado_categorias (nome, grupo, modo_controle) VALUES
    ('Equipamentos de treino', 'equipamento', 'unidade'),
    ('Materiais por quantidade', 'equipamento', 'quantidade'),
    ('Vestuário esportivo', 'vestuario', 'unidade'),
    ('Outros itens', 'outro', 'quantidade')
ON CONFLICT DO NOTHING;
