# ADR-005 — FILAS, CACHE, LOCKS E IDEMPOTÊNCIA

**Identificador:** ADR-005
**Versão:** 1.0.0
**Status:** Proposto
**Prioridade:** P0 — Bloqueador
**Produto:** SDV Access — Implantação Santa Rita
**Responsável pelo produto:** Vinicius Velasco de Azevedo
**Responsável técnico:** Soluções do Vale Tecnologia
**Data:** Julho de 2026

---

## Controle de versões

| Versão | Data | Responsável | Alteração |
|---|---|---|---|
| 1.0.0 | Julho/2026 | Soluções do Vale | Proposta da estratégia de filas, cache, locks e idempotência |

---

# 1. Contexto

O SDV Access executará tarefas que não devem permanecer no ciclo síncrono da requisição:

- sincronização de credenciais;
- comandos e reconciliação de equipamentos;
- notificações;
- processamento de arquivos;
- OCR ou IA condicional;
- exports;
- indicadores;
- expirações;
- retentativas;
- limpeza técnica.

Também precisará:

- acelerar leituras seguras;
- coordenar processos concorrentes;
- limitar requisições;
- impedir duplicidade de efeitos;
- retomar falhas;
- operar com múltiplos processos web e workers.

O ADR-004 aprovou auditoria transacional e outbox com entrega pelo menos uma vez. Este ADR define a infraestrutura e os padrões usados após a persistência da outbox.

---

# 2. Problema

Definir:

- backend de filas;
- backend de cache;
- mecanismo de locks distribuídos;
- divisão das filas;
- estratégia de retentativa;
- inbox e idempotência;
- comportamento em indisponibilidade;
- isolamento por implantação;
- limites de payload;
- monitoramento;
- escalabilidade;
- retenção dos registros duráveis.

A decisão bloqueia processamento assíncrono, integrações, rate limiting e fluxos concorrentes.

---

# 3. Objetivos

- baixa latência operacional;
- processamento assíncrono confiável;
- compatibilidade com Laravel;
- outbox como origem durável;
- idempotência persistente;
- isolamento por implantação;
- locks com expiração;
- filas observáveis;
- retentativas controladas;
- operação degradada explícita;
- escalabilidade de workers;
- independência de fornecedor comercial.

---

# 4. Não objetivos

Este ADR não define:

- fornecedor de nuvem;
- imagem ou distribuição final do serviço;
- cluster de produção;
- número inicial de réplicas;
- SLO e capacidade;
- contratos dos fabricantes;
- scheduler completo;
- política final de sessão;
- arquitetura de streaming;
- Kafka ou event sourcing;
- ferramenta final de observabilidade.

---

# 5. Base técnica

O Laravel fornece API unificada de filas com suporte a diferentes backends, incluindo Redis e banco relacional. O framework também fornece locks atômicos por meio de backends de cache compartilhados.

Fontes oficiais:

- [Laravel — Queues](https://laravel.com/docs/12.x/queues);
- [Laravel — Cache e Atomic Locks](https://laravel.com/docs/12.x/cache#atomic-locks);
- [Laravel — Redis](https://laravel.com/docs/12.x/redis).

A versão definitiva do framework e do cliente será registrada no baseline técnico. A implementação deverá provar as capacidades usadas.

---

# 6. Terminologia

| Termo | Definição |
|---|---|
| Fila | sequência lógica de jobs aguardando processamento |
| Job | unidade assíncrona de trabalho |
| Worker | processo consumidor de jobs |
| Cache | dado derivado e descartável |
| Lock | exclusão mútua temporária |
| Lease | posse temporária com expiração |
| Idempotência | repetição sem duplicar efeito |
| Inbox | registro durável de mensagens consumidas |
| Outbox | mensagens duráveis produzidas na transação |
| Backoff | espera progressiva entre tentativas |
| Jitter | variação aleatória para evitar retentativas simultâneas |
| Dead letter lógico | item que exige intervenção após falhas |

---

# 7. Princípios

1. PostgreSQL permanece fonte de verdade.
2. Cache é descartável.
3. Fila não substitui outbox.
4. Lock não substitui constraint.
5. Entrega duplicada é esperada.
6. Consumidor é idempotente.
7. Job não carrega segredo.
8. Implantação acompanha todo trabalho operacional.
9. Retentativa possui limite.
10. Falha permanente não entra em loop.
11. Operação degradada é explícita.
12. Mudança de backend exige teste e decisão controlada.

---

# 8. Alternativas consideradas

## 8.1 Alternativa A — PostgreSQL para filas, cache e locks

### Vantagens

- uma dependência de infraestrutura;
- transações e backup conhecidos;
- suporte do framework;
- operação inicial simples.

### Desvantagens

- disputa com carga transacional;
- polling;
- maior custo para dados efêmeros;
- limpeza de tabelas;
- locks e cache aumentam pressão no banco principal.

---

## 8.2 Alternativa B — Serviço compatível com Redis

Usar serviço em memória compatível com as capacidades Redis necessárias para fila, cache e locks.

### Vantagens

- baixa latência;
- integração madura com Laravel;
- locks compartilhados;
- separação da carga transacional;
- filas e cache no mesmo serviço lógico;
- escalabilidade de workers.

### Desvantagens

- nova dependência;
- dados em memória exigem política de persistência;
- compatibilidade precisa ser testada;
- falha afeta filas, cache e locks;
- configuração e segurança adicionais.

---

## 8.3 Alternativa C — Broker dedicado e cache separado

Broker de mensagens para filas e serviço separado para cache/locks.

### Vantagens

- capacidades especializadas;
- isolamento de falhas;
- maior flexibilidade de topologia;
- recursos avançados de mensageria.

### Desvantagens

- duas dependências;
- maior custo operacional;
- contratos e ferramentas adicionais;
- complexidade desproporcional ao MVP.

---

## 8.4 Alternativa D — Execução síncrona

Executar jobs dentro da requisição.

### Vantagens

- simplicidade aparente;
- resposta imediata do efeito.

### Desvantagens

- aumenta latência;
- acopla disponibilidade externa;
- reduz capacidade de retentativa;
- não escala;
- risco de timeout;
- inadequada para integrações e arquivos.

---

# 9. Matriz de avaliação

Escala: 1 — desfavorável; 5 — muito favorável.

| Critério | Peso | PostgreSQL | Redis compatível | Broker + cache | Síncrono |
|---|---:|---:|---:|---:|---:|
| Latência | 4 | 3 | 5 | 5 | 2 |
| Simplicidade do MVP | 4 | 4 | 4 | 1 | 5 |
| Isolamento do banco | 4 | 1 | 5 | 5 | 3 |
| Compatibilidade Laravel | 5 | 5 | 5 | 4 | 5 |
| Locks distribuídos | 4 | 3 | 5 | 5 | 1 |
| Escala de workers | 4 | 3 | 5 | 5 | 1 |
| Operação | 3 | 4 | 4 | 2 | 4 |
| Retentativa | 4 | 4 | 5 | 5 | 1 |
| Adequação ao contexto | 5 | 3 | 5 | 2 | 1 |

A alternativa B oferece o melhor equilíbrio para o MVP.

---

# 10. Decisão proposta

Adotar:

- serviço compatível com o protocolo e capacidades Redis necessárias;
- Laravel Queue sobre esse serviço;
- Laravel Cache para cache efêmero;
- locks atômicos compartilhados;
- PostgreSQL para outbox, inbox e idempotência durável;
- namespaces e conexões lógicas separados;
- workers separados por categoria;
- entrega pelo menos uma vez;
- jobs idempotentes;
- retentativas com backoff e jitter;
- falha permanente encaminhada para intervenção;
- cache nunca como fonte de verdade;
- ausência de fallback automático e silencioso para execução síncrona;
- fornecedor e distribuição definidos na infraestrutura, após teste de compatibilidade.

---

# 11. Limite entre PostgreSQL e serviço em memória

## 11.1 PostgreSQL

Persistirá:

- estado de negócio;
- auditoria;
- outbox;
- inbox quando necessária;
- chave e resultado idempotente;
- comandos;
- eventos;
- estado de sincronização;
- histórico de tentativas relevante.

## 11.2 Serviço em memória

Manterá:

- jobs enfileirados;
- cache derivado;
- locks temporários;
- rate limits;
- sinais efêmeros de coordenação;
- métricas técnicas temporárias quando aplicável.

Perder o serviço em memória não poderá apagar fato de negócio confirmado. A outbox permitirá republicação.

---

# 12. Outbox e fila

Fluxo aprovado:

```text
transação PostgreSQL
  → negócio
  → auditoria
  → outbox
commit
  → dispatcher
  → fila
  → worker
  → inbox/idempotência
  → efeito
  → resultado persistido
```

## 12.1 Regras

- job não será criado antes do commit;
- outbox continuará sendo origem reconciliável;
- publicação duplicada será tolerada;
- remoção acidental da fila não removerá a outbox;
- dispatcher e consumidor terão responsabilidades distintas;
- o estado “publicado” não será confundido com “efeito concluído”.

---

# 13. Categorias de filas

| Fila lógica | Uso | Prioridade |
|---|---|---:|
| `access-critical` | comandos e reconciliação de acesso | crítica |
| `integrations` | sincronizações com equipamentos | alta |
| `files` | validação e processamento de arquivos | média |
| `notifications` | mensagens aos usuários | média |
| `exports` | relatórios e exports | baixa |
| `maintenance` | limpeza e rotinas técnicas | baixa |
| `default` | apenas jobs classificados e aprovados | normal |

OCR/IA terá fila própria se o ADR-011 for aprovado.

## 13.1 Regras

- job terá fila explícita;
- fila crítica não será bloqueada por export;
- workers poderão ter escalabilidade própria;
- nomes finais serão configuráveis;
- uma fila não representará uma implantação individual por padrão;
- isolamento ocorrerá no payload, contexto e chaves.

---

# 14. Prioridade e justiça

- workers críticos terão reserva de capacidade;
- filas baixas não consumirirão toda a capacidade;
- rajada de uma implantação deverá ser limitada;
- prioridade não autoriza fome indefinida;
- idade máxima será monitorada;
- rate limiting por integração poderá reduzir sobrecarga;
- escalabilidade responderá a volume e idade.

Política exata de capacidade depende de SLO e teste de carga.

---

# 15. Estrutura do job

Payload mínimo:

- `job_id` UUIDv7;
- `message_id` ou referência da outbox;
- `implantacao_id`;
- tipo;
- versão;
- correlação;
- causação;
- identificadores de entidades;
- parâmetros mínimos;
- instante de criação.

Não incluir:

- model serializado;
- objeto de conexão;
- token;
- senha;
- imagem;
- documento completo;
- payload externo bruto;
- contexto de sessão.

O worker recarregará o estado necessário no contexto correto.

---

# 16. Contexto da implantação

Ao processar um job:

1. validar formato;
2. carregar implantação;
3. validar estado;
4. estabelecer contexto imutável;
5. verificar recurso;
6. executar;
7. persistir resultado;
8. limpar contexto;
9. emitir métricas.

Worker reutilizado não manterá contexto do job anterior.

Chaves de fila, cache e locks seguirão namespace com ambiente e implantação quando forem específicas.

---

# 17. Entrega e confirmação

A fila oferece transporte, não garantia de efeito único.

- job será confirmado somente após conclusão;
- falha antes da confirmação poderá entregar novamente;
- timeout poderá produzir resultado desconhecido;
- efeito externo terá correlação;
- confirmação de fila não será confirmação do equipamento;
- resultado será persistido no domínio;
- ack prematuro será proibido.

---

# 18. Idempotência

## 18.1 Operações cobertas

- conversão de pré-cadastro;
- comando de acesso;
- sincronização de credencial;
- callback;
- contribuição;
- notificação quando duplicidade for relevante;
- export;
- importação;
- webhook.

## 18.2 Chave

Escopo:

```text
implantacao + consumidor/operação + chave idempotente
```

## 18.3 Registro

Persistir:

- chave ou hash;
- versão;
- hash do payload;
- status;
- resultado;
- entidade criada;
- início;
- conclusão;
- validade;
- tentativas.

Payload diferente com mesma chave deverá falhar.

---

# 19. Inbox

Inbox será obrigatória quando:

- consumidor recebe mensagem que pode duplicar;
- efeito não possui constraint suficiente;
- resultado anterior precisa ser recuperado;
- processamento cruza integração;
- risco de duplicidade é relevante.

## 19.1 Fluxo

```text
receber mensagem
  → tentar registrar inbox
  → se já concluída, devolver resultado
  → se em processamento, aplicar política
  → executar efeito
  → concluir inbox e negócio atomicamente quando possível
```

Inbox não substituirá constraint única do domínio.

---

# 20. Concorrência idempotente

Dois workers poderão receber a mesma operação.

Controles:

- constraint única;
- transação;
- lock quando necessário;
- estado “processando” com lease;
- versão esperada;
- resultado persistido;
- detecção de payload divergente.

Não será usado apenas “verificar antes e criar”, pois existe condição de corrida.

---

# 21. Retentativas

Cada tipo de job definirá:

- tentativas máximas;
- timeout;
- backoff;
- jitter;
- exceções retentáveis;
- exceções permanentes;
- tempo máximo total;
- destino após esgotamento.

## 21.1 Transitórias

Exemplos:

- timeout de rede;
- serviço temporariamente indisponível;
- limite externo;
- lock ocupado;
- conexão interrompida.

## 21.2 Permanentes

Exemplos:

- contrato inválido;
- credencial revogada;
- entidade inexistente;
- operação incompatível;
- payload corrompido.

Falha permanente não será retentada automaticamente sem alteração de condição.

---

# 22. Backoff e jitter

- backoff progressivo;
- jitter para evitar rajada sincronizada;
- limites por integração;
- respeito a indicação externa de espera;
- fila crítica com política própria;
- nenhum loop sem pausa;
- métricas de próxima tentativa;
- configuração versionada quando afetar operação.

Valores serão definidos por tipo após prova com cada integração.

---

# 23. Jobs falhos e intervenção

Após esgotamento:

- persistir estado de intervenção;
- preservar erro sanitizado;
- alertar;
- permitir consulta;
- permitir reprocessamento autorizado;
- exigir justificativa quando crítico;
- manter histórico de tentativas;
- não editar payload original;
- criar nova mensagem se o conteúdo mudar.

Limpar uma fila não equivale a resolver jobs falhos.

---

# 24. Locks distribuídos

Locks serão usados para coordenação temporária, como:

- impedir dois fechamentos de caixa;
- evitar publicação concorrente da mesma configuração;
- coordenar scheduler;
- limitar sincronização por equipamento;
- reservar reconciliação;
- proteger job não paralelizável.

## 24.1 Regras

- chave com ambiente e implantação;
- owner/token de posse;
- TTL obrigatório;
- liberação somente pelo proprietário;
- duração maior que execução esperada ou renovação segura;
- tratamento de expiração;
- timeout de aquisição;
- `finally` para liberação;
- métrica de contenção.

---

# 25. Limites dos locks

Lock não substitui:

- chave única;
- FK;
- transação;
- concorrência otimista;
- estado de domínio;
- idempotência.

## 25.1 Falha segura

Para operação crítica:

- indisponibilidade do lock falhará fechada;
- não haverá execução concorrente “por garantia”;
- usuário receberá estado temporariamente indisponível;
- falha será observável.

Para operação não crítica, poderá haver execução sem cache, mas nunca sem integridade.

---

# 26. Cache

Cache será usado para dados derivados ou de baixa mutação:

- catálogos;
- configurações publicadas;
- capacidades de adaptadores;
- consultas autorizadas de leitura;
- rate limiting;
- resultados técnicos temporários.

Não armazenará como fonte única:

- autorização vigente;
- decisão de acesso;
- movimento financeiro;
- auditoria;
- evento de acesso;
- vínculo;
- configuração ainda não publicada.

---

# 27. Chaves de cache

Formato conceitual:

```text
sdv:{ambiente}:{implantacao}:{modulo}:{recurso}:{versao}:{chave}
```

## 27.1 Regras

- não conter dado pessoal em claro;
- incluir implantação;
- incluir versão quando schema mudar;
- possuir TTL quando aplicável;
- tamanho limitado;
- hash para entradas sensíveis ou longas;
- invalidação documentada;
- prefixo diferente por ambiente;
- cache global explicitamente marcado.

---

# 28. Invalidação

Estratégias:

- expiração por TTL;
- remoção após publicação;
- troca de versão;
- invalidação por evento;
- reconstrução sob demanda.

## 28.1 Regras

- alteração de configuração invalidará cache relacionado;
- falha de invalidação não confirmará dado incorreto como permanente;
- cache stampede será mitigado quando necessário;
- invalidação ampla exigirá cuidado multi-implantação;
- interface administrativa mostrará publicação real, não apenas cache.

---

# 29. Cache e decisão de acesso

Cache poderá acelerar consulta, mas antes de autorizar:

- estados críticos serão revalidados conforme política;
- validade e bloqueio não dependerão de dado indefinido;
- cache terá versão e expiração curta quando usado;
- invalidação será acionada por mudanças críticas;
- operação degradada será documentada;
- cache local de equipamento depende do ADR-008.

Este ADR não aprova acesso offline.

---

# 30. Rate limiting

Aplicações:

- login;
- recuperação;
- pré-cadastro;
- consulta pública;
- callbacks;
- comandos de integração;
- exports;
- operações administrativas críticas.

Chave poderá combinar:

- implantação;
- usuário;
- credencial técnica;
- IP;
- operação;
- recurso.

Limite não revelará existência de recurso e não será única defesa contra abuso.

---

# 31. Sessões

Este ADR não define a política final de sessão.

Diretriz inicial:

- não tornar a autenticação dependente de decisão silenciosa deste ADR;
- backend de sessão será definido com requisitos de revogação e continuidade;
- se o serviço Redis compatível for usado, terá namespace/conexão lógica distinta;
- falha não poderá reutilizar sessão de outro usuário ou implantação;
- cookies continuam protegidos;
- inativação deverá permitir revogação.

---

# 32. Conexões e namespaces

Separações lógicas mínimas:

- cache;
- filas;
- locks/rate limits;
- sessões, se adotadas.

## 32.1 Regras

- prefixos por aplicação e ambiente;
- credenciais com menor privilégio quando suportado;
- limites de memória por finalidade;
- política de eviction compatível;
- cache pode sofrer eviction;
- fila não poderá ser configurada como cache descartável;
- teste impedirá colisão entre ambientes.

Separação física poderá ocorrer quando escala ou risco justificar.

---

# 33. Persistência do serviço em memória

Para filas:

- persistência e recuperação deverão ser configuradas conforme o produto escolhido;
- restart não deverá descartar trabalho confirmado de forma silenciosa;
- outbox continuará permitindo reconciliação;
- perda de fila acionará republicação controlada.

Para cache:

- perda é aceitável;
- aplicação deve reconstruir;
- aquecimento não será requisito de integridade.

O nível de persistência será definido na infraestrutura e testado.

---

# 34. Indisponibilidade

## 34.1 Filas indisponíveis

- negócio poderá confirmar se outbox foi gravada;
- mensagem permanecerá pendente;
- dispatcher retentará;
- operação que exige resposta imediata seguirá fluxo próprio;
- idade da outbox gerará alerta.

## 34.2 Cache indisponível

- leitura não crítica poderá ir ao PostgreSQL;
- proteção contra sobrecarga será aplicada;
- nenhum dado incorreto será presumido;
- desempenho degradado será sinalizado.

## 34.3 Locks indisponíveis

- operação crítica falhará fechada;
- operação não crítica usará alternativa somente se formalmente segura.

## 34.4 Proibição

Não haverá troca automática para driver síncrono ou banco sem validação, pois isso muda semântica e carga.

---

# 35. Segurança

- serviço não será exposto à internet;
- autenticação e criptografia conforme topologia;
- segredo fora do código;
- usuário com menor privilégio;
- comandos administrativos restritos;
- payload sem tokens;
- logs sanitizados;
- namespaces por ambiente;
- backups/persistência protegidos quando usados;
- dependências e imagens verificadas;
- acesso operacional auditado.

Compatibilidade de protocolo não implica compatibilidade de segurança; cada produto será homologado.

---

# 36. Multi-implantação

- jobs carregam implantação;
- cache inclui implantação;
- locks incluem implantação;
- rate limits incluem escopo apropriado;
- inbox e idempotência usam chave composta;
- worker limpa contexto;
- relatórios de fila não expõem dados de outra implantação;
- suporte global exige autoridade;
- métricas usam identificador opaco.

Teste com duas implantações será obrigatório.

---

# 37. Workers

Workers deverão:

- executar usuário não privilegiado;
- reiniciar de forma controlada;
- encerrar graciosamente;
- respeitar timeout;
- limitar memória;
- processar filas autorizadas;
- estabelecer contexto;
- limpar estado entre jobs;
- emitir heartbeat;
- expor saúde;
- usar mesma versão compatível da aplicação.

Deploy deverá coordenar workers e contratos de jobs.

---

# 38. Scheduler

Scheduler poderá criar ou coordenar:

- expirações;
- reconciliação;
- republicação de outbox;
- limpeza;
- relatórios;
- verificação de saúde.

## 38.1 Regras

- execução única quando necessária;
- lock com TTL;
- idempotência;
- fuso da implantação;
- ausência de sobreposição;
- resultado auditável;
- alerta de atraso.

---

# 39. Observabilidade

Métricas mínimas:

- tamanho da fila;
- idade do job mais antigo;
- taxa de entrada e saída;
- tempo de espera;
- duração;
- sucesso e falha;
- tentativas;
- jobs em intervenção;
- locks adquiridos, falhos e expirados;
- cache hit/miss;
- memória e eviction;
- conexões;
- outbox pendente;
- duplicidades detectadas.

Logs usarão correlação e não incluirão payload completo.

---

# 40. Alertas

Alertas mínimos:

- fila crítica envelhecida;
- worker ausente;
- taxa de falha;
- outbox sem publicação;
- intervenção acumulada;
- lock preso ou contenção elevada;
- eviction em área não descartável;
- memória próxima do limite;
- conexão indisponível;
- job excedendo timeout;
- divergência de inbox.

Cada alerta deverá possuir responsável e ação esperada.

---

# 41. Limpeza e retenção

## 41.1 Fila

Jobs confirmados serão removidos conforme o backend.

## 41.2 Falhas

Histórico relevante será persistido no PostgreSQL ou observabilidade pelo período aprovado.

## 41.3 Inbox e idempotência

- retenção maior que a janela de duplicidade;
- não remover operação ainda repetível;
- limpeza idempotente;
- proteção de resultado financeiro ou de acesso;
- política por tipo.

## 41.4 Cache

TTL e eviction são esperados.

---

# 42. Testes obrigatórios

- job processado com sucesso;
- job entregue duas vezes;
- worker morre durante execução;
- lock expira;
- lock só é liberado pelo owner;
- payload divergente com mesma chave;
- fila indisponível após commit;
- cache vazio;
- cache indisponível;
- duas implantações com mesmo ID lógico;
- retry com backoff;
- falha permanente;
- reprocessamento autorizado;
- restart do serviço;
- deploy com job antigo;
- fila crítica isolada de export.

---

# 43. Consequências positivas

- baixa latência;
- banco transacional protegido de carga efêmera;
- filas e locks integrados ao Laravel;
- cache reconstruível;
- outbox mantém durabilidade;
- consumidores idempotentes;
- escalabilidade por fila;
- operação degradada definida;
- fornecedor substituível mediante compatibilidade;
- monitoramento consistente.

---

# 44. Consequências negativas

- dependência adicional;
- operação e segurança do serviço;
- necessidade de persistência para filas;
- falha compartilhada entre cache, filas e locks;
- consumidores mais complexos;
- retenção de inbox;
- configuração de memória;
- necessidade de monitoramento;
- semântica pode variar entre produtos compatíveis;
- fallback exige planejamento.

Esses custos são aceitos com separação lógica, outbox e homologação.

---

# 45. Riscos e mitigações

| Risco | Mitigação |
|---|---|
| perda de fila | outbox e persistência |
| duplicidade | idempotência e inbox |
| cache virar fonte de verdade | revisão e testes |
| lock expirar cedo | TTL e renovação segura |
| lock órfão | expiração |
| implantação ausente | validação do job |
| fila baixa bloquear crítica | workers separados |
| memória esgotar | limites, eviction e alertas |
| produto “compatível” divergir | suíte de contrato |
| fallback sobrecarregar PostgreSQL | degradação controlada |
| job antigo após deploy | contratos versionados |
| segredo no payload | schema e sanitização |

---

# 46. Estratégia de implementação

1. aprovar este ADR;
2. escolher implementação compatível para prova;
3. definir baseline do cliente PHP;
4. separar conexões e namespaces;
5. implementar fila piloto;
6. implementar cache descartável;
7. provar lock atômico;
8. integrar dispatcher da outbox;
9. implementar inbox;
10. testar duplicidade;
11. testar indisponibilidade;
12. criar métricas e alertas;
13. documentar operação;
14. homologar persistência e restart.

---

# 47. Validação

A decisão será validada quando:

- Laravel publicar e consumir job;
- outbox republicar item perdido;
- duplicidade produzir um efeito;
- lock coordenar dois workers;
- lock expirado ser recuperado;
- cache perdido ser reconstruído;
- fila crítica manter capacidade;
- contexto não vazar entre jobs;
- serviço reiniciar sem perda silenciosa;
- indisponibilidade seguir degradação definida;
- métricas e alertas funcionarem;
- suíte rodar no pipeline.

---

# 48. Critérios de aceite

**CA-ADR-005-001:** PostgreSQL permanece fonte de verdade.

**CA-ADR-005-002:** serviço compatível com Redis é usado para fila, cache e locks.

**CA-ADR-005-003:** fornecedor não é fixado por este ADR.

**CA-ADR-005-004:** compatibilidade é comprovada antes da produção.

**CA-ADR-005-005:** outbox precede a publicação da fila.

**CA-ADR-005-006:** filas não substituem fatos persistidos.

**CA-ADR-005-007:** jobs possuem UUIDv7, implantação e correlação.

**CA-ADR-005-008:** jobs não serializam models ou segredos.

**CA-ADR-005-009:** filas críticas e pesadas possuem capacidade separável.

**CA-ADR-005-010:** entrega duplicada é tratada por idempotência.

**CA-ADR-005-011:** inbox é usada quando constraint do domínio não basta.

**CA-ADR-005-012:** mesma chave com payload divergente falha.

**CA-ADR-005-013:** retentativas possuem limite, backoff e jitter.

**CA-ADR-005-014:** falha permanente exige intervenção.

**CA-ADR-005-015:** locks possuem owner e TTL.

**CA-ADR-005-016:** locks não substituem constraints ou transações.

**CA-ADR-005-017:** cache é descartável.

**CA-ADR-005-018:** chaves de cache incluem ambiente e implantação.

**CA-ADR-005-019:** decisão crítica não depende de cache indefinido.

**CA-ADR-005-020:** falha do cache não produz dado presumido.

**CA-ADR-005-021:** indisponibilidade do lock falha fechada em operação crítica.

**CA-ADR-005-022:** não existe fallback automático para execução síncrona.

**CA-ADR-005-023:** workers limpam contexto entre jobs.

**CA-ADR-005-024:** filas, cache, locks e outbox possuem métricas.

**CA-ADR-005-025:** testes cobrem duplicidade, restart e indisponibilidade.

**CA-ADR-005-026:** acesso offline de equipamentos não é aprovado por este ADR.

---

# 49. Rastreabilidade

## 49.1 Documentos

- `docs/009_REGRAS_DE_NEGOCIO.md`;
- `docs/010_BANCO_DE_DADOS.md`;
- `docs/011_ARQUITETURA_DO_SISTEMA.md`;
- `docs/ADR/000_CATALOGO_DE_ADRS.md`;
- `docs/ADR/ADR-001_MONOLITO_MODULAR_LARAVEL.md`;
- `docs/ADR/ADR-002_MULTI_IMPLANTACAO_E_ISOLAMENTO.md`;
- `docs/ADR/ADR-003_IDENTIFICADORES_INTERNOS_E_PUBLICOS.md`;
- `docs/ADR/ADR-004_AUDITORIA_EVENTOS_E_OUTBOX.md`.

## 49.2 Regras

- `RN-012`, `RN-020` — expiração;
- `RN-055` — segregação;
- `RN-057` — transições;
- `RN-077` a `RN-080` — decisão e idempotência;
- `RN-084`, `RN-085` — caixa e idempotência financeira;
- `RN-088`, `RN-089` — falha e contingência;
- `RN-092`, `RN-093` — fila e sincronização;
- `RN-100` — concorrência e segredos.

---

# 50. Dependências

| ADR | Relação |
|---|---|
| ADR-001 | workers no monólito modular |
| ADR-002 | contexto por implantação |
| ADR-003 | UUIDv7 e chaves idempotentes |
| ADR-004 | outbox, auditoria e entrega |
| ADR-007 | filas de equipamentos |
| ADR-008 | cache operacional e contingência |
| ADR-009 | credenciais do serviço |
| ADR-010 | métricas e alertas |
| ADR-012 | topologia e restart |

---

# 51. Pendências

| PEN-ADR-005 | Pendência | Tratamento |
|---|---|---|
| PEN-ADR-005-001 | Produto/distribuição compatível com Redis | infraestrutura e homologação |
| PEN-ADR-005-002 | Cliente PHP e baseline | Manual do Desenvolvedor |
| PEN-ADR-005-003 | Persistência e alta disponibilidade | ADR-012 |
| PEN-ADR-005-004 | SLO e capacidade por fila | testes de carga |
| PEN-ADR-005-005 | Política final de sessões | segurança |
| PEN-ADR-005-006 | Retenção por chave idempotente | regras por módulo |
| PEN-ADR-005-007 | Interface de jobs em intervenção | Administração |
| PEN-ADR-005-008 | Ferramenta de monitoramento de filas | ADR-010 |
| PEN-ADR-005-009 | Valores de timeout, backoff e tentativas | contratos de integração |

---

# 52. Aprovação

| Papel | Nome | Decisão | Data | Observações |
|---|---|---|---|---|
| Product Owner | Vinicius Velasco de Azevedo | Pendente | — | Aguardando decisão |
| Responsável técnico | Soluções do Vale Tecnologia | Recomendado | Julho/2026 | Serviço compatível com Redis e PostgreSQL para registros duráveis |

---

# 53. Decisão resultante

Enquanto este ADR estiver **Proposto**, a alternativa compatível com Redis permanece recomendada, mas a infraestrutura assíncrona continua bloqueada.

Se aprovado:

- o catálogo será atualizado no mesmo commit;
- filas, cache e locks usarão a abstração aprovada;
- PostgreSQL manterá outbox, inbox e idempotência durável;
- implementação compatível será homologada;
- o Manual do Desenvolvedor detalhará jobs e testes;
- ADR-012 definirá topologia e continuidade.

---

## Situação do ADR

**Proposto.** Aguardando aprovação formal do Product Owner.
