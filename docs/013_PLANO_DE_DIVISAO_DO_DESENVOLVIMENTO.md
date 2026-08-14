# Plano de divisão e acompanhamento do desenvolvimento

**Projeto:** SDV Access — Santa Rita

**Equipe:** Lucas Pastorelli e Vinicius Velasco

**Última atualização:** 14 de agosto de 2026

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
| ✅ Concluídas | 21 | P01 a P16 e P18 a P22 |
| 🟡 Em andamento | 2 | P17 e P26 |
| 🟢 Disponíveis | 0 | — |
| 🔴 Bloqueadas | 4 | P23 a P25 e P27 |
| ⚪ Planejada | 0 | — |

O avanço por quantidade de partes é de **21 concluídas em 27 (aproximadamente 78%)**. Esse percentual representa o número de partes concluídas, não o esforço total, pois banco de dados, integrações, segurança e publicação possuem complexidades diferentes. O AJ-001 foi concluído como reforço de segurança de partes existentes e, por isso, não altera a contagem das 27 partes principais.

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
| P02 | Dashboard operacional | Indicadores, alertas, gráfico, acessos recentes e câmeras demonstrativas | P01 | ✅ Concluída — `app/Livewire/Dashboard.php` passou a consultar dados reais (`Pessoa`, `HistoricoAcesso`, `Vinculo`, `Veiculo`, `CaixaMovimentacao`, `PreRegistration`) em vez de métricas fixas simuladas; a seção de câmeras não afirma mais "AO VIVO" nem qualquer conexão — exibe "Não integrado" e explica que depende da homologação do equipamento (P23). Corrigido dentro do PR #60 (`codex/correcoes-avaliacao-previa`), motivado por uma avaliação prévia do sistema que identificou o dashboard como fonte de dados fictícios | Lucas + Codex + Vinicius | `codex/fundacao-frontend`, `codex/correcoes-avaliacao-previa` |
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
| P08 | Pré-cadastro | Solicitação antecipada e análise pela portaria | P04 | ✅ Concluída — convite público, fila e análise persistem dados reais; documento e selfie reais passaram a usar armazenamento privado e conferência autorizada no AJ-001 | Lucas + Codex + Vinicius | `codex/p08-pre-cadastro`, [`vinicius/pre-registration-review-persistente`](https://github.com/remotevelaskoo/SDV-SANTARITA/pull/21), `codex/aj001-conferencia-visual` |
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
| Fluxo em seis etapas | Dados pessoais, endereço informado, envio real de documento e selfie, veículo opcional e confirmação | ✅ Concluída |
| Protocolo | Resultado demonstrativo, cópia e mensagem de que o protocolo não autoriza entrada | ✅ Concluída |
| Fila da portaria | Resumo, busca, filtros, tabela responsiva e cartões no celular, com dados persistidos em banco (`pre_registrations`) | ✅ Concluída |
| Análise | Ficha completa com todos os dados preenchidos (não só checklist), conferência visual privada de documento/selfie, histórico, aprovação, rejeição e solicitação de correção | ✅ Concluída |
| Edição controlada pela portaria | Correção textual com permissão específica, justificativa obrigatória, auditoria persistente (`pre_registration_edits`) e controle de concorrência por versão — [PR #21](https://github.com/remotevelaskoo/SDV-SANTARITA/pull/21) | ✅ Concluída |
| Segurança do protótipo | Aprovação separada da Validação de Entrada e ausência de comando físico | ✅ Concluída |
| Qualidade | Computador, celular, validações, testes automáticos e compilação visual | ✅ Aprovada |

**Nota sobre persistência e arquivos:** a fila, a análise e o convite público usam tabelas reais (UUIDv7, conforme `ADR-003`) e login real nas áreas internas. O AJ-001 substituiu a simulação de documento/selfie por arquivos reais: JPG, PNG ou WebP de até 8 MB, catálogo no banco e conteúdo fora da área pública. A configuração local usa pasta privada; a mesma interface está preparada para armazenamento compatível com S3 quando a infraestrutura de produção for escolhida. Antivírus externo, retenção definitiva e o provedor de produção continuam decisões de infraestrutura e não devem ser considerados entregues.

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
| P16 | Relatórios | Consultas, filtros e exportações autorizadas | Dados reais dos módulos | ✅ Concluída — especificação `SDV-UXR-014`; relatórios reais de acessos e caixa; escopo próprio para Porteiro/Caixa e consolidado para Gestor/Auditor/Administrador; filtros, totais conciliáveis, exportação CSV sem dados sensíveis e 7 testes específicos. A exportação CSV passou a ser auditada na P22. PDF e exportação persistida/assíncrona permanecem nas dependências documentadas | Vinicius | `codex/p16-relatorios` |
| P17 | Administração | Usuários, perfis, permissões, configurações, equipamentos e auditoria | Login real e banco de dados | 🟡 Em andamento — planejada em fatias, como P20/P21. **Fatia 1 concluída** — gerenciamento de usuários (`UserManagement.php`, rota `/usuarios`, permissão `usuarios.administrar`): listar, convidar, bloquear e inativar contas. O administrador nunca define a senha: o usuário recebe convite, fica `pendente` e escolhe a própria senha. Login exige `status='ativo'`; bloquear é reversível e inativar encerra os perfis vigentes; as duas ações são vetadas para a própria conta e para o último administrador ativo. **Integração concluída na P22:** as alterações entram na auditoria genérica e bloquear/inativar também encerra as sessões abertas da conta. **Fatia 2 concluída** — CRUD de perfis (`PerfilManagement.php`, rota `/perfis`, permissão órfã `perfis.administrar` desde o P18, agora usada pela primeira vez): criar/editar nome e matriz de permissões (agrupada por módulo, reaproveitando `x-ui.checkbox`), inativar/reativar (sem exclusão definitiva — `usuario_perfis.perfil_id` já é `restrictOnDelete`, e a doc recomenda "Apagar perfil" → "Inativar perfil"). Proteção do último concedente: não deixa remover `usuarios.administrar` nem `perfis.administrar` de um perfil, nem inativá-lo, se for o único perfil ativo que concede essa permissão a um usuário ativo — mesmo espírito de `wouldRemoveLastAdministrator()` do P17 fatia 1, generalizado para múltiplas permissões críticas. Auditado via `AuditService` (`perfil_criado`/`perfil_alterado`/`perfil_inativado`/`perfil_reativado`). Fica para fatias futuras: pontos de acesso, cadastro de equipamentos; e da própria spec de perfis (`docs/008` §13-16) — tipos de perfil, duplicar, comparar versões, resumo de impacto, permissões efetivas explicáveis e herança de perfis (esta última, o próprio doc já adia: "Herança de perfis não será pressuposta"). **Fatia 3 concluída** — configurações da implantação (`ConfiguracaoManagement.php`, rota `/configuracoes`, permissão órfã `configuracoes.gerenciar` desde o P18/P20, agora usada pela primeira vez): catálogo global de configurações (tabela `configuracoes`, mesmo espírito de `permissoes` — não depende de implantação) com override esparso por implantação (`implantacao_configuracoes` — ausência de linha significa "usando o valor padrão"). Corte em relação à spec completa (`docs/008` §17-18, `docs/010` §20, RN-094/095, todas com o fluxo de publicação já registrado como pendência aberta `PEN-RNG-019`): sem versionamento/rascunho/agendamento — "restaurar padrão" apaga o override (reversão sem apagar o histórico de auditoria, que nunca é removido) e cada alteração é auditada via `AuditService` (`configuracao_alterada`/`configuracao_restaurada_padrao`, módulo `configuracoes`). Catálogo inicial com só 3 chaves em 2 categorias (`geral.telefone_contato`, `geral.email_contato`, `caixa.saldo_sugerido_abertura`) — as outras 9 categorias da spec (identidade configurável, integrações, recursos habilitados etc.) ficam pendência explícita, sem esboço, por implicarem funcionalidades que ainda não existem no sistema. `CashRegister::mount()` passou a consumir `caixa.saldo_sugerido_abertura` de verdade (valor sugerido no formulário de abertura de turno), prova de que a configuração tem efeito real e não é só uma tela administrativa isolada. **Fatia 4 concluída (última fatia sem dependência de hardware — as restantes, pontos de acesso e cadastro de equipamentos, seguem bloqueadas junto com o P23-P25)** — catálogos parametrizados (`CatalogoManagement.php`, rota `/catalogos`, nova permissão `catalogos.gerenciar`, concedida só a Administrador): mesmo padrão de duas camadas da fatia 3 (`catalogos` global — só a chave/rótulo, como `permissoes` — e `catalogo_itens` por implantação, que podem ser criados/editados/inativados por quem administra, diferente da fatia 3 onde só o valor é sobrescrito). Corte em relação à spec completa (`docs/008` §19, ~12 exemplos de catálogo): só o catálogo `motivos_negativa` nesta fatia — os demais (motivos de rejeição/bloqueio/contingência, tipos de vínculo, papéis, categorias de prestador, formas de pagamento, isenções, áreas, horários, tipos documentais) ficam pendência, sem esboço, por não terem hoje um ponto de consumo hardcoded óbvio para substituir. **Consumo real**: `AccessValidation::denialReason`/`denialReasonLabel()` (`app/Livewire/AccessValidation.php`) e a view de Validação de entrada trocaram o `Rule::in()`/`match()` fixo (4 motivos hardcoded) pelos itens reais e ativos do catálogo — inativar um motivo o remove do formulário de negativa imediatamente, sem apagar o histórico de acessos já registrados com ele (RN-096). Auditado via `AuditService` (`catalogo_item_criado`/`catalogo_item_alterado`/`catalogo_item_inativado`/`catalogo_item_reativado`, módulo `catalogos`). Seeder novo (`CatalogoSeeder`) preserva os mesmos 4 motivos e textos que já existiam hardcoded, sem mudar o comportamento de quem já usa o sistema; `MultiImplantacaoDemoSeeder` replica os mesmos itens para Jardins | Vinicius | [`vinicius/p17-usuarios-fatia1`](https://github.com/remotevelaskoo/SDV-SANTARITA/pull/50), `vinicius/p17-perfis-fatia2`, `vinicius/p17-configuracoes-fatia3`, `vinicius/p17-catalogos-fatia4` |

### 4.4 Parte interna e dados reais

| ID | Parte | Entrega principal | Dependência | Situação | Responsável | Branch |
|---|---|---|---|---|---|---|
| P18 | Banco de dados inicial | Estrutura segura para imóveis, pessoas, vínculos, veículos e usuários | Regras e arquitetura aprovadas | ✅ Concluída — fundação multi-implantação, grupo Imóveis, grupo Pessoas, grupo Vínculos, grupo Veículos e grupo Usuários/Perfis/Permissões (`usuario_implantacoes`, `permissoes`, `perfis`, `perfil_permissoes`, `usuario_perfis`) entregues, todos com testes de isolamento e concorrência. Pendências conhecidas, deixadas para quando existir tela real de administração (P19/P20/P21): `usuario_excecoes_permissao` e `sessoes_usuario` não construídas (sem consumidor ainda); `ImplantacaoContext::current()` ainda não consulta `usuario_implantacoes` — resolve sempre para a implantação única; precedência entre concessão e negação de permissão segue indefinida (`PEN-BDD-017`); integridade temporal (`término > início`) só é validada em `vinculos`/`veiculo_vinculos`/`usuario_perfis`, faltando em `enderecos_imoveis`/`pessoa_documentos`/`pessoa_contatos`/`pessoa_enderecos`. `users.can_edit_pre_registrations` foi removida na fatia 1 do P20 — `PreRegistrationPolicy` passou a usar `hasPermission('pre-registro.editar')` | Vinicius | [`vinicius/p18-fundacao-imoveis`](https://github.com/remotevelaskoo/SDV-SANTARITA/pull/22), [`vinicius/p18-pessoas`](https://github.com/remotevelaskoo/SDV-SANTARITA/pull/23), [`vinicius/p18-vinculos`](https://github.com/remotevelaskoo/SDV-SANTARITA/pull/26), [`vinicius/p18-veiculos`](https://github.com/remotevelaskoo/SDV-SANTARITA/pull/27), [`vinicius/p18-usuarios`](https://github.com/remotevelaskoo/SDV-SANTARITA/pull/28) |
| P19 | Login real | Usuários individuais, senhas protegidas, sessões e recuperação de acesso | P18 | ✅ Concluída — login (`Auth::attempt`) e logout de verdade (`POST /sair`, invalida sessão e regenera token CSRF) entregues; nome e iniciais reais do operador logado substituem o "Tatiane Souza" fixo no layout (`components/layouts/app.blade.php`); proteção das rotas com middleware `auth` foi entregue (junto com a autorização granular) na fatia 2 do P20. Revogação de sessões entregue: tela `/sessoes` (`ActiveSessions.php`) lista as sessões do operador autenticado a partir da tabela nativa `sessions` (driver `database`, já configurada em produção via `SESSION_DRIVER=database`), permite encerrar uma sessão específica ou todas as outras com um clique, e nunca expõe ou encerra sessão de outro usuário (`where('user_id', Auth::id())` em toda leitura/exclusão); decisão explícita do PO de reaproveitar `sessions` em vez de criar `sessoes_usuario` (citada em docs/010 linha ~513 mas nunca com estrutura de colunas definida em lugar nenhum). Link de acesso adicionado ao popover do usuário no layout. Recuperação de acesso entregue: rotas públicas `password.request`/`password.reset` (`ForgotPassword.php`/`ResetPassword.php`), usando o broker nativo do Laravel (tabela `password_reset_tokens`, já existente) — a identificação (`username`) é resolvida para o e-mail internamente antes de acionar `Password::sendResetLink()`, e a tela sempre mostra a mesma mensagem de sucesso, exista ou não o usuário (docs/008 §12.1, "não revelar existência de usuário publicamente"). `ResetPassword::createUrlUsing`/`toMailUsing`, customizados em `AppServiceProvider`, apontam o link do e-mail para a rota real do produto e trocam o texto padrão em inglês por uma mensagem com a marca "SDV Access Santa Rita"; `MAIL_FROM_ADDRESS`/`MAIL_FROM_NAME` ajustados de "hello@example.com" para o domínio do produto. Fluxo verificado de ponta a ponta usando o mailer `log` (`MAIL_MAILER=log`, sem SMTP real configurado ainda). **Contexto real de multi-implantação entregue (fecha o P19):** `ImplantacaoContext::current()` passou a resolver pela sessão (`implantacao_atual_id`) quando o usuário está autenticado, revalidando a cada chamada que a implantação segue `ativa` e que o usuário ainda tem `usuario_implantacoes` ativa para ela (não confia cegamente na sessão — ADR-002 §11); fallback para CLI/seeders/testes preservado sem alteração. Novo middleware `EnsureImplantacaoSelected` (grupo `web`) é o único ponto que decide: 0 ou 1 implantação ativa passa direto (grava a única automaticamente na sessão), 2+ sem seleção válida redireciona para `/selecionar-implantacao` (`ImplantacaoSelection.php`, tela dedicada fora do layout autenticado, já que o usuário ainda não tem contexto pra renderizar nada implantação-scoped); a seleção revalida a associação do usuário antes de gravar (nunca confia no id vindo do clique). Vazamento real corrigido nesta fatia: `UserManagement::filteredUsers()`/`userCounts()` não filtravam por implantação (inofensivo enquanto só existia Santa Rita) — passaram a exigir `usuario_implantacoes` ativa na implantação atual, verificado ao vivo trocando o contexto de um usuário com acesso a duas implantações e confirmando que a lista de `/usuarios` muda (5 usuários em Santa Rita, 1 em Jardins). Seeder novo `MultiImplantacaoDemoSeeder` cria uma segunda implantação de demonstração (`Jardins`) e o login `administrador.multi` (senha `sdv2026`) com acesso às duas, sem tocar nos 4 logins demo existentes (continuam só em Santa Rita, sem ver a tela de seleção). Fica para outra fatia, fora do escopo do ADR-002 §11.1 aprovado aqui (seleção pós-login): resolução por subdomínio/domínio por implantação (`PEN-ADR-002-004`, explicitamente adiada pelo próprio ADR) e convidar um usuário já existente para uma segunda implantação (`UserManagement::saveUser()` continua só criando usuário novo) | Vinicius | [`vinicius/p19-logout-real`](https://github.com/remotevelaskoo/SDV-SANTARITA/pull/29), [`vinicius/p19-revogacao-sessoes`](https://github.com/remotevelaskoo/SDV-SANTARITA/pull/46), [`vinicius/p19-recuperacao-acesso`](https://github.com/remotevelaskoo/SDV-SANTARITA/pull/47), `vinicius/p19-multi-implantacao` |
| P20 | Perfis e permissões | Definir o que porteiro/caixa, gestor, auditor e administrador podem fazer, e proteger as rotas internas | P18 e P19 | ✅ Concluída — catálogo real dos 4 perfis definidos pelo PO (`PORTEIRO_CAIXA`, `GESTOR`, `AUDITOR`, `ADMINISTRADOR`) e proteção das rotas internas com autenticação mais permissão específica. A P22 ampliou o catálogo de 25 para 27 permissões de auditoria. O AJ-001 elevou o total para 28 com `arquivos.sensiveis.visualizar`, concedida a Porteiro/Caixa, Gestor e Administrador; Auditor não recebe imagens sensíveis automaticamente. Permanece fora deste escopo o contexto operacional Portaria/Caixa (troca de tela sem novo login) | Vinicius + Lucas + Codex | [`vinicius/p20-perfis-permissoes`](https://github.com/remotevelaskoo/SDV-SANTARITA/pull/30), [`vinicius/p20-protecao-rotas`](https://github.com/remotevelaskoo/SDV-SANTARITA/pull/31), `codex/aj001-conferencia-visual` |
| P21 | Conexão das telas | Trocar dados demonstrativos por cadastros e operações reais | P18 a P20 | ✅ Concluída — planejada e entregue em 7 fatias (uma PR cada). **Fatia 1 concluída** — `PropertyManagement.php` (P11) e `VehicleManagement.php` (P12) passaram a ler/escrever em `Imovel`/`Veiculo`/`VeiculoVinculo` reais (P18), com o CEP (ViaCEP) e o normalizador de placa já existentes reaproveitados; proprietário de veículo agora precisa ser uma `Pessoa` já cadastrada (erro claro quando não encontrada), já que `veiculo_vinculos.pessoa_id` é obrigatório. **Fatia 2 concluída** — `PersonRegistration.php` (`/pessoas/nova`) passa a criar `Pessoa`+`PessoaDocumento`+`PessoaContato`+`Vinculo` reais (o seletor de imóvel, antes com 3 opções fixas, agora lista os imóveis reais); `PublicPreRegistration.php` passa a persistir em `PreRegistration` real, com protocolo gerado no servidor, endereço concatenado numa única string e destino/responsável resolvidos como já fazia a fila de análise. Duas decisões provisórias registradas nesta fatia: (a) o formulário público não coleta período pretendido da visita — usa uma janela padrão de 24h a partir do envio até essa decisão de produto ser tomada; (b) um bug real foi encontrado e corrigido durante a verificação no navegador — a validação do campo "imóvel" estava presa à etapa 3 (Endereço), mas o seletor de imóvel só existe na etapa 4 (Informações de acesso); com o valor padrão fixo antigo isso nunca disparava, e ficou exposto ao trocar para dados reais. **Fatia 3 concluída** — `CompanyManagement.php` (P13) passa a ler/escrever em `Empresa` real; schema nova criada (`empresas`, `empresa_prestadores`, `empresa_documentos`, `empresa_servicos`, seguindo o padrão UUIDv7/`BelongsToImplantacao`/ADR-002/003 do P18). Prestadores, documentos e serviços continuam sem UI de criação (igual ao botão "Vincular prestador", já desabilitado desde o protótipo) — ficam populados via `EmpresaDemoSeeder`, ligando prestadores só a `Pessoa`s já semeadas (mesmo critério do `VinculoDemoSeeder`/`VeiculoDemoSeeder`). Um bug real de fatias anteriores foi encontrado e corrigido durante a verificação no navegador desta fatia: os botões "Visualizar"/"Editar" de `PropertyManagement.php` e `VehicleManagement.php` (fatia 1) passavam o UUID sem aspas para o `wire:click`, o que é uma expressão JS inválida — corrigido em PR dedicado ([#37](https://github.com/remotevelaskoo/SDV-SANTARITA/pull/37)), já que era um bug ativo em produção (impedia abrir/editar qualquer imóvel ou veículo já existente). **Fatia 4 concluída** — `PackageManagement.php` (P15) passa a ler/escrever em `Encomenda` real; schema nova criada (`encomendas`, com `imovel_id` obrigatório e `received_by` referenciando o usuário autenticado real, em vez do nome fixo "Tatiane Souza" do protótipo). O seletor de imóvel do formulário passou a listar os imóveis reais, mesmo padrão já usado em `PersonRegistration.php`/`CompanyManagement.php`. `EncomendaDemoSeeder` recria as encomendas demo originais; a de Eduardo Nogueira (Bloco A — Apto 112) ficou de fora por já ser uma inconsistência pré-existente (esse código não existe entre os imóveis semeados, mesmo caso já registrado no `VinculoDemoSeeder`). **Fatia 5 concluída** — `CashRegister.php` (P14) passa a ler/escrever em `CaixaTurno`+`CaixaMovimentacao` reais (schema nova); abrir/fechar turno e registrar movimentação persistem de verdade, com o operador de cada lançamento sendo o usuário autenticado real (distinto de quem abriu o turno, cobrindo troca de operador no meio do expediente). `CaixaDemoSeeder` recria o turno aberto e o histórico fechado originais; a sessão fechada de "Marcos Almeida" fica sem operador vinculado por não existir conta demo com esse nome. Decisão registrada: o histórico de acesso (fatia 6) é uma tabela de domínio própria, não a estrutura genérica de auditoria do ADR-004 — a P22 continua separada e adiada. **Fatia 6 concluída** — schema nova `historico_acessos` (pessoa/imóvel/veículo opcionais, ponto de acesso, tipo, resultado, motivo da negação, operador, protocolo único, observações, `occurred_at`); `AccessHistory.php` (P09) passa a ler/filtrar/paginar registros reais, com contadores (total/liberado/negado/pendente) calculados no banco. `AccessValidation.php` (P06 + cadastro rápido embutido do P07) passa a identificar uma pessoa real com vínculo ativo e registrar cada decisão (negar/salvar sem liberar/validar e liberar) como uma linha real de `HistoricoAcesso`, com o operador sendo sempre o usuário autenticado; o cadastro rápido cria `Pessoa`+`PessoaDocumento`+`PessoaContato`+`Vinculo` reais, reaproveitando o mesmo padrão da fatia 2, e agora exige o código do imóvel (antes opcional para alguns tipos), já que `vinculos.imovel_id` é obrigatório no schema real. A comparação de placa (seção 2) permanece com dado estático de exemplo — o botão "Alterar placa ou veículo" já nasceu desabilitado no protótipo, e essa conexão fica para quando existir cadastro real de leitura de placa (fora do escopo desta fatia). `HistoricoAcessoDemoSeeder` recria 3 dos 8 registros originais (Bianca Moretti, Rafael Domingues, Mariana Souza) — os demais ficaram de fora por não existir `Pessoa` e/ou `Vinculo` real semeado para eles, mesmo critério já usado nos seeders anteriores. **Fatia 7 concluída (última da P21)** — `Portaria.php` (P05, o resumo operacional da portaria) passa a agregar dados reais: o alerta de pré-cadastros usa `PreRegistration` com `status = 'aguardando'` e `submitted_at` há mais de 24h (some quando não há nenhum); o card de caixa lê o `CaixaTurno` aberto real (`expectedBalance()`, mesmo cálculo do P14) e mostra "Nenhum caixa aberto" quando não há turno; os atendimentos recentes vêm dos 5 `HistoricoAcesso` mais recentes, já que "atendimento" é o mesmo conceito usado em `AccessValidation`/`AccessHistory` desde a fatia 6; a saudação usa o nome do usuário autenticado. Dos 4 atalhos, "Consultar caixa" passou a apontar para `/caixa` (real desde a fatia 5); "Cadastro rápido" permanece desabilitado por não existir tela própria — esse fluxo só existe embutido dentro de `AccessValidation` (P06), não como página independente. Com esta fatia, a P21 está inteiramente concluída: as 10 telas do escopo original (P05 a P07, P09, P11 a P15) leem e escrevem dados reais | Vinicius | [`vinicius/p21-imoveis-veiculos`](https://github.com/remotevelaskoo/SDV-SANTARITA/pull/33), [`vinicius/p21-pessoas`](https://github.com/remotevelaskoo/SDV-SANTARITA/pull/35), [`vinicius/p21-empresas`](https://github.com/remotevelaskoo/SDV-SANTARITA/pull/38), [`vinicius/p21-encomendas`](https://github.com/remotevelaskoo/SDV-SANTARITA/pull/40), [`vinicius/p21-caixa`](https://github.com/remotevelaskoo/SDV-SANTARITA/pull/42), [`vinicius/p21-historico-validacao`](https://github.com/remotevelaskoo/SDV-SANTARITA/pull/44), [`vinicius/p21-portaria`](https://github.com/remotevelaskoo/SDV-SANTARITA/pull/45) |
| P22 | Auditoria | Registrar quem realizou cada operação, quando e em qual contexto | P18 a P20 | ✅ Concluída — estrutura imutável e isolada por implantação (`auditoria_eventos`, `auditoria_alteracoes`, `auditoria_contextos`); captura automática das operações reais; registros específicos de autenticação, sessões e exportações; dados sensíveis protegidos; tela `/auditoria` com filtros, detalhes, paginação e CSV; acesso restrito por permissões próprias; suíte completa aprovada com 129 testes/615 verificações | Lucas + Codex | `codex/p22-auditoria` |

#### Acompanhamento detalhado da P22

| Entrega da P22 | Conteúdo | Situação |
|---|---|---|
| Estrutura persistente | Cabeçalho do evento, campos alterados e contexto técnico em tabelas próprias com UUIDv7 e isolamento por implantação | ✅ Concluída |
| Registro automático | Cadastros, vínculos, veículos, imóveis, empresas, encomendas, caixa, acessos, pré-cadastros, usuários e perfis registram criação, alteração e exclusão | ✅ Concluída |
| Segurança e imutabilidade | Registros não podem ser editados ou excluídos pelos modelos; senha, token, sessão, documento, biometria, foto e arquivo são mascarados | ✅ Concluída |
| Autenticação e sessões | Tentativas de login, entrada, saída, recuperação e revogação são registradas; bloquear ou inativar usuário encerra as sessões abertas | ✅ Concluída |
| Consulta administrativa | Página `/auditoria` com busca, período, usuário, módulo, operação, resultado, detalhes das mudanças e paginação | ✅ Concluída |
| Exportação controlada | CSV autorizado, sem valores sensíveis; a própria consulta de detalhe e a exportação são auditadas | ✅ Concluída |
| Permissões | `auditoria.consultar` e `auditoria.exportar` concedidas somente aos perfis Auditor e Administrador | ✅ Concluída |
| Qualidade | 6 cenários específicos; suíte completa com 129 testes e 615 verificações; formatação automática aprovada | ✅ Aprovada |

### 4.5 Equipamentos, segurança e publicação

| ID | Parte | Entrega principal | Dependência | Situação | Responsável | Branch |
|---|---|---|---|---|---|---|
| P23 | Integração com câmeras | Consultar estados e imagens autorizadas sem tratar demonstração como imagem real | P21 e escolha dos equipamentos | 🔴 Bloqueada | A definir | A definir |
| P24 | Portões e controladoras | Enviar comandos, confirmar resultado e tratar falhas | P06, P21 e equipamentos | 🔴 Bloqueada | A definir | A definir |
| P25 | Leitura de placas e reconhecimento | Integrar placa, face e outras credenciais | P21 e equipamentos | 🔴 Bloqueada | A definir | A definir |
| P26 | Testes e segurança finais | Validar regras, permissões, privacidade, falhas e uso nos equipamentos reais | Partes funcionais concluídas | 🟡 Em andamento — a parte "uso nos equipamentos reais" segue bloqueada pelo hardware, mas "validar regras, permissões, privacidade, falhas" não depende disso. **Fatia 1 concluída**: auditoria de cobertura de permissões em todas as rotas/telas e de correção do isolamento entre implantações em todos os 29 models com `BelongsToImplantacao`. **Vulnerabilidade real encontrada e corrigida**: `UserManagement::openUser()`/`findSelected()` (`app/Livewire/UserManagement.php`) consultavam `User::find($id)` sem filtrar pela implantação atual — como `selectedUserId` é uma propriedade pública do Livewire, um administrador da implantação A podia visualizar, bloquear, desbloquear ou inativar um usuário que só existe na implantação B (escalação entre tenants). Corrigido com o mesmo filtro `whereHas('implantacoes', ...)` já usado em `filteredUsers()`/`userCounts()` desde o P19. **Defesa em profundidade adicionada**: `PerfilManagement`, `ConfiguracaoManagement` e `CatalogoManagement` passaram a reverificar a própria permissão em `render()` (`abort_unless($this->canManage(), 403)`, mesmo padrão já usado em `Reports.php`/`AuditLog.php`), não confiando só no middleware da rota. `UserManagement` não recebeu essa mesma checagem de propósito — exigir `usuarios.administrar` para renderizar tornaria a proteção de "último administrador" (`wouldRemoveLastAdministrator()`) estruturalmente inalcançável, já que quem chegasse ao componente já seria administrador. **Investigado e confirmado como falso positivo (não corrigido)**: `/sessoes` sem `permissao:` middleware é intencional (autoatendimento, cada operador só vê/revoga as próprias sessões); `dashboard.visualizar` nunca é checado porque o dashboard é universal por design; `saveUser()` verificar duplicidade de username/e-mail globalmente é intencional (`User` é identidade global entre implantações, ADR-002 §10). **Pendência registrada, não construída** (funcionalidade ainda não existe, não falha de proteção): as permissões `integracoes.gerenciar`, `autorizacoes.gerenciar`, `contribuicao.registrar`, `integracao.encaminhar`, `ocorrencias.registrar`, `manutencao.gerenciar`, `caixa.consolidado.consultar` estão no catálogo mas não são checadas em nenhuma rota nem componente. **Fatia 2 concluída**: proteção contra força bruta no login (`docs/008` §34 lista "rate limit" como controle básico obrigatório — era o único item da lista sem nenhuma implementação; `grep` por `RateLimiter`/`throttle` não encontrava nada no projeto). `app/Livewire/Login.php` passou a usar `Illuminate\Support\Facades\RateLimiter` diretamente no componente (não via middleware `throttle:` de rota, já que a ação `login()` do Livewire passa pelo endpoint compartilhado de todos os componentes da página, não por uma rota HTTP própria) — chave combina `identification` normalizado e IP, limite de 5 tentativas por 60 segundos (mesmo padrão default do `Illuminate\Foundation\Auth\ThrottlesLogins` da própria framework), bloqueando antes mesmo de consultar `Auth::attempt()` ao exceder. Login bem-sucedido limpa o contador; excesso de tentativas gera evento de auditoria próprio (`reason_code: limite_tentativas_excedido`), distinto de `credenciais_invalidas` | Vinicius | `vinicius/p26-seguranca-fatia1`, `vinicius/p26-rate-limit-login-fatia2` |
| P27 | Publicação | Colocar o sistema em hospedagem, configurar domínio, cópias de segurança e acompanhamento | P26 | 🔴 Bloqueada | A definir | A definir |

## 5. Partes que podem começar em paralelo agora

P09, P14, P15, **P16 — Relatórios**, o **P18 — Banco de dados inicial**, o **P19 — Login real**, o **P20 — Perfis e permissões**, o **P21 — Conexão das telas**, a **P22 — Auditoria** e os ajustes **AJ-001 — Conferência visual protegida** e **AJ-002 — Revelação controlada de CPF/documento** já foram concluídos. **P17 — Administração** e **P26 — Testes e segurança finais** (a parte que não depende de equipamento) seguem em andamento com Vinicius. P23 a P25 continuam bloqueadas até a equipe definir e disponibilizar os equipamentos físicos; P27 depende da P26.

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
| Escolher o responsável por P22 | Lucas e Vinicius | Resolvida — Lucas + Codex, branch `codex/p22-auditoria` |
| Confirmar quais equipamentos existem na portaria | Lucas e Vinicius | Pendente |
| Definir quem revisará cada primeira entrega | Lucas e Vinicius | Pendente |

## 8.1 Ajustes funcionais aprovados para as próximas entregas

| Ajuste | Entrega esperada | Momento | Dependências | Situação |
|---|---|---|---|---|
| AJ-001 — Conferência visual protegida | Porteiro/Caixa autorizado abre documento e selfie efetivamente submetidos no detalhe do pré-cadastro e da validação; acesso privado e auditado | Antes da homologação interna dos fluxos de pré-cadastro e validação | Armazenamento privado, P20 e P22 | ✅ Concluída — branch `codex/aj001-conferencia-visual`; pronta para revisão |
| AJ-002 — Revelação controlada de CPF/documento | Listas permanecem mascaradas; detalhe permite revelação explícita, temporária, autorizada e auditada para conferir identidade | Antes da homologação interna dos fluxos de pré-cadastro e validação | Extensão do catálogo P20 e auditoria P22 | ✅ Concluída — permissão `dados-sensiveis.revelar`, revelação temporária nas fichas de pré-cadastro e validação, remascaramento e evento restrito de auditoria |
| AJ-003 — Integração facial BRAVAS | Sincronização separada da aprovação do pré-cadastro, por adaptador, fila, confirmação e revogação | P25 | Inventário do equipamento, política jurídica, homologação e retomada da ADR-013 | 🔴 Bloqueada |
| AJ-004 — Importação assistida por IA | Importar fontes legadas por área de preparação, extração com confiança, deduplicação e revisão humana obrigatória | Após estabilização do modelo e da auditoria | P17, P22 e retomada da ADR-011 | 🟡 Planejada |

As quatro decisões acima não reabrem P08, P20 ou P21 como um todo. AJ-001 e AJ-002 são endurecimentos necessários para homologação; AJ-003 permanece no escopo futuro de P25; AJ-004 deverá ser detalhada em entrega própria quando suas dependências forem resolvidas.

#### Resultado detalhado do AJ-001

| Entrega | Resultado | Situação |
|---|---|---|
| Envio real | Documento e selfie aceitam somente JPG, PNG ou WebP, com limite de 8 MB e validação do conteúdo | ✅ Concluída |
| Armazenamento privado | Conteúdo binário fica fora da pasta pública; o banco guarda catálogo, tipo detectado, tamanho, checksum e classificação | ✅ Concluída |
| Privacidade | Chaves opacas sem nome, CPF ou protocolo; nome original criptografado; arquivos separados por implantação | ✅ Concluída |
| Histórico de versões | Substituir um arquivo cria nova versão e preserva a anterior, sem sobrescrever o objeto antigo | ✅ Concluída |
| Conferência | Documento e selfie aparecem no detalhe do pré-cadastro e na Validação de Entrada quando existe pré-cadastro aprovado correspondente | ✅ Concluída |
| Autorização | Nova permissão `arquivos.sensiveis.visualizar`; rota autenticada; imagem não é carregada antes do clique | ✅ Concluída |
| Auditoria | Cada visualização registra operador, horário, pré-cadastro, categoria, contexto e resultado, sem gravar imagem, endereço interno ou nome do arquivo | ✅ Concluída |
| Download e biometria | Interface não oferece download; conferência humana da selfie não cria credencial facial e não libera entrada | ✅ Concluída |
| Qualidade | Suíte completa com 138 testes e 665 verificações; migração e dados iniciais executados do zero; compilação visual aprovada | ✅ Aprovada |
| Infraestrutura futura | Provedor S3 de produção, antivírus externo e política definitiva de retenção/descarte | ⚪ Pendente de decisão de infraestrutura; não bloqueia a revisão local |

## 9. Correções e ajustes técnicos

Registro leve de correções em partes já marcadas como concluídas — não criam uma nova linha no quadro principal, pois não são uma nova etapa, apenas um ajuste sobre o que já existia. Cada uma tem descrição completa no commit/PR correspondente.

| Data | Parte afetada | O que foi corrigido | PR |
|---|---|---|---|
| 10/08/2026 | P08 / P11 | Cenário do pré-cadastro ajustado para turista (praia) e busca automática de endereço por CEP adicionada ao Cadastro de imóveis | [#17](https://github.com/remotevelaskoo/SDV-SANTARITA/pull/17) |
| 10/08/2026 | P08 | Busca automática de endereço por CEP também no Pré-cadastro | [#18](https://github.com/remotevelaskoo/SDV-SANTARITA/pull/18) |
| 10/08/2026 | P08 | Destino da visita reativo ao tipo de acesso (turista → praia automático; visitante → escolhe o imóvel) | [#19](https://github.com/remotevelaskoo/SDV-SANTARITA/pull/19) |
| 11/08/2026 | P08 | Painel de edição da fila de pré-cadastros fechava sozinho ao abrir, por um detalhe de ordem dos hooks do Livewire (`dehydrate()` roda depois de o framework já ter processado os eventos despachados) | [#25](https://github.com/remotevelaskoo/SDV-SANTARITA/pull/25) |
| 11/08/2026 | P11 / P12 | Botões "Visualizar"/"Editar" de Imóveis e Veículos passavam o UUID sem aspas para o `wire:click` — expressão JS inválida que quebrava o clique em qualquer registro real da lista | [#37](https://github.com/remotevelaskoo/SDV-SANTARITA/pull/37) |
| 13/08/2026 | P06 / P08 / P20 / P22 | AJ-001: envio, armazenamento e conferência visual protegida de documento e selfie, com permissão própria, isolamento e auditoria | PR a abrir — branch `codex/aj001-conferencia-visual` |
| 13/08/2026 | P06 (Modo Portaria) | AJ-001 cobriu o detalhe do pré-cadastro e a Validação de Entrada, conforme o texto do ajuste (§8.1) — mas a observação original do PO que motivou o AJ-001 também citava a falta de imagem no Modo Portaria ("moto portaria"). Reaproveitada a mesma ponte por CPF já usada em `AccessValidation::currentProtectedFiles()` (sem tabela ou serviço novos) para exibir "Conferência visual protegida" nos atendimentos recentes da Portaria quando existe um pré-cadastro aprovado correspondente | `vinicius/aj001-portaria-fatia2` |

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
