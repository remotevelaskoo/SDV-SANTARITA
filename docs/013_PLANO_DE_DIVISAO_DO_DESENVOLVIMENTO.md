# Plano de divisão e acompanhamento do desenvolvimento

**Projeto:** SDV Access — Santa Rita

**Equipe:** Lucas Pastorelli e Vinicius Velasco

**Última atualização:** 10 de agosto de 2026

## 1. Objetivo deste documento

Este documento divide o desenvolvimento em partes pequenas e identificáveis para que Lucas e Vinicius possam trabalhar em paralelo sem alterar acidentalmente a mesma área.

Ele deverá responder, a qualquer momento:

- qual parte está disponível;
- quem é o responsável;
- o que será entregue;
- de quais partes ela depende;
- qual é a situação atual;
- onde o trabalho está sendo desenvolvido.

Este é um documento vivo. Sempre que alguém iniciar, concluir ou bloquear uma parte, deverá atualizar o quadro abaixo.

## 2. Significado das situações

| Situação | Significado |
|---|---|
| ✅ Concluída | Foi desenvolvida, testada e está disponível para revisão ou uso. |
| 🟡 Em andamento | Já existe uma pessoa trabalhando nesta parte. |
| 🟢 Disponível | Pode ser iniciada agora por Lucas ou Vinicius. |
| 🔴 Bloqueada | Depende de uma decisão ou de outra parte ainda não concluída. |
| ⚪ Planejada | Faz parte do projeto, mas ainda não é o momento recomendado para iniciar. |

### 2.1 Resumo atual

| Situação | Quantidade | Partes |
|---|---:|---|
| ✅ Concluídas | 12 | P01 a P08 e P10 a P13 |
| 🟢 Disponíveis | 4 | P09, P14, P15 e P18 |
| 🔴 Bloqueadas | 10 | P16, P17, P19 a P25 e P27 |
| ⚪ Planejada | 1 | P26 |

O avanço por quantidade de partes é de **12 concluídas em 27 (aproximadamente 44%)**. Esse percentual representa o número de partes concluídas, não o esforço total, pois banco de dados, integrações, segurança e publicação possuem complexidades diferentes.

## 3. Termos usados pela equipe

- **Frontend:** telas, botões, menus, formulários e tudo que a pessoa vê e utiliza.
- **Backend:** parte interna que salva informações, aplica regras e controla permissões.
- **Banco de dados:** local organizado onde as informações reais ficam armazenadas.
- **Branch:** cópia separada do projeto usada para desenvolver uma parte sem interferir no trabalho da outra pessoa.
- **Pull Request:** pedido feito no GitHub para revisar e juntar uma branch ao projeto principal.
- **Responsivo:** funcionamento e aparência adequados no computador, tablet e celular.
- **Integração:** comunicação do sistema com equipamentos ou serviços externos.

## 4. Quadro principal das partes

### 4.1 Fundação visual compartilhada

| ID | Parte | Entrega principal | Dependência | Situação | Responsável | Branch |
|---|---|---|---|---|---|---|
| P01 | Estrutura principal | Menu lateral, cabeçalho, navegação, identidade visual e adaptação para celular | Nenhuma | ✅ Concluída | Lucas + Codex | `codex/fundacao-frontend` |
| P02 | Dashboard operacional | Indicadores, alertas, gráfico, acessos recentes e câmeras demonstrativas | P01 | ✅ Concluída visualmente | Lucas + Codex | `codex/fundacao-frontend` |
| P03 | Login visual | Entrada no sistema, aviso de erro, mostrar senha e acesso demonstrativo | P01 | ✅ Concluída | Lucas + Codex | `codex/fundacao-frontend` |
| P04 | Componentes compartilhados | Padrões reutilizáveis de campos, botões, tabelas, avisos, cartões e janelas | P01 | ✅ Concluída | Lucas + Codex | `codex/fundacao-frontend` |

#### Acompanhamento detalhado do P04

O catálogo local dos componentes pode ser acessado em `/componentes` durante o desenvolvimento. Ele serve para testar cada peça separadamente antes de utilizá-la nas telas reais do sistema.

| Entrega do P04 | Componentes incluídos | Situação |
|---|---|---|
| Entrega 1 — Base visual | Botões, grupos de ações, campos de texto, badges, alertas, cartões e estado vazio | ✅ Concluída e testada |
| Entrega 2 — Formulários e dados | Seleção, checkbox, radio, switch, progresso, carregamento, erro e tabela responsiva | ✅ Concluída e testada |
| Entrega 3 — Navegação e painéis | Breadcrumb, abas, indicador de etapas, paginação, janela de confirmação e painel lateral | ✅ Concluída e testada |
| Entrega 4 — Complementos | Autocomplete, data e período, upload, toast, lista de atividade, métrica e tooltip | ✅ Concluída e testada |
| Entrega 5 — Componentes da operação | Pessoa, vínculo, veículo, placa, sincronização, decisão de acesso, contribuição, caixa e protocolo | ✅ Concluída e testada |

**Verificações do P04:** funcionamento no computador e no celular, interações por mouse e teclado, estados de carregamento e erro, abertura e fechamento das janelas, compilação visual e testes automáticos aprovados.

### 4.2 Operação da portaria

| ID | Parte | Entrega principal | Dependência | Situação | Responsável | Branch |
|---|---|---|---|---|---|---|
| P05 | Modo Portaria | Página inicial do porteiro com caixa, atalhos, alertas e atendimentos recentes | P01 e definição dos atalhos | ✅ Concluída (protótipo — atalhos provisórios, aguardando confirmação da equipe) | Vinicius | [`vinicius/p05-modo-portaria`](https://github.com/remotevelaskoo/SDV-SANTARITA/pull/4) |
| P06 | Validação de entrada | Identificação da pessoa, veículo, contribuição, observações, negação e liberação | P04 | ✅ Concluída (protótipo demonstrativo, sem equipamento real) | Lucas + Codex | `codex/p06-validacao-entrada` |
| P07 | Cadastro rápido no atendimento | Criar cadastro mínimo sem perder a validação em andamento | P06 e P10 | ✅ Concluída (protótipo demonstrativo) | Lucas + Codex | `codex/p07-cadastro-rapido` |
| P08 | Pré-cadastro | Solicitação antecipada e análise pela portaria | P04 | ✅ Concluída (protótipo demonstrativo) | Lucas + Codex | `codex/p08-pre-cadastro` |
| P09 | Entradas e saídas | Histórico, consulta, filtros, detalhes e resultados das tentativas | P06 | ✅ Concluída (protótipo demonstrativo) | Vinicius | `vinicius/p09-entradas-saidas` |
| P10 | Cadastro de pessoas | Dados pessoais, documentos, foto, vínculos, credenciais e situação | P04 | ✅ Concluída (protótipo — sem persistência real, OCR ou sincronização facial) | Vinicius | `vinicius/p10-cadastro-pessoas` |
| P11 | Cadastro de imóveis | Blocos, unidades, endereços, moradores, responsáveis e vínculos | P04 | ✅ Concluída (protótipo demonstrativo) | Lucas + Codex | `codex/p11-cadastro-imoveis` |
| P12 | Cadastro de veículos | Placa, características, proprietário, vínculo e situação | P04 | ✅ Concluída (protótipo demonstrativo) | Lucas + Codex | `codex/p12-cadastro-veiculos` |
| P13 | Prestadores e empresas | Empresas, funcionários, serviços, documentos e autorizações | P04 | ✅ Concluída (protótipo demonstrativo) | Vinicius | `vinicius/p13-prestadores-empresas` |

#### Acompanhamento detalhado da P06

| Entrega da P06 | Conteúdo | Situação |
|---|---|---|
| Identificação | Pessoa, documento protegido, imóvel, vínculo, autorização e estados independentes | ✅ Concluída |
| Veículo | Placa reconhecida e cadastrada, veículo vinculado e confiança da leitura LPR | ✅ Concluída |
| Contribuição | Opções contribui, não contribui e isento, forma de pagamento e resumo | ✅ Concluída |
| Decisão | Negar com motivo, salvar sem liberar e validar em modo demonstrativo | ✅ Concluída |
| Segurança do protótipo | Mensagens deixam claro que nenhum equipamento físico é acionado | ✅ Concluída |
| Qualidade | Navegação, computador, celular, testes automáticos e compilação visual | ✅ Aprovada |

#### Acompanhamento detalhado da P07

| Entrega da P07 | Conteúdo | Situação |
|---|---|---|
| Acesso pelo atendimento | Ação “Pessoa não encontrada?” dentro da Validação de Entrada | ✅ Concluída |
| Cadastro mínimo | Nome, documento, telefone, tipo de acesso, responsável, imóvel e observação | ✅ Concluída |
| Prevenção de duplicidade | Documento existente bloqueia a criação de uma segunda pessoa | ✅ Concluída |
| Continuidade | Contribuição, observações e demais dados da validação são preservados ao abrir, cancelar ou salvar | ✅ Concluída |
| Retorno à validação | Pessoa provisória é anexada ao atendimento com pendências claramente apresentadas | ✅ Concluída |
| Segurança do protótipo | Cadastro rápido não concede autorização nem libera entrada ou equipamento | ✅ Concluída |
| Qualidade | Computador, celular, interações, testes automáticos e compilação visual | ✅ Aprovada |

#### Acompanhamento detalhado da P08

| Entrega da P08 | Conteúdo | Situação |
|---|---|---|
| Convite público | Boas-vindas, destino protegido, período, validade e aviso de segurança | ✅ Concluída |
| Fluxo em seis etapas | Dados pessoais, endereço informado, documento, selfie, veículo opcional e confirmação | ✅ Concluída |
| Protocolo | Resultado demonstrativo, cópia e mensagem de que o protocolo não autoriza entrada | ✅ Concluída |
| Fila da portaria | Resumo, busca, filtros, tabela responsiva e cartões no celular | ✅ Concluída |
| Análise | Detalhes, checklist, histórico, aprovação, rejeição e solicitação de correção | ✅ Concluída |
| Segurança do protótipo | Aprovação separada da Validação de Entrada e ausência de comando físico | ✅ Concluída |
| Qualidade | Computador, celular, validações, testes automáticos e compilação visual | ✅ Aprovada |

#### Acompanhamento detalhado da P11

| Entrega da P11 | Conteúdo | Situação |
|---|---|---|
| Lista de imóveis | Resumo, busca, filtro por situação, tabela e cartões responsivos | ✅ Concluída |
| Detalhe estrutural | Código, bloco, unidade, endereço, situação e atualização | ✅ Concluída |
| Pessoas e vínculos | Natureza, papel, responsabilidade, vigência e situação apresentados separadamente | ✅ Concluída |
| Veículos e histórico | Vínculos de veículos, ocupação e eventos estruturais preservados | ✅ Concluída |
| Cadastro e edição | Formulário estrutural, prevenção de duplicidade, rascunho e situação independente | ✅ Concluída |
| Segurança do protótipo | Criar ou bloquear imóvel não cria, exclui ou ativa pessoas e acessos automaticamente | ✅ Concluída |
| Qualidade | Computador, celular, interações, testes automáticos e compilação visual | ✅ Aprovada |

#### Acompanhamento detalhado da P12

| Entrega da P12 | Conteúdo | Situação |
|---|---|---|
| Lista de veículos | Resumo, busca, filtros por situação e tipo, tabela e cartões responsivos | ✅ Concluída |
| Detalhe do veículo | Placa, características, documento protegido, proprietário e situação | ✅ Concluída |
| Vínculos independentes | Pessoa, imóvel, empresa, finalidade de uso e situação apresentados separadamente | ✅ Concluída |
| Leitura de placa (LPR) | Estado da sincronização, última leitura e aviso de que não há liberação automática | ✅ Concluída |
| Cadastro e edição | Formulário, normalização e prevenção de placa duplicada, rascunho e bloqueio | ✅ Concluída |
| Segurança do protótipo | Nenhuma câmera, portão, pessoa ou imóvel é alterado automaticamente | ✅ Concluída |
| Qualidade | Computador, celular, interações, testes automáticos e compilação visual | ✅ Aprovada |

### 4.3 Módulos operacionais e administrativos

| ID | Parte | Entrega principal | Dependência | Situação | Responsável | Branch |
|---|---|---|---|---|---|---|
| P14 | Caixa | Abertura, movimentações, contribuições, conferência e fechamento | P04 e regras financeiras | ✅ Concluída (protótipo — regras completas da contribuição ainda pendentes, `PEN-RNG-011`) | Vinicius | `vinicius/p14-caixa` |
| P15 | Encomendas | Recebimento, armazenamento, aviso e entrega de pacotes | P04 e cadastro de pessoas | ✅ Concluída (protótipo demonstrativo) | Vinicius | `vinicius/p15-encomendas` |
| P16 | Relatórios | Consultas, filtros e exportações autorizadas | Dados reais dos módulos | 🔴 Bloqueada | A definir | A definir |
| P17 | Administração | Usuários, perfis, permissões, configurações, equipamentos e auditoria | Login real e banco de dados | 🔴 Bloqueada | A definir | A definir |

### 4.4 Parte interna e dados reais

| ID | Parte | Entrega principal | Dependência | Situação | Responsável | Branch |
|---|---|---|---|---|---|---|
| P18 | Banco de dados inicial | Estrutura segura para imóveis, pessoas, vínculos, veículos e usuários | Regras e arquitetura aprovadas | 🟢 Disponível | A definir | A definir |
| P19 | Login real | Usuários individuais, senhas protegidas, sessões e recuperação de acesso | P18 | 🔴 Bloqueada | A definir | A definir |
| P20 | Perfis e permissões | Definir o que porteiro, caixa, gestor, administrador e auditor podem fazer | P18 e P19 | 🔴 Bloqueada | A definir | A definir |
| P21 | Conexão das telas | Trocar dados demonstrativos por cadastros e operações reais | P18 a P20 | 🔴 Bloqueada | A definir | A definir |
| P22 | Auditoria | Registrar quem realizou cada operação, quando e em qual contexto | P18 a P20 | 🔴 Bloqueada | A definir | A definir |

### 4.5 Equipamentos, segurança e publicação

| ID | Parte | Entrega principal | Dependência | Situação | Responsável | Branch |
|---|---|---|---|---|---|---|
| P23 | Integração com câmeras | Consultar estados e imagens autorizadas sem tratar demonstração como imagem real | P21 e escolha dos equipamentos | 🔴 Bloqueada | A definir | A definir |
| P24 | Portões e controladoras | Enviar comandos, confirmar resultado e tratar falhas | P06, P21 e equipamentos | 🔴 Bloqueada | A definir | A definir |
| P25 | Leitura de placas e reconhecimento | Integrar placa, face e outras credenciais | P21 e equipamentos | 🔴 Bloqueada | A definir | A definir |
| P26 | Testes e segurança finais | Validar regras, permissões, privacidade, falhas e uso nos equipamentos reais | Partes funcionais concluídas | ⚪ Planejada | Lucas + Vinicius | A definir |
| P27 | Publicação | Colocar o sistema em hospedagem, configurar domínio, cópias de segurança e acompanhamento | P26 | 🔴 Bloqueada | A definir | A definir |

## 5. Partes que podem começar em paralelo agora

Depois de definir um responsável, as seguintes partes podem ser iniciadas agora:

1. **P09 — Entradas e saídas:** frontend do histórico operacional; a dependência P06 está concluída;
2. **P14 — Caixa:** frontend demonstrativo; o P04 e as regras financeiras estão concluídos e aprovados;
3. **P15 — Encomendas:** frontend do recebimento e entrega; o P04 e o cadastro de pessoas estão concluídos;
4. **P18 — Banco de dados inicial:** parte interna; as regras, o modelo de dados e a arquitetura estão aprovados.

Como a equipe escolheu desenvolver primeiro o frontend, a sequência recomendada é **P09, P14 e P15**. A **P18** já está liberada tecnicamente e pode ser iniciada depois desses protótipos ou em paralelo por outro responsável, desde que a equipe combine a divisão para evitar alterações conflitantes.

Antes de começar qualquer uma dessas partes, deverá ser registrado neste documento o responsável e a branch utilizada. “A definir” não significa que a parte está bloqueada; significa apenas que a equipe ainda não atribuiu a parte a Lucas ou Vinicius.

## 6. Regra de trabalho para cada parte

Antes de começar:

1. escolher uma parte com situação disponível;
2. registrar o nome do responsável neste documento;
3. mudar a situação para “Em andamento”;
4. criar uma branch exclusiva, por exemplo `vinicius/p11-cadastro-imoveis`;
5. confirmar quais documentos de requisitos orientam aquela parte.

Durante o trabalho:

1. não alterar uma parte que já tenha outro responsável sem combinar antes;
2. manter dados demonstrativos claramente identificados;
3. testar computador e celular;
4. realizar entregas pequenas, evitando acumular muitas telas em uma única revisão;
5. registrar decisões que mudem regras ou estrutura.

Para concluir:

1. executar os testes da parte;
2. conferir se não existem erros visuais ou mensagens confusas;
3. abrir um Pull Request no GitHub;
4. pedir revisão da outra pessoa;
5. corrigir os apontamentos;
6. juntar a parte revisada ao projeto principal;
7. atualizar a situação para “Concluída”.

## 7. Critérios mínimos para considerar uma parte pronta

Uma parte somente estará pronta quando:

- respeitar a documentação funcional correspondente;
- funcionar no computador e no celular;
- indicar carregamento, ausência de dados e erros;
- possuir textos compreensíveis para o usuário;
- respeitar as permissões previstas, mesmo que ainda demonstrativas;
- possuir testes proporcionais ao risco;
- passar pela revisão da outra pessoa da equipe;
- não apresentar dados demonstrativos como se fossem dados reais.

## 8. Decisões pendentes da equipe

| Decisão | Responsáveis | Situação |
|---|---|---|
| Escolher a primeira parte que Vinicius desenvolverá | Lucas e Vinicius | Resolvida — P05 (Modo Portaria) |
| Definir os atalhos exatos do Modo Portaria | Lucas e Vinicius | Pendente — protótipo do P05 usa atalhos provisórios até confirmação |
| Definir os campos mínimos do cadastro rápido durante o atendimento | Lucas e Vinicius | Resolvida — campos implementados e P07 concluída |
| Escolher os responsáveis por P09, P14, P15 e P18 | Lucas e Vinicius | Pendente |
| Confirmar quais equipamentos existem na portaria | Lucas e Vinicius | Pendente |
| Definir quem revisará cada primeira entrega | Lucas e Vinicius | Pendente |

## 9. Documentos principais para consulta

- [Diretrizes do projeto](000_DIRETRIZES_DO_PROJETO.md)
- [Product Book — Parte 1](001_VOLUME_01_PRODUCT_BOOK_PARTE_01.md)
- [Product Book — Parte 2](001_VOLUME_01_PRODUCT_BOOK_PARTE_02.md)
- [Product Book — Parte 3](001_VOLUME_01_PRODUCT_BOOK_PARTE_03.md)
- [Design System](003_DESIGN_SYSTEM.md)
- [Dashboard](004_UX_UI_DASHBOARD.md)
- [Validação de Entrada](005_UX_UI_VALIDACAO.md)
- [Pré-cadastro](006_UX_UI_PRE_CADASTRO.md)
- [Cadastro de imóvel e pessoa](007_UX_UI_CADASTRO_IMOVEL.md)
- [Administração](008_ADMINISTRACAO.md)
- [Regras de negócio](009_REGRAS_DE_NEGOCIO.md)
- [Banco de dados](010_BANCO_DE_DADOS.md)
- [Arquitetura do sistema](011_ARQUITETURA_DO_SISTEMA.md)
