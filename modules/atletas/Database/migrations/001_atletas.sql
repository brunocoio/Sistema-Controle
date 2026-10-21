CREATE TABLE IF NOT EXISTS atletas (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    apelido VARCHAR(80),
    numero SMALLINT CHECK (numero BETWEEN 0 AND 99),
    genero VARCHAR(12),
    cargo VARCHAR(80),
    data_nascimento DATE,
    celular VARCHAR(20),
    rg VARCHAR(30),
    cpf VARCHAR(11),
    email VARCHAR(254),
    contato_emergencia_nome VARCHAR(120),
    contato_emergencia_celular VARCHAR(20),
    contato_emergencia_parentesco VARCHAR(60),
    tipo_sanguineo VARCHAR(3) CHECK (tipo_sanguineo IN ('A+','A-','B+','B-','AB+','AB-','O+','O-')),
    alergias TEXT,
    foto BYTEA,
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    criado_por BIGINT REFERENCES usuarios(id) ON DELETE SET NULL,
    atualizado_por BIGINT REFERENCES usuarios(id) ON DELETE SET NULL,
    criado_em TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK ((contato_emergencia_nome IS NULL) = (contato_emergencia_celular IS NULL))
);

ALTER TABLE atletas ADD COLUMN IF NOT EXISTS genero VARCHAR(12);
CREATE UNIQUE INDEX IF NOT EXISTS atletas_id_genero_unique ON atletas (id, genero);
CREATE UNIQUE INDEX IF NOT EXISTS atletas_cpf_unique ON atletas (cpf) WHERE cpf IS NOT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS atletas_email_unique ON atletas (lower(email)) WHERE email IS NOT NULL;
CREATE INDEX IF NOT EXISTS atletas_nome_idx ON atletas (lower(nome), lower(apelido));
CREATE INDEX IF NOT EXISTS atletas_ativo_idx ON atletas (ativo, lower(nome));

CREATE TABLE IF NOT EXISTS categorias (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    modalidade VARCHAR(40) NOT NULL,
    genero VARCHAR(12) NOT NULL CHECK (genero IN ('masculino', 'feminino')),
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    criado_em TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (id, genero)
);
CREATE UNIQUE INDEX IF NOT EXISTS categorias_modalidade_genero_unique ON categorias (lower(modalidade), genero);

CREATE TABLE IF NOT EXISTS categoria_sides (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    categoria_id BIGINT NOT NULL REFERENCES categorias(id) ON DELETE CASCADE,
    codigo VARCHAR(24) NOT NULL CHECK (codigo IN ('ataque', 'defesa', 'time_especial')),
    nome VARCHAR(40) NOT NULL,
    UNIQUE (categoria_id, codigo),
    UNIQUE (categoria_id, id)
);

CREATE TABLE IF NOT EXISTS categoria_posicoes (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    categoria_id BIGINT NOT NULL,
    side_id BIGINT NOT NULL,
    nome VARCHAR(60) NOT NULL,
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    criado_em TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (categoria_id, side_id) REFERENCES categoria_sides(categoria_id, id) ON DELETE CASCADE,
    UNIQUE (categoria_id, id)
);
CREATE UNIQUE INDEX IF NOT EXISTS categoria_posicoes_nome_unique
    ON categoria_posicoes (categoria_id, side_id, lower(nome));
CREATE UNIQUE INDEX IF NOT EXISTS categoria_posicoes_nome_categoria_unique
    ON categoria_posicoes (categoria_id, lower(nome));

CREATE TABLE IF NOT EXISTS atleta_categorias (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    atleta_id BIGINT NOT NULL,
    categoria_id BIGINT NOT NULL,
    genero VARCHAR(12) NOT NULL,
    numero_camisa SMALLINT CHECK (numero_camisa BETWEEN 0 AND 99),
    criado_em TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (atleta_id, genero) REFERENCES atletas(id, genero) ON DELETE CASCADE,
    FOREIGN KEY (categoria_id, genero) REFERENCES categorias(id, genero) ON DELETE RESTRICT,
    UNIQUE (atleta_id, categoria_id),
    UNIQUE (id, categoria_id)
);
ALTER TABLE atleta_categorias ADD COLUMN IF NOT EXISTS numero_camisa SMALLINT CHECK (numero_camisa BETWEEN 0 AND 99);
CREATE UNIQUE INDEX IF NOT EXISTS atleta_categoria_numero_unique
    ON atleta_categorias (categoria_id, numero_camisa) WHERE numero_camisa IS NOT NULL;

CREATE TABLE IF NOT EXISTS cargos (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nome VARCHAR(60) NOT NULL,
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    criado_em TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX IF NOT EXISTS cargos_nome_unique ON cargos (lower(nome));
INSERT INTO cargos (nome) VALUES
    ('Staff'), ('Capitão'), ('Treinador'), ('Atleta'), ('Head Coach'), ('Manager')
ON CONFLICT DO NOTHING;

CREATE TABLE IF NOT EXISTS atleta_cargos_por_categoria (
    atleta_id BIGINT NOT NULL REFERENCES atletas(id) ON DELETE CASCADE,
    categoria_id BIGINT NOT NULL REFERENCES categorias(id) ON DELETE CASCADE,
    cargo_id BIGINT NOT NULL REFERENCES cargos(id) ON DELETE RESTRICT,
    PRIMARY KEY (atleta_id, categoria_id, cargo_id)
);
CREATE INDEX IF NOT EXISTS atleta_cargos_por_categoria_categoria_idx ON atleta_cargos_por_categoria (categoria_id, atleta_id);

CREATE TABLE IF NOT EXISTS atleta_categoria_posicoes (
    atleta_categoria_id BIGINT NOT NULL,
    categoria_id BIGINT NOT NULL,
    posicao_id BIGINT NOT NULL,
    PRIMARY KEY (atleta_categoria_id, posicao_id),
    FOREIGN KEY (atleta_categoria_id, categoria_id)
        REFERENCES atleta_categorias(id, categoria_id) ON DELETE CASCADE,
    FOREIGN KEY (categoria_id, posicao_id)
        REFERENCES categoria_posicoes(categoria_id, id) ON DELETE RESTRICT
);

CREATE INDEX IF NOT EXISTS atleta_categorias_categoria_idx ON atleta_categorias (categoria_id, atleta_id);
CREATE INDEX IF NOT EXISTS atleta_categoria_posicoes_posicao_idx ON atleta_categoria_posicoes (posicao_id);

INSERT INTO categorias (modalidade, genero) VALUES
    ('X5', 'masculino'), ('X5', 'feminino'), ('X8', 'masculino'), ('X8', 'feminino')
ON CONFLICT DO NOTHING;

INSERT INTO categoria_sides (categoria_id, codigo, nome)
SELECT categorias.id, sides.codigo, sides.nome
FROM categorias
CROSS JOIN (VALUES ('ataque', 'Ataque'), ('defesa', 'Defesa'), ('time_especial', 'Time especial')) AS sides(codigo, nome)
ON CONFLICT (categoria_id, codigo) DO NOTHING;

INSERT INTO categoria_posicoes (categoria_id, side_id, nome)
SELECT categorias.id, categoria_sides.id, seed.nome
FROM (VALUES
    ('X5', 'masculino', 'defesa', 'LB'),
    ('X5', 'masculino', 'ataque', 'QB'),
    ('X5', 'masculino', 'ataque', 'WR'),
    ('X8', 'masculino', 'defesa', 'CB'),
    ('X8', 'masculino', 'defesa', 'DL'),
    ('X8', 'masculino', 'ataque', 'WR'),
    ('X8', 'masculino', 'time_especial', 'Kicker')
) AS seed(modalidade, genero, side_codigo, nome)
JOIN categorias ON lower(categorias.modalidade) = lower(seed.modalidade) AND categorias.genero = seed.genero
JOIN categoria_sides ON categoria_sides.categoria_id = categorias.id AND categoria_sides.codigo = seed.side_codigo
ON CONFLICT DO NOTHING;
