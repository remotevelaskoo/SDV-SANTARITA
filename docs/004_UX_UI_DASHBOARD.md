# SDV ACCESS — UX/UI DO DASHBOARD
## Especificação funcional, visual e responsiva

**Documento:** SDV-UXD-004  
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
| 1.0.0 | Julho/2026 | Soluções do Vale | Especificação inicial do Dashboard baseada na referência visual aprovada |
| 1.0.1 | 28/07/2026 | Product Owner | Aprovação formal da especificação UX/UI do Dashboard |

---

# 1. Objetivo

Este documento especifica a experiência do Dashboard do SDV Access para a implantação Santa Rita.

O Dashboard deverá:

- oferecer visão operacional imediata;
- apresentar indicadores originados de dados reais e rastreáveis;
- destacar acessos recentes e variações do dia;
- permitir navegação rápida para módulos autorizados;
- adaptar informações ao perfil do usuário;
- sinalizar dados indisponíveis, desatualizados ou parciais;
- preservar a composição visual aprovada;
- funcionar em desktop, tablet e celular conforme o contexto.

O Dashboard não substitui relatórios, auditoria ou telas de operação. Seu papel é resumir a situação e direcionar o usuário ao detalhe autorizado.

---

# 2. Fontes e rastreabilidade

## 2.1 Referência visual

**REF-UXD-001:** `docs/references/01-cadastro-pessoa-dados.png`

A área superior direita da prancha, identificada como **“01. DASHBOARD”**, constitui a referência visual principal.

Elementos observados:

- navegação lateral azul-marinho;
- marca SDV Access;
- cabeçalho superior compacto;
- oito cartões de indicadores;
- painel de acessos recentes;
- gráfico de entradas e saídas do dia;
- fundo claro e cartões brancos;
- item atual destacado no menu;
- uso de azul, verde e âmbar;
- hierarquia compacta voltada à operação.

Os números e nomes exibidos na prancha são ilustrativos e não poderão ser inseridos como dados fixos.

## 2.2 Requisitos relacionados

| Identificador | Relação |
|---|---|
| `RF-003` | Exibir dashboard conforme perfil e layout aprovado |
| `RF-004` | Permitir pesquisa de cadastros |
| `RF-020` | Gerar relatórios operacionais |
| `RF-031` | Consultar histórico de acessos da pessoa |
| `RF-037` | Exibir situação de integração |
| `RF-039` | Manter notificações operacionais |
| `RN-041` | Registrar tentativas de acesso |
| `RN-042` | Distinguir entrada, saída, ponto e método |
| `RN-046` a `RN-049` | Preservar auditoria |
| `RNF-003` e `RNF-018` | Responsividade |
| `RNF-004` e `RNF-013` | Desempenho operacional |
| `RNF-008` | Observabilidade |
| `RNF-012` | Segregação de dados |
| `RNF-017` | Auditoria de exportação |
| `RNF-019` | Acessibilidade |

## 2.3 Componentes do Design System

O Dashboard utilizará prioritariamente:

- `DS-CMP-001 — Sidebar`;
- `DS-CMP-002 — Operational Header`;
- `DS-CMP-003 — Botão`;
- `DS-CMP-005 — Campo de texto`;
- `DS-CMP-007 — Autocomplete`;
- `DS-CMP-011 — Badge de status`;
- `DS-CMP-012 — Alerta`;
- `DS-CMP-013 — Toast`;
- `DS-CMP-014 — Estado vazio`;
- `DS-CMP-015 — Skeleton e progresso`;
- `DS-CMP-016 — Card`;
- `DS-CMP-017 — Tabela`;
- `DS-CMP-018 — Lista de atividade`;
- `DS-CMP-019 — Métrica`;
- `DS-CMP-020 — Breadcrumb`, quando aplicável;
- `DS-CMP-026 — Tooltip`.

---

# 3. Usuários e objetivos

## 3.1 Operador de portaria

Necessita:

- acompanhar entradas e saídas recentes;
- localizar rapidamente pessoa, imóvel, documento ou placa;
- identificar pendências operacionais;
- acessar Validação de Entrada e Pré-Cadastros;
- perceber falhas que afetem a operação.

## 3.2 Administrador

Necessita:

- visualizar volume geral de cadastros;
- acompanhar integrações e equipamentos;
- acessar cadastros e configurações;
- identificar pendências e falhas;
- consultar indicadores consolidados.

## 3.3 Gestor ou síndico

Necessita:

- acompanhar movimentação e arrecadação autorizada;
- comparar entradas e saídas;
- consultar acessos recentes;
- acessar relatórios;
- observar tendências sem alterar configurações críticas.

## 3.4 Operador de caixa

Necessita:

- visualizar situação do próprio caixa;
- acompanhar arrecadação e movimentos autorizados;
- acessar validação limitada e fechamento;
- evitar indicadores sem relação com sua função.

## 3.5 Auditor

Necessita:

- consultar indicadores sem executar ações operacionais;
- acessar eventos e relatórios;
- verificar origem, período e atualização dos dados.

---

# 4. Escopo da tela

## 4.1 Conteúdo obrigatório

Conforme a referência, o Dashboard deverá apresentar:

1. pessoas cadastradas;
2. visitantes do dia;
3. entradas do dia;
4. saídas do dia;
5. moradores;
6. prestadores;
7. veículos cadastrados;
8. arrecadação do dia;
9. acessos recentes;
10. gráfico de entradas e saídas do dia.

## 4.2 Conteúdo condicional

Conforme perfil, configuração e disponibilidade:

- pré-cadastros pendentes;
- alertas de integração;
- equipamentos indisponíveis;
- caixa aberto ou fechado;
- sincronizações com falha;
- indicadores de turistas com destino à praia;
- atalhos operacionais.

Conteúdo condicional deverá ser incorporado sem substituir a estrutura principal por um layout genérico. Alertas críticos podem anteceder os indicadores.

## 4.3 Fora do escopo

Não pertence ao Dashboard:

- edição direta de cadastros;
- aprovação completa de pré-cadastro;
- liberação de acesso sem validação;
- fechamento de caixa;
- exportação ampla sem passagem por relatório;
- configuração de equipamentos;
- análise financeira equivalente a ERP;
- gráficos avançados não aprovados.

---

# 5. Arquitetura da informação

```text
Dashboard
├── App shell
│   ├── Navegação lateral
│   └── Cabeçalho operacional
├── Contexto e alertas
├── Indicadores
│   ├── Pessoas cadastradas
│   ├── Visitantes hoje
│   ├── Entradas hoje
│   ├── Saídas hoje
│   ├── Moradores
│   ├── Prestadores
│   ├── Veículos cadastrados
│   └── Arrecadação hoje
└── Operação do dia
    ├── Acessos recentes
    └── Entradas e saídas por horário
```

---

# 6. Layout de desktop

## 6.1 Estrutura geral

Em desktop operacional:

```text
┌───────────────┬──────────────────────────────────────────────┐
│               │ Cabeçalho operacional                       │
│ Navegação     ├──────────────────────────────────────────────┤
│ lateral       │ Alertas críticos, quando existirem           │
│               ├──────────┬──────────┬──────────┬─────────────┤
│               │ Métrica 1│ Métrica 2│ Métrica 3│ Métrica 4   │
│               ├──────────┼──────────┼──────────┼─────────────┤
│               │ Métrica 5│ Métrica 6│ Métrica 7│ Métrica 8   │
│               ├─────────────────────┬────────────────────────┤
│               │ Acessos recentes    │ Entradas × saídas      │
└───────────────┴─────────────────────┴────────────────────────┘
```

## 6.2 Navegação lateral

Deverá:

- usar fundo azul-marinho;
- exibir a marca SDV Access;
- agrupar módulos;
- destacar “Dashboard”;
- mostrar somente itens autorizados;
- apresentar usuário e ação de saída;
- permitir modo recolhido somente se aprovado no protótipo.

## 6.3 Cabeçalho

Deverá conter, conforme permissão e contexto:

- botão para recolher ou abrir menu;
- busca global;
- notificações;
- usuário autenticado;
- perfil;
- situação do caixa quando aplicável;
- data e hora da sessão ou operação.

A busca global é uma ação operacional. Sua disponibilidade depende da definição de escopo em `PEN-UXD-004`.

## 6.4 Indicadores

Os oito cartões serão distribuídos em quatro colunas por duas linhas na referência desktop.

Cada cartão deverá conter:

- rótulo;
- valor;
- unidade implícita ou explícita;
- comparação opcional;
- período;
- estado de atualização;
- ação de navegação quando autorizada.

## 6.5 Área inferior

Em desktop, “Acessos recentes” e “Entradas × Saídas” deverão compartilhar a mesma linha, preservando maior largura para o gráfico quando necessário.

---

# 7. Cabeçalho e busca global

## 7.1 Busca

A busca poderá localizar:

- pessoa por nome;
- CPF ou documento;
- imóvel;
- placa;
- protocolo.

Requisitos:

- rótulo acessível;
- atalho de teclado somente se documentado;
- resultados agrupados por tipo;
- destaque do termo;
- controle por permissão;
- mascaramento de dados sensíveis;
- navegação por teclado;
- estado de carregamento;
- nenhum resultado;
- erro;
- limite de resultados e acesso à pesquisa completa.

## 7.2 Notificações

O sino poderá exibir contador. Ao abrir:

- listar notificações permitidas;
- diferenciar lida e não lida;
- identificar gravidade;
- levar ao contexto correto;
- não expor dados pessoais além do necessário;
- registrar leitura apenas quando tecnicamente confirmada.

## 7.3 Usuário

O menu do usuário poderá conter:

- nome;
- perfil;
- implantação;
- preferências autorizadas;
- troca segura de senha;
- saída.

---

# 8. Especificação dos indicadores

## 8.1 UXD-MET-001 — Pessoas cadastradas

**Definição:** quantidade de cadastros únicos de pessoa considerados no escopo da implantação.

Deverá:

- informar se conta pessoas ativas ou todas;
- não somar vínculos como pessoas;
- direcionar para pesquisa de pessoas, quando autorizado;
- exibir comparação somente com período definido.

## 8.2 UXD-MET-002 — Visitantes hoje

**Definição:** quantidade de visitas ou visitantes com evento ou autorização relevante no dia, conforme fórmula aprovada.

Deve ser esclarecido se representa:

- pessoas distintas;
- autorizações;
- entradas realizadas.

Até essa decisão, o rótulo não poderá induzir uma interpretação específica.

## 8.3 UXD-MET-003 — Entradas hoje

**Definição:** eventos de acesso com direção de entrada e resultado incluído na fórmula.

Deverá excluir:

- tentativas não confirmadas, salvo indicação;
- eventos de teste;
- duplicidades técnicas;
- reenvios idempotentes.

## 8.4 UXD-MET-004 — Saídas hoje

**Definição:** eventos de saída confirmados no período local da implantação.

A ausência de saída automática ou completa deverá ser indicada para não sugerir dado exato quando a captura for parcial.

## 8.5 UXD-MET-005 — Moradores

**Definição:** pessoas com vínculo de morador ativo no momento da consulta.

A mesma pessoa não deverá ser duplicada por possuir mais de um vínculo ativo sem que a fórmula declare essa escolha.

## 8.6 UXD-MET-006 — Prestadores

**Definição:** pessoas com vínculo de prestador conforme situação definida.

O indicador deverá declarar se considera:

- ativos;
- autorizados no dia;
- cadastrados totais.

## 8.7 UXD-MET-007 — Veículos cadastrados

**Definição:** veículos únicos dentro do estado selecionado.

Placas duplicadas em análise não deverão inflar silenciosamente o resultado.

## 8.8 UXD-MET-008 — Arrecadação hoje

**Definição:** total líquido dos movimentos de contribuição incluídos no dia.

Deverá:

- respeitar permissão financeira;
- usar `R$` e formatação brasileira;
- diferenciar bruto, cancelamentos e líquido;
- coincidir com relatório de caixa;
- indicar indisponibilidade se movimentos ainda não estiverem conciliados.

## 8.9 Comparações

Textos como “+12 este mês” ou “+15 hoje”, observados na referência, somente poderão ser apresentados quando:

- a base de comparação estiver definida;
- o cálculo for rastreável;
- sinal e período forem compreensíveis;
- zero e valores negativos forem tratados corretamente.

Não associar automaticamente crescimento à cor verde quando crescimento puder representar risco operacional.

---

# 9. Acessos recentes

## 9.1 Conteúdo

Cada linha deverá apresentar:

- foto ou avatar;
- nome;
- tipo de vínculo ou acesso;
- direção;
- horário;
- resultado quando necessário;
- ponto de acesso em detalhe ou tooltip;
- acesso ao evento conforme permissão.

## 9.2 Ordenação

- eventos mais recentes primeiro;
- usar data e hora da ocorrência, não apenas de processamento;
- resolver empates por identificador estável;
- indicar quando evento chegou atrasado.

## 9.3 Quantidade

O Dashboard exibirá quantidade limitada, suficiente para visão rápida. A ação “Ver todos” direcionará ao relatório ou histórico de acessos com filtros correspondentes.

## 9.4 Privacidade

- foto dependerá de permissão;
- documento não será exibido;
- nome poderá ser reduzido conforme política;
- dados de outra implantação nunca aparecerão;
- eventos sensíveis poderão exigir perfil específico.

## 9.5 Estados

- carregando;
- lista disponível;
- nenhum acesso no período;
- dados parciais;
- erro;
- sem permissão.

---

# 10. Gráfico de entradas e saídas

## 10.1 Finalidade

Mostrar a distribuição dos eventos do dia ao longo do tempo, permitindo perceber picos e diferença entre entradas e saídas.

## 10.2 Estrutura

O gráfico deverá conter:

- título;
- período;
- legenda;
- eixo temporal;
- eixo quantitativo;
- série de entradas;
- série de saídas;
- tooltip acessível;
- resumo textual ou tabela alternativa;
- estado de atualização.

## 10.3 Padrão visual

- entradas: azul primário;
- saídas: âmbar;
- linhas distinguíveis também por marcador ou padrão quando necessário;
- grade discreta;
- sem efeitos tridimensionais;
- sem animação contínua;
- escala iniciando em zero, salvo justificativa explícita.

## 10.4 Agrupamento

O intervalo de agrupamento poderá variar conforme o período, mas para “Hoje” deverá ser estável e compreensível, como hora ou faixa configurada.

## 10.5 Interação

- foco ou ponteiro apresenta valores;
- toque seleciona ponto no celular;
- seleção não altera dados sem indicação;
- clique em faixa poderá abrir relatório filtrado, se autorizado;
- gráfico deverá possuir alternativa tabular.

## 10.6 Dados ausentes

Ausência de eventos é diferente de indisponibilidade:

- zero: linha ou valor zero;
- dado ausente: lacuna ou estado indisponível;
- fonte parcial: aviso explícito.

---

# 11. Alertas operacionais

## 11.1 Tipos

O Dashboard poderá alertar sobre:

- equipamento indisponível;
- integração com falha;
- fila de sincronização acumulada;
- pré-cadastros aguardando análise;
- caixa em situação irregular;
- dado desatualizado;
- rotina de expiração com falha.

## 11.2 Prioridade

| Nível | Exemplo | Apresentação |
|---|---|---|
| Crítico | ponto de acesso indisponível | alerta persistente antes dos indicadores |
| Alto | sincronização interrompida | alerta destacado com ação |
| Médio | pré-cadastros pendentes | cartão ou notificação |
| Informativo | atualização concluída | notificação transitória |

## 11.3 Regras

- alerta deve explicar impacto;
- ação deve levar ao contexto;
- fechar não deve resolver o evento;
- alertas críticos não podem desaparecer apenas porque foram lidos;
- contagem deve ser reconciliável;
- silêncio de monitoramento não pode ser representado como funcionamento normal.

---

# 12. Personalização por perfil

## 12.1 Matriz inicial

| Conteúdo | Portaria | Administrador | Gestor | Caixa | Auditor |
|---|---:|---:|---:|---:|---:|
| Pessoas cadastradas | C limitado | C | C | — | C |
| Visitantes hoje | C | C | C | — | C |
| Entradas hoje | C | C | C | C limitado | C |
| Saídas hoje | C | C | C | — | C |
| Moradores | C limitado | C | C | — | C |
| Prestadores | C limitado | C | C | — | C |
| Veículos | C limitado | C | C | — | C |
| Arrecadação | conforme permissão | C | C | C próprio | C |
| Acessos recentes | C operacional | C | C | C limitado | C |
| Alertas de integração | C operacional | C/G | C | — | C |

“—” significa oculto por padrão. A matriz granular aprovada prevalecerá.

## 12.2 Layout sem permissão

O conteúdo não autorizado deverá ser removido, e a grade deverá se reorganizar sem deixar cartões vazios ou revelar títulos sensíveis.

## 12.3 Configuração

Personalização de conteúdo deverá ser controlada por:

- permissão;
- perfil;
- configuração da implantação;
- disponibilidade do módulo.

Preferências individuais de ordem ou ocultação permanecem fora desta versão, salvo decisão posterior.

---

# 13. Período, fuso e atualização

## 13.1 Período padrão

O Dashboard inicia em “Hoje”, considerando o fuso configurado para a implantação.

Indicadores de estoque, como pessoas e veículos, representam o estado no momento da consulta. Indicadores de fluxo representam o período.

## 13.2 Atualização

Cada região deverá informar:

- momento da última atualização;
- carregamento;
- falha;
- defasagem conhecida;
- fonte parcial.

## 13.3 Atualização manual e automática

Deverá existir atualização manual acessível. Atualização automática poderá ser adotada após definição de:

- intervalo;
- custo;
- comportamento de foco;
- tratamento de mudanças durante leitura;
- impacto no servidor;
- comunicação de dados novos.

Atualização automática não deverá reorganizar a tela ou retirar conteúdo sob interação do usuário.

## 13.4 Livewire

Regiões independentes poderão atualizar por componentes Livewire distintos, evitando recarregar todo o Dashboard.

Deverão:

- preservar foco;
- manter estado de filtros;
- bloquear consultas duplicadas;
- tratar timeout;
- cancelar respostas obsoletas quando aplicável;
- validar permissões a cada solicitação.

---

# 14. Estados da tela

## 14.1 Carregamento inicial

- app shell disponível;
- skeleton nos cartões;
- skeleton ou espaço reservado na lista e gráfico;
- nenhuma métrica fictícia;
- nenhuma alteração brusca de layout.

## 14.2 Carregamento parcial

Uma região em atualização não deverá bloquear as demais.

## 14.3 Vazio

Exemplos:

- “Nenhum acesso registrado hoje”;
- “Nenhum pré-cadastro aguardando análise”.

O estado vazio deverá diferenciar operação normal de configuração ausente.

## 14.4 Erro parcial

Se uma métrica falhar:

- manter outras regiões;
- identificar o indicador;
- oferecer nova tentativa;
- registrar falha técnica;
- não substituir por zero.

## 14.5 Erro geral

Se o Dashboard não puder carregar:

- manter navegação segura;
- explicar indisponibilidade;
- oferecer nova tentativa;
- apresentar canal de suporte conforme configuração;
- não expor detalhes internos.

## 14.6 Dado desatualizado

Exibir:

- último valor conhecido;
- marca de desatualizado;
- horário da última atualização;
- ação de atualizar;
- alerta quando impactar decisão.

## 14.7 Sem permissão

Usuário sem acesso ao Dashboard deverá ser direcionado à primeira rota autorizada ou receber página de acesso negado, conforme política de navegação.

---

# 15. Responsividade

## 15.1 Desktop amplo

- sidebar persistente;
- quatro indicadores por linha;
- acessos recentes e gráfico lado a lado;
- busca global no cabeçalho.

## 15.2 Notebook

- sidebar persistente ou recolhível;
- quatro ou duas colunas conforme espaço real;
- região inferior pode manter duas colunas;
- rótulos não serão truncados sem alternativa.

## 15.3 Tablet

- sidebar recolhível;
- duas métricas por linha;
- acessos recentes e gráfico empilhados;
- filtros e notificações em painel.

## 15.4 Celular

- navegação em drawer;
- uma métrica por linha, admitindo duas apenas se legível;
- lista de acessos em cartões compactos;
- gráfico com rolagem proibida como única forma de leitura;
- alternativa tabular;
- busca em tela ou painel próprio;
- áreas de toque de pelo menos 44 px.

## 15.5 Ordem no celular

1. alertas críticos;
2. indicadores operacionais do perfil;
3. acessos recentes;
4. gráfico;
5. indicadores secundários.

---

# 16. Acessibilidade

O Dashboard deverá:

- utilizar título principal identificado;
- preservar hierarquia de cabeçalhos;
- fornecer link para pular navegação;
- permitir operação por teclado;
- apresentar foco visível;
- nomear métricas e períodos;
- não comunicar variação somente por cor;
- fornecer descrição ou tabela alternativa do gráfico;
- associar notificações ao contador;
- usar texto alternativo apropriado em avatares;
- permitir zoom de 200%;
- respeitar redução de movimento;
- anunciar erros e atualizações críticas sem excesso;
- manter ordem de leitura compatível com a ordem visual.

Atualizações frequentes não deverão interromper o leitor de tela. Somente eventos relevantes devem usar regiões vivas.

---

# 17. Conteúdo e microcopy

## 17.1 Rótulos recomendados

- Pessoas cadastradas;
- Visitantes hoje;
- Entradas hoje;
- Saídas hoje;
- Moradores;
- Prestadores;
- Veículos cadastrados;
- Arrecadação hoje;
- Acessos recentes;
- Entradas e saídas — Hoje;
- Ver todos;
- Última atualização;
- Atualizar.

## 17.2 Mensagens

| Situação | Mensagem recomendada |
|---|---|
| Sem acessos | Nenhum acesso registrado hoje. |
| Métrica indisponível | Não foi possível carregar este indicador. |
| Dados antigos | Dados desatualizados. Última atualização às {hora}. |
| Falha geral | Não foi possível carregar o Dashboard. Tente novamente. |
| Sem gráfico | Ainda não há eventos suficientes para exibir o gráfico. |
| Sem permissão | Você não possui permissão para consultar este conteúdo. |

## 17.3 Formatação

- números inteiros com separador de milhar brasileiro;
- moeda como `R$ 3.450,00`;
- horários conforme configuração, inicialmente `HH:mm`;
- data como `dd/MM/yyyy`;
- comparações sempre acompanhadas do período.

---

# 18. Navegação e interações

## 18.1 Cartões

Um cartão poderá ser acionável quando:

- houver destino claro;
- o usuário possuir permissão;
- todo o cartão indicar interatividade;
- foco e teclado funcionarem;
- o destino preservar o filtro correspondente.

Caso contenha link interno, o cartão não deverá possuir controles aninhados conflitantes.

## 18.2 Acessos recentes

Selecionar um evento poderá abrir:

- detalhe em drawer;
- página de evento;
- histórico da pessoa.

A escolha deverá ser padronizada na UX/UI de Relatórios e Acessos.

## 18.3 Gráfico

A navegação para detalhe deverá carregar relatório com:

- data;
- direção;
- faixa horária;
- implantação;
- demais filtros autorizados.

## 18.4 Atalhos

Atalhos para Validação de Entrada ou Pré-Cadastros poderão ser incluídos no cabeçalho ou alertas, sem competir com os indicadores.

---

# 19. Segurança e privacidade

- todas as consultas deverão ser segregadas pela implantação;
- o servidor aplicará permissões por indicador e detalhe;
- valores financeiros exigirão permissão específica;
- fotos e nomes deverão respeitar necessidade operacional;
- respostas Livewire não poderão conter dados ocultos apenas por CSS;
- cache deverá ser segregado por implantação e perfil;
- busca global deverá limitar resultados e dados apresentados;
- erros não deverão expor consultas, chaves ou estruturas internas;
- acesso a evento ou relatório deverá ser auditado quando aplicável;
- o Dashboard não deverá revelar existência de cadastro sem permissão.

---

# 20. Desempenho e observabilidade

## 20.1 Diretrizes

- evitar consultas repetidas por cartão;
- utilizar agregações consistentes;
- carregar regiões pesadas progressivamente;
- limitar acessos recentes;
- calcular séries com estratégia apropriada;
- evitar atualização simultânea desnecessária;
- manter resposta inicial operacionalmente rápida.

## 20.2 Telemetria

Registrar, sem dados pessoais desnecessários:

- tempo de carregamento;
- falha por região;
- timeout;
- consultas lentas;
- atualização manual;
- erro de integração que afete indicador;
- divergência de cálculo identificada.

## 20.3 Integridade

Métrica rápida não poderá usar fonte incompatível com o relatório oficial sem indicação. Cache e agregações deverão possuir regra de invalidação e reconciliação.

---

# 21. Contrato inicial de dados

Este contrato é funcional e não define API final.

## 21.1 Contexto

- implantação;
- usuário;
- perfil;
- fuso;
- período;
- última atualização;
- qualidade dos dados.

## 21.2 Métrica

Cada métrica deverá fornecer:

- identificador;
- rótulo;
- valor;
- unidade;
- período;
- comparação opcional;
- situação;
- última atualização;
- destino autorizado opcional.

## 21.3 Acesso recente

- identificador do evento;
- pessoa autorizada para exibição;
- avatar autorizado;
- classificação;
- direção;
- resultado;
- instante do evento;
- ponto de acesso;
- destino autorizado.

## 21.4 Série

- instante ou faixa;
- entradas;
- saídas;
- qualidade ou completude;
- fuso.

---

# 22. Regras de cálculo e conciliação

Antes da implementação, cada indicador deverá possuir ficha com:

- nome;
- objetivo;
- fórmula;
- fonte;
- estados incluídos;
- estados excluídos;
- tratamento de duplicidade;
- fuso;
- periodicidade;
- responsável;
- relatório de conciliação.

Indicadores do Dashboard e totais de relatório devem convergir quando utilizarem o mesmo recorte.

Diferença justificada por atualização, cache ou escopo deverá ser comunicada.

---

# 23. Cenários de teste

## 23.1 Funcionais

- usuário vê somente métricas autorizadas;
- números correspondem às fontes;
- “Ver todos” preserva período;
- cartão direciona ao módulo correto;
- acessos recentes estão ordenados;
- busca retorna tipos autorizados;
- gráfico corresponde aos eventos;
- arrecadação coincide com caixa;
- alertas direcionam ao contexto;
- atualização manual renova o horário.

## 23.2 Estados

- nenhum evento no dia;
- métrica zero;
- métrica indisponível;
- dados desatualizados;
- erro parcial;
- erro geral;
- integração parcial;
- caixa fechado;
- usuário sem permissão financeira;
- carregamento lento.

## 23.3 Responsividade

- desktop de referência;
- notebook;
- tablet retrato e paisagem;
- celular estreito;
- zoom de 200%;
- texto ampliado;
- menu aberto e fechado.

## 23.4 Acessibilidade

- navegação completa por teclado;
- foco na ordem correta;
- nomes acessíveis;
- leitura dos cartões;
- alternativa do gráfico;
- atualização sem interrupção;
- alertas anunciados;
- contraste.

## 23.5 Segurança

- tentativa de consultar indicador sem permissão;
- acesso cruzado entre implantações;
- resposta cacheada de outro perfil;
- busca por dado sensível;
- acesso direto ao detalhe;
- sessão expirada durante atualização.

---

# 24. Critérios de aceite

## 24.1 Funcionais

**CA-UXD-001:** o Dashboard apresenta conteúdo conforme perfil.  
**CA-UXD-002:** os oito indicadores da referência estão disponíveis quando autorizados e configurados.  
**CA-UXD-003:** valores são reais, rastreáveis e conciliáveis.  
**CA-UXD-004:** acessos recentes apresentam ordem e dados corretos.  
**CA-UXD-005:** gráfico representa entradas e saídas no período e fuso corretos.  
**CA-UXD-006:** links e cartões preservam contexto e filtros.  
**CA-UXD-007:** alertas representam falhas e pendências reais.  
**CA-UXD-008:** atualização não duplica dados nem quebra interação.  

## 24.2 Visuais

**CA-UXD-009:** navegação lateral, cartões, lista e gráfico preservam a composição da referência.  
**CA-UXD-010:** cores e tokens seguem o Design System.  
**CA-UXD-011:** hierarquia, espaçamento e densidade são consistentes.  
**CA-UXD-012:** números ilustrativos da referência não aparecem como dados fixos.  
**CA-UXD-013:** estados de sucesso, alerta e erro combinam texto e cor.  

## 24.3 Responsivos e acessíveis

**CA-UXD-014:** a tela funciona nas faixas definidas.  
**CA-UXD-015:** não há perda de conteúdo crítico em celular.  
**CA-UXD-016:** a navegação funciona por teclado.  
**CA-UXD-017:** o gráfico possui alternativa acessível.  
**CA-UXD-018:** contraste, foco e alvos interativos atendem ao Design System.  

## 24.4 Segurança e desempenho

**CA-UXD-019:** permissões são aplicadas no servidor.  
**CA-UXD-020:** dados permanecem segregados por implantação.  
**CA-UXD-021:** falha de uma região não bloqueia toda a tela.  
**CA-UXD-022:** metas de desempenho são atendidas após definição formal.  

---

# 25. Pendências abertas

| PEN-UXD | Pendência | Impacto | Encaminhamento |
|---|---|---|---|
| PEN-UXD-001 | Aprovar fórmula de cada indicador | Impede conciliação definitiva | Regras de negócio e relatórios |
| PEN-UXD-002 | Definir quais métricas aparecem por perfil | Afeta layout e permissão | Matriz granular |
| PEN-UXD-003 | Definir intervalo e política de atualização automática | Afeta desempenho e operação | Arquitetura e UX |
| PEN-UXD-004 | Aprovar escopo e comportamento da busca global | Afeta cabeçalho e privacidade | UX/UI de pesquisa |
| PEN-UXD-005 | Definir quantidade de acessos recentes | Afeta densidade | Teste com operadores |
| PEN-UXD-006 | Definir agrupamento temporal do gráfico | Afeta leitura e cálculo | Produto e relatórios |
| PEN-UXD-007 | Selecionar biblioteca ou estratégia de gráficos | Afeta implementação e acessibilidade | Decisão técnica |
| PEN-UXD-008 | Definir metas de carregamento | Afeta aceite | Requisito não funcional |
| PEN-UXD-009 | Confirmar navegadores e terminais da portaria | Afeta responsividade e testes | Matriz de compatibilidade |
| PEN-UXD-010 | Definir se arrecadação será visível à portaria | Afeta menor privilégio | Matriz de permissões |
| PEN-UXD-011 | Definir fórmula de visitantes do dia | Pode representar pessoas, autorizações ou entradas | Regra de negócio |
| PEN-UXD-012 | Definir completude esperada das saídas | Afeta confiabilidade do indicador | Operação e equipamentos |
| PEN-UXD-013 | Definir destino da seleção de acesso recente | Afeta interação | UX/UI de Acessos |
| PEN-UXD-014 | Definir limites e canais de notificações | Afeta cabeçalho | Regras de notificação |
| PEN-UXD-015 | Confirmar prioridade e apresentação de alertas de equipamento | Afeta continuidade | UX/UI e arquitetura |
| PEN-UXD-016 | Validar contraste e paleta final após ativos oficiais | Afeta homologação visual | Pendências do Design System |
| PEN-UXD-017 | Produzir protótipos nos viewports homologados | Afeta aprovação final da tela | Etapa de prototipação |

---

# 26. Decisões consolidadas

Ficam consolidados:

- preservação da composição principal da referência;
- oito indicadores como estrutura-base;
- acessos recentes e gráfico como áreas operacionais inferiores;
- números da prancha tratados apenas como exemplo;
- indicadores calculados por dados reais e rastreáveis;
- conteúdo controlado por perfil e permissão;
- arrecadação protegida por permissão específica;
- atualização parcial por região;
- erro não representado como zero;
- dado desatualizado identificado;
- gráfico com alternativa acessível;
- pré-cadastro, integrações e equipamentos apresentados como alertas ou conteúdo condicional;
- Dashboard orientado à consulta e navegação, não à edição;
- Blade e Livewire como base de implementação;
- autorização executada no servidor.

## 26.1 Registro de aprovação

| Papel | Nome | Decisão | Data | Observações |
|---|---|---|---|---|
| Product Owner | Vinicius Velasco de Azevedo | Aprovado | 28/07/2026 | UX/UI do Dashboard aprovada como referência para prototipação, testes e implementação futura |
| Soluções do Vale | Representante designado | Ciente | 28/07/2026 | Aprovação registrada no repositório oficial |

---

# 27. Próximo documento

Após a aprovação desta especificação, deverá ser produzido:

**`docs/005_UX_UI_VALIDACAO.md`**

A futura especificação deverá detalhar a jornada completa de Validação de Entrada, incluindo:

- identificação da pessoa;
- veículo e LPR;
- contribuição;
- observações;
- negativa;
- salvamento sem liberação;
- validação e comando;
- falha de equipamento;
- contingência;
- estados responsivos e acessíveis.

---

## Situação do documento

Esta especificação consolida o comportamento funcional, visual e responsivo do Dashboard do SDV Access e encontra-se **aprovada**. As pendências de cálculo, atualização, permissões e prototipação permanecem rastreadas e deverão ser resolvidas antes da implementação definitiva dos elementos afetados, sem invalidar esta aprovação documental.
