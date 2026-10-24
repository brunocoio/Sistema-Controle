-- Módulo financeiro independente. Não altera nem remove registros existentes.
CREATE TABLE IF NOT EXISTS financeiro_config (
    id SMALLINT PRIMARY KEY DEFAULT 1 CHECK (id = 1),
    pix_chave VARCHAR(180),
    pix_nome VARCHAR(120),
    atualizado_por BIGINT REFERENCES usuarios(id) ON DELETE SET NULL,
    atualizado_em TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);
INSERT INTO financeiro_config (id) VALUES (1) ON CONFLICT (id) DO NOTHING;

CREATE TABLE IF NOT EXISTS financeiro_categorias (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nome VARCHAR(80) NOT NULL,
    tipo VARCHAR(8) NOT NULL CHECK (tipo IN ('entrada', 'saida')),
    ativa BOOLEAN NOT NULL DEFAULT TRUE,
    criado_por BIGINT REFERENCES usuarios(id) ON DELETE SET NULL,
    criado_em TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX IF NOT EXISTS financeiro_categorias_nome_tipo_unique
    ON financeiro_categorias (lower(nome), tipo);

INSERT INTO financeiro_categorias (nome, tipo) VALUES
    ('Contribuições de atletas', 'entrada'),
    ('Patrocínios', 'entrada'),
    ('Doações', 'entrada'),
    ('Outras entradas', 'entrada'),
    ('Despesas de jogos', 'saida'),
    ('Uniformes', 'saida'),
    ('Equipamentos', 'saida'),
    ('Outras despesas', 'saida')
ON CONFLICT DO NOTHING;

CREATE TABLE IF NOT EXISTS financeiro_lancamentos (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    categoria_id BIGINT NOT NULL REFERENCES financeiro_categorias(id) ON DELETE RESTRICT,
    tipo VARCHAR(8) NOT NULL CHECK (tipo IN ('entrada', 'saida')),
    valor NUMERIC(12,2) NOT NULL CHECK (valor > 0),
    descricao VARCHAR(240) NOT NULL,
    data_movimento DATE NOT NULL,
    origem VARCHAR(20) NOT NULL DEFAULT 'manual' CHECK (origem IN ('manual', 'contribuicao_atleta')),
    pagamento_id BIGINT UNIQUE,
    criado_por BIGINT REFERENCES usuarios(id) ON DELETE SET NULL,
    criado_em TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS financeiro_lancamentos_data_idx
    ON financeiro_lancamentos (data_movimento DESC, id DESC);
CREATE INDEX IF NOT EXISTS financeiro_lancamentos_categoria_idx
    ON financeiro_lancamentos (categoria_id, data_movimento DESC);

CREATE TABLE IF NOT EXISTS financeiro_regras (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    modalidade VARCHAR(10) NOT NULL CHECK (modalidade IN ('mensal', 'jogo')),
    valor NUMERIC(12,2) NOT NULL CHECK (valor > 0),
    dia_vencimento SMALLINT NOT NULL DEFAULT 1 CHECK (dia_vencimento BETWEEN 1 AND 28),
    vigencia_inicio DATE NOT NULL,
    vigencia_fim DATE,
    ativa BOOLEAN NOT NULL DEFAULT TRUE,
    criado_por BIGINT REFERENCES usuarios(id) ON DELETE SET NULL,
    criado_em TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (vigencia_fim IS NULL OR vigencia_fim >= vigencia_inicio)
);
CREATE INDEX IF NOT EXISTS financeiro_regras_ativas_idx
    ON financeiro_regras (ativa, modalidade, vigencia_inicio, vigencia_fim);

CREATE TABLE IF NOT EXISTS financeiro_isencoes (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    usuario_id BIGINT NOT NULL REFERENCES usuarios(id) ON DELETE RESTRICT,
    vigencia_inicio DATE NOT NULL,
    vigencia_fim DATE,
    motivo VARCHAR(240) NOT NULL,
    ativa BOOLEAN NOT NULL DEFAULT TRUE,
    criado_por BIGINT REFERENCES usuarios(id) ON DELETE SET NULL,
    criado_em TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (vigencia_fim IS NULL OR vigencia_fim >= vigencia_inicio)
);
CREATE INDEX IF NOT EXISTS financeiro_isencoes_usuario_periodo_idx
    ON financeiro_isencoes (usuario_id, ativa, vigencia_inicio, vigencia_fim);

CREATE TABLE IF NOT EXISTS financeiro_obrigacoes (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    regra_id BIGINT REFERENCES financeiro_regras(id) ON DELETE SET NULL,
    usuario_id BIGINT NOT NULL REFERENCES usuarios(id) ON DELETE RESTRICT,
    atleta_id BIGINT REFERENCES atletas(id) ON DELETE SET NULL,
    evento_id BIGINT REFERENCES eventos(id) ON DELETE SET NULL,
    evento_titulo VARCHAR(120),
    atleta_nome VARCHAR(120) NOT NULL,
    referencia VARCHAR(20) NOT NULL,
    valor_original NUMERIC(12,2) NOT NULL CHECK (valor_original > 0),
    valor_negociado NUMERIC(12,2) CHECK (valor_negociado IS NULL OR (valor_negociado >= 0 AND valor_negociado <= valor_original)),
    vencimento DATE NOT NULL,
    status VARCHAR(12) NOT NULL DEFAULT 'pendente'
        CHECK (status IN ('pendente', 'parcial', 'pago', 'postergado', 'negociado', 'isento', 'cancelado')),
    motivo_status VARCHAR(240),
    criado_por BIGINT REFERENCES usuarios(id) ON DELETE SET NULL,
    criado_em TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (regra_id, usuario_id, referencia)
);
CREATE INDEX IF NOT EXISTS financeiro_obrigacoes_usuario_idx
    ON financeiro_obrigacoes (usuario_id, vencimento DESC, id DESC);
CREATE INDEX IF NOT EXISTS financeiro_obrigacoes_status_idx
    ON financeiro_obrigacoes (status, vencimento, usuario_id);

CREATE TABLE IF NOT EXISTS financeiro_pagamentos (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    obrigacao_id BIGINT NOT NULL REFERENCES financeiro_obrigacoes(id) ON DELETE RESTRICT,
    valor NUMERIC(12,2) NOT NULL CHECK (valor > 0),
    data_pagamento DATE NOT NULL,
    descricao VARCHAR(240),
    status VARCHAR(12) NOT NULL DEFAULT 'aguardando'
        CHECK (status IN ('aguardando', 'confirmado', 'recusado')),
    registrado_por BIGINT REFERENCES usuarios(id) ON DELETE SET NULL,
    analisado_por BIGINT REFERENCES usuarios(id) ON DELETE SET NULL,
    criado_em TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    analisado_em TIMESTAMPTZ
);
CREATE INDEX IF NOT EXISTS financeiro_pagamentos_obrigacao_idx
    ON financeiro_pagamentos (obrigacao_id, criado_em DESC);
CREATE UNIQUE INDEX IF NOT EXISTS financeiro_pagamentos_um_pendente_idx
    ON financeiro_pagamentos (obrigacao_id) WHERE status = 'aguardando';

CREATE TABLE IF NOT EXISTS financeiro_negociacoes (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    obrigacao_id BIGINT NOT NULL REFERENCES financeiro_obrigacoes(id) ON DELETE RESTRICT,
    solicitante_id BIGINT NOT NULL REFERENCES usuarios(id) ON DELETE RESTRICT,
    valor_proposto NUMERIC(12,2) NOT NULL CHECK (valor_proposto > 0),
    mensagem VARCHAR(240),
    status VARCHAR(10) NOT NULL DEFAULT 'aguardando' CHECK (status IN ('aguardando','aceita','recusada')),
    resposta VARCHAR(240),
    analisado_por BIGINT REFERENCES usuarios(id) ON DELETE SET NULL,
    analisado_em TIMESTAMPTZ,
    criado_em TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS financeiro_negociacoes_obrigacao_idx
    ON financeiro_negociacoes (obrigacao_id, criado_em DESC);
CREATE UNIQUE INDEX IF NOT EXISTS financeiro_negociacoes_um_pendente_idx
    ON financeiro_negociacoes (obrigacao_id) WHERE status = 'aguardando';

ALTER TABLE financeiro_lancamentos DROP CONSTRAINT IF EXISTS financeiro_lancamentos_pagamento_id_fkey;
ALTER TABLE financeiro_lancamentos ADD CONSTRAINT financeiro_lancamentos_pagamento_id_fkey
    FOREIGN KEY (pagamento_id) REFERENCES financeiro_pagamentos(id) ON DELETE RESTRICT;

CREATE TABLE IF NOT EXISTS financeiro_historico (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    obrigacao_id BIGINT NOT NULL REFERENCES financeiro_obrigacoes(id) ON DELETE RESTRICT,
    acao VARCHAR(24) NOT NULL CHECK (acao IN ('gerada', 'pagamento_enviado', 'pagamento_confirmado', 'pagamento_recusado', 'postergada', 'negociada', 'isenta', 'cancelada')),
    observacao VARCHAR(240),
    valor_anterior NUMERIC(12,2),
    valor_novo NUMERIC(12,2),
    vencimento_anterior DATE,
    vencimento_novo DATE,
    realizado_por BIGINT REFERENCES usuarios(id) ON DELETE SET NULL,
    criado_em TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS financeiro_historico_obrigacao_idx
    ON financeiro_historico (obrigacao_id, criado_em DESC);
