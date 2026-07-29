# SDV ACCESS — DESIGN SYSTEM
## Fundamentos, tokens, componentes e padrões de interface

**Documento:** SDV-DSG-003  
**Versão:** 1.0.1  
**Status:** Aprovado  
**Produto:** SDV Access — Implantação Santa Rita  
**Marca proprietária:** Soluções do Vale Tecnologia  
**Responsável pelo produto:** Vinicius Velasco de Azevedo  
**Data:** Julho de 2026

---

## Controle de versões

| Versão | Data | Responsável | Alteração |
|---|---|---|---|
| 1.0.0 | Julho/2026 | Soluções do Vale | Estrutura inicial do Design System derivada do Brand Book e das referências oficiais |
| 1.0.1 | 28/07/2026 | Product Owner | Aprovação formal do Design System |

---

# 1. Objetivo

Este documento transforma o Brand Book aprovado e as referências visuais oficiais do SDV Access em regras reutilizáveis para especificação UX/UI, desenvolvimento em Blade e Livewire, testes e homologação visual.

O Design System tem como objetivos:

- preservar a identidade aprovada;
- reduzir inconsistências entre módulos;
- fornecer uma linguagem comum a produto, design, desenvolvimento e qualidade;
- estabelecer tokens e contratos de componentes;
- garantir acessibilidade e responsividade;
- padronizar estados, mensagens e ações críticas;
- permitir evolução para outras implantações sem criar temas incompatíveis;
- manter rastreabilidade entre referência, especificação e implementação.

Este documento não autoriza o desenvolvimento de telas ainda não especificadas e aprovadas. Ele define os elementos que essas telas deverão reutilizar.

---

# 2. Escopo e fontes

## 2.1 Documentos normativos

O Design System deverá ser interpretado em conjunto com:

- `docs/000_DIRETRIZES_DO_PROJETO.md`;
- `docs/001_VOLUME_01_PRODUCT_BOOK_PARTE_01.md`;
- `docs/001_VOLUME_01_PRODUCT_BOOK_PARTE_02.md`;
- `docs/001_VOLUME_01_PRODUCT_BOOK_PARTE_03.md`;
- `docs/002_BRAND_BOOK.md`;
- imagens disponíveis em `docs/references/`.

## 2.2 Precedência

1. decisões formais aprovadas prevalecem sobre textos técnicos conflitantes presentes nas imagens;
2. referências aprovadas prevalecem para composição visual, hierarquia e jornadas;
3. o Brand Book prevalece para arquitetura de marca e linguagem;
4. este documento prevalece para tokens, componentes e padrões reutilizáveis;
5. especificações UX/UI futuras detalharão as telas sem contrariar estas bases;
6. divergências relevantes deverão ser registradas, não corrigidas silenciosamente.

## 2.3 Cobertura

O sistema abrange:

- aplicação administrativa e operacional;
- portaria;
- fluxos públicos responsivos;
- relatórios e documentos gerados;
- estados de integração;
- componentes de dados pessoais e operacionais;
- personalização controlada por implantação.

Não abrange, nesta versão:

- aplicativo móvel nativo;
- materiais impressos não definidos;
- portal completo do morador;
- componentes de ERP financeiro;
- visualizações avançadas ainda não especificadas.

---

# 3. Princípios do Design System

**DS-PR-001 — Clareza operacional**  
Informações necessárias à decisão devem ser identificáveis sem interpretação ambígua.

**DS-PR-002 — Segurança visível**  
Situação, vigência, bloqueio, autorização e resultado técnico devem ser apresentados de forma explícita.

**DS-PR-003 — Consistência**  
O mesmo conceito deve manter nome, aparência e comportamento em todos os módulos.

**DS-PR-004 — Acessibilidade por padrão**  
Contraste, foco, teclado, leitura e comunicação não dependente de cor devem fazer parte do componente, não de correção posterior.

**DS-PR-005 — Responsividade orientada à tarefa**  
O layout deve preservar prioridade e fluxo, não apenas reduzir dimensões.

**DS-PR-006 — Histórico em vez de destruição**  
Componentes de remoção devem refletir encerramento, inativação ou desvinculação rastreável quando exigido pelo domínio.

**DS-PR-007 — Estado real**  
A interface não deve declarar sucesso antes da confirmação correspondente.

**DS-PR-008 — Menor privilégio**  
Componentes e ações devem respeitar permissões no servidor e refletir disponibilidade na interface.

**DS-PR-009 — Composição progressiva**  
Telas devem ser construídas por elementos reutilizáveis, sem duplicar marcação e comportamento.

**DS-PR-010 — Evolução controlada**  
Novas variantes exigem necessidade demonstrável, documentação, testes e governança.

---

# 4. Arquitetura de tokens

## 4.1 Camadas

Os tokens serão organizados em três camadas:

```text
Tokens primitivos
    ↓
Tokens semânticos
    ↓
Tokens de componente
```

Exemplo:

```text
blue.600
    ↓
color.action.primary
    ↓
button.primary.background
```

## 4.2 Convenção de nomes

Formato:

```text
categoria.propriedade.variante.estado
```

Exemplos:

- `color.text.primary`;
- `color.status.success.background`;
- `space.4`;
- `radius.md`;
- `button.primary.background.hover`;
- `field.border.invalid`;
- `sidebar.item.background.active`.

Na implementação CSS, os nomes deverão utilizar kebab-case:

```css
--color-text-primary
--button-primary-background-hover
```

## 4.3 Situação dos valores

Os valores cromáticos e tipográficos herdados do Brand Book permanecem provisórios até a obtenção dos ativos mestres. A estrutura dos tokens, seus significados e suas relações podem ser aprovados independentemente dos valores exatos.

---

# 5. Cores

## 5.1 Tokens primitivos provisórios

| Token | Valor | Uso de origem |
|---|---|---|
| `navy.900` | `#001C3D` | navegação e fundo institucional |
| `navy.800` | `#002A58` | variação escura |
| `blue.700` | `#0759B8` | azul de ênfase |
| `blue.600` | `#0867D1` | ação primária |
| `blue.500` | `#1788E8` | foco e detalhes |
| `cyan.400` | `#21A6E8` | acento tecnológico |
| `neutral.950` | `#09162D` | texto de máxima ênfase |
| `neutral.800` | `#263750` | texto principal |
| `neutral.600` | `#66748A` | texto secundário |
| `neutral.400` | `#A9B4C3` | conteúdo desabilitado |
| `neutral.300` | `#D5DDE8` | borda |
| `neutral.200` | `#E5EAF1` | divisor |
| `neutral.100` | `#F2F5F9` | superfície alternativa |
| `neutral.050` | `#F8FAFC` | fundo geral |
| `neutral.000` | `#FFFFFF` | superfície principal |
| `green.700` | `#168A3C` | texto positivo |
| `green.600` | `#20A447` | ação positiva |
| `green.100` | `#E8F7EC` | fundo positivo |
| `amber.700` | `#B66B00` | texto de atenção |
| `amber.500` | `#F2A000` | atenção |
| `amber.100` | `#FFF4D9` | fundo de atenção |
| `red.700` | `#C62D2D` | texto de erro |
| `red.600` | `#E33B35` | ação negativa |
| `red.100` | `#FDE9E7` | fundo de erro |
| `purple.600` | `#7B3FF2` | categoria temporária |

## 5.2 Tokens semânticos

| Token | Referência provisória |
|---|---|
| `color.background.app` | `neutral.050` |
| `color.background.surface` | `neutral.000` |
| `color.background.subtle` | `neutral.100` |
| `color.background.inverse` | `navy.900` |
| `color.text.primary` | `neutral.950` |
| `color.text.secondary` | `neutral.600` |
| `color.text.inverse` | `neutral.000` |
| `color.text.link` | `blue.700` |
| `color.border.default` | `neutral.300` |
| `color.border.subtle` | `neutral.200` |
| `color.action.primary` | `blue.600` |
| `color.action.primary.hover` | `blue.700` |
| `color.focus` | `blue.500` |
| `color.status.success.foreground` | `green.700` |
| `color.status.success.background` | `green.100` |
| `color.status.warning.foreground` | `amber.700` |
| `color.status.warning.background` | `amber.100` |
| `color.status.danger.foreground` | `red.700` |
| `color.status.danger.background` | `red.100` |
| `color.status.info.foreground` | `blue.700` |
| `color.status.info.background` | `#EAF3FF` |

## 5.3 Regras

- nenhum estado dependerá somente da cor;
- texto normal deverá atender contraste mínimo de 4,5:1;
- texto grande e elementos gráficos essenciais deverão atender contraste mínimo de 3:1;
- foco deverá ser visível sobre superfícies claras e escuras;
- cores de implantação não substituirão tokens semânticos;
- vermelho será reservado a negativa, erro ou ação de alto impacto;
- verde será reservado a validade, confirmação, sucesso ou liberação;
- âmbar indicará espera, atenção ou continuação posterior;
- azul indicará ação primária, seleção ou informação.

---

# 6. Tipografia

## 6.1 Família

A família oficial permanece condicionada a `PEN-BR-005`. Até sua definição, a implementação deverá utilizar token, nunca o nome da fonte diretamente no componente:

```css
--font-family-sans: system-ui, -apple-system, "Segoe UI", sans-serif;
--font-family-mono: ui-monospace, "Cascadia Mono", monospace;
```

A fonte monoespaçada será restrita a identificadores técnicos quando melhorar a leitura. CPF, placa e protocolo não precisam ser monoespaçados por padrão.

## 6.2 Pesos

| Token | Valor |
|---|---:|
| `font.weight.regular` | 400 |
| `font.weight.medium` | 500 |
| `font.weight.semibold` | 600 |
| `font.weight.bold` | 700 |

## 6.3 Escala tipográfica provisória

Base de `16 px`, respeitando preferências do navegador.

| Token | Tamanho | Linha | Uso |
|---|---:|---:|---|
| `font.size.xs` | `0.75rem` | `1rem` | metadados e apoio |
| `font.size.sm` | `0.875rem` | `1.25rem` | campos, tabela e texto secundário |
| `font.size.md` | `1rem` | `1.5rem` | corpo padrão |
| `font.size.lg` | `1.125rem` | `1.625rem` | subtítulo e dado em destaque |
| `font.size.xl` | `1.25rem` | `1.75rem` | título de seção |
| `font.size.2xl` | `1.5rem` | `2rem` | título de página |
| `font.size.3xl` | `1.875rem` | `2.375rem` | destaque institucional |

## 6.4 Estilos semânticos

| Estilo | Composição |
|---|---|
| `text.page-title` | `2xl`, semibold, texto primário |
| `text.section-title` | `xl`, semibold, texto primário |
| `text.card-title` | `md`, semibold, texto primário |
| `text.body` | `md`, regular, texto primário |
| `text.body-small` | `sm`, regular, texto secundário |
| `text.label` | `sm`, medium, texto primário |
| `text.caption` | `xs`, regular, texto secundário |
| `text.metric` | `2xl`, semibold, texto primário |
| `text.button` | `sm` ou `md`, semibold |

## 6.5 Conteúdo numérico

Valores monetários, horários, placas, protocolos e totais deverão:

- permanecer legíveis;
- utilizar algarismos tabulares quando a fonte suportar;
- alinhar valores comparáveis;
- preservar formatação brasileira;
- não depender apenas de peso para indicar estado.

---

# 7. Espaçamento e dimensionamento

## 7.1 Escala

A escala utiliza unidade-base de `4 px`.

| Token | Valor |
|---|---:|
| `space.0` | `0` |
| `space.1` | `4px` |
| `space.2` | `8px` |
| `space.3` | `12px` |
| `space.4` | `16px` |
| `space.5` | `20px` |
| `space.6` | `24px` |
| `space.8` | `32px` |
| `space.10` | `40px` |
| `space.12` | `48px` |
| `space.16` | `64px` |

## 7.2 Regras

- usar somente valores da escala, salvo exceção documentada;
- elementos relacionados devem ficar mais próximos entre si do que de outros grupos;
- cartões usarão preenchimento entre `space.4` e `space.6`;
- grupos de formulário usarão intervalo mínimo de `space.4`;
- ações no rodapé usarão intervalo mínimo de `space.3`;
- áreas de toque deverão ter pelo menos `44 × 44 px`;
- densidade compacta não reduzirá área interativa abaixo do mínimo.

## 7.3 Alturas de controle

| Token | Valor | Uso |
|---|---:|---|
| `control.height.sm` | `36px` | tabelas e filtros compactos |
| `control.height.md` | `44px` | padrão |
| `control.height.lg` | `52px` | fluxos públicos ou ação destacada |

---

# 8. Grade, contêineres e responsividade

## 8.1 Grade

A aplicação deverá utilizar grade fluida de 12 colunas em telas amplas, com:

- margem externa responsiva;
- intervalo entre colunas baseado em `space.4` ou `space.6`;
- largura máxima definida por contexto;
- cartões alinhados à grade;
- coluna lateral contextual quando demonstrada nas referências.

## 8.2 Breakpoints provisórios

| Token | Largura mínima | Uso |
|---|---:|---|
| `breakpoint.sm` | `640px` | celulares amplos |
| `breakpoint.md` | `768px` | tablets |
| `breakpoint.lg` | `1024px` | notebook e navegação persistente |
| `breakpoint.xl` | `1280px` | desktop operacional |
| `breakpoint.2xl` | `1536px` | desktop amplo |

Breakpoints deverão responder ao conteúdo; os valores poderão ser ajustados após protótipos sem alterar a lógica mobile-first.

## 8.3 Comportamento por faixa

**Abaixo de `md`:**

- uma coluna principal;
- menu lateral substituído por navegação temporária;
- ações críticas fixas somente se não cobrirem conteúdo;
- tabelas convertidas para cartões ou rolagem controlada;
- etapas exibidas de forma compacta;
- painéis laterais movidos para fluxo principal.

**Entre `md` e `lg`:**

- uma ou duas colunas conforme tarefa;
- navegação recolhível;
- filtros em painel;
- cartões de resumo reorganizados.

**A partir de `lg`:**

- menu lateral persistente;
- conteúdo principal e painel contextual;
- tabelas completas;
- agrupamento horizontal de ações.

## 8.4 Fluxos públicos

Pré-cadastro será mobile-first. Não deverá reproduzir seis telas lado a lado como na prancha; cada etapa ocupará a largura disponível e preservará progresso, retorno e dados.

---

# 9. Forma, borda, elevação e movimento

## 9.1 Raios

| Token | Valor | Uso |
|---|---:|---|
| `radius.sm` | `4px` | indicadores compactos |
| `radius.md` | `8px` | campos e botões |
| `radius.lg` | `12px` | cartões e painéis |
| `radius.full` | `9999px` | avatar e badge circular |

## 9.2 Bordas

| Token | Valor |
|---|---|
| `border.width.default` | `1px` |
| `border.width.strong` | `2px` |
| `border.color.default` | `color.border.default` |
| `border.color.focus` | `color.focus` |

## 9.3 Elevação

Sombras devem ser discretas. Bordas e superfícies serão preferidas para organizar conteúdo.

| Token | Uso |
|---|---|
| `shadow.none` | conteúdo plano |
| `shadow.sm` | cartão sobre fundo alternativo |
| `shadow.md` | menu temporário, popover e painel elevado |
| `shadow.lg` | modal |

## 9.4 Movimento

| Token | Valor |
|---|---:|
| `motion.duration.fast` | `120ms` |
| `motion.duration.normal` | `200ms` |
| `motion.duration.slow` | `300ms` |
| `motion.easing.standard` | `ease-out` |

Animações:

- devem indicar relação espacial ou mudança de estado;
- não podem atrasar ação operacional;
- devem respeitar `prefers-reduced-motion`;
- não devem ser contínuas em telas de portaria;
- não podem substituir feedback textual.

---

# 10. Iconografia e imagens

## 10.1 Ícones

A família oficial permanece pendente. Todos os ícones deverão:

- ser SVG controlado pelo projeto;
- usar traço e dimensões consistentes;
- herdar cor do contexto quando aplicável;
- possuir nome acessível quando transmitirem informação;
- ser decorativos para tecnologia assistiva quando acompanhados de rótulo equivalente;
- evitar emojis como elemento de interface.

Tamanhos:

| Token | Valor | Uso |
|---|---:|---|
| `icon.size.sm` | `16px` | campo e tabela |
| `icon.size.md` | `20px` | botão e menu |
| `icon.size.lg` | `24px` | título e ação destacada |
| `icon.size.xl` | `32px` | estado vazio |

## 10.2 Avatares e retratos

| Variante | Dimensão sugerida |
|---|---:|
| `avatar.xs` | `24px` |
| `avatar.sm` | `32px` |
| `avatar.md` | `40px` |
| `avatar.lg` | `64px` |
| `avatar.profile` | responsiva, proporção definida na UX/UI |

Retratos deverão usar `object-fit: cover`, texto alternativo contextual e fallback não estigmatizante.

## 10.3 Documento e veículo

- miniaturas devem preservar proporção;
- visualização ampliada deverá ocorrer em componente protegido;
- placa reconhecida deve possuir delimitação que não encubra caracteres;
- imagem capturada deve ser identificada como evidência operacional;
- carregamento deverá utilizar skeleton ou espaço reservado para evitar deslocamento.

---

# 11. Estrutura da aplicação

## 11.1 App shell

O app shell é composto por:

```text
Navegação lateral
        +
Cabeçalho operacional
        +
Área principal
        +
Painel contextual opcional
        +
Região de notificações
```

## 11.2 Navegação lateral

**Componente:** `DS-CMP-001 Sidebar`

Responsabilidades:

- exibir marca do produto;
- agrupar módulos;
- filtrar itens por permissão;
- indicar página atual;
- apresentar usuário e saída;
- admitir modo recolhido e temporário.

Estados do item:

- padrão;
- hover;
- foco;
- ativo;
- desabilitado;
- com contador;
- com submenu expandido.

O servidor deverá controlar a autorização; ocultar o item não substitui a verificação de permissão.

## 11.3 Cabeçalho operacional

**Componente:** `DS-CMP-002 Operational Header`

Pode conter:

- título e descrição;
- breadcrumb;
- data e hora;
- situação do caixa;
- notificações;
- identificação do usuário;
- ações globais autorizadas.

Em telas menores, informações secundárias deverão ser recolhidas sem ocultar alertas críticos.

## 11.4 Conteúdo e painel contextual

O conteúdo principal concentra a tarefa. O painel contextual apresenta resumo, vínculos, histórico ou resultado, sem duplicar campos editáveis.

---

# 12. Catálogo de componentes fundamentais

## 12.1 Ações

### DS-CMP-003 — Botão

Variantes:

- `primary`;
- `secondary`;
- `tertiary`;
- `success`;
- `warning`;
- `danger`;
- `ghost`;
- `icon-only`.

Estados:

- padrão;
- hover;
- foco;
- pressionado;
- carregando;
- desabilitado.

Regras:

- botão principal deve ser único por região;
- carregamento deve manter largura e impedir duplicidade;
- `icon-only` exige nome acessível e tooltip quando necessário;
- ação crítica exige rótulo específico;
- link não deve ser estilizado como botão quando apenas navega, salvo padrão consistente.

### DS-CMP-004 — Grupo de ações

Organiza ações primária, secundária e negativa. Em celular:

- ações podem empilhar;
- ordem visual deve preservar risco e frequência;
- negativa não deve ficar próxima da confirmação sem separação;
- largura total é permitida em fluxos públicos.

## 12.2 Formulários

### DS-CMP-005 — Campo de texto

Anatomia:

```text
Rótulo
Controle
Texto auxiliar ou erro
Contador opcional
```

Estados:

- vazio;
- preenchido;
- foco;
- inválido;
- válido quando necessário;
- somente leitura;
- desabilitado;
- carregando.

Placeholder não substitui rótulo.

### DS-CMP-006 — Seleção

Aplicável a listas curtas e controladas. Para grandes volumes, usar busca ou autocomplete.

### DS-CMP-007 — Autocomplete

Usado para:

- imóvel;
- pessoa;
- empresa;
- responsável;
- veículo.

Deverá suportar:

- digitação;
- carregamento;
- nenhum resultado;
- seleção por teclado;
- resultado com identificadores suficientes;
- prevenção de seleção ambígua.

### DS-CMP-008 — Data e período

- exibir formato brasileiro;
- manter valor interno inequívoco;
- permitir digitação e calendário;
- indicar fuso quando relevante;
- validar início e término;
- explicar prazo indeterminado quando permitido.

### DS-CMP-009 — Checkbox, radio e switch

- checkbox: seleção independente;
- radio: escolha exclusiva;
- switch: alteração imediata de estado somente quando o efeito for claro e reversível.

Ações de alto impacto não deverão usar switch sem confirmação.

### DS-CMP-010 — Upload e captura

Aplicável a documento, selfie e imagem.

Deverá exibir:

- tipo aceito;
- limite;
- progresso;
- pré-visualização protegida;
- resultado técnico;
- opção de substituir;
- erro e orientação;
- origem da captura quando necessária.

## 12.3 Informação e estado

### DS-CMP-011 — Badge de status

Combina cor, texto e, quando útil, ícone.

Variantes:

- neutro;
- informação;
- sucesso;
- atenção;
- perigo;
- categoria.

O texto deve usar o estado do domínio, não uma descrição genérica.

### DS-CMP-012 — Alerta

Variantes:

- informação;
- sucesso;
- atenção;
- erro.

Anatomia:

- ícone;
- título opcional;
- mensagem;
- ação opcional;
- fechar somente quando o alerta não for bloqueador.

### DS-CMP-013 — Toast

Usado para confirmação transitória. Não deverá:

- ser o único registro de erro;
- ocultar ação necessária;
- desaparecer antes de leitura razoável;
- confirmar comando externo ainda não respondido.

### DS-CMP-014 — Estado vazio

Deverá explicar:

- o que não existe;
- por que pode estar vazio;
- qual ação é possível;
- se filtros estão limitando os resultados.

### DS-CMP-015 — Skeleton e progresso

- skeleton para carregamento previsível;
- spinner para ação curta e localizada;
- barra para upload ou processo mensurável;
- texto para processos demorados.

## 12.4 Estrutura de conteúdo

### DS-CMP-016 — Card

Variantes:

- padrão;
- resumo;
- métrica;
- status;
- selecionável;
- crítico.

Cartão não deverá ser clicável se também contiver ações concorrentes sem separação clara.

### DS-CMP-017 — Tabela

Deverá suportar:

- cabeçalho;
- ordenação;
- filtros;
- paginação;
- seleção quando necessária;
- ações por linha;
- estado vazio;
- carregamento;
- erro;
- leitura por teclado;
- alternativa responsiva.

Dados numéricos serão alinhados de forma consistente. A primeira coluna deverá identificar claramente o registro.

### DS-CMP-018 — Lista de atividade

Usada para acessos, auditoria e histórico. Cada item deverá informar:

- evento;
- data e hora;
- ator ou origem;
- resultado;
- ponto de acesso quando aplicável;
- acesso ao detalhe conforme permissão.

### DS-CMP-019 — Métrica

Composta por:

- rótulo;
- valor;
- comparação opcional;
- período;
- estado de dados.

Não usar indicador decorativo sem fonte rastreável.

## 12.5 Navegação e fluxo

### DS-CMP-020 — Breadcrumb

Mostra localização, não histórico de navegação. Em telas pequenas poderá ser reduzido, preservando retorno compreensível.

### DS-CMP-021 — Tabs

Usadas para visões equivalentes do mesmo contexto. Não usar como substituto de etapas obrigatórias.

### DS-CMP-022 — Stepper

Usado em cadastro e pré-cadastro.

Estados:

- futuro;
- atual;
- concluído;
- com erro;
- desabilitado.

Deverá preservar dados e permitir retorno às etapas autorizadas.

### DS-CMP-023 — Paginação

Deverá informar:

- intervalo exibido;
- total conhecido;
- página atual;
- tamanho da página;
- controles acessíveis.

## 12.6 Sobreposições

### DS-CMP-024 — Modal

Reservado a:

- confirmação;
- edição curta;
- detalhe contextual;
- decisão bloqueadora.

Não usar modal para jornadas longas.

### DS-CMP-025 — Drawer

As referências utilizam painel lateral para detalhe de pré-cadastro. O drawer deverá:

- manter contexto da lista;
- possuir título e fechamento;
- gerenciar foco;
- impedir interação acidental com conteúdo de fundo quando modal;
- reorganizar-se em tela completa no celular.

### DS-CMP-026 — Tooltip

Complementa, nunca substitui, rótulo essencial. Deve funcionar com teclado e toque quando aplicável.

---

# 13. Componentes de domínio

## 13.1 DS-CMP-027 — Seletor de tipo de acesso

Apresenta:

- morador;
- inquilino;
- prestador;
- visitante;
- turista.

Cada opção combina ícone, nome e descrição. A seleção altera campos e regras, mas não deve descartar dados sem confirmação.

## 13.2 DS-CMP-028 — Resumo de pessoa

Pode conter:

- foto;
- nome;
- nome social quando aplicável;
- documento mascarado conforme permissão;
- tipo de acesso;
- imóvel;
- responsável;
- status;
- vigência.

## 13.3 DS-CMP-029 — Painel de vínculo

Exibe:

- imóvel;
- natureza;
- responsabilidade;
- início e término;
- situação;
- permissões derivadas;
- ações de suspensão ou encerramento.

Pessoa ativa não implica vínculo ativo.

## 13.4 DS-CMP-030 — Cartão de veículo

Exibe:

- placa;
- marca e modelo;
- cor;
- proprietário ou responsável;
- vínculo;
- situação;
- ação de consultar ou alterar.

## 13.5 DS-CMP-031 — Comparador LPR

Deverá separar:

- imagem capturada;
- placa reconhecida;
- placa cadastrada;
- confiança;
- dados do veículo;
- divergência;
- decisão.

Baixa confiança ou divergência não poderá ser representada como sucesso.

## 13.6 DS-CMP-032 — Estado de sincronização

Estados:

- não enviado;
- aguardando;
- enviado;
- sincronizado;
- falha;
- removido;
- atualização pendente.

Deverá informar equipamento, última tentativa e ação autorizada.

## 13.7 DS-CMP-033 — Decisão de acesso

Agrupa:

- negar entrada;
- salvar sem liberar;
- validar e liberar.

Regras:

- ações com cores e rótulos distintos;
- negativa exige motivo;
- salvar sem liberar não aciona equipamento;
- validar e liberar informa as etapas de registro, comando e confirmação;
- falha de comando permanece distinta da autorização.

## 13.8 DS-CMP-034 — Contribuição

Apresenta:

- contribui;
- não contribui;
- isento;
- valor;
- forma de pagamento;
- pagador;
- caixa;
- resumo.

Deve deixar claro que contribuição é parte operacional, não autorização por pagamento.

## 13.9 DS-CMP-035 — Resumo de caixa

Exibe:

- operador;
- terminal;
- abertura;
- saldo inicial;
- entradas;
- saídas;
- cancelamentos;
- saldo esperado;
- total informado;
- diferença.

## 13.10 DS-CMP-036 — Protocolo

O protocolo deverá:

- ser selecionável e copiável;
- possuir leitura clara;
- não ser o único meio de localizar o registro;
- apresentar situação e data;
- evitar exposição de dados pessoais no identificador.

---

# 14. Padrões de formulários

## 14.1 Organização

Formulários deverão:

- agrupar campos por assunto;
- apresentar obrigatoriedade antes do envio;
- manter campos condicionais próximos da escolha que os habilita;
- preservar dados em navegação por etapas;
- evitar dupla digitação;
- usar dados do imóvel em vez de repetir endereço para moradores;
- indicar dados herdados ou somente leitura.

## 14.2 Validação

Validação deve ocorrer:

- no servidor em todos os casos;
- no cliente como apoio imediato;
- ao sair do campo quando útil;
- no envio;
- novamente antes de ação crítica.

Mensagens deverão:

- identificar o campo;
- explicar o problema;
- orientar correção;
- preservar o valor informado quando seguro;
- mover foco ou fornecer resumo de erros no envio.

## 14.3 Máscaras

Máscaras são apresentação, não armazenamento.

Aplicações:

- CPF;
- telefone;
- CEP;
- moeda;
- placa quando útil;
- datas e horários.

O sistema deverá aceitar colagem e tecnologias assistivas sem exigir digitação artificial.

## 14.4 Salvamento

Estados possíveis:

- não alterado;
- alterado;
- salvando;
- salvo;
- falhou;
- conflito de atualização.

“Salvar rascunho” e “Salvar e ativar” são ações diferentes e não poderão compartilhar confirmação ambígua.

## 14.5 Cancelamento e saída

Ao sair com alterações não salvas:

- avisar;
- permitir permanecer;
- permitir descartar conforme autorização;
- não gerar vínculo ou autorização parcial.

---

# 15. Padrões de dados, tabelas e filtros

## 15.1 Busca

A busca deverá:

- indicar campos pesquisáveis;
- tolerar normalização de CPF e placa;
- permitir limpar;
- informar quantidade de resultados;
- não executar consulta pesada a cada tecla sem controle;
- preservar filtros ao abrir e fechar detalhes quando útil.

## 15.2 Filtros

Filtros deverão:

- possuir rótulo;
- indicar estado aplicado;
- permitir limpeza total;
- manter valores após atualização Livewire;
- diferenciar período de data única;
- respeitar permissões.

## 15.3 Ações por linha

- ações frequentes podem ficar visíveis;
- ações secundárias podem usar menu;
- ícones exigem tooltip e nome acessível;
- ação destrutiva aparente deverá usar linguagem do domínio;
- nenhuma ação será autorizada apenas pela presença do botão.

## 15.4 Exportação

O componente deverá:

- informar formato;
- respeitar filtros;
- alertar sobre dados sensíveis quando aplicável;
- registrar auditoria;
- apresentar preparação e conclusão;
- não expor URL pública permanente.

---

# 16. Feedback, estados e mensagens

## 16.1 Hierarquia

1. erro ou bloqueio crítico;
2. pendência que impede conclusão;
3. alerta operacional;
4. informação;
5. confirmação.

## 16.2 Confirmação de ação crítica

Confirmação deverá indicar:

- objeto afetado;
- consequência;
- possibilidade de reversão;
- justificativa quando necessária;
- ação principal específica.

Evitar “Tem certeza?” sem contexto.

## 16.3 Falhas externas

Para equipamentos:

```text
Autorização validada
        ↓
Comando enviado
        ↓
Confirmação recebida ou falha
```

A interface deverá mostrar cada estágio. Não poderá apresentar “Acesso liberado” somente porque o comando foi enviado.

## 16.4 Operação concorrente

Quando dois usuários alterarem o mesmo registro:

- detectar versão incompatível;
- preservar dados do usuário;
- explicar o conflito;
- permitir recarregar ou comparar quando necessário;
- registrar a decisão final.

---

# 17. Acessibilidade

## 17.1 Requisitos mínimos

Componentes deverão cumprir:

- HTML semântico;
- nome, função e estado acessíveis;
- foco visível;
- ordem de tabulação lógica;
- operação por teclado;
- contraste adequado;
- mensagens associadas aos campos;
- regiões dinâmicas anunciadas quando necessário;
- títulos hierárquicos;
- alvos de toque mínimos;
- redução de movimento;
- zoom e redimensionamento sem perda essencial.

## 17.2 Livewire e foco

Atualizações Livewire deverão:

- preservar foco quando o contexto não mudou;
- mover foco intencionalmente ao abrir modal ou avançar etapa;
- anunciar resultado de salvamento e erros;
- evitar substituição ampla do DOM sem necessidade;
- manter identificação estável dos controles.

## 17.3 Testes

Cada componente deverá ser verificado com:

- teclado;
- leitor de tela em cenários críticos;
- zoom de 200%;
- contraste;
- redução de movimento;
- mensagens de erro;
- dispositivo móvel para fluxos públicos.

---

# 18. Responsividade por componente

| Componente | Desktop | Celular |
|---|---|---|
| Sidebar | persistente | drawer temporário |
| Header | contexto completo | título, alertas e ações prioritárias |
| Grade de métricas | múltiplas colunas | uma ou duas colunas |
| Formulário | colunas relacionadas | uma coluna |
| Painel contextual | lateral | abaixo ou tela completa |
| Tabela | colunas completas | cartões ou rolagem controlada |
| Stepper | etapas com rótulo | etapa atual e progresso compacto |
| Grupo de ações | horizontal | empilhado ou largura total |
| Modal | largura limitada | quase tela completa |
| Drawer | lateral | tela completa |
| Comparador LPR | imagem e dados lado a lado | imagem seguida de dados |

Nenhum conteúdo crítico poderá existir apenas por hover.

---

# 19. Diretrizes para Blade e Livewire

## 19.1 Arquitetura

A camada visual deverá utilizar:

- layouts Blade para app shell e estruturas compartilhadas;
- componentes Blade para elementos predominantemente de apresentação;
- componentes Livewire para estado de interface, busca, filtros, formulários e jornadas interativas;
- serviços e políticas do backend para regras de negócio e autorização;
- JavaScript pontual somente para comportamento de interface que não deva residir no servidor.

React não será utilizado no MVP.

## 19.2 CSS

Tokens deverão ser publicados como CSS custom properties.

Estrutura sugerida:

```text
resources/
└── css/
    ├── tokens/
    │   ├── colors.css
    │   ├── typography.css
    │   ├── spacing.css
    │   ├── shape.css
    │   └── motion.css
    ├── foundations/
    ├── components/
    └── app.css
```

A escolha entre CSS próprio, Tailwind, Bootstrap ou outra ferramenta não está aprovada por este documento e deverá ser registrada antes da implementação.

## 19.3 Nomes de componentes

Convenção sugerida:

```text
resources/views/components/ds/button.blade.php
resources/views/components/ds/status-badge.blade.php
app/Livewire/Access/EntryValidation.php
```

O prefixo `ds` separa componentes do Design System de componentes exclusivos de uma página.

## 19.4 Contrato

Cada componente deverá documentar:

- finalidade;
- propriedades;
- slots;
- variantes;
- estados;
- eventos;
- permissões relevantes;
- acessibilidade;
- comportamento responsivo;
- exemplos corretos e incorretos;
- testes.

## 19.5 Segurança

- propriedades vindas do usuário deverão ser escapadas;
- HTML arbitrário não será aceito sem política explícita;
- visibilidade de botão não substitui `Policy`, `Gate` ou autorização equivalente;
- downloads deverão usar autorização e URL temporária;
- mensagens não deverão expor segredos ou detalhes internos;
- estado Livewire não será tratado como fonte confiável sem validação no servidor.

## 19.6 Idempotência

Botões de ação crítica deverão:

- bloquear reenvio durante processamento;
- usar identificador idempotente quando aplicável;
- apresentar estado de processamento;
- lidar com timeout sem afirmar falha ou sucesso indevido;
- permitir consulta posterior do resultado.

---

# 20. Documentação e catálogo

O projeto deverá manter catálogo navegável de componentes, mesmo que inicialmente interno.

Cada entrada deverá conter:

- nome e identificador;
- descrição;
- anatomia;
- variantes;
- estados;
- exemplos;
- conteúdo recomendado;
- acessibilidade;
- responsividade;
- dependências;
- versão;
- responsável.

O catálogo poderá ser implementado como rota interna protegida ou ferramenta de documentação aprovada. A adoção de Storybook não é pressuposta, pois React não integra o MVP e nenhuma ferramenta de catálogo foi aprovada.

---

# 21. Testes e homologação

## 21.1 Testes de componente

Verificar:

- renderização;
- variantes;
- estados;
- atributos acessíveis;
- permissões;
- eventos;
- mensagens;
- carregamento;
- erro;
- responsividade.

## 21.2 Testes visuais

Comparações deverão usar:

- especificação UX/UI aprovada;
- referências oficiais;
- viewport identificado;
- dados representativos;
- estados normais e críticos;
- tolerância documentada.

Mudança intencional deverá atualizar a referência de teste após aprovação, nunca automaticamente.

## 21.3 Testes de interação

Cenários prioritários:

- navegação por teclado;
- envio com erros;
- salvamento e falha;
- modal e retorno de foco;
- filtros e paginação;
- etapas com retorno;
- ação duplicada;
- timeout de integração;
- permissão negada;
- atualização concorrente.

## 21.4 Critérios gerais de aceite

Um componente será aceito quando:

1. possuir finalidade clara;
2. usar tokens, sem valores arbitrários;
3. atender variantes necessárias;
4. funcionar com teclado;
5. possuir contraste e rótulos adequados;
6. responder às faixas previstas;
7. manter autorização no servidor;
8. tratar carregamento, vazio e erro;
9. possuir testes proporcionais ao risco;
10. estar documentado no catálogo;
11. corresponder à especificação aprovada;
12. não introduzir dependência arquitetural não decidida.

---

# 22. Governança

## 22.1 Ciclo de componente

```text
Proposto
   ↓
Em avaliação
   ↓
Experimental
   ↓
Estável
   ↓
Descontinuado
   ↓
Removido em versão compatível
```

Componentes experimentais não deverão sustentar jornada crítica sem aceite formal.

## 22.2 Inclusão ou alteração

Uma proposta deverá informar:

- problema;
- casos de uso;
- componentes existentes avaliados;
- variantes;
- impacto visual;
- acessibilidade;
- impacto técnico;
- migração;
- testes;
- documentos afetados.

## 22.3 Responsabilidades

| Papel | Responsabilidade |
|---|---|
| Product Owner | aprovar mudanças que afetem produto ou jornada |
| UX/UI | manter padrões, especificações e exemplos |
| Desenvolvimento | implementar contratos e reportar limitações |
| Qualidade | validar comportamento, visual e acessibilidade |
| Segurança | revisar componentes de dados sensíveis e ações críticas |
| Governança de marca | validar ativos e tokens de identidade |

## 22.4 Versionamento

- correção compatível: patch;
- novo componente ou variante compatível: minor;
- alteração incompatível: major;
- descontinuação deverá possuir aviso e caminho de migração.

---

# 23. Matriz inicial de componentes por jornada

| Jornada | Componentes principais |
|---|---|
| Dashboard | `DS-CMP-001`, `002`, `016`, `017`, `018`, `019` |
| Cadastro de pessoa | `003` a `010`, `011`, `016`, `020`, `022`, `027` a `030`, `032` |
| Pré-cadastro | `003` a `010`, `012`, `015`, `022`, `036` |
| Análise pela portaria | `001`, `002`, `005` a `008`, `011`, `017`, `021`, `025`, `028`, `030`, `036` |
| Validação de entrada | `001` a `004`, `011` a `013`, `016`, `028`, `031`, `033`, `034` |
| Reconhecimento facial | `011` a `013`, `015`, `028`, `032` |
| Leitura de placas | `011` a `013`, `015`, `030`, `031` |
| Caixa | `003` a `009`, `011`, `016` a `019`, `034`, `035` |
| Relatórios | `002`, `003`, `005` a `008`, `014`, `017`, `019`, `023` |
| Administração | `001` a `009`, `011` a `018`, `020`, `021`, `024` |

Esta matriz não substitui a especificação de cada tela.

---

# 24. Pendências abertas

| PEN-DS | Pendência | Impacto | Encaminhamento |
|---|---|---|---|
| PEN-DS-001 | Confirmar cores oficiais a partir dos arquivos mestres | Tokens cromáticos são provisórios | Resolver `PEN-BR-004` |
| PEN-DS-002 | Definir e licenciar tipografia oficial | Escala existe, família não | Resolver `PEN-BR-005` |
| PEN-DS-003 | Selecionar família oficial de ícones | Catálogo visual incompleto | Resolver `PEN-BR-006` |
| PEN-DS-004 | Obter ativos vetoriais e variantes de marca | App shell e login dependem dos ativos | Resolver `PEN-BR-001` a `003` |
| PEN-DS-005 | Decidir estratégia CSS e ferramenta de build visual | Afeta implementação e catálogo | Decisão técnica ou ADR |
| PEN-DS-006 | Confirmar breakpoints com protótipos e equipamentos reais | Valores atuais são provisórios | Testes UX/UI |
| PEN-DS-007 | Definir densidade operacional para terminais da portaria | Afeta tabela e validação | Levantamento de hardware |
| PEN-DS-008 | Definir navegadores, versões e dispositivos suportados | Afeta CSS, testes e suporte | Matriz de compatibilidade |
| PEN-DS-009 | Aprovar padrão de modal, drawer e notificações | Afeta interação e acessibilidade | Protótipos UX/UI |
| PEN-DS-010 | Definir comportamento responsivo das tabelas por módulo | Há diferentes densidades de dados | Especificações UX/UI |
| PEN-DS-011 | Confirmar se haverá modo de alto contraste | Afeta tokens e preferências | Avaliação de acessibilidade |
| PEN-DS-012 | Definir política de temas por implantação | Afeta multicliente e marca | Decisão de produto |
| PEN-DS-013 | Definir ferramenta de testes de regressão visual | Afeta homologação contínua | Decisão técnica |
| PEN-DS-014 | Definir catálogo vivo e ambiente de documentação | Afeta governança | Decisão técnica |
| PEN-DS-015 | Validar contraste dos valores definitivos | Obrigatório antes da implementação final | Auditoria após tokens oficiais |
| PEN-DS-016 | Definir padrões de gráficos e visualização de dados | Dashboard possui gráficos nas referências | UX/UI do Dashboard |
| PEN-DS-017 | Definir padrão de impressão e PDF | Afeta relatórios | UX/UI de Relatórios |
| PEN-DS-018 | Definir máscara e apresentação de dados pessoais por perfil | Afeta privacidade e componentes | Regras de negócio e segurança |

---

# 25. Decisões consolidadas

Ficam estabelecidos:

- tokens em camadas primitivas, semânticas e de componente;
- CSS custom properties como contrato de valores;
- escala de espaçamento baseada em 4 px;
- componentes acessíveis e responsivos por padrão;
- navegação lateral escura e superfícies claras;
- estado comunicado por cor, texto e ícone quando útil;
- formulários com rótulos persistentes;
- pré-cadastro mobile-first;
- componentes de domínio distintos para pessoa, vínculo, veículo, credencial, decisão e evento;
- falha de equipamento separada da autorização;
- exclusão visual traduzida conforme a regra de histórico;
- Blade para estruturas reutilizáveis e Livewire para interação;
- autorização e validação mantidas no servidor;
- nenhuma biblioteca CSS ou catálogo externo escolhido silenciosamente;
- React fora do MVP;
- componentes versionados, documentados e testados;
- valores exatos de marca condicionados aos ativos oficiais.

---

# 26. Critérios de aprovação do Design System

Este documento poderá ser aprovado quando:

1. princípios e arquitetura de tokens estiverem aceitos;
2. paleta provisória estiver corretamente identificada;
3. escalas de tipografia, espaço, forma e movimento estiverem adequadas;
4. estrutura responsiva refletir as jornadas;
5. catálogo cobrir componentes fundamentais e de domínio;
6. padrões de formulário, dados, feedback e acessibilidade estiverem completos;
7. diretrizes Blade/Livewire respeitarem as decisões técnicas;
8. governança e testes forem viáveis;
9. pendências estiverem rastreadas;
10. o documento puder orientar as especificações UX/UI sem substituir sua aprovação.

## 26.1 Registro de aprovação

| Papel | Nome | Decisão | Data | Observações |
|---|---|---|---|---|
| Product Owner | Vinicius Velasco de Azevedo | Aprovado | 28/07/2026 | Design System aprovado como base para as especificações UX/UI |
| Soluções do Vale | Representante designado | Ciente | 28/07/2026 | Aprovação registrada no repositório oficial |

---

# 27. Próximos documentos

Após a aprovação deste Design System, deverão ser produzidas as especificações UX/UI:

1. `docs/004_UX_UI_DASHBOARD.md`;
2. `docs/005_UX_UI_VALIDACAO.md`;
3. `docs/006_UX_UI_PRE_CADASTRO.md`;
4. `docs/007_UX_UI_CADASTRO_IMOVEL.md`;
5. `docs/008_ADMINISTRACAO.md`.

Cada documento deverá:

- identificar referências visuais;
- mapear regras e requisitos;
- especificar layout por viewport;
- utilizar os componentes deste Design System;
- documentar estados, exceções e permissões;
- definir conteúdo e mensagens;
- fornecer critérios de aceite funcional, visual e acessível.

---

## Situação do documento

Este Design System consolida os fundamentos e contratos iniciais da interface do SDV Access e encontra-se **aprovado**. Os valores provisórios e as decisões técnicas pendentes permanecem rastreados e deverão ser resolvidos antes da implementação definitiva dos componentes afetados, sem invalidar esta aprovação documental.
