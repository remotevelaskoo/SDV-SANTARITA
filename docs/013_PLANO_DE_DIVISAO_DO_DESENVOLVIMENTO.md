# Plano de divisão e acompanhamento do desenvolvimento

**Projeto:** SDV Access — Santa Rita

**Equipe:** Lucas Pastorelli e Vinicius Velasco

**Última atualização:** 11 de agosto de 2026

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
| ✅ Concluídas | 17 | P01 a P15, P18 e P20 |
| 🟡 Em andamento | 2 | P19 e P21 |
| 🟢 Disponíveis | 0 | — |
| 🔴 Bloqueadas | 7 | P16, P17, P22 a P25 e P27 |
| ⚪ Planejada | 1 | P26 |

O avanço por quantidade de partes é de **17 concluídas em 27 (aproximadamente 63%)**. Esse percentual representa o número de partes concluídas, não o esforço total, pois banco de dados, integrações, segurança e publicação possuem complexidades diferentes.

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
| P08 | Pré-cadastro | Solicitação antecipada e análise pela portaria | P04 | ✅ Concluída (convite público ainda demonstrativo; fila e análise da portaria com persistência real desde o PR #21) | Lucas + Codex + Vinicius | `codex/p08-pre-cadastro`, [`vinicius/pre-registration-review-persistente`](https://github.com/remotevelaskoo/SDV-SANTARITA/pull/21) |
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
| Fila da portaria | Resumo, busca, filtros, tabela responsiva e cartões no celular, com dados persistidos em banco (`pre_registrations`) | ✅ Concluída |
| Análise | Ficha completa com todos os dados preenchidos (não só checklist), histórico, aprovação, rejeição e solicitação de correção | ✅ Concluída |
| Edição controlada pela portaria | Correção textual com permissão específica, justificativa obrigatória, auditoria persistente (`pre_registration_edits`) e controle de concorrência por versão — [PR #21](https://github.com/remotevelaskoo/SDV-SANTARITA/pull/21) | ✅ Concluída |
| Segurança do protótipo | Aprovação separada da Validação de Entrada e ausência de comando físico | ✅ Concluída |
| Qualidade | Computador, celular, validações, testes automáticos e compilação visual | ✅ Aprovada |

**Nota sobre persistência e login (PR #21):** a fila e a análise da portaria deixaram de usar dados demonstrativos em array e passaram a usar tabelas reais (UUIDv7, conforme `ADR-003`), com login real via `Auth::attempt` restrito à permissão de edição. Isso é uma fatia mínima e escopada de P18/P19/P20, feita apenas para viabilizar a edição auditada — não é a entrega completa dessas partes, que seguem conforme registrado abaixo. O convite público do pré-cadastro (etapas 1 a 6) continua demonstrativo e ainda não persiste no banco.

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
| P18 | Banco de dados inicial | Estrutura segura para imóveis, pessoas, vínculos, veículos e usuários | Regras e arquitetura aprovadas | ✅ Concluída — fundação multi-implantação, grupo Imóveis, grupo Pessoas, grupo Vínculos, grupo Veículos e grupo Usuários/Perfis/Permissões (`usuario_implantacoes`, `permissoes`, `perfis`, `perfil_permissoes`, `usuario_perfis`) entregues, todos com testes de isolamento e concorrência. Pendências conhecidas, deixadas para quando existir tela real de administração (P19/P20/P21): `usuario_excecoes_permissao` e `sessoes_usuario` não construídas (sem consumidor ainda); `ImplantacaoContext::current()` ainda não consulta `usuario_implantacoes` — resolve sempre para a implantação única; precedência entre concessão e negação de permissão segue indefinida (`PEN-BDD-017`); integridade temporal (`término > início`) só é validada em `vinculos`/`veiculo_vinculos`/`usuario_perfis`, faltando em `enderecos_imoveis`/`pessoa_documentos`/`pessoa_contatos`/`pessoa_enderecos`. `users.can_edit_pre_registrations` foi removida na fatia 1 do P20 — `PreRegistrationPolicy` passou a usar `hasPermission('pre-registro.editar')` | Vinicius | [`vinicius/p18-fundacao-imoveis`](https://github.com/remotevelaskoo/SDV-SANTARITA/pull/22), [`vinicius/p18-pessoas`](https://github.com/remotevelaskoo/SDV-SANTARITA/pull/23), [`vinicius/p18-vinculos`](https://github.com/remotevelaskoo/SDV-SANTARITA/pull/26), [`vinicius/p18-veiculos`](https://github.com/remotevelaskoo/SDV-SANTARITA/pull/27), [`vinicius/p18-usuarios`](https://github.com/remotevelaskoo/SDV-SANTARITA/pull/28) |
| P19 | Login real | Usuários individuais, senhas protegidas, sessões e recuperação de acesso | P18 | 🟡 Em andamento — login (`Auth::attempt`) e logout de verdade (`POST /sair`, invalida sessão e regenera token CSRF) entregues; nome e iniciais reais do operador logado substituem o "Tatiane Souza" fixo no layout (`components/layouts/app.blade.php`); proteção das rotas com middleware `auth` foi entregue (junto com a autorização granular) na fatia 2 do P20. Faltam recuperação de acesso (exige envio de e-mail, ainda não configurado), revogação de outras sessões/`sessoes_usuario` e resolução real de contexto multi-implantação (`ImplantacaoContext` continua fixo na única implantação) | Vinicius | [`vinicius/p19-logout-real`](https://github.com/remotevelaskoo/SDV-SANTARITA/pull/29) |
| P20 | Perfis e permissões | Definir o que porteiro/caixa, gestor, auditor e administrador podem fazer, e proteger as rotas internas | P18 e P19 | ✅ Concluída — fatia 1: catálogo real de 25 permissões e os 4 perfis definidos pelo PO (`PORTEIRO_CAIXA`, `GESTOR`, `AUDITOR`, `ADMINISTRADOR`), com os grants exatos da especificação (inclui Veículos/Empresas/Encomendas no PORTEIRO_CAIXA, por decisão do PO); `users.can_edit_pre_registrations` removida — `PreRegistrationPolicy` agora usa `hasPermission('pre-registro.editar')`; contas demo `portaria`→Porteiro/Caixa, `portaria.leitura`→Auditor, e duas novas (`gestor`, `administrador`). Fatia 2: todas as rotas internas passaram a exigir `auth`, mais o middleware `permissao:<chave>` (`App\Http\Middleware\EnsurePermission`) checando `hasPermission()` por rota; usuário autenticado sem a permissão é redirecionado ao dashboard com aviso "Sem permissão"; usuário não autenticado é redirecionado ao login (comportamento padrão do Laravel); só `/entrar` e `/pre-cadastro/convite-demonstracao` continuam públicas. Pendências fora do escopo desta parte, registradas para depois: o contexto operacional Portaria/Caixa (troca de tela sem novo login) e a auditoria genérica de toda operação (P22) | Vinicius | [`vinicius/p20-perfis-permissoes`](https://github.com/remotevelaskoo/SDV-SANTARITA/pull/30), [`vinicius/p20-protecao-rotas`](https://github.com/remotevelaskoo/SDV-SANTARITA/pull/31) |
| P21 | Conexão das telas | Trocar dados demonstrativos por cadastros e operações reais | P18 a P20 | 🟡 Em andamento — planejada em 7 fatias (uma PR cada). **Fatia 1 concluída** — `PropertyManagement.php` (P11) e `VehicleManagement.php` (P12) passaram a ler/escrever em `Imovel`/`Veiculo`/`VeiculoVinculo` reais (P18), com o CEP (ViaCEP) e o normalizador de placa já existentes reaproveitados; proprietário de veículo agora precisa ser uma `Pessoa` já cadastrada (erro claro quando não encontrada), já que `veiculo_vinculos.pessoa_id` é obrigatório. **Fatia 2 concluída** — `PersonRegistration.php` (`/pessoas/nova`) passa a criar `Pessoa`+`PessoaDocumento`+`PessoaContato`+`Vinculo` reais (o seletor de imóvel, antes com 3 opções fixas, agora lista os imóveis reais); `PublicPreRegistration.php` passa a persistir em `PreRegistration` real, com protocolo gerado no servidor, endereço concatenado numa única string e destino/responsável resolvidos como já fazia a fila de análise. Duas decisões provisórias registradas nesta fatia: (a) o formulário público não coleta período pretendido da visita — usa uma janela padrão de 24h a partir do envio até essa decisão de produto ser tomada; (b) um bug real foi encontrado e corrigido durante a verificação no navegador — a validação do campo "imóvel" estava presa à etapa 3 (Endereço), mas o seletor de imóvel só existe na etapa 4 (Informações de acesso); com o valor padrão fixo antigo isso nunca disparava, e ficou exposto ao trocar para dados reais. Faltam: fatia 3 (Empresas/prestadores — schema nova), fatia 4 (Encomendas — schema nova), fatia 5 (Caixa — schema nova), fatia 6 (Histórico de acesso + Validação real, a mais complexa — schema nova), fatia 7 (Portaria/Dashboard, por último). Decisão registrada: o histórico de acesso (fatia 6) é uma tabela de domínio própria, não a estrutura genérica de auditoria do ADR-004 — a P22 continua separada e adiada | Vinicius | [`vinicius/p21-imoveis-veiculos`](https://github.com/remotevelaskoo/SDV-SANTARITA/pull/33), [`vinicius/p21-pessoas`](https://github.com/remotevelaskoo/SDV-SANTARITA/tree/vinicius/p21-pessoas) |
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

P09, P14, P15, o **P18 — Banco de dados inicial** (fundação multi-implantação e os grupos Imóveis, Pessoas, Vínculos, Veículos e Usuários/Perfis/Permissões) e o **P20 — Perfis e permissões** (catálogo real de perfis e rotas internas protegidas) já foram concluídos. **P19 — Login real** e **P21 — Conexão das telas** seguem em andamento com Vinicius (P21 dividida em 7 fatias, uma por vez). As demais partes (P16, P17, P22 a P25 e P27) seguem bloqueadas por dependência, e a P26 está apenas planejada.

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
| Escolher os responsáveis por P09, P14 e P15 | Lucas e Vinicius | Resolvida — Vinicius desenvolveu as três partes |
| Escolher o responsável por P18 | Lucas e Vinicius | Resolvida — Vinicius iniciou pela fundação e pelo grupo Imóveis |
| Escolher o responsável por P20 | Lucas e Vinicius | Resolvida — Vinicius, a partir de especificação detalhada do PO ("Correção da decisão do P20") |
| Escolher o responsável por P21 | Lucas e Vinicius | Resolvida — Vinicius, dividida em 7 fatias planejadas previamente |
| Confirmar quais equipamentos existem na portaria | Lucas e Vinicius | Pendente |
| Definir quem revisará cada primeira entrega | Lucas e Vinicius | Pendente |

## 9. Correções e ajustes técnicos

Registro leve de correções em partes já marcadas como concluídas — não criam uma nova linha no quadro principal, pois não são uma nova etapa, apenas um ajuste sobre o que já existia. Cada uma tem descrição completa no commit/PR correspondente.

| Data | Parte afetada | O que foi corrigido | PR |
|---|---|---|---|
| 10/08/2026 | P08 / P11 | Cenário do pré-cadastro ajustado para turista (praia) e busca automática de endereço por CEP adicionada ao Cadastro de imóveis | [#17](https://github.com/remotevelaskoo/SDV-SANTARITA/pull/17) |
| 10/08/2026 | P08 | Busca automática de endereço por CEP também no Pré-cadastro | [#18](https://github.com/remotevelaskoo/SDV-SANTARITA/pull/18) |
| 10/08/2026 | P08 | Destino da visita reativo ao tipo de acesso (turista → praia automático; visitante → escolhe o imóvel) | [#19](https://github.com/remotevelaskoo/SDV-SANTARITA/pull/19) |
| 11/08/2026 | P08 | Painel de edição da fila de pré-cadastros fechava sozinho ao abrir, por um detalhe de ordem dos hooks do Livewire (`dehydrate()` roda depois de o framework já ter processado os eventos despachados) | [#25](https://github.com/remotevelaskoo/SDV-SANTARITA/pull/25) |

## 10. Documentos principais para consulta

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
