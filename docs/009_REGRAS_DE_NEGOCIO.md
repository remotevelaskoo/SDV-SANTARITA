# SDV ACCESS — REGRAS DE NEGÓCIO
## Catálogo consolidado, estados, validações e rastreabilidade

**Documento:** SDV-RNG-009  
**Versão:** 1.0.1  
**Status:** Aprovado  
**Produto:** SDV Access — Implantação Santa Rita  
**Empresa proprietária:** Soluções do Vale Tecnologia  
**Responsável pelo produto:** Vinicius Velasco de Azevedo  
**Data:** Julho de 2026

---

## Controle de versões

| Versão | Data | Responsável | Alteração |
|---|---|---|---|
| 1.0.0 | Julho/2026 | Soluções do Vale | Consolidação das regras existentes e incorporação das decisões UX/UI aprovadas |
| 1.0.1 | 28/07/2026 | Product Owner | Aprovação formal do catálogo consolidado de Regras de Negócio |

---

# 1. Objetivo

Este documento constitui o catálogo canônico de regras de negócio do SDV Access para a implantação Santa Rita.

Seus objetivos são:

- preservar os identificadores `RN-001` a `RN-054`;
- consolidar regras distribuídas no Product Book;
- incorporar decisões aprovadas no Brand Book, Design System e especificações UX/UI;
- criar regras adicionais com numeração contínua;
- formalizar estados, transições, validações e exceções;
- orientar banco de dados, APIs, arquitetura, desenvolvimento e testes;
- registrar pendências sem convertê-las em decisões implícitas.

---

# 2. Documentos de origem

Este catálogo consolida:

- `docs/000_DIRETRIZES_DO_PROJETO.md`;
- `docs/001_VOLUME_01_PRODUCT_BOOK_PARTE_01.md`;
- `docs/001_VOLUME_01_PRODUCT_BOOK_PARTE_02.md`;
- `docs/001_VOLUME_01_PRODUCT_BOOK_PARTE_03.md`;
- `docs/002_BRAND_BOOK.md`;
- `docs/003_DESIGN_SYSTEM.md`;
- `docs/004_UX_UI_DASHBOARD.md`;
- `docs/005_UX_UI_VALIDACAO.md`;
- `docs/006_UX_UI_PRE_CADASTRO.md`;
- `docs/007_UX_UI_CADASTRO_IMOVEL.md`;
- `docs/008_ADMINISTRACAO.md`.

---

# 3. Precedência e interpretação

## 3.1 Precedência

Em caso de conflito:

1. diretrizes oficiais e decisões técnicas aprovadas;
2. regras deste catálogo;
3. Product Book aprovado;
4. especificações UX/UI aprovadas;
5. Design System e Brand Book;
6. referências visuais;
7. decisões futuras formalizadas.

Uma referência visual não altera silenciosamente uma regra.

## 3.2 Linguagem normativa

- **deverá:** obrigatório;
- **não deverá:** proibido;
- **poderá:** permitido sob condição;
- **quando aplicável:** depende de configuração ou regra identificada;
- **pendente:** não pode ser presumido.

## 3.3 Identificadores

- identificadores são permanentes;
- regra alterada mantém o identificador e histórico;
- regra substituída não tem número reutilizado;
- novas regras continuam a sequência;
- requisitos e testes deverão referenciar regras aplicáveis.

---

# 4. Glossário do domínio

| Termo | Definição |
|---|---|
| Implantação | configuração segregada do SDV Access para uma organização |
| Condomínio ou organização | contexto superior da implantação |
| Bloco | agrupamento opcional de imóveis |
| Imóvel | entidade central que possui identificação e endereço |
| Pessoa | cadastro único de uma identidade |
| Vínculo | relação temporal entre pessoa e imóvel, empresa ou contexto |
| Natureza do vínculo | proprietário, morador, inquilino, prestador ou outro |
| Papel | titular, cônjuge, filho, dependente ou classificação semelhante |
| Responsabilidade | capacidade de responder por operações do imóvel |
| Autorização | permissão de acesso com condições |
| Credencial | meio de identificação, como face, placa, tag, QR Code ou código |
| Veículo | cadastro operacional identificado, principalmente, pela placa |
| Pré-cadastro | solicitação antecipada sujeita à análise |
| Atendimento | contexto operacional de uma validação |
| Evento de acesso | tentativa, entrada, saída, negativa, pendência ou falha |
| Equipamento | componente físico ou lógico integrado |
| Adaptador | integração desacoplada para fabricante ou protocolo |
| Contribuição | movimento financeiro operacional relacionado ao acesso |
| Auditoria | trilha de ações e alterações relevantes |

---

# 5. Princípios invariáveis

1. o imóvel é a entidade central;
2. uma pessoa possui cadastro único;
3. vínculo não é atributo fixo da pessoa;
4. cadastro não é autorização;
5. autorização não é credencial;
6. credencial não é evento;
7. decisão não é confirmação física;
8. histórico substitui exclusão destrutiva;
9. menor privilégio orienta a operação;
10. integrações permanecem desacopladas;
11. dados são segregados por implantação;
12. operações relevantes são auditáveis.

---

# 6. Catálogo consolidado — Imóveis

**RN-001 — Existência prévia do imóvel**  
Um imóvel deverá existir antes que moradores, inquilinos, visitantes vinculados ou veículos residenciais sejam associados a ele.

**RN-002 — Endereço centralizado**  
O endereço deverá ser armazenado no imóvel, não repetido em cada pessoa vinculada.

**RN-003 — Identificação única**  
Cada imóvel deverá possuir identificação única dentro da implantação, composta conforme a estrutura aprovada, como bloco, unidade, número ou código interno.

**RN-004 — Múltiplos ocupantes**  
Um imóvel poderá possuir vários ocupantes ativos simultaneamente.

**RN-005 — Histórico de ocupação**  
A troca de ocupantes não deverá apagar vínculos anteriores. O sistema deverá preservar o histórico de início e término.

**RN-006 — Situação do imóvel**  
O imóvel deverá possuir estado operacional próprio, como ativo, inativo, bloqueado ou em implantação.

---

# 7. Catálogo consolidado — Pessoas e vínculos

**RN-007 — Cadastro único de pessoa**  
Uma pessoa não deverá ser duplicada apenas por possuir mais de um vínculo.

**RN-008 — Vínculos independentes**  
A mesma pessoa poderá possuir vínculos distintos em períodos ou imóveis diferentes, quando permitido.

**RN-009 — Classificação do vínculo**  
Cada vínculo deverá identificar sua natureza, como morador, proprietário, inquilino, dependente, funcionário, prestador ou outro.

**RN-010 — Situação do vínculo**  
O vínculo deverá possuir situação própria, independente da situação cadastral da pessoa.

**RN-011 — Vigência**  
Vínculos temporários deverão conter data de início e data de término.

**RN-012 — Expiração automática**  
Ao término da vigência, o vínculo deverá perder validade automaticamente, sem depender de ação manual.

**RN-013 — Dados obrigatórios**  
A obrigatoriedade dos campos deverá variar conforme o tipo de pessoa, vínculo, finalidade e tela aprovada.

**RN-014 — Documento duplicado**  
O sistema deverá impedir ou alertar sobre documentos únicos já vinculados a outra pessoa.

---

# 8. Catálogo consolidado — Moradores

**RN-015 — Vínculo com imóvel**  
Todo morador deverá estar vinculado a pelo menos um imóvel.

**RN-016 — Responsável principal**  
Um imóvel poderá possuir um ou mais responsáveis, mas deverá existir regra clara para identificar o responsável principal quando necessário.

**RN-017 — Acesso independente**  
A permissão de acesso de um morador deverá ser controlada individualmente.

**RN-018 — Veículos do morador**  
Os veículos poderão ser vinculados ao morador, ao imóvel ou a ambos, conforme o modelo definitivo de dados.

---

# 9. Catálogo consolidado — Inquilinos

**RN-019 — Período obrigatório**  
O vínculo de inquilino deverá possuir início e término obrigatórios.

**RN-020 — Encerramento automático**  
Ao final do contrato ou período informado, o acesso derivado deverá ser suspenso automaticamente.

**RN-021 — Renovação rastreável**  
Prorrogações deverão gerar histórico de alteração, sem apagar os dados anteriores.

**RN-022 — Conflito de vigência**  
O sistema deverá alertar quando houver vínculos incompatíveis ou sobrepostos conforme a regra do imóvel.

---

# 10. Catálogo consolidado — Visitantes

**RN-023 — Responsável pelo visitante**  
Todo visitante de imóvel autorizado deverá possuir um responsável identificado e vinculado ao imóvel de destino. O turista não exige responsável de imóvel.

**RN-024 — Imóvel de destino**  
Todo visitante deverá estar associado ao imóvel visitado. Para o turista, o destino válido será a Praia do Santa Rita, sem vínculo com imóvel.

**RN-025 — Autorização limitada**  
A autorização deverá conter período, quantidade de acessos ou outra condição de validade aprovada.

**RN-026 — Pré-cadastro não é liberação automática**  
O pré-cadastro registra dados, mas não garante acesso até que a validação exigida seja concluída.

**RN-027 — Reutilização controlada**  
Visitantes recorrentes poderão reutilizar o cadastro da pessoa, mas novas visitas deverão gerar novas autorizações quando aplicável.

**RN-028 — Documento e imagem**  
A captura de documento e imagem deverá seguir os campos, etapas, finalidade, proteção e retenção aprovados.

---

# 11. Catálogo consolidado — Prestadores e empresas

**RN-029 — Prestador vinculado à empresa**  
Quando aplicável, o prestador deverá estar vinculado a uma empresa cadastrada.

**RN-030 — Autorização por atividade**  
A autorização poderá registrar serviço, local, responsável, período e observações.

**RN-031 — Documentação obrigatória**  
O sistema poderá exigir documentos conforme a categoria do prestador.

**RN-032 — Vigência temporária**  
Prestadores deverão possuir período de autorização definido, salvo categorias permanentes aprovadas.

**RN-033 — Empresa inativa**  
A inativação da empresa deverá impedir novas autorizações, sem apagar registros históricos.

---

# 12. Catálogo consolidado — Veículos

**RN-034 — Placa como identificador operacional**  
A placa deverá ser normalizada para busca e integração, preservando apresentação adequada na interface.

**RN-035 — Duplicidade de placa**  
Uma mesma placa não deverá gerar cadastros ativos conflitantes sem alerta.

**RN-036 — Vínculo de veículo**  
O veículo deverá possuir vínculo com pessoa, imóvel, empresa ou autorização temporária.

**RN-037 — Situação do veículo**  
O veículo deverá possuir situação própria, como ativo, inativo, bloqueado ou temporário.

**RN-038 — Leitura de placa**  
A leitura automática não deverá liberar o acesso sem consulta às permissões válidas.

---

# 13. Catálogo consolidado — Acesso

**RN-039 — Cadastro não implica autorização**  
A existência do cadastro não garante acesso.

**RN-040 — Decisão centralizada**  
O SDV Access deverá ser responsável pela decisão de autorização, ainda que o equipamento mantenha cache operacional aprovado.

**RN-041 — Registro de tentativa**  
Tentativas autorizadas, negadas, manuais ou com falha deverão ser registradas.

**RN-042 — Entrada e saída**  
Os eventos deverão distinguir direção, ponto de acesso e método de identificação.

**RN-043 — Liberação manual**  
Toda liberação manual deverá identificar o operador e o motivo.

**RN-044 — Acesso excepcional**  
Exceções deverão possuir justificativa, responsável e validade.

**RN-045 — Credenciais**  
Credenciais como face, placa, QR Code, tag ou código deverão possuir estado e vigência próprios.

---

# 14. Catálogo consolidado — Auditoria

**RN-046 — Operações auditáveis**  
Criações, alterações, inativações, permissões, acessos e liberações deverão gerar registros auditáveis.

**RN-047 — Conteúdo mínimo do log**  
O log deverá conter data, hora, usuário ou ator, operação, entidade, identificador, origem e dados relevantes da alteração.

**RN-048 — Imutabilidade lógica**  
Registros de auditoria não deverão ser editáveis por usuários operacionais.

**RN-049 — Valor anterior e posterior**  
Alterações relevantes deverão registrar os valores antes e depois da operação.

---

# 15. Catálogo consolidado — Usuários e permissões

**RN-050 — Usuário individual**  
Cada operador deverá utilizar credencial própria.

**RN-051 — Perfil de acesso**  
Permissões deverão ser atribuídas por perfil e, quando necessário, por exceção individual.

**RN-052 — Menor privilégio**  
O usuário deverá receber somente as permissões necessárias para sua função.

**RN-053 — Ações críticas**  
Operações críticas poderão exigir confirmação adicional, justificativa, reautenticação ou permissão específica.

**RN-054 — Inativação imediata**  
Usuários inativados não poderão iniciar novas sessões.

---

# 16. Regras adicionais — Domínio e ciclo de vida

**RN-055 — Segregação por implantação**  
Toda entidade, consulta, cache, arquivo, fila, configuração e evento deverá respeitar o contexto da implantação e impedir acesso cruzado.

**RN-056 — Separação de entidades de acesso**  
Pessoa, vínculo, autorização, credencial, atendimento, comando e evento de acesso deverão possuir identidade e ciclo de vida próprios.

**RN-057 — Transições válidas**  
Uma entidade somente poderá mudar de estado por transição prevista, com ator, instante, origem e motivo quando aplicável.

**RN-058 — Rascunho sem efeito operacional**  
Rascunho não deverá ativar vínculo, autorização, credencial, sincronização, pagamento ou liberação.

**RN-059 — Resultado de ativação por entidade**  
A ativação deverá informar separadamente o resultado da pessoa, vínculo, autorização, credencial e sincronização.

**RN-060 — Alteração do endereço do imóvel**  
Alterar o endereço a partir do contexto de uma pessoa deverá executar operação explícita no imóvel, aplicar permissão própria, informar impacto e gerar auditoria.

**RN-061 — Natureza, papel e responsabilidade**  
Natureza do vínculo, papel familiar ou operacional e responsabilidade administrativa deverão ser armazenados e apresentados separadamente.

**RN-062 — Substituição de responsável**  
A alteração do responsável principal deverá preservar histórico, identificar autor e não revogar automaticamente o acesso de outros ocupantes.

**RN-063 — Renovação por versão**  
Renovar vínculo temporário deverá criar nova versão ou período rastreável, sem sobrescrever a vigência anterior.

**RN-064 — Detecção segura de duplicidade**  
A detecção de pessoa existente deverá evitar nova cópia sem revelar dados a usuário ou solicitante não autorizado.

**RN-065 — Finalidade para dado sensível**  
Dado pessoal sensível ou adicional somente deverá ser coletado quando houver finalidade, necessidade, acesso e retenção definidos.

**RN-066 — Arquivo protegido**  
Documento, selfie, foto e imagem operacional deverão permanecer privados e ser acessados por autorização ou endereço temporário.

---

# 17. Regras adicionais — Pré-cadastro

**RN-067 — Convite seguro**  
O início do pré-cadastro deverá validar token ou convite temporário, implantação, situação, validade, limite e integridade.

**RN-068 — Uso limitado do convite**  
Convites deverão respeitar quantidade de cadastros, tipo, destino, período e cancelamento.

**RN-069 — Protocolo não previsível**  
O protocolo deverá ser único, não previsível e não conter dado pessoal ou identificação direta do imóvel.

**RN-070 — Estado do pré-cadastro**  
O pré-cadastro deverá seguir estados e transições definidos, preservando ator, instante e versão.

**RN-071 — Aprovação não garante entrada**  
A aprovação do pré-cadastro somente habilita a solicitação para as próximas validações e não garante entrada ou comando físico.

**RN-072 — Correção versionada**  
Solicitação de correção, reenvio e edição autorizada pela portaria deverão preservar a submissão anterior e identificar os campos alterados. A edição pelo operador exige permissão e justificativa, registra valor anterior, valor novo, operador e instante, e não altera silenciosamente cadastros, vínculos, autorizações ou credenciais relacionados.

**RN-073 — Mensagem pública separada**  
Motivo e observação internos não deverão ser enviados automaticamente ao solicitante; a mensagem pública deverá ser controlada separadamente.

**RN-074 — OCR assistivo**  
Resultado de OCR deverá ser tratado como sugestão sujeita a conferência e não como validação documental.

**RN-075 — Selfie não é credencial automática**  
A coleta de selfie não deverá criar ou sincronizar credencial biométrica sem finalidade, base legal, política e autorização aplicáveis.

**RN-076 — Veículo opcional não autoriza**  
O veículo informado no pré-cadastro não deverá garantir acesso nem liberação automática.

---

# 18. Regras adicionais — Validação de entrada

**RN-077 — Revalidação na decisão**  
Antes de autorizar ou liberar, o sistema deverá revalidar cadastro, vínculo, vigência, autorização, credencial, ponto, horário, veículo, contribuição, caixa, equipamento e permissão aplicáveis.

**RN-078 — Autorização, comando e confirmação**  
Autorização concedida, comando enviado e abertura confirmada deverão ser estados distintos.

**RN-079 — Idempotência do atendimento**  
Reenvio ou retomada do atendimento não deverá duplicar decisão, evento, pagamento ou comando.

**RN-080 — Timeout como resultado desconhecido**  
Timeout de equipamento não deverá ser registrado como sucesso nem como falha física confirmada até reconciliação.

**RN-081 — Negativa motivada**  
Negar entrada deverá exigir motivo estruturado e registrar a tentativa sem enviar comando.

**RN-082 — Salvar sem liberar**  
Salvar sem liberar deverá preservar atendimento pendente sem enviar comando e sem concluir pagamento.

**RN-083 — Contribuição não autoriza**  
Pagamento, isenção ou classificação de contribuição não deverá criar ou substituir autorização.

**RN-084 — Caixa aplicável**  
Quando a contribuição exigir caixa aberto, o movimento não poderá ser concluído em caixa fechado, salvo exceção formal autorizada e auditada.

**RN-085 — Idempotência financeira operacional**  
Uma contribuição não deverá ser registrada mais de uma vez para a mesma operação idempotente.

**RN-086 — Evidência LPR preservada**  
Imagem, leitura original, confiança, câmera e instante do LPR deverão ser preservados, mesmo após correção cadastral.

**RN-087 — Limiar de automação LPR**  
Liberação automática por placa somente poderá ocorrer quando a confiança atender ao limiar configurado e todas as demais regras estiverem válidas.

**RN-088 — Falha externa sem perda de cadastro**  
Falha de equipamento ou integração não deverá apagar ou corromper cadastro, vínculo, autorização ou evento já confirmado.

**RN-089 — Contingência autorizada**  
Contingência deverá exigir permissão, justificativa, ponto de acesso, responsável e registro do resultado conhecido.

---

# 19. Regras adicionais — Integrações

**RN-090 — Identificador externo secundário**  
Identificador retornado por equipamento deverá ser armazenado como referência externa e não substituir a chave interna.

**RN-091 — Capacidade declarada**  
Cada adaptador deverá declarar capacidades, e a aplicação não deverá oferecer operação incompatível.

**RN-092 — Fila idempotente**  
Operações de sincronização e comando deverão usar correlação e idempotência para permitir retentativa segura.

**RN-093 — Estado de sincronização**  
Sincronização deverá possuir estados explícitos e apresentar última tentativa, equipamento, erro sanitizado e resultado.

---

# 20. Regras adicionais — Administração

**RN-094 — Configuração versionada**  
Alteração relevante de configuração deverá criar versão com autor, motivo, instante, valor anterior, valor posterior e vigência.

**RN-095 — Reversão como nova versão**  
Reverter configuração deverá criar nova versão e não apagar o histórico.

**RN-096 — Catálogo histórico**  
Item de catálogo já utilizado deverá ser inativado para novos usos, permanecendo legível no histórico.

**RN-097 — Permissão efetiva explicável**  
O sistema deverá informar o resultado efetivo de uma permissão e sua origem em perfil, exceção, restrição ou vigência.

**RN-098 — Exceção individual controlada**  
Exceção individual de permissão deverá possuir justificativa, origem, auditoria e prazo quando possível.

**RN-099 — Proibição de autoelevação**  
Usuário não deverá conceder a si próprio privilégio que não possuía autoridade para administrar.

**RN-100 — Segredos protegidos e concorrência segura**  
Segredos de integração não deverão retornar ao frontend ou aos logs, e alterações concorrentes em configurações ou cadastros não deverão sobrescrever versões silenciosamente.

---

# 21. Estados canônicos

## 21.1 Imóvel

| Estado | Significado | Transições principais |
|---|---|---|
| Em implantação | cadastro estrutural em preparação | Ativo, Inativo |
| Ativo | disponível para operação permitida | Bloqueado, Inativo |
| Bloqueado | restrição operacional | Ativo, Inativo |
| Inativo | fora de novos usos | Ativo por reativação autorizada |

## 21.2 Pessoa

| Estado | Significado |
|---|---|
| Rascunho | incompleta e sem acesso |
| Pendente de validação | aguardando conferência |
| Ativa | cadastro válido |
| Rejeitada | cadastro não aprovado |
| Bloqueada | cadastro com restrição |
| Inativa | cadastro encerrado |
| Pendente de atualização | requer nova validação |

## 21.3 Vínculo

| Estado | Significado |
|---|---|
| Agendado | início futuro |
| Ativo | dentro da vigência e habilitado |
| Suspenso | temporariamente inválido |
| Encerrado | finalizado manualmente |
| Expirado | finalizado automaticamente |

## 21.4 Pré-cadastro

| Estado | Significado |
|---|---|
| Rascunho | preenchimento não enviado |
| Enviado | submissão recebida |
| Aguardando análise | na fila |
| Correção solicitada | requer ajuste |
| Em correção | edição autorizada |
| Reenviado | nova versão entregue |
| Aprovado | análise favorável, sem garantir entrada |
| Rejeitado | análise desfavorável |
| Cancelado | interrompido |
| Expirado | fora da validade |

## 21.5 Atendimento e comando

| Estado | Significado |
|---|---|
| Em validação | dados em conferência |
| Pendente | salvo sem liberação |
| Negado | tentativa negada |
| Autorizado | decisão lógica favorável |
| Comando pendente | aguardando envio ou processamento |
| Comando enviado | solicitação transmitida |
| Abertura confirmada | equipamento confirmou |
| Comando recusado | equipamento recusou |
| Falha técnica | falha confirmada |
| Confirmação desconhecida | resultado ainda não reconciliado |

## 21.6 Sincronização

- não enviado;
- aguardando;
- processando;
- enviado;
- sincronizado;
- atualização pendente;
- falha;
- removido;
- intervenção necessária.

---

# 22. Matriz de obrigatoriedade conceitual

| Dado | Morador | Inquilino | Visitante | Turista | Prestador |
|---|---:|---:|---:|---:|---:|
| Pessoa | Obrigatório | Obrigatório | Obrigatório | Obrigatório | Obrigatório |
| Imóvel ou destino | Obrigatório | Obrigatório | Imóvel obrigatório | Praia do Santa Rita | Conforme autorização |
| Responsável | Conforme papel | Obrigatório conforme regra | Responsável do imóvel obrigatório | Não exige responsável de imóvel | Conforme serviço |
| Início | Obrigatório | Obrigatório | Obrigatório | Obrigatório | Obrigatório |
| Término | Conforme regra | Obrigatório | Obrigatório | Obrigatório | Obrigatório salvo exceção |
| Empresa | Não | Não | Não | Não | Quando aplicável |
| Documento | Conforme política | Conforme política | Conforme política | Conforme política | Conforme categoria |
| Selfie | Conforme política | Conforme política | Conforme política | Conforme política | Conforme política |
| Veículo | Opcional | Opcional | Opcional | Opcional | Opcional |

Os campos detalhados permanecem sujeitos às pendências de privacidade e categoria.

---

# 23. Validações transversais

## 23.1 Identidade

- normalizar documento;
- validar formato;
- pesquisar duplicidade;
- proteger dados do cadastro encontrado.

## 23.2 Período

- início e término inequívocos;
- fuso da implantação;
- término posterior ao início;
- conflito;
- transição automática.

## 23.3 Placa

- normalização;
- formatos aceitos;
- duplicidade;
- situação;
- vínculo;
- evidência LPR.

## 23.4 Arquivo

- formato;
- tamanho;
- conteúdo;
- proteção;
- malware;
- acesso;
- retenção.

## 23.5 Permissão

- usuário;
- perfil;
- exceção;
- escopo;
- implantação;
- vigência;
- contexto do recurso.

## 23.6 Concorrência

- versão esperada;
- detecção de conflito;
- preservação da entrada;
- nova tentativa consciente;
- auditoria.

---

# 24. Regras de indicadores

Antes da implementação, cada indicador deverá possuir:

- identificador;
- nome;
- fórmula;
- fonte;
- estados incluídos;
- estados excluídos;
- tratamento de duplicidade;
- período;
- fuso;
- atualização;
- responsável;
- relatório de conciliação.

## 24.1 Regras mínimas

- pessoa não será contada por quantidade de vínculos sem indicação;
- evento técnico duplicado não inflará entradas ou saídas;
- falha de leitura não será tratada como zero;
- arrecadação deverá conciliar com caixa;
- comparação deverá declarar base e período;
- dado parcial deverá ser identificado.

---

# 25. Permissões e ações

## 25.1 Ações mínimas separadas

- consultar;
- criar;
- editar;
- inativar;
- suspender;
- encerrar;
- renovar;
- aprovar;
- rejeitar;
- solicitar correção;
- liberar;
- aplicar contingência;
- exportar;
- configurar;
- auditar.

## 25.2 Resultado efetivo

A autorização deverá considerar:

```text
Usuário
 + perfis
 + exceções
 + restrições
 + vigência
 + implantação
 + recurso
 + contexto
 = decisão efetiva
```

A precedência exata entre concessão e negação permanece pendente.

---

# 26. Exceções consolidadas

| Exceção | Tratamento obrigatório |
|---|---|
| Pessoa existente | reutilizar cadastro e criar vínculo |
| CPF inválido | impedir ativação ou aplicar fluxo autorizado |
| Documento ilegível | correção ou análise manual |
| Foto inadequada | substituir ou manter sincronização pendente |
| Vínculo expirado | negar autorização derivada |
| Placa divergente | conferir, justificar e preservar evidência |
| Equipamento indisponível | registrar e aplicar contingência autorizada |
| Caixa fechado | impedir recebimento normal |
| Falha de comando | separar autorização de abertura |
| Queda de conexão | retomar por idempotência e reconciliar |
| Configuração concorrente | impedir sobrescrita silenciosa |
| Segredo inválido | substituir com auditoria, sem exibição |

---

# 27. Auditoria por categoria

| Categoria | Eventos mínimos |
|---|---|
| Imóvel | criação, endereço, situação |
| Pessoa | criação, alteração, inativação |
| Vínculo | criação, vigência, suspensão, renovação, encerramento |
| Autorização | criação, aprovação, rejeição, revogação |
| Credencial | associação, sincronização, falha, remoção |
| Veículo | criação, vínculo, placa, situação |
| Pré-cadastro | convite, envio, correção, decisão, expiração |
| Acesso | tentativa, negativa, autorização, comando, confirmação |
| Caixa | abertura, movimento, cancelamento, fechamento |
| Usuário | convite, ativação, bloqueio, inativação, sessão |
| Permissão | perfil, concessão, retirada, exceção |
| Configuração | versão, publicação, reversão |
| Equipamento | cadastro, segredo, teste, estado |
| Exportação | solicitante, filtros, resultado, arquivo |

---

# 28. Rastreabilidade das regras

| Faixa | Domínio principal | Requisitos relacionados | Documentos principais |
|---|---|---|---|
| `RN-001` a `RN-006` | Imóveis | `RF-005`, `RF-023`, `RF-024` | 007 |
| `RN-007` a `RN-014` | Pessoas e vínculos | `RF-006`, `RF-007`, `RF-021`, `RF-022`, `RF-025` | 007 |
| `RN-015` a `RN-022` | Moradores e inquilinos | `RF-006`, `RF-007`, `RF-024` | 007 |
| `RN-023` a `RN-028` | Visitantes | `RF-008`, `RF-026` a `RF-030` | 006 |
| `RN-029` a `RN-033` | Prestadores | `RF-009`, `RF-010` | 006, 007 |
| `RN-034` a `RN-038` | Veículos | `RF-011`, `RF-033` | 005, 007 |
| `RN-039` a `RN-045` | Acesso | `RF-012` a `RF-016`, `RF-038` | 005 |
| `RN-046` a `RN-049` | Auditoria | `RF-019` | 008 |
| `RN-050` a `RN-054` | Usuários | `RF-001`, `RF-002`, `RF-017`, `RF-018`, `RF-040` | 008 |
| `RN-055` a `RN-066` | Domínio e ciclo | múltiplos | 004 a 008 |
| `RN-067` a `RN-076` | Pré-cadastro | `RF-025` a `RF-030` | 006 |
| `RN-077` a `RN-089` | Validação | `RF-012` a `RF-016`, `RF-033` a `RF-038` | 005 |
| `RN-090` a `RN-093` | Integrações | `RF-032`, `RF-033`, `RF-037` | 005, 007, 008 |
| `RN-094` a `RN-100` | Administração | `RF-017` a `RF-019`, `RF-040` | 008 |

---

# 29. Pendências abertas

| PEN-RNG | Pendência | Regras afetadas |
|---|---|---|
| PEN-RNG-001 | Estrutura e identificação real dos imóveis Santa Rita | `RN-003` |
| PEN-RNG-002 | Natureza, papel, responsabilidade e parentesco definitivos | `RN-009`, `RN-016`, `RN-061`, `RN-062` |
| PEN-RNG-003 | Conflitos de vigência incompatíveis | `RN-022`, `RN-063` |
| PEN-RNG-004 | Campos obrigatórios por tipo | `RN-013`, `RN-065` |
| PEN-RNG-005 | Política de documentos, imagens e biometria | `RN-028`, `RN-065`, `RN-066`, `RN-075` |
| PEN-RNG-006 | Finalidade do endereço informado no pré-cadastro | `RN-002`, `RN-060`, `RN-065` |
| PEN-RNG-007 | Regras de turista — resolvida em 10/08/2026: destino praia, sem imóvel e com vigência | `RN-011`, `RN-024`, `RN-025` |
| PEN-RNG-008 | Documentação de prestador | `RN-031` |
| PEN-RNG-009 | Vínculo definitivo de veículo | `RN-018`, `RN-036` |
| PEN-RNG-010 | Limiar LPR | `RN-087` |
| PEN-RNG-011 | Regras completas da contribuição | `RN-083` a `RN-085` |
| PEN-RNG-012 | Contingência | `RN-080`, `RN-089` |
| PEN-RNG-013 | Fabricantes e capacidades | `RN-090` a `RN-093` |
| PEN-RNG-014 | Motivos parametrizados | `RN-081`, `RN-089`, `RN-096` |
| PEN-RNG-015 | Catálogo granular de permissões | `RN-051` a `RN-053`, `RN-097` a `RN-099` |
| PEN-RNG-016 | Precedência de permissões | `RN-097`, `RN-098` |
| PEN-RNG-017 | Política de sessão, senha e MFA | `RN-050`, `RN-054` |
| PEN-RNG-018 | Proteção do último administrador | `RN-052`, `RN-053`, `RN-099` |
| PEN-RNG-019 | Configurações e fluxo de publicação | `RN-094`, `RN-095` |
| PEN-RNG-020 | Retenção de auditoria e arquivos | `RN-046` a `RN-049`, `RN-066` |
| PEN-RNG-021 | Fórmulas dos indicadores | seção 24 |
| PEN-RNG-022 | Metas de desempenho e continuidade | homologação |

---

# 30. Critérios de qualidade das regras

Uma regra será considerada pronta para implementação quando:

1. possuir identificador;
2. usar termos do glossário;
3. ter condição e resultado verificáveis;
4. indicar exceções;
5. possuir estados aplicáveis;
6. apontar pendências;
7. estar ligada a requisito e teste;
8. não contradizer decisão aprovada;
9. não depender de interpretação visual;
10. possuir responsável quando configurável.

---

# 31. Critérios de aceite do documento

**CA-RNG-001:** `RN-001` a `RN-054` permanecem identificadas e semanticamente preservadas.  
**CA-RNG-002:** novas regras iniciam em `RN-055` sem colisão.  
**CA-RNG-003:** a sequência alcança `RN-100` sem lacunas.  
**CA-RNG-004:** imóvel permanece entidade central.  
**CA-RNG-005:** pessoa, vínculo, autorização, credencial e evento permanecem separados.  
**CA-RNG-006:** regras de pré-cadastro preservam ausência de liberação automática.  
**CA-RNG-007:** autorização, comando e confirmação permanecem distintos.  
**CA-RNG-008:** contribuição não cria autorização.  
**CA-RNG-009:** integrações permanecem desacopladas e idempotentes.  
**CA-RNG-010:** regras administrativas aplicam menor privilégio.  
**CA-RNG-011:** histórico substitui exclusão destrutiva.  
**CA-RNG-012:** estados e transições estão consolidados.  
**CA-RNG-013:** pendências não são convertidas em regra definitiva.  
**CA-RNG-014:** rastreabilidade aponta requisitos e documentos.  
**CA-RNG-015:** regras podem orientar banco, API, arquitetura e testes.  

---

# 32. Decisões consolidadas

Ficam consolidados:

- catálogo único de `RN-001` a `RN-100`;
- preservação dos identificadores originais;
- extensão derivada somente de documentos aprovados;
- estados canônicos;
- validações transversais;
- segregação por implantação;
- arquivos protegidos;
- pré-cadastro, aprovação e entrada separados;
- decisão lógica e confirmação física separadas;
- idempotência operacional e financeira;
- evidência LPR preservada;
- configurações versionadas;
- permissões efetivas explicáveis;
- segredos protegidos;
- concorrência sem sobrescrita silenciosa;
- pendências rastreadas para detalhamento posterior.

## 32.1 Registro de aprovação

| Papel | Nome | Decisão | Data | Observações |
|---|---|---|---|---|
| Product Owner | Vinicius Velasco de Azevedo | Aprovado | 28/07/2026 | Catálogo `RN-001` a `RN-100` aprovado como referência para dados, APIs, arquitetura, desenvolvimento e testes |
| Soluções do Vale | Representante designado | Ciente | 28/07/2026 | Aprovação registrada no repositório oficial |

---

# 33. Próximo documento

Após a aprovação deste catálogo, deverá ser produzido:

**`docs/010_BANCO_DE_DADOS.md`**

O modelo de dados deverá:

- implementar as entidades separadas;
- preservar históricos;
- suportar estados e vigências;
- garantir segregação;
- impor unicidade no escopo correto;
- permitir auditoria;
- armazenar referências externas sem acoplamento;
- documentar índices, chaves, retenção e migração.

---

## Situação do documento

Este documento consolida as regras de negócio do SDV Access de `RN-001` a `RN-100` e encontra-se **aprovado**. As pendências abertas deverão ser resolvidas nos documentos e decisões correspondentes antes da implementação das regras afetadas, sem invalidar esta aprovação documental.
