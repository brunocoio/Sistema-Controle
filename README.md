# Sistema FLAG

Sistema de gestão para uma organização de FLAG Football, desenvolvido com PHP 8.x, arquitetura MVC modular, HTML5, CSS3, JavaScript puro e Bootstrap.

> **Documento:** README técnico e arquitetural  
> **Versão do sistema:** 0.1.0  
> **Status:** Arquitetura inicial / em desenvolvimento  
> **Ambiente local atual:** Laragon  
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

- inicialização da aplicação;
- roteamento;
- requisição;
- resposta;
- conexão com banco;
- sessão;
- autenticação;
- renderização;
- carregamento de módulos;
- contratos entre módulos.

O Core não deverá conhecer regras específicas de:

- atletas;
- jogos;
- treinos;
- financeiro;
- almoxarifado;
- desempenho;
- ou qualquer outro domínio de negócio.

### 2.3 Módulos independentes

Cada módulo deverá concentrar tudo que pertence ao seu domínio.

Adicionar um módulo deverá significar, preferencialmente:

```text
Adicionar pasta do módulo
        ↓
Registrar módulo
        ↓
Executar migrations
        ↓
Disponibilizar suas rotas
        ↓
Disponibilizar seus Assets
```

Remover um módulo deverá ser possível sem alterar a implementação interna dos demais.

### 2.4 Comunicação entre módulos

Um módulo não deverá acessar diretamente Controllers, Models ou Views internas de outro módulo.

Quando houver necessidade de comunicação, deverão ser utilizados contratos/interfaces ou mecanismos definidos pelo sistema.

Referências entre módulos deverão ser tratadas de maneira controlada, evitando acoplamento estrutural.

---

# 3. Stack tecnológica

## Backend

- PHP 8.x
- PDO para acesso ao banco
- PHP sem framework

## Frontend

- HTML5
- CSS3
- JavaScript puro
- Bootstrap

## Ícones

Preferência:

1. Bootstrap Icons
2. Google Material Symbols/Icons, quando necessário

Não será utilizado Font Awesome como dependência padrão.

## Desenvolvimento local

- Laragon
- Apache
- PHP 8.x
- Banco de dados compatível com a arquitetura definida

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

# 4. Compatibilidade

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

# 5. Diretório atual

O projeto será iniciado no ambiente local em:

```text
C:\laragon\www\testes\control
```

Essa pasta será considerada a raiz do projeto.

---

# 6. Estrutura geral

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

# 7. Configurações centralizadas

As configurações da aplicação deverão ser centralizadas.

Não criar arquivos espalhados como:

```text
config_db.php
config_php.php
config_token.php
config_email.php
config_api.php
config_auth.php
```

sem uma necessidade arquitetural real.

A preferência é:

```text
config/
└── config.php
```

O arquivo central poderá organizar as configurações por seção:

```php
<?php

return [

    'app' => [
        'name' => 'Sistema FLAG',
        'version' => '0.1.0',
        'environment' => 'development',
        'debug' => true,
        'url' => 'http://localhost/control',
    ],

    'database' => [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'flag',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],

    'session' => [
        'name' => 'FLAG_SESSION',
        'lifetime' => 7200,
    ],

    'security' => [
        'csrf_enabled' => true,
    ],

];
```

> Credenciais, tokens e informações sensíveis não devem ser versionados no Git. Quando necessário, utilizar variáveis de ambiente ou um arquivo local protegido, mantendo a configuração da aplicação centralizada.

O objetivo é ter **uma única referência de configuração**, organizada por contexto, em vez de espalhar configurações pelo projeto.

---

# 8. Front Controller

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

# 11. Autenticação

O sistema possuirá login e logout.

O módulo de autenticação será:

```text
modules/autenticacao/
```

Estrutura:

```text
autenticacao/
├── Controllers/
│   ├── LoginController.php
│   └── LogoutController.php
│
├── Models/
│   └── Usuario.php
│
├── Views/
│   └── login.php
│
├── Routes/
│   └── web.php
│
├── Assets/
│   ├── css/
│   └── js/
│
├── Database/
│   ├── migrations/
│   └── seeds/
│
└── module.php
```

Autenticação e autorização são conceitos separados.

```text
Autenticação
    ↓
Quem é o usuário?

Autorização
    ↓
O que esse usuário pode fazer?
```

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

# 20. Bootstrap

Bootstrap será utilizado como biblioteca visual principal.

Exemplos:

- grid;
- cards;
- buttons;
- forms;
- tables;
- modals;
- alerts;
- dropdowns;
- navbar;
- sidebar;
- responsive layout.

A aplicação não deverá depender de um framework JavaScript adicional.

---

# 21. Ícones

Preferência:

```text
1. Bootstrap Icons
2. Google Material Symbols
```

Exemplo:

```html
<i class="bi bi-person"></i>
<i class="bi bi-calendar-event"></i>
<i class="bi bi-trophy"></i>
<i class="bi bi-pencil"></i>
<i class="bi bi-trash"></i>
```

Os ícones deverão manter consistência visual em todo o sistema.

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

# 48. Estado atual da arquitetura

```text
┌─────────────────────────────────────────────┐
│              SISTEMA FLAG                   │
├─────────────────────────────────────────────┤
│ PHP 8.x                                     │
│ MVC modular                                 │
│ HTML5 + CSS3 + JavaScript                   │
│ Bootstrap                                   │
│ Bootstrap Icons                             │
│ Google Material Symbols (alternativa)       │
│ PDO                                         │
│ Laragon                                     │
├─────────────────────────────────────────────┤
│ Sem Node.js                                 │
│ Sem Laravel                                 │
│ Sem Symfony                                 │
│ Sem React                                   │
│ Sem Vue                                     │
│ Sem Angular                                 │
└─────────────────────────────────────────────┘
```
---

# 49. Estrutura estup

Pasta setup contem os arquivos para criar toda a estrutura



> **php setup/01-estrutura.php**


```

---
