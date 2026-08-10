# SDV Access — Santa Rita — Fase 1

Base: repositório `remotevelaskoo/SDV-SANTARITA` (12 volumes + 14 ADRs). Entrega desta fase: **Design System + App Shell + Dashboard + Login**, front-end apenas, com dados de exemplo.

## Decisões desta fase

- **Stack:** React (TanStack Start) + Tailwind. Divergente da ADR-001 (Laravel + Blade/Livewire). Recomendo registrar uma ADR-015 no repositório de docs formalizando a mudança ou o caráter de protótipo.
- **Sem banco agora:** nenhum backend/Lovable Cloud. Todos os números, acessos recentes e usuários vêm de um módulo de dados de exemplo (`src/data/`) isolado, para ser trocado por API depois sem mexer nas telas.
- **Login:** tela de autenticação funcional com validação, estados de erro e sessão persistida no navegador, validando credenciais contra a lista de usuários de exemplo. Não é autenticação segura — é a jornada completa pronta para plugar no backend. Quando quiser login real com senha, ativamos o Lovable Cloud numa fase seguinte.
- **Idioma:** interface toda em português, nomenclatura idêntica à dos documentos.

## 1. Design System (base primeiro)

Tokens em `src/styles.css`, convertidos para `oklch`, conforme 002_BRAND_BOOK e 003_DESIGN_SYSTEM:

- Marca: navy 900 `#001C3D`, navy 800 `#002A58`, blue 700 `#0759B8`, blue 600 `#0867D1` (ação primária), blue 500 `#1788E8`, cyan 400 `#21A6E8`.
- Neutros 950→000 (`#09162D` … `#F8FAFC` fundo geral, `#FFFFFF` cartões).
- Semânticos: success 700/600/100, warning 700/500/100, danger 700/600/100, info 700/100, special purple.
- Tipografia por token (`--font-family-sans: system-ui…`, mono restrito a identificadores técnicos), escala tipográfica e estilos semânticos da seção 6.
- Escala de espaçamento (seção 7), alturas de controle, raios (seção 9.1), bordas, elevação e tokens de movimento.

Componentes implementados nesta fase (os exigidos pelo Dashboard, seção 2.3 do doc 004):

- `DS-CMP-001 Sidebar`, `DS-CMP-002 Operational Header`
- `DS-CMP-003 Botão` + `DS-CMP-004 Grupo de ações`
- `DS-CMP-011 Badge de status`, `DS-CMP-012 Alerta`, `DS-CMP-013 Toast`
- `DS-CMP-014 Estado vazio`, `DS-CMP-015 Skeleton`
- `DS-CMP-016 Card`, `DS-CMP-017 Tabela`, `DS-CMP-018 Lista de atividade`, `DS-CMP-019 Métrica`
- `DS-CMP-005 Campo de texto` e `DS-CMP-007 Autocomplete` (busca global e login)

Os demais componentes (upload/captura, stepper, data/período, tabs, paginação) entram junto das telas que os exigem.

## 2. App Shell

Layout de `docs/003_DESIGN_SYSTEM.md` §11 e `docs/004_UX_UI_DASHBOARD.md` §6.1:

```text
┌───────────────┬──────────────────────────────────────────────┐
│ Navegação     │ Cabeçalho operacional                        │
│ lateral       ├──────────────────────────────────────────────┤
│ (navy 900)    │ Alertas críticos                             │
│               │ Métricas 1–8 (4 colunas × 2 linhas)          │
│               │ Acessos recentes │ Entradas × Saídas         │
└───────────────┴──────────────────────────────────────────────┘
```

- **Sidebar** navy, marca SDV Access, módulos agrupados, item ativo destacado, modo recolhido (ícones), usuário + sair no rodapé, itens filtrados por perfil.
- **Cabeçalho operacional:** botão recolher, busca global, notificações, usuário/perfil, situação do caixa (condicional) e data/hora da sessão.
- Itens de menu para os módulos previstos (Validação, Pré-cadastro, Imóveis, Pessoas, Empresas, Prestadores, Veículos, Entradas/Saídas, Administração, Relatórios) — nesta fase só o Dashboard tem tela; os demais mostram estado "em desenvolvimento", sem links quebrados.
- Responsivo: sidebar vira drawer no mobile, métricas reflow para 2 colunas e 1 coluna, alertas críticos nunca ocultos.

## 3. Dashboard

Oito indicadores exatamente como especificado em §8:

1. Pessoas cadastradas · 2. Visitantes hoje · 3. Entradas hoje · 4. Saídas hoje
5. Moradores · 6. Prestadores · 7. Veículos cadastrados · 8. Arrecadação hoje

Cada cartão com rótulo, valor, unidade, comparação, período, estado de atualização e navegação quando autorizada.

- **Acessos recentes:** lista de atividade ordenada por horário decrescente, com regra de privacidade (mascaramento de documento) e estados vazio/carregando/erro.
- **Entradas × Saídas:** gráfico com as duas séries, agrupamento por período, seguindo o padrão visual do doc §10.
- Cartões e conteúdos visíveis conforme o perfil da sessão (portaria, administrador, gestor, caixa, auditor).

## 4. Login e perfis

- Rota pública `/entrar`: marca, campos de identificação e senha, validação, mensagens de erro sem expor detalhes internos, estado de carregamento.
- Perfis de exemplo: Operador de portaria, Administrador, Gestor/síndico, Operador de caixa, Auditor.
- Rotas protegidas atrás de um layout autenticado que redireciona para `/entrar`, preservando o destino.
- Cabeçalho reflete a sessão (usuário, perfil, sair). Sair limpa a sessão e volta ao login.
- Permissões por perfil controlam itens do menu, métricas e ações visíveis.

## Estrutura técnica

```text
src/styles.css                     tokens de marca, semânticos, tipografia, espaçamento
src/components/ds/                 componentes DS-CMP-*
src/components/shell/              Sidebar, OperationalHeader, AppShell
src/components/dashboard/          MetricCard, RecentAccessList, EntradasSaidasChart
src/data/                          dados de exemplo + catálogo de perfis/permissões
src/lib/session.ts                 sessão simulada (localStorage) e helpers de permissão
src/routes/index.tsx               redireciona para /entrar ou /painel
src/routes/entrar.tsx              login
src/routes/_authenticated.tsx      guarda de rota
src/routes/_authenticated/painel.tsx  Dashboard
```

Gráfico com Recharts. Ícones Lucide (sem emojis, conforme §10.1). Cada rota com `head()` próprio (título e descrição).

## Fora desta fase

Validação de Entrada, Pré-cadastro, Cadastro de Imóvel, Administração, integrações com equipamentos/LPR/OCR, banco de dados e auditoria/outbox — entram nas fases seguintes, na ordem que você definir.
