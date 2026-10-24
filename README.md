# Sistema FLAG

Sistema de gestão para uma organização de FLAG Football, desenvolvido com PHP 8.x, arquitetura MVC modular, HTML5, CSS3 e JavaScript puro. O frontend utiliza componentes e CSS próprio inspirado na biblioteca Bootstrap, com foco estrito em performance (PageSpeed 100/100, Core Web Vitals) e acessibilidade, sem frameworks pesados de frontend ou backend.

> **Documento:** README técnico e arquitetural
> **Versão do sistema:** 0.1.0
> **Status:** Em desenvolvimento
> **Ambiente local atual:** Laragon com Nginx e PHP 8.x (extensão `pdo_pgsql`)
> **Ambientes de validação:** Vercel (aplicação PHP conectada a um banco PostgreSQL gerenciado)
> **Banco de dados unificado:** PostgreSQL gerenciado pelo Neon via Vercel Marketplace (mesma instância/cluster consumido tanto no desenvolvimento local quanto na homologação na Vercel)
> **Caminho atual:** `C:\laragon\www\testes\control`

---

## 1. Objetivo

O Sistema FLAG será utilizado para centralizar a gestão esportiva, administrativa e operacional da organização.

O sistema terá:

- site público;
- sistema de login/logout;
- área administrativa;
- área exclusiva para atletas;
- área para Staff;
- gerenciamento de atletas;
- categorias e equipes;
- comissão técnica;
- treinos;
- jogos;
- convocações;
- escalações;
- frequência;
- desempenho;
- mensalidades;
- financeiro;
- almoxarifado;
- documentos;
- comunicados e informativos;
- relatórios;
- calendário de atividades.

A arquitetura deverá permitir que novos módulos sejam adicionados ou removidos sem exigir refatoração dos módulos existentes.

---

# 2. Princípios arquiteturais

O projeto seguirá os princípios abaixo.

### 2.1 MVC em todos os módulos

Cada módulo possuirá seu próprio:

- Controller;
- Model;
- View;
- Routes;
- Assets;
- Database;
- Contracts, quando necessário;
- configuração específica do módulo, somente quando realmente necessária.

O `Core` **não será um MVC central de negócio**.

### 2.2 Core somente como infraestrutura

O Core será responsável por mecanismos compartilhados da aplicação, como:

- inicialização da aplicação (bootstrap);
- roteamento;
- requisição e resposta;
- conexão com banco de dados;
- sessão e autenticação;
- renderização de templates e views;
- catálogo e estado dos módulos;
- componentes globais de interface, validações, modais e segurança.

O Core **não deverá conhecer regras específicas** de atletas, jogos, treinos, financeiro, almoxarifado ou qualquer outro domínio de negócio.

Campos, labels, inputs, selects/options, radios, drag and drop, upload, textarea, links, botões, ícones, cores, validações, proteções, tooltips, confirmações e toasts são padrões globais centralizados no Core/Padrão.

### 2.3 Módulos independentes e o Módulo Padrão (Default)

Cada módulo deverá concentrar tudo que pertence ao seu domínio.

- **Módulo Padrão (Default/Core):** É obrigatório e fundamental. **Não é possível desativar** o módulo padrão. Ele centraliza a identidade visual, componentes comuns, layout base e mecanismos transversais.
- **Módulos de Domínio (ex.: Atletas, Eventos):** São modulares, independentes e **opcionais**. Podem ser ativados ou desativados pelo administrador de maior nível via painel de controle. Desativar um módulo oculta seus acessos e rotas, mas preserva integralmente seus arquivos e dados no banco.

Adicionar um módulo deverá significar:

```text
Adicionar pasta do módulo
        ↓
Registrar módulo no catálogo
        ↓
Executar migrations explicitamente
        ↓
Disponibilizar suas rotas
        ↓
Disponibilizar seus Assets
```

### 2.4 Comunicação entre módulos e Regra de Promoção de Componentes

Um módulo não deverá acessar diretamente Controllers, Models ou Views internas de outro módulo.

- **Regra de Isolamento Específico:** Enquanto um componente pertencer a apenas um módulo (exemplo: o seletor de data/hora e o *calendar* do módulo de Eventos), ele deve permanecer encapsulado exclusivamente nos arquivos do respectivo módulo (`modules/eventos/`).
- **Regra de Promoção ao Padrão (DRY):** Assim que outro módulo necessitar desse mesmo componente (ex.: calendário em treinos ou convocações), o componente deve ser **promovido para o Padrão (Default/Core)**. A implementação específica é então removida dos módulos individuais, unificando CSS, JavaScript e validações em um único lugar global para evitar redundâncias e duplicidade de código.

---

# 3. Stack tecnológica

## Backend

- PHP 8.x
- PDO para acesso ao banco (driver `pgsql`)
- PHP sem framework (arquitetura MVC limpa)

## Frontend

- HTML5 semântico
- CSS3 próprio e componentes globais
- JavaScript puro (ES6+, sem jQuery ou frameworks SPA)
- Design system padronizado e responsivo

## Ícones

Catálogo central no helper global `App\Core\View::icon()` com vetores SVG nativos de alta performance, mantendo compatibilidade universal (100% de dispositivos), zero CLS e zero requisições externas de rede.

## Desenvolvimento local e Homologação

- **Local:** Laragon com servidor web **Nginx** e PHP 8.x (`pdo_pgsql` habilitado).
- **Homologação:** Vercel (PHP serverless).
- **Banco de Dados Unificado:** PostgreSQL gerenciado pelo **Neon** via Vercel Marketplace, conectado tanto no ambiente local quanto na Vercel para garantir 100% de paridade de dados e comportamento.
- **Regra de Permissão Externa:** Nenhuma alteração no Neon (migrations, DDL, comandos de banco) ou qualquer interação fora do projeto pode ser executada sem autorização explícita do usuário.
- **Diretiva de Economia de Tokens:** Documentação técnica hierárquica, direta e visual, evitando repetição desnecessária de código nas interações rotineiras.

## Não utilizar

O projeto não utilizará:

- Node.js
- npm como requisito da aplicação
- Laravel
- Symfony
- CodeIgniter
- React
- Vue
- Angular
- outros frameworks de aplicação
- bundlers obrigatórios
- SPA como arquitetura principal

---

# 4. Processo de criação do projeto

As pastas e os arquivos de cada etapa devem ser criados pelos scripts correspondentes na pasta `setup/`. Antes de implementar uma etapa, deve-se criar ou atualizar seu script de setup, executá-lo e conferir os arquivos gerados no projeto.

Os scripts devem ser seguros para reexecução e não sobrescrever alterações existentes sem uma ação explícita. Quando uma etapa exigir atualizar arquivos já criados, o script deverá identificar claramente o que será atualizado e preservar o trabalho manual.

Fluxo esperado:

```text
Definir a etapa
      ↓
Criar/atualizar o script em setup/
      ↓
Executar o script
      ↓
Revisar os arquivos gerados
      ↓
Implementar e validar a etapa
```

Cada script deve documentar objetivo, pré-requisitos, arquivos criados ou alterados e comando de execução. A ordem das etapas deve permanecer explícita no README.

---

# 5. Compatibilidade

O código deverá ser desenvolvido para PHP 8.x.

A implementação deve priorizar recursos estáveis do PHP 8 e evitar dependência desnecessária de recursos exclusivos de uma versão muito específica.

Recursos permitidos quando fizerem sentido:

- type declarations;
- typed properties;
- return types;
- exceptions;
- enums;
- `match`;
- nullsafe operator;
- classes e interfaces;
- namespaces;
- PDO.

O código deverá permanecer legível para desenvolvedores que estejam entrando no projeto posteriormente.

---

# 7. Diretório atual

O projeto será iniciado no ambiente local em:

```text
C:\laragon\www\testes\control
```

Essa pasta será considerada a raiz do projeto.

---

# 8. Estrutura geral

Estrutura inicial prevista:

```text
control/
│
├── index.php
├── login.php
├── logout.php
│
├── admin/
│   └── index.php
│
├── app/
│   └── Core/
│       ├── Application.php
│       ├── Router.php
│       ├── Request.php
│       ├── Response.php
│       ├── Database.php
│       ├── Session.php
│       ├── Auth.php
│       ├── View.php
│       ├── ModuleManager.php
│       └── ContractManager.php
│
├── modules/
│   ├── autenticacao/
│   ├── usuarios/
│   ├── atletas/
│   ├── responsaveis/
│   ├── modalidades/
│   ├── categorias/
│   ├── equipes/
│   ├── comissao/
│   ├── estrutura-esportiva/
│   ├── treinos/
│   ├── jogos/
│   ├── escalacoes/
│   ├── frequencia/
│   ├── desempenho/
│   ├── financeiro/
│   ├── almoxarifado/
│   ├── documentos/
│   ├── comunicados/
│   └── relatorios/
│
├── config/
│   └── config.php
│
├── assets/
│   ├── css/
│   │   └── app.css
│   └── js/
│       └── app.js
│
├── storage/
│   ├── logs/
│   ├── uploads/
│   └── cache/
│
└── README.md
```

---

# 9. Configurações centralizadas e Versionamento Granular

As configurações da aplicação são centralizadas em um único ponto:

```text
config/
├── conf_sample.php (modelo versionado no Git sem segredos)
└── conf.php        (arquivo local privado com credenciais e URLs reais)
```

### 9.1 Versionamento Granular de Assets

Para controle de cache rigoroso e compatibilidade com diretivas de PageSpeed, os recursos estáticos contam com versionamento independente:

```php
return [
    'app' => [
        'name' => 'Sistema FLAG',
        'version' => '1.3.4',
        'environment' => 'development',
        'debug' => false,
    ],
    'assets' => [
        'css_version'   => '1.0.0',
        'js_version'    => '1.0.0',
        'image_version' => '1.0.0',
    ],
    'database' => [
        'url' => 'postgresql://...',
        'url_environment_variable' => 'DATABASE_URL',
        'sslmode' => 'require',
        'connect_timeout' => 20,
    ],
];
```

O helper `App\Core\View::assetUrl()` identifica automaticamente a extensão do recurso (`.css`, `.js`, imagens) e aplica a versão correspondente na URL para cache-busting preciso sem exigir limpeza manual do navegador.

### 9.2 Versão do sistema no rodapé

O rodapé público e administrativo exibe a versão `vMAJOR.MÓDULOS.REVISÃO`. A revisão final deve ser incrementada em toda publicação (sem reutilizar números); quando a Vercel disponibilizar `VERCEL_GIT_COMMIT_SHA`, o rodapé também mostra os sete primeiros caracteres do commit para identificar exatamente a implantação.

---

# 10. Front Controller

O `index.php` será o ponto de entrada principal da aplicação.

Fluxo:

```text
Requisição
    ↓
index.php
    ↓
Application
    ↓
Router
    ↓
Módulo
    ↓
Controller
    ↓
Model
    ↓
View
    ↓
Resposta HTML
```

O `index.php` não deverá conter regras de negócio.

Evitar:

```php
if ($pagina === 'atletas') {
    // lógica de atletas
}

if ($pagina === 'jogos') {
    // lógica de jogos
}
```

A responsabilidade de decidir o destino deverá ficar no Router.

---

# 9. Site público

O sistema terá uma área pública acessível sem login.

Exemplos:

```text
/
├── início
├── atletas
├── equipes
├── categorias
├── calendário
├── treinos
├── jogos
├── resultados
├── informativos
├── comissão técnica
└── login
```

Informações públicas deverão ser controladas pelo sistema.

Dados pessoais e informações administrativas não deverão ser expostos.

---

# 10. Área administrativa

A área administrativa será acessada por:

```text
/admin
```

O arquivo:

```text
admin/index.php
```

será o ponto de entrada da área administrativa.

O Admin não será dono das regras de negócio dos módulos.

Exemplo:

```text
/admin/atletas
        ↓
Admin
        ↓
Módulo Atletas
        ↓
AtletaAdminController
```

---

# 11. Autenticação em Duas Etapas

O módulo de autenticação (`modules/autenticacao/`) opera de forma robusta e amigável:

### 11.1 Fluxo em Duas Etapas

1. **Etapa 1 (Identificação):**
   - Apresenta apenas o campo de **E-mail/Login** acompanhado do botão circular com a seta para a direita (`arrow-right`), sem bordas pesadas e sem fundo sólido.
   - O usuário pode avançar clicando na seta ou pressionando `Enter`.
   - Uma verificação assíncrona (`action=check-user`) consulta no banco se o e-mail corresponde a um usuário ativo.
   - **Caso não encontre:** Exibe feedback amigável no campo e via toast (*"Usuário não encontrado ou inativo."*), mantendo o campo de e-mail focado para correção.
   - **Caso encontre:** Oculta suavemente a etapa 1 com animação rápida de transição (220ms).

2. **Etapa 2 (Autenticação):**
   - Exibe o cartão de identificação do usuário com sua foto de perfil (quando cadastrada na tabela de atletas vinculada pelo e-mail) ou o avatar padrão vetorial (`user`).
   - Apresenta o nome e o e-mail identificado.
   - Exibe o campo de **Senha** com foco automático imediato e o botão principal **Entrar**.
   - **Botão de Retorno Contextual:** O botão circular com a seta para a esquerda (`arrow-left`) surge exclusivamente na Etapa 2, permitindo retornar à Etapa 1 para trocar de conta ou corrigir o e-mail a qualquer momento.

3. **Ambiente Visual:**
   - O fundo da tela de login (`.auth-page`) utiliza a cor branca (`#fff`), oferecendo uma estética leve e limpa.

### 11.2 Cabeçalho Administrativo e Ação de Logout
- O botão **Sair** no cabeçalho do painel administrativo foi padronizado em formato compacto (`36px` de altura, tipografia `13px` com peso `700`).
- Incorpora o ícone vetorial nativo `logout` ao lado do texto.
- Opera sem fundo e sem borda em qualquer estado (normal e `:hover`), adotando a cor de alerta (`var(--red)` / `#b50c14`) para manter clareza e discrição.

---

# 12. Níveis de acesso

Inicialmente serão considerados:

```text
Visitante
Atleta
Staff
Administrador
```

O sistema deverá ser preparado para que novos perfis sejam criados futuramente.

A autorização deverá ser baseada em permissões, evitando uma simples regra:

```text
admin = true
```

O modelo deverá permitir permissões como:

```text
atletas.visualizar
atletas.criar
atletas.editar
atletas.excluir

treinos.visualizar
treinos.criar
treinos.editar

jogos.visualizar
jogos.criar
jogos.editar

financeiro.visualizar
financeiro.editar
```

---

# 13. Área do atleta

Após o login, um atleta poderá acessar informações próprias.

Exemplo:

```text
Área do Atleta
│
├── Dashboard
├── Meu Perfil
├── Minha Equipe
├── Calendário
├── Convocações
├── Frequência
├── Desempenho
├── Mensalidades
└── Informativos
```

O atleta deverá visualizar somente os dados aos quais possui permissão.

---

# 14. Área da Staff

A Staff terá acesso amplo ao sistema.

Exemplo:

```text
Staff
│
├── Dashboard
├── Atletas
├── Equipes
├── Categorias
├── Comissão Técnica
├── Treinos
├── Jogos
├── Convocações
├── Escalações
├── Frequência
├── Desempenho
├── Financeiro
├── Almoxarifado
├── Documentos
├── Informativos
└── Relatórios
```

Mesmo com acesso amplo, o sistema deverá permitir futuramente restringir determinadas áreas por cargo ou permissão.

---

# 15. Layout público

O site público terá um layout compartilhado:

```text
┌──────────────────────────────────────┐
│                HEADER                │
├──────────────────────────────────────┤
│                                      │
│              CONTEÚDO                │
│                                      │
├──────────────────────────────────────┤
│                FOOTER                │
└──────────────────────────────────────┘
```

Componentes globais:

```text
app/
└── Core/
    └── Views/
        └── Layout/
            ├── layout.php
            ├── header.php
            └── footer.php
```

---

# 16. Layout administrativo

O Admin possuirá layout próprio:

```text
┌──────────────┬─────────────────────────┐
│              │         TOPBAR          │
│    MENU      ├─────────────────────────┤
│              │                         │
│ Dashboard    │                         │
│ Atletas      │       CONTEÚDO          │
│ Treinos      │                         │
│ Jogos        │                         │
│ Financeiro   │                         │
│ ...          │                         │
│              │                         │
└──────────────┴─────────────────────────┘
```

Bootstrap será utilizado para estruturar os componentes visuais.

---

# 17. Estrutura de um módulo

Todos os módulos deverão seguir o padrão:

```text
modules/
└── atletas/
    │
    ├── Controllers/
    │
    ├── Models/
    │
    ├── Views/
    │
    ├── Routes/
    │
    ├── Assets/
    │   ├── css/
    │   └── js/
    │
    ├── Database/
    │   ├── migrations/
    │   └── seeds/
    │
    ├── Contracts/
    │
    ├── Config/
    │
    └── module.php
```

Nem todos os módulos precisarão utilizar todas as pastas.

Não criar arquivos vazios apenas para cumprir estrutura.

---

# 18. MVC dos módulos

Exemplo:

```text
Atletas
│
├── Controller
│      ↓
│   recebe requisição
│
├── Model
│      ↓
│   acessa dados/regras do domínio
│
└── View
       ↓
    apresenta HTML
```

Fluxo:

```text
Request
   ↓
Route
   ↓
Controller
   ↓
Model
   ↓
Controller
   ↓
View
   ↓
HTML
```

---

# 19. CSS e JavaScript

Cada módulo terá seus próprios Assets.

Exemplo:

```text
modules/atletas/Assets/
├── css/
│   └── atletas.css
└── js/
    └── atletas.js
```

Não criar um único JavaScript gigantesco contendo todo o sistema.

Recursos globais podem ficar em:

```text
assets/
├── css/
│   └── app.css
└── js/
    └── app.js
```

Somente funcionalidades realmente globais deverão entrar nesses arquivos.

---

# 20. Biblioteca visual e componentes

O frontend do Sistema FLAG foi desenvolvido com foco em acessibilidade e pontuação máxima no PageSpeed (Core Web Vitals):

- Grid responsivo;
- Cards e painéis modulares;
- Botões de ação primários, outline e de perigo;
- Formulários rotulados com foco acessível;
- Modais nativos via `<dialog>` acessível;
- Alertas e toasts de notificação (`window.AppUI.showToast`);
- Menu lateral administrativo (sidebar) responsivo e retrátil, com persistência da preferência do usuário em banco.

O projeto utiliza o padrão visual Bootstrap como referência de design e classes, implementado através de **CSS próprio otimizado** em `assets/css/app.css`, evitando scripts pesados e mantendo o carregamento ultrarrápido.

---

# 21. Ícones

### 21.1 Catálogo de Referência Visual
A biblioteca adota como guia de desenho e semântica:

```text
1. Bootstrap Icons (referência primária de desenho)
2. Google Material Symbols (referência secundária)
```

Nomenclaturas de referência padronizadas:
- `bi-person` (usuário / atleta)
- `bi-calendar-event` (eventos / jogos / treinos)
- `bi-shield-check` (segurança / perfil)
- `bi-pencil` (editar)
- `bi-trash` (excluir)
- `bi-list` (menu)
- `bi-arrow-right` (avançar)
- `bi-arrow-left` (voltar)
- `bi-box-arrow-right` (logout / sair)

### 21.2 Implementação de Alta Performance (SVG Nativo)
Para eliminar incompatibilidades em dispositivos móveis, bloqueios por adblockers e requisições adicionais de arquivos de fonte `.woff2` que prejudicariam as notas do PageSpeed, os ícones são gerados pelo helper global centralizado:

```php
App\Core\View::icon(string $name): string
```

Os ícones são renderizados como SVGs inline nativos, com `stroke: currentColor`, herdando cores, transições e estados de hover diretamente das variáveis CSS globais do projeto.

---

# 22. Domínio esportivo

O FLAG trabalhará com:

```text
Modalidade
├── FLAG 5x5
└── FLAG 8x8
```

Categorias combinam:

```text
Modalidade
+
Faixa etária
+
Sexo
```

Exemplo:

```text
FLAG 5x5 - Sub-14 - Masculino
FLAG 8x8 - Sub-16 - Feminino
```

Categoria e equipe são conceitos diferentes.

Uma categoria poderá possuir várias equipes.

---

# 23. Sides e posições

Os lados inicialmente considerados:

```text
ATK
DEF
ST
```

Onde:

- ATK = Ataque
- DEF = Defesa
- ST = Special Teams / Times Especiais

Um atleta poderá possuir múltiplas posições e múltiplos lados.

Exemplo:

```text
João
│
├── ATK
│   ├── WR
│   └── TE
│
├── DEF
│   ├── SF
│   └── CB
│
└── ST
    └── K
```

Não será utilizado:

```text
atletas.posicao_id
```

como única posição do atleta.

Será utilizada uma relação que permita múltiplas posições.

---

# 24. Escalações

As posições cadastradas para o atleta representam suas capacidades/posições conhecidas.

A escalação representa sua utilização em determinado jogo.

Exemplo:

```text
Jogo
│
├── ATK
│   ├── Titular - WR
│   ├── Titular - TE
│   └── Reserva - WR
│
├── DEF
│   ├── Titular - CB
│   └── Reserva - SF
│
└── ST
    └── Titular - K
```

O mesmo atleta poderá aparecer em mais de um lado quando permitido pela escalação.

---

# 25. Comissão técnica

Hierarquia inicial:

```text
Manager / Presidente
└── Head Coach
    ├── Treinador de Ataque
    │   └── Auxiliar / Capitão
    ├── Treinador de Defesa
    │   └── Auxiliar / Capitão
    └── Treinador de Times Especiais
        └── Auxiliar / Capitão
```

A estrutura deverá ser configurável pelo sistema.

---

# 26. Treinos

O módulo de treinos deverá permitir:

- data;
- horário;
- local;
- categoria;
- equipe;
- responsável;
- side;
- conteúdo;
- observações;
- recorrência;
- exceções;
- registro de frequência.

Os dias atualmente utilizados são:

```text
Sábado
Domingo
```

Mas não deverão ser fixados no código.

O sistema deverá permitir configurar outros dias.

---

# 27. Jogos

O módulo de jogos deverá permitir:

- data;
- horário;
- adversário;
- competição;
- local;
- mando;
- categoria;
- equipe;
- uniforme;
- status;
- resultado;
- observações.

Resultados poderão ser publicados no site público conforme configuração.

---

# 28. Convocações

O sistema deverá permitir convocar atletas para jogos.

Exemplo:

```text
Jogo
   ↓
Convocação
   ├── Atleta A → convocado
   ├── Atleta B → convocado
   ├── Atleta C → reserva
   └── Atleta D → não convocado
```

O atleta poderá visualizar suas próprias convocações.

---

# 29. Frequência

O módulo de frequência deverá permitir registrar:

```text
PRESENTE
AUSENTE
JUSTIFICADO
ATRASADO
```

A frequência poderá ser relacionada a treinos e, quando necessário, outras atividades.

Relatórios futuros poderão mostrar:

- frequência por atleta;
- frequência por equipe;
- frequência por categoria;
- frequência por período;
- frequência por treino.

---

# 30. Desempenho

O módulo de desempenho deverá manter histórico das avaliações dos atletas.

As avaliações poderão considerar:

- técnica;
- tática;
- física;
- comportamento;
- posição;
- side.

O desempenho poderá variar conforme posição.

Exemplo:

```text
João
├── ATK / WR
│   └── avaliação
├── DEF / CB
│   └── avaliação
└── ST / K
    └── avaliação
```

---

# 31. Financeiro

O módulo financeiro deverá futuramente contemplar:

- mensalidades;
- contas a receber;
- contas a pagar;
- categorias financeiras;
- fornecedores;
- fluxo de caixa;
- situação financeira;
- relatórios.

Atletas deverão visualizar suas próprias mensalidades.

A Staff autorizada poderá administrar os dados financeiros.

---

# 32. Almoxarifado

O módulo deverá contemplar:

- produtos;
- materiais;
- categorias;
- entradas;
- saídas;
- estoque;
- estoque mínimo;
- movimentações;
- atribuição de materiais;
- histórico.

---

# 33. Informativos e resultados

O módulo de comunicados poderá publicar:

```text
PUBLICO
ATLETAS
STAFF
```

Tipos possíveis:

```text
Notícia
Resultado
Aviso
Informativo
Comunicado interno
```

Isso permitirá que o mesmo módulo atenda ao site público e às áreas autenticadas.

---

# 34. Banco de dados

Cada módulo será responsável por suas migrations.

Exemplo:

```text
modules/
└── atletas/
    └── Database/
        ├── migrations/
        │   ├── 001_create_atletas.php
        │   ├── 002_create_atleta_posicoes.php
        │   └── ...
        │
        └── seeds/
```

Não utilizar inicialmente um único arquivo gigantesco:

```text
database.sql
```

como fonte principal da estrutura.

As migrations serão a fonte oficial da evolução do banco.

---

# 35. Banco e independência dos módulos

É importante evitar dependências rígidas entre tabelas de módulos quando isso impedir a remoção independente de um módulo.

Exemplo conceitual:

```text
Escalações
    |
    └── atleta_id
            |
            └── Atletas
```

A comunicação deverá ser controlada por contrato.

Não permitir que:

```text
EscalacoesController
```

importe diretamente:

```text
AtletaController
```

ou utilize internamente arquivos do módulo Atletas.

---

# 36. Calendário

O calendário deverá funcionar como uma visão das atividades.

Treinos e jogos continuam sendo donos de seus próprios dados.

Conceitualmente:

```text
             CALENDÁRIO
                  │
        ┌─────────┼─────────┐
        ↓         ↓         ↓
     Treinos     Jogos    Eventos
```

O calendário não deverá ser responsável por duplicar os dados originais.

---

# 37. Segurança

O sistema deverá considerar desde o início:

- sessões seguras;
- controle de acesso;
- autorização por permissão;
- proteção CSRF;
- validação de entrada;
- sanitização de saída;
- prepared statements com PDO;
- senhas utilizando `password_hash()`;
- verificação utilizando `password_verify()`;
- proteção contra acesso direto a arquivos sensíveis;
- controle de uploads;
- logs de operações importantes.

Nunca armazenar senhas em texto puro.

Nunca armazenar tokens ou credenciais diretamente no código versionado.

---

# 38. Padrão de código

Todos os desenvolvedores deverão:

- manter nomes claros;
- evitar funções gigantes;
- evitar código duplicado;
- separar responsabilidades;
- manter Controllers enxutos;
- manter Models relacionados ao domínio;
- manter Views focadas na apresentação;
- comentar somente quando o comentário agregar contexto;
- não colocar regra de negócio dentro da View;
- não colocar HTML dentro do Model;
- não colocar SQL espalhado pelo Controller.

---

# 39. Regra de dependências

Preferência:

```text
Core
  ↑
  │
Módulos
```

Um módulo poderá depender de uma capacidade/contrato, mas não deverá acessar diretamente a implementação interna de outro módulo.

Evitar:

```php
require '../../outro-modulo/Models/OutroModel.php';
```

Evitar:

```php
include '../../../modules/atletas/...';
```

O sistema deverá utilizar mecanismos de carregamento e contratos.

---

# 40. Ciclo de vida dos módulos

Cada módulo deverá possuir um manifesto:

```text
module.php
```

Exemplo:

```php
<?php

return [
    'name' => 'Atletas',
    'slug' => 'atletas',
    'version' => '1.0.0',
    'enabled' => true,
];
```

Futuramente poderá incluir informações como:

```php
'provides' => [],
'requires' => [],
'contracts' => [],
```

O ModuleManager será responsável por:

- descobrir módulos;
- verificar se estão ativos;
- carregar rotas;
- carregar recursos;
- registrar migrations;
- verificar contratos;
- controlar o ciclo de vida.

---

# 41. Instalação, ativação e remoção

Devemos distinguir:

### Disable

Desativa o módulo, mas mantém seus dados.

### Uninstall

Remove o módulo e, mediante confirmação, seus recursos e dados.

Isso evita perda acidental de dados.

Fluxo:

```text
Instalar
   ↓
Ativar
   ↓
Usar
   ↓
Desativar
   ↓
Reativar
```

ou:

```text
Instalar
   ↓
Usar
   ↓
Desinstalar
   ↓
Remover definitivamente
```

---

# 42. Versionamento

Formato:

```text
MAJOR.MINOR.PATCH
```

Exemplo:

```text
0.1.0
```

Durante desenvolvimento inicial:

```text
0.x.x
```

Quando a primeira versão estável estiver pronta:

```text
1.0.0
```

Cada módulo também poderá possuir sua própria versão.

Exemplo:

```text
Atletas: 1.2.0
Jogos: 1.0.3
Financeiro: 0.5.0
```

---

# 43. Git

O projeto deverá ser versionado.

Não enviar para o repositório:

```text
.env
senhas
tokens
credenciais
uploads privados
logs
arquivos temporários
cache
```

Deverá existir um `.gitignore`.

---

# 44. Ambiente de desenvolvimento

Ambiente atual:

```text
Laragon
└── C:\laragon\www\testes\control
```

URL local esperada:

```text
http://localhost/control
```

ou o domínio virtual configurado pelo Laragon.

---

# 45. Diretrizes para novos desenvolvedores

Antes de criar código:

1. Entender o módulo que será alterado.
2. Verificar se a funcionalidade pertence realmente a esse módulo.
3. Não colocar regra de negócio no Core.
4. Não acessar diretamente arquivos internos de outro módulo.
5. Seguir o padrão MVC.
6. Criar migrations dentro do próprio módulo.
7. Criar CSS/JS dentro do próprio módulo.
8. Utilizar Bootstrap para componentes visuais.
9. Utilizar Bootstrap Icons como padrão.
10. Manter configurações centralizadas.
11. Não criar arquivos de configuração duplicados sem necessidade.
12. Não introduzir Node.js ou frameworks sem decisão arquitetural explícita.
13. Não alterar módulos não relacionados para resolver um problema local.
14. Atualizar este README quando uma regra arquitetural for alterada.

---

# 46. Regra principal do projeto

A regra mais importante do Sistema FLAG é:

> **Cada módulo deve ser responsável pelo seu próprio domínio e possuir seu próprio MVC, recursos visuais, JavaScript, rotas e banco, enquanto o Core fornece apenas a infraestrutura necessária para que esses módulos funcionem.**

Em termos práticos:

```text
MÓDULO = MVC + ROTAS + CSS + JS + BANCO + CONTRATOS
```

e:

```text
CORE = INFRAESTRUTURA
```

---

# 47. Próximas etapas

A implementação deverá seguir aproximadamente esta ordem:

```text
1. Criar estrutura física do projeto
        ↓
2. Configuração centralizada
        ↓
3. Core mínimo
        ↓
4. Router
        ↓
5. Layout público
        ↓
6. Login / Logout
        ↓
7. Sessão e autorização
        ↓
8. Área Admin
        ↓
9. Sistema de módulos
        ↓
10. Banco e migrations
        ↓
11. Estrutura esportiva
        ↓
12. Atletas
        ↓
13. Equipes e categorias
        ↓
14. Comissão
        ↓
15. Treinos
        ↓
16. Jogos
        ↓
17. Escalações e convocações
        ↓
18. Frequência
        ↓
19. Desempenho
        ↓
20. Financeiro
        ↓
21. Almoxarifado
        ↓
22. Comunicados
        ↓
23. Relatórios
        ↓
24. Testes
        ↓
25. Preparação para produção
```

---

## Módulo de atletas (Regras e Persistência)

O cadastro administrativo mantém nome, apelido, gênero para elegibilidade, data de nascimento, celular, RG, CPF, e-mail, contato de emergência (nome, celular e parentesco), tipo sanguíneo, alergias, foto e status ativo/inativo. O CPF é único. A idade é calculada a partir da data de nascimento.

Uma pessoa pode ter vários vínculos de categoria, cargos, posições e números de camisa. Camisa (0 a 99) é única dentro de cada categoria; X5 e X8 mantêm listas independentes, e o formulário mostra somente os números disponíveis. CPF também é único no banco.

Cada posição pertence a uma categoria e a um side específico (ataque, defesa ou time especial), e seu nome não pode se repetir em nenhum side da mesma categoria. O vínculo entre categorias e posições impede que posições de X5 sejam confundidas com as de X8. Cargos são cadastrados em um catálogo e podem ser combinados por categoria; os cargos iniciais são Staff, Capitão, Treinador, Atleta, Head Coach e Manager. Uma pessoa pode, por exemplo, ser atleta e treinadora no X8 e capitã no X5. Cargos técnicos podem ser associados a categorias de outro gênero; camisas, posições e o cargo Atleta exigem gênero esportivo compatível.

A configuração administrativa permite adicionar categorias, posições e cargos sem alterar o código. A migration prepara X5 e X8 masculinas e femininas; posições iniciais são semeadas conforme o exemplo informado para X5 e X8 masculinos.

A lista exibe apenas dados esportivos e status; documentos, saúde e contatos pessoais ficam restritos à edição administrativa. A foto enviada é validada, redimensionada, convertida para JPEG e armazenada como BYTEA no PostgreSQL, sem depender do armazenamento efêmero da hospedagem. Fotos aceitam até 5 MB no envio e são limitadas a 500 KB após otimização.

O código de Atletas é mantido em `modules/atletas/`; o instalador não gera sua estrutura. Após aplicar a migration de autenticação, aplique a migration de atletas explicitamente com `php setup/install.php --migrate-athletes`. O comando altera o banco selecionado em `config/conf.php` ou `DATABASE_URL`; não é executado automaticamente.

---

# 48. Estado atual da arquitetura

```text
┌─────────────────────────────────────────────┐
│              SISTEMA FLAG                   │
├─────────────────────────────────────────────┤
│ PHP 8.x sem framework                      │
│ MVC modular independente                    │
│ HTML5 semântico + CSS3 próprio + JS puro    │
│ Ícones nativos via App\Core\View::icon()   │
│ PDO PostgreSQL (Neon - Nuvem)               │
│ Local: Laragon com Nginx                    │
│ Homologação: Vercel (Serverless)            │
├─────────────────────────────────────────────┤
│ Módulo Padrão (Default) obrigatório         │
│ Módulos Atletas e Eventos opcionais         │
│ Login em 2 etapas com verificação e foto    │
│ Logout compacto na barra superior           │
│ Versionamento granular de assets            │
│ Sem dependência obrigatória de Node/npm     │
└─────────────────────────────────────────────┘
```

---

# 49. Setup do projeto e Histórico de Restauração

O instalador em `setup/install.php` atua como runner de migrations explícitas e preparador de diretórios de runtime. Não gera código nem altera o banco sem comando específico.

Comandos disponíveis:

```text
php setup/install.php                       Prepara diretórios de runtime
php setup/install.php --check-db           Valida a conexão PostgreSQL
php setup/install.php --migrate-system     Aplica o esquema do módulo padrão
php setup/install.php --migrate-auth       Aplica o esquema de autenticação
php setup/install.php --migrate-athletes   Aplica o esquema de atletas
php setup/install.php --migrate-events     Aplica o esquema de eventos
php setup/install.php --migrate-all        Aplica todos os esquemas
php setup/install.php --migrate-local-config Migra configuração local antiga
php setup/install.php --create-admin      Cria o administrador inicial de teste
```

### Histórico de Pontos de Restauração (`restore-points/`)

1. **`sistema-flag-20261022-141919-before-reference-consolidation.zip`**
   - Ponto anterior à consolidação inicial dos módulos e referências.
2. **`sistema-flag-20261022-163500-login-stepper-and-icons-update.zip`**
   - Consolidação da tela de login em 2 etapas (verificação assíncrona de e-mail, foto/avatar do usuário, transições suaves).
   - Catálogo nativo de ícones expandido (`arrow-right`, `arrow-left`, `user`, `logout`).
   - Botão de sair compacto e limpo no cabeçalho.
   - Restauração de todas as diretivas conceituais do README original e integração da regra de promoção de componentes modulares.
