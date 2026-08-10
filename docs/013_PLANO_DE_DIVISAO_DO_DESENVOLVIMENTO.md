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
| P05 | Modo Portaria | Página inicial do porteiro com caixa, atalhos, alertas e atendimentos recentes | P01 e definição dos atalhos | 🟢 Disponível para protótipo | A definir | A definir |
| P06 | Validação de entrada | Identificação da pessoa, veículo, contribuição, observações, negação e liberação | P04 | 🟢 Disponível para protótipo | A definir | A definir |
| P07 | Cadastro rápido no atendimento | Criar cadastro mínimo sem perder a validação em andamento | P06 e P10 | 🔴 Bloqueada | A definir | A definir |
| P08 | Pré-cadastro | Solicitação antecipada e análise pela portaria | P04 | 🟢 Disponível | A definir | A definir |
| P09 | Entradas e saídas | Histórico, consulta, filtros, detalhes e resultados das tentativas | P06 | ⚪ Planejada | A definir | A definir |
| P10 | Cadastro de pessoas | Dados pessoais, documentos, foto, vínculos, credenciais e situação | P04 | 🟢 Disponível | A definir | A definir |
| P11 | Cadastro de imóveis | Blocos, unidades, endereços, moradores, responsáveis e vínculos | P04 | 🟢 Disponível | A definir | A definir |
| P12 | Cadastro de veículos | Placa, características, proprietário, vínculo e situação | P04 | 🟢 Disponível | A definir | A definir |
| P13 | Prestadores e empresas | Empresas, funcionários, serviços, documentos e autorizações | P04 | 🟢 Disponível para protótipo | A definir | A definir |

### 4.3 Módulos operacionais e administrativos

| ID | Parte | Entrega principal | Dependência | Situação | Responsável | Branch |
|---|---|---|---|---|---|---|
| P14 | Caixa | Abertura, movimentações, contribuições, conferência e fechamento | P04 e regras financeiras | ⚪ Planejada | A definir | A definir |
| P15 | Encomendas | Recebimento, armazenamento, aviso e entrega de pacotes | P04 e cadastro de pessoas | ⚪ Planejada | A definir | A definir |
| P16 | Relatórios | Consultas, filtros e exportações autorizadas | Dados reais dos módulos | 🔴 Bloqueada | A definir | A definir |
| P17 | Administração | Usuários, perfis, permissões, configurações, equipamentos e auditoria | Login real e banco de dados | 🔴 Bloqueada | A definir | A definir |

### 4.4 Parte interna e dados reais

| ID | Parte | Entrega principal | Dependência | Situação | Responsável | Branch |
|---|---|---|---|---|---|---|
| P18 | Banco de dados inicial | Estrutura segura para imóveis, pessoas, vínculos, veículos e usuários | Regras e arquitetura aprovadas | ⚪ Planejada | A definir | A definir |
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

Depois de definir um responsável, as seguintes partes podem ser iniciadas sem aguardar a conclusão do login visual:

1. **P05 — Protótipo do Modo Portaria**;
2. **P06 — Protótipo da Validação de Entrada**;
3. **P08 — Pré-cadastro**;
4. **P10 — Cadastro de pessoas**;
5. **P11 — Cadastro de imóveis**;
6. **P12 — Cadastro de veículos**;
7. **P13 — Protótipo de prestadores e empresas**.

Com o **P04 — Componentes compartilhados** concluído, Lucas ou Vinicius poderão assumir uma das partes **P05, P06, P08, P10, P11, P12 ou P13**. A parte escolhida deverá receber responsável e branch neste documento antes do início.

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
| Escolher a primeira parte que Vinicius desenvolverá | Lucas e Vinicius | Pendente |
| Definir os atalhos exatos do Modo Portaria | Lucas e Vinicius | Pendente |
| Definir os campos mínimos do cadastro rápido durante o atendimento | Lucas e Vinicius | Pendente |
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
