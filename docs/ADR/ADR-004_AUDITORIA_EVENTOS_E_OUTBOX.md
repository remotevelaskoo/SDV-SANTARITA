# ADR-004 — AUDITORIA, EVENTOS E OUTBOX

**Identificador:** ADR-004
**Versão:** 1.0.1
**Status:** Aprovado
**Prioridade:** P0 — Bloqueador
**Produto:** SDV Access — Implantação Santa Rita
**Responsável pelo produto:** Vinicius Velasco de Azevedo
**Responsável técnico:** Soluções do Vale Tecnologia
**Data:** Julho de 2026

---

## Controle de versões

| Versão | Data | Responsável | Alteração |
|---|---|---|---|
| 1.0.0 | Julho/2026 | Soluções do Vale | Proposta da estratégia de auditoria, eventos e outbox |
| 1.0.1 | 30/07/2026 | Product Owner | Aprovação formal da estratégia de auditoria, eventos e outbox |

---

# 1. Contexto

O SDV Access deverá registrar e explicar operações como:

- criação e alteração de pessoas;
- criação, renovação e encerramento de vínculos;
- aprovação ou rejeição de pré-cadastros;
- concessão e revogação de autorizações;
- associação e sincronização de credenciais;
- decisões, comandos e eventos de acesso;
- movimentos de caixa;
- alterações de usuários e permissões;
- mudanças de configurações;
- chamadas a equipamentos;
- exports e acessos a arquivos.

Parte desses fatos precisa ser persistida atomicamente com a operação. Outra parte precisa acionar processamento assíncrono sem risco de ser perdida depois do commit.

O ADR-001 aprovou monólito modular. O ADR-002 aprovou isolamento por implantação. O ADR-003 aprovou UUIDv7 e identidades próprias para eventos, comandos e auditoria.

---

# 2. Problema

Definir como:

- operações relevantes serão auditadas;
- eventos representarão fatos de domínio;
- efeitos assíncronos sobreviverão ao commit;
- falhas entre banco e fila serão evitadas;
- mensagens duplicadas serão tratadas;
- autoria e contexto serão preservados;
- dados sensíveis serão protegidos;
- auditoria permanecerá imutável;
- eventos serão versionados;
- retenção e reconciliação funcionarão.

A decisão bloqueia casos de uso críticos, filas, integrações, notificações e estrutura das tabelas de auditoria.

---

# 3. Objetivos

- rastreabilidade ponta a ponta;
- atomicidade entre operação e registro obrigatório;
- publicação confiável após commit;
- consumidores idempotentes;
- separação de finalidades;
- proteção de dados pessoais;
- explicabilidade de decisões;
- reconciliação operacional;
- compatibilidade com Laravel e PostgreSQL;
- evolução de contratos;
- observabilidade sem confusão com auditoria.

---

# 4. Não objetivos

Este ADR não define:

- ferramenta de fila;
- plataforma de logs;
- política jurídica final de retenção;
- data warehouse;
- event sourcing;
- replicação entre bancos;
- SIEM específico;
- assinatura digital de todos os registros;
- blockchain;
- contrato detalhado de cada evento;
- fluxo completo de incidentes de segurança.

---

# 5. Terminologia

| Termo | Definição |
|---|---|
| Evento de domínio | fato relevante ocorrido no domínio |
| Evento de integração | mensagem estável destinada a consumidor assíncrono |
| Auditoria | trilha de quem fez o quê, quando, onde e com qual resultado |
| Log técnico | diagnóstico de execução, falha ou desempenho |
| Outbox | registros de mensagens gravados na mesma transação do negócio |
| Dispatcher | worker que publica ou executa mensagens da outbox |
| Consumer | componente que processa uma mensagem |
| Correlação | identificador comum ao fluxo |
| Causação | identificador da ação ou mensagem que originou outra |
| At-least-once | entrega pelo menos uma vez, admitindo duplicidade |
| Idempotência | repetição sem duplicar o efeito de negócio |

---

# 6. Separação de finalidades

## 6.1 Auditoria

Responde:

- quem atuou;
- em qual implantação;
- qual ação;
- sobre qual entidade;
- quando;
- por qual origem;
- qual resultado;
- o que mudou;
- qual justificativa.

## 6.2 Evento de domínio

Expressa um fato de negócio:

```text
PreCadastroAprovado
VinculoExpirado
AcessoAutorizado
ComandoDeAberturaSolicitado
```

## 6.3 Log técnico

Auxilia diagnóstico:

```text
timeout de HTTP
query lenta
job falhou
conexão recusada
```

## 6.4 Evento de acesso

É fato operacional persistente do domínio Acesso e não deverá ser confundido com evento técnico ou mensagem de integração.

Um mesmo caso de uso poderá produzir registros nas quatro categorias, cada um com finalidade própria.

---

# 7. Alternativas consideradas

## 7.1 Alternativa A — Publicar diretamente na fila após o commit

### Vantagens

- implementação simples;
- baixa latência;
- uso direto do mecanismo de filas.

### Desvantagens

- processo pode falhar entre commit e publicação;
- mensagem pode ser perdida;
- reconciliação é difícil;
- não há atomicidade entre dado e efeito.

---

## 7.2 Alternativa B — Publicar na fila antes do commit

### Vantagens

- consumidor pode iniciar cedo;
- código aparentemente direto.

### Desvantagens

- consumidor pode observar dado não confirmado;
- rollback deixa mensagem órfã;
- corrida entre banco e worker;
- integridade inadequada.

---

## 7.3 Alternativa C — Outbox transacional

Gravar operação e mensagem na mesma transação PostgreSQL. Um dispatcher processa a outbox após o commit.

### Vantagens

- mensagem não se perde após commit;
- atomicidade local;
- retentativa;
- reconciliação;
- histórico de publicação;
- compatível com monólito modular.

### Desvantagens

- exige dispatcher;
- entrega pode duplicar;
- requer limpeza e monitoramento;
- adiciona latência assíncrona;
- contratos precisam de governança.

---

## 7.4 Alternativa D — Event sourcing

Eventos são a fonte primária do estado.

### Vantagens

- histórico completo;
- reconstrução de estado;
- modelo orientado a fatos.

### Desvantagens

- complexidade elevada;
- consultas e projeções adicionais;
- mudança profunda no modelo;
- equipe e domínio ainda não justificam;
- incompatível com a simplicidade desejada para o MVP.

---

# 8. Matriz de avaliação

Escala: 1 — desfavorável; 5 — muito favorável.

| Critério | Peso | Após commit | Antes do commit | Outbox | Event sourcing |
|---|---:|---:|---:|---:|---:|
| Atomicidade | 5 | 2 | 1 | 5 | 5 |
| Simplicidade adequada | 4 | 5 | 4 | 4 | 1 |
| Retentativa | 4 | 3 | 3 | 5 | 4 |
| Reconciliação | 4 | 2 | 1 | 5 | 5 |
| Compatibilidade com MVP | 5 | 4 | 2 | 5 | 1 |
| Auditabilidade | 5 | 3 | 2 | 5 | 5 |
| Operação | 3 | 4 | 3 | 4 | 2 |
| Evolução | 3 | 3 | 2 | 5 | 5 |

A outbox transacional oferece o melhor equilíbrio.

---

# 9. Decisão proposta

Adotar:

- auditoria persistida em tabelas próprias e somente anexada;
- eventos de domínio explícitos;
- outbox transacional para efeitos assíncronos duráveis;
- gravação da outbox na mesma transação da mudança de negócio;
- dispatcher após commit;
- entrega **pelo menos uma vez**;
- consumidores obrigatoriamente idempotentes;
- identificadores UUIDv7 para auditoria, eventos e mensagens;
- correlação e causação;
- contratos versionados;
- contexto de implantação em todos os registros operacionais;
- logs técnicos separados;
- mascaramento e classificação de dados;
- reconciliação e monitoramento da outbox;
- event sourcing fora do MVP.

---

# 10. Modelo conceitual da auditoria

## 10.1 Evento de auditoria

Campos mínimos:

- `id`;
- `implantacao_id`;
- `occurred_at`;
- `recorded_at`;
- ator;
- tipo de ator;
- ação;
- módulo;
- entidade;
- identificador da entidade;
- origem;
- resultado;
- motivo estruturado;
- justificativa autorizada;
- correlação;
- causação;
- endereço de rede e agente quando aplicável;
- classificação.

## 10.2 Alterações

Mudanças relevantes poderão usar registros filhos:

- nome do campo lógico;
- valor anterior protegido;
- valor posterior protegido;
- classificação;
- indicador de mascaramento.

O snapshot integral não será obrigatório quando aumentar exposição sem benefício.

---

# 11. Atores

Tipos mínimos:

- usuário;
- solicitante público;
- sistema;
- scheduler;
- worker;
- integração;
- equipamento;
- administrador global;
- suporte autorizado.

## 11.1 Identificação

O registro deverá preservar:

- tipo;
- ID interno quando houver;
- nome exibível no instante, quando necessário;
- implantação;
- credencial técnica ou integração, sem segredo;
- delegação ou impersonação, se futuramente permitida.

Usuário removido ou renomeado não tornará a auditoria ilegível.

---

# 12. Tempo

Auditoria e eventos manterão:

- `occurred_at`: quando o fato ocorreu;
- `recorded_at`: quando o SDV registrou;
- horário externo original quando aplicável;
- fuso ou origem externa quando necessário.

## 12.1 Regras

- persistência em UTC;
- UUIDv7 não substitui timestamp;
- data de equipamento não será confiada sem validação;
- atraso entre ocorrência e registro será observável;
- ordenação estrita usará sequência ou critério próprio quando necessário;
- relógios de infraestrutura serão sincronizados.

---

# 13. Resultado e motivo

Resultados deverão ser estruturados:

- sucesso;
- negado;
- falha;
- parcial;
- pendente;
- desconhecido.

Motivos:

- usarão catálogo quando aplicável;
- poderão ter observação complementar;
- não dependerão somente de texto livre;
- separarão motivo interno de mensagem pública;
- preservarão código histórico mesmo após inativação do catálogo.

---

# 14. Imutabilidade lógica

Registros de auditoria:

- não terão edição operacional;
- não terão exclusão por interface;
- serão inseridos por serviço controlado;
- terão permissões de banco restritas;
- serão corrigidos por evento complementar, nunca sobrescrita;
- serão protegidos por backup;
- terão acesso auditado quando necessário.

## 14.1 Não adotado no MVP

Não serão exigidos inicialmente:

- blockchain;
- ledger externo;
- hash encadeado de todos os registros;
- assinatura digital por evento.

Esses controles poderão ser avaliados se risco, contrato ou regulação exigir.

---

# 15. Falha de auditoria

Quando uma operação classificada como auditável não conseguir gravar o registro obrigatório:

- a transação deverá falhar;
- a alteração de negócio não será confirmada;
- erro será sanitizado ao usuário;
- falha técnica será alertada;
- correlação será preservada.

Eventos técnicos não essenciais poderão falhar sem rollback do negócio, desde que exista observabilidade apropriada.

A classificação de operações auditáveis virá das regras e do catálogo do módulo.

---

# 16. Eventos de domínio

Um evento de domínio:

- representa fato já ocorrido;
- usa nome no passado;
- é criado pelo domínio ou caso de uso;
- possui identidade;
- possui instante;
- possui implantação;
- referencia o agregado;
- inclui versão do contrato;
- carrega somente dados necessários;
- não contém serviço, model ou conexão;
- é imutável após criação.

Eventos não serão usados como comandos disfarçados.

---

# 17. Eventos de integração

Um evento de integração será derivado de fato interno quando houver consumidor assíncrono.

Exemplos:

- sincronizar credencial;
- enviar notificação;
- gerar export;
- reconciliar equipamento;
- solicitar OCR;
- atualizar projeção.

O contrato de integração poderá ser mais estável e limitado que o evento interno. Nem todo evento de domínio será publicado externamente.

---

# 18. Modelo conceitual da outbox

Campos mínimos:

- `id`;
- `implantacao_id`;
- `message_type`;
- `contract_version`;
- `aggregate_type`;
- `aggregate_id`;
- `correlation_id`;
- `causation_id`;
- `occurred_at`;
- `available_at`;
- payload;
- headers permitidos;
- estado;
- quantidade de tentativas;
- próxima tentativa;
- instante de processamento;
- erro sanitizado;
- lock ou lease;
- destino lógico.

## 18.1 Estados

```text
Pendente
Processando
Processado
Falha temporária
Intervenção necessária
Cancelado por decisão controlada
```

Mensagem confirmada não será editada.

---

# 19. Transação

Fluxo:

```text
iniciar transação
  → validar caso de uso
  → alterar estado do negócio
  → gravar auditoria obrigatória
  → gravar mensagens na outbox
confirmar transação
  → dispatcher torna mensagem elegível
```

Se o commit falhar, negócio, auditoria e outbox serão revertidos juntos.

Efeitos externos não ocorrerão dentro da transação principal.

---

# 20. Dispatcher

O dispatcher deverá:

1. buscar mensagens elegíveis;
2. reservar lote com concorrência segura;
3. estabelecer implantação e correlação;
4. publicar ou executar o destino;
5. registrar resultado;
6. reagendar falha transitória;
7. encaminhar falha permanente para intervenção;
8. liberar lease expirado;
9. emitir métricas.

## 20.1 Concorrência

- múltiplos workers poderão operar;
- uma mensagem não deverá ser processada simultaneamente de forma normal;
- lock terá duração limitada;
- worker morto não deixará mensagem bloqueada para sempre;
- lote será pequeno e configurável;
- transações do dispatcher serão curtas.

---

# 21. Garantia de entrega

A garantia será **at-least-once**:

- uma mensagem confirmada será processada ao menos uma vez;
- falhas poderão causar nova entrega;
- duplicidade é esperada;
- exatamente uma entrega não será prometida;
- exatamente um efeito será buscado por idempotência.

Não será usado o termo “exactly once” sem delimitar tecnicamente o recurso e a transação envolvidos.

---

# 22. Idempotência do consumidor

Cada consumidor deverá:

- identificar a mensagem;
- registrar processamento ou resultado;
- recusar payload divergente para mesma chave;
- retornar resultado anterior quando seguro;
- criar efeitos com constraint única;
- tratar retentativa;
- não depender apenas de memória;
- incluir implantação no escopo.

## 22.1 Inbox

Consumidores relevantes poderão manter tabela inbox:

- consumidor;
- mensagem;
- implantação;
- status;
- hash do payload;
- resultado;
- instante.

A necessidade de inbox por categoria será detalhada no ADR-005.

---

# 23. Retentativas

Retentativas deverão ter:

- limite;
- backoff;
- jitter quando aplicável;
- timeout;
- classificação transitória ou permanente;
- próxima tentativa;
- erro sanitizado;
- alerta por idade ou quantidade;
- ação manual autorizada.

Erro de validação de contrato não será retentado indefinidamente.

---

# 24. Intervenção e reprocessamento

Operador autorizado poderá:

- consultar mensagem;
- visualizar erro sanitizado;
- reprocessar;
- cancelar por decisão formal;
- corrigir configuração externa;
- registrar justificativa.

Não poderá:

- editar payload confirmado;
- marcar sucesso sem evidência;
- apagar histórico;
- alterar implantação;
- expor segredo.

Correção que exigir novo conteúdo criará nova mensagem correlacionada.

---

# 25. Contratos e versionamento

Cada tipo de mensagem terá:

- nome estável;
- versão;
- proprietário;
- schema;
- campos obrigatórios;
- campos opcionais;
- classificação;
- política de compatibilidade;
- testes de contrato.

## 25.1 Evolução

- adicionar campo opcional poderá manter versão;
- remover, renomear ou alterar semântica exigirá nova versão;
- consumidor desconhecido deverá falhar de forma controlada;
- versões antigas terão período de suporte;
- payload não será acoplado à serialização de model Eloquent.

---

# 26. Conteúdo do payload

O payload deverá ser mínimo.

Preferir:

- identificadores;
- fatos necessários;
- versão;
- instante;
- correlação.

Evitar:

- documentos completos;
- imagens;
- tokens;
- segredos;
- snapshots integrais;
- objetos serializados do framework;
- dados que o consumidor não usa.

Arquivos serão referenciados por ID protegido, não incorporados.

---

# 27. Segurança e privacidade

## 27.1 Auditoria

- campos sensíveis serão mascarados, omitidos ou cifrados;
- “antes e depois” obedecerá classificação;
- tokens e senhas nunca serão gravados;
- acesso à auditoria seguirá menor privilégio;
- exportação de auditoria será auditada.

## 27.2 Outbox

- payload não conterá segredo;
- workers usarão credenciais próprias;
- implantação será validada;
- erros externos serão sanitizados;
- retenção será limitada;
- reprocessamento exigirá permissão.

## 27.3 Logs

Logs não duplicarão dados sensíveis da auditoria.

---

# 28. Multi-implantação

Auditoria, outbox e inbox operacionais terão `implantacao_id`.

- índices incluirão implantação quando necessário;
- dispatcher estabelecerá contexto por mensagem;
- worker limpará contexto ao concluir;
- reprocessamento não poderá mudar implantação;
- mensagem global será excepcional e explicitamente classificada;
- consultas administrativas globais exigirão autoridade;
- métricas usarão identificador opaco.

FKs críticas seguirão ADR-002 e ADR-003.

---

# 29. Relação com eventos de acesso

O evento de acesso é fato do domínio e terá tabela operacional própria.

Exemplo:

```text
atendimento
  → decisão de acesso
  → comando
  → confirmação
  → evento de acesso
```

Auditoria registrará a ação do operador e as transições relevantes. Outbox transportará efeitos como comando ao equipamento ou notificação.

Não será criada uma única tabela genérica para substituir todas essas entidades.

---

# 30. Relação com integrações

Para equipamentos:

- decisão e comando são persistidos;
- mensagem de comando entra na outbox;
- adaptador processa idempotentemente;
- resposta externa cria registro próprio;
- callback preserva correlação;
- timeout mantém resultado desconhecido;
- reconciliação poderá criar nova mensagem;
- auditoria registra intervenção manual.

O ID externo permanece secundário conforme ADR-003.

---

# 31. Relação com notificações

- operação de negócio não aguardará canal lento;
- outbox registrará solicitação de notificação;
- destinatário será minimizado;
- template terá versão;
- falha de envio não reverterá negócio já confirmado;
- retentativa respeitará política;
- mensagem pública será separada de observação interna;
- resultado de entrega será rastreável.

---

# 32. Relação com arquivos e exports

- geração de export poderá ser solicitada por outbox;
- arquivo terá entidade e implantação próprias;
- conclusão produzirá evento;
- URL temporária não será gravada na mensagem permanente;
- acesso e download poderão ser auditados;
- falha não produzirá estado de conclusão falso;
- retenção do payload não excederá a necessidade.

---

# 33. Logs técnicos

Logs técnicos serão estruturados com:

- timestamp;
- nível;
- ambiente;
- módulo;
- operação;
- correlação;
- implantação opaca quando permitido;
- mensagem sanitizada;
- classe de erro;
- duração.

Não deverão:

- substituir auditoria;
- ser fonte única de fato de negócio;
- conter payload integral;
- conter credencial;
- decidir estado da entidade.

---

# 34. Métricas e alertas

Métricas mínimas:

- mensagens pendentes;
- idade da mais antiga;
- taxa de processamento;
- latência até processamento;
- tentativas;
- falhas temporárias;
- intervenções necessárias;
- leases expirados;
- duplicidades detectadas;
- falhas de auditoria;
- volume por tipo.

Alertas deverão ser acionáveis e não conter dados pessoais.

---

# 35. Retenção e limpeza

## 35.1 Auditoria

Retenção depende da política aprovada e não será apagada automaticamente antes dela.

## 35.2 Outbox

Mensagens processadas poderão ser:

- mantidas por período operacional;
- arquivadas;
- resumidas;
- descartadas de forma controlada.

## 35.3 Regras

- mensagem pendente não será removida;
- falha não será ocultada por limpeza;
- auditoria de reprocessamento será preservada;
- particionamento depende de volume medido e ADR-014;
- limpeza será idempotente e monitorada.

---

# 36. Reconciliação

Rotina de reconciliação deverá detectar:

- negócio confirmado sem efeito esperado;
- mensagem pendente além do limite;
- mensagem processada sem resultado;
- comando sem confirmação;
- callback sem correlação;
- lease expirado;
- duplicidade;
- divergência entre outbox e inbox.

Reconciliação não alterará estado silenciosamente. Toda correção será rastreável.

---

# 37. Desempenho

- índices por estado e `available_at`;
- lotes limitados;
- payload pequeno;
- transações curtas;
- processamento fora da requisição;
- retenção controlada;
- particionamento apenas por evidência;
- auditoria de alterações em estrutura própria;
- consultas operacionais separadas de relatórios pesados.

A auditoria obrigatória faz parte do custo da transação e deverá ser medida.

---

# 38. Consequências positivas

- operação e auditoria confirmadas juntas;
- efeitos assíncronos não se perdem após commit;
- retentativa segura;
- reconciliação explícita;
- separação entre domínio, auditoria e logs;
- integração desacoplada;
- rastreabilidade ponta a ponta;
- contratos versionados;
- suporte a falhas externas;
- observabilidade operacional.

---

# 39. Consequências negativas

- tabelas e workers adicionais;
- entrega duplicada possível;
- consumidores mais complexos;
- necessidade de limpeza;
- latência assíncrona;
- governança de contratos;
- custo de armazenamento;
- necessidade de monitoramento;
- transação de negócio inclui auditoria e outbox;
- reprocessamento exige interface administrativa.

Esses custos são aceitos para assegurar integridade e auditabilidade.

---

# 40. Riscos e mitigações

| Risco | Mitigação |
|---|---|
| dispatcher parar | health check, métrica e alerta |
| mensagem processada duas vezes | idempotência e inbox |
| payload conter dado sensível | schema, revisão e testes |
| outbox crescer | retenção e monitoramento |
| contrato quebrar consumidor | versionamento e teste |
| auditoria falhar silenciosamente | rollback da operação obrigatória |
| lock ficar preso | lease com expiração |
| erro permanente retentar sempre | classificação e intervenção |
| log substituir fato | entidades operacionais próprias |
| evento genérico demais | ownership por módulo |
| reprocessamento mudar payload | nova mensagem correlacionada |
| event sourcing surgir por acidente | persistência atual continua fonte do estado |

---

# 41. Estratégia de implementação

1. aprovar este ADR;
2. definir tabelas de auditoria;
3. definir tabela outbox;
4. criar serviço transacional de auditoria;
5. criar contrato de evento;
6. implementar dispatcher piloto;
7. implementar consumidor idempotente;
8. simular falha após commit;
9. simular duplicidade;
10. implementar métricas;
11. implementar reconciliação;
12. provar isolamento por implantação;
13. documentar contratos no Manual do Desenvolvedor.

---

# 42. Validação

A decisão será validada quando:

- rollback remover negócio, auditoria e outbox;
- commit preservar os três;
- dispatcher retomar após interrupção;
- duplicidade não duplicar efeito;
- mensagem inválida ir para intervenção;
- payload não conter segredo;
- auditoria registrar antes e depois permitidos;
- duas implantações permanecerem isoladas;
- correlação atravessar requisição, outbox e consumidor;
- métricas detectarem atraso;
- reconciliação encontrar inconsistência simulada.

---

# 43. Critérios de aceite

**CA-ADR-004-001:** auditoria, evento de domínio e log técnico possuem finalidades distintas.

**CA-ADR-004-002:** eventos de acesso permanecem fatos operacionais próprios.

**CA-ADR-004-003:** auditoria obrigatória é gravada na transação do negócio.

**CA-ADR-004-004:** falha da auditoria obrigatória causa rollback.

**CA-ADR-004-005:** mensagens duráveis são gravadas em outbox na mesma transação.

**CA-ADR-004-006:** efeitos externos ocorrem somente após commit.

**CA-ADR-004-007:** a entrega é declarada como at-least-once.

**CA-ADR-004-008:** consumidores são idempotentes.

**CA-ADR-004-009:** duplicidade não duplica efeito de negócio.

**CA-ADR-004-010:** eventos e mensagens possuem UUIDv7 próprio.

**CA-ADR-004-011:** correlação e causação são preservadas.

**CA-ADR-004-012:** contratos possuem versão.

**CA-ADR-004-013:** payloads não serializam models do framework.

**CA-ADR-004-014:** tokens e segredos não entram em auditoria, outbox ou logs.

**CA-ADR-004-015:** implantação acompanha registros operacionais.

**CA-ADR-004-016:** dispatcher suporta concorrência e lease expirável.

**CA-ADR-004-017:** falhas temporárias e permanentes têm tratamento distinto.

**CA-ADR-004-018:** reprocessamento exige permissão e justificativa.

**CA-ADR-004-019:** mensagem confirmada não é editada.

**CA-ADR-004-020:** auditoria é somente anexada.

**CA-ADR-004-021:** logs técnicos não substituem fatos de negócio.

**CA-ADR-004-022:** métricas monitoram idade, falhas e volume.

**CA-ADR-004-023:** reconciliação detecta operações incompletas.

**CA-ADR-004-024:** event sourcing permanece fora do MVP.

---

# 44. Rastreabilidade

## 44.1 Documentos

- `docs/009_REGRAS_DE_NEGOCIO.md`;
- `docs/010_BANCO_DE_DADOS.md`;
- `docs/011_ARQUITETURA_DO_SISTEMA.md`;
- `docs/ADR/000_CATALOGO_DE_ADRS.md`;
- `docs/ADR/ADR-001_MONOLITO_MODULAR_LARAVEL.md`;
- `docs/ADR/ADR-002_MULTI_IMPLANTACAO_E_ISOLAMENTO.md`;
- `docs/ADR/ADR-003_IDENTIFICADORES_INTERNOS_E_PUBLICOS.md`.

## 44.2 Regras

- `RN-041` — tentativas de acesso;
- `RN-043`, `RN-044` — liberações e exceções;
- `RN-046` a `RN-049` — auditoria;
- `RN-055` — segregação;
- `RN-056`, `RN-057` — entidades e transições;
- `RN-072`, `RN-073` — versões e mensagens;
- `RN-078` a `RN-080` — comando e idempotência;
- `RN-085` — idempotência financeira;
- `RN-086` — evidência LPR;
- `RN-092`, `RN-093` — filas e sincronização;
- `RN-094`, `RN-095` — configurações versionadas.

---

# 45. Dependências

| ADR | Relação |
|---|---|
| ADR-001 | módulos e transações |
| ADR-002 | implantação em auditoria e mensagens |
| ADR-003 | UUIDv7, correlação e IDs externos |
| ADR-005 | fila, inbox, locks e idempotência |
| ADR-007 | adaptadores consumidores |
| ADR-009 | proteção de segredos |
| ADR-010 | métricas, logs e alertas |
| ADR-014 | retenção e particionamento condicional |

---

# 46. Pendências

| PEN-ADR-004 | Pendência | Tratamento |
|---|---|---|
| PEN-ADR-004-001 | Tabelas físicas e índices | banco e migrations |
| PEN-ADR-004-002 | Ferramenta de fila | ADR-005 |
| PEN-ADR-004-003 | Necessidade de inbox por consumidor | ADR-005 |
| PEN-ADR-004-004 | Política final de retenção | privacidade e ADR-014 |
| PEN-ADR-004-005 | Campos auditáveis por módulo | Manual do Desenvolvedor |
| PEN-ADR-004-006 | Interface de intervenção | Administração |
| PEN-ADR-004-007 | Política de acesso à auditoria | segurança |
| PEN-ADR-004-008 | Necessidade futura de hash encadeado | avaliação de risco |

---

# 47. Aprovação

| Papel | Nome | Decisão | Data | Observações |
|---|---|---|---|---|
| Product Owner | Vinicius Velasco de Azevedo | Aprovado | 30/07/2026 | Auditoria transacional e outbox com entrega at-least-once aprovadas |
| Responsável técnico | Soluções do Vale Tecnologia | Recomendado | Julho/2026 | Auditoria transacional e outbox com entrega at-least-once |

---

# 48. Decisão resultante

Com este ADR **Aprovado**:

- o catálogo será atualizado no mesmo commit;
- operações obrigatórias gravarão auditoria atomicamente;
- efeitos duráveis usarão outbox;
- consumidores serão idempotentes;
- ADR-005 detalhará filas, inbox, locks e retentativas;
- o Manual do Desenvolvedor definirá contratos e testes.

---

## Situação do ADR

**Aprovado.** Auditoria transacional, eventos explícitos e outbox com entrega at-least-once constituem a estratégia vigente.
