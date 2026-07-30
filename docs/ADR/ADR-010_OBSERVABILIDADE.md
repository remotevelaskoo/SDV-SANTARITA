# ADR-010 — OBSERVABILIDADE

**Identificador:** ADR-010
**Versão:** 1.0.0
**Status:** Aprovado
**Prioridade:** P1 — Obrigatório do MVP
**Produto:** SDV Access — Implantação Santa Rita
**Responsável pelo produto:** Vinicius Velasco de Azevedo
**Data:** 30/07/2026

---

## Controle de versões

| Versão | Data | Responsável | Alteração |
|---|---|---|---|
| 1.0.0 | 30/07/2026 | Product Owner | Criação e aprovação da estratégia de observabilidade |

# 1. Contexto

A aplicação web, workers, scheduler, PostgreSQL, filas, S3 e equipamentos formarão fluxos distribuídos. Falhas precisam ser detectadas e correlacionadas sem depender de acesso direto ao banco ou exposição de dados.

# 2. Problema

Definir logs, métricas, traces, saúde, alertas, correlação e responsabilidades operacionais de forma independente de fornecedor.

# 3. Decisão

Adotar:

- logs estruturados;
- métricas técnicas e operacionais;
- traces distribuídos quando úteis;
- correlação UUIDv7;
- contexto de causação;
- health checks;
- dashboards;
- alertas acionáveis;
- padrões abertos e backend substituível;
- sanitização e controle de cardinalidade;
- separação entre telemetria e auditoria.

# 4. Pilares

| Pilar | Finalidade |
|---|---|
| logs | detalhes de eventos técnicos |
| métricas | tendências, taxas e limites |
| traces | percurso e latência entre etapas |
| auditoria | evidência de operação relevante |

Auditoria não será reconstruída apenas a partir de logs.

# 5. Correlação

```text
requisição
 → caso de uso
 → transação
 → outbox
 → fila
 → worker
 → adaptador
 → callback
```

Todos os estágios preservarão `correlation_id`; eventos derivados poderão usar `causation_id`.

# 6. Logs estruturados

Campos mínimos:

- timestamp UTC;
- nível;
- ambiente;
- serviço/processo;
- módulo;
- operação;
- correlação;
- implantação opaca quando permitido;
- resultado;
- duração;
- classe do erro;
- versão da aplicação.

# 7. Proibições em logs

- senha;
- token;
- segredo;
- URL pré-assinada;
- documento completo;
- template biométrico;
- imagem;
- payload integral;
- query com dado sensível;
- stack trace para o usuário.

# 8. Níveis

- debug: somente ambiente controlado;
- info: eventos técnicos normais relevantes;
- warning: degradação recuperável;
- error: falha que exige análise;
- critical: risco de indisponibilidade, integridade ou segurança.

Produção não manterá debug irrestrito.

# 9. Métricas

Categorias:

- HTTP;
- banco;
- filas e outbox;
- cache e locks;
- S3;
- equipamentos;
- autenticação;
- jobs;
- scheduler;
- exports;
- arquivos;
- recursos de infraestrutura.

# 10. Cardinalidade

Não usar como labels:

- pessoa;
- CPF;
- placa;
- UUID de entidade;
- protocolo;
- correlação;
- mensagem de erro livre.

Implantação usará identificador opaco somente quando necessário e controlado.

# 11. Traces

- amostragem configurável;
- propagação de contexto;
- spans por fronteira relevante;
- atributos sanitizados;
- erros registrados sem segredo;
- nenhuma garantia de auditoria;
- custo e volume monitorados.

# 12. Health checks

Separar:

- **liveness:** processo responde;
- **readiness:** pode receber trabalho;
- **dependency:** estado de banco, fila, S3 e integrações;
- **business health:** filas, outbox e comandos envelhecidos.

Endpoint público não revelará detalhes internos.

# 13. SLI e SLO

Indicadores mínimos:

- disponibilidade web;
- latência de decisão de acesso;
- taxa de erro;
- idade da outbox;
- tempo de fila;
- confirmação de equipamento;
- processamento de arquivos;
- restauração;
- sucesso de jobs críticos.

Metas numéricas permanecem pendentes de homologação.

# 14. Alertas

Um alerta deverá:

- indicar impacto;
- possuir severidade;
- identificar responsável;
- incluir runbook;
- evitar dado pessoal;
- permitir correlação;
- reduzir duplicidade;
- ter condição de resolução.

# 15. Alertas mínimos

- aplicação indisponível;
- banco indisponível;
- fila crítica envelhecida;
- worker ausente;
- outbox parada;
- S3 indisponível;
- equipamento offline;
- timeouts elevados;
- falha de auditoria;
- espaço/capacidade;
- erro de autenticação anormal;
- backup ou restauração falha.

# 16. Segurança

Eventos de segurança:

- login repetido;
- acesso negado anormal;
- mudança de permissão;
- break-glass;
- segredo vencendo;
- callback inválido;
- tentativa entre implantações.

Telemetria de segurança terá acesso restrito e retenção própria.

# 17. Retenção

- logs por classe;
- métricas agregadas;
- traces com amostragem;
- auditoria conforme política própria;
- descarte controlado;
- custo monitorado;
- legal hold quando aplicável.

# 18. Ambientes

- tags claras;
- backends ou índices separados;
- produção com acesso restrito;
- homologação sem dados reais indevidos;
- desenvolvimento com saída legível;
- nenhuma mistura de alertas.

# 19. Instrumentação

- biblioteca central;
- contexto automático quando seguro;
- métricas de domínio explicitamente definidas;
- nenhuma instrumentação escondendo regra;
- falha da telemetria não corrompe negócio;
- buffer e backpressure limitados.

# 20. Operação

- dashboards por jornada;
- runbooks versionados;
- plantão/responsáveis definidos;
- revisão de alertas;
- pós-incidente;
- testes de alerta;
- acesso auditado.

# 21. Testes

- correlação ponta a ponta;
- sanitização;
- health checks;
- alerta simulado;
- dependência lenta;
- fila acumulada;
- equipamento offline;
- cardinalidade;
- backend de telemetria indisponível.

# 22. Consequências e riscos

Positivas:

- diagnóstico rápido;
- falhas visíveis;
- SLO mensurável;
- fornecedor substituível.

Riscos:

| Risco | Mitigação |
|---|---|
| custo alto | amostragem e retenção |
| dado sensível | sanitização |
| alerta excessivo | revisão e severidade |
| alta cardinalidade | allowlist de labels |
| telemetria bloquear aplicação | export assíncrono e limites |

# 23. Critérios de aceite

**CA-ADR-010-001:** logs são estruturados.
**CA-ADR-010-002:** correlação atravessa filas e integrações.
**CA-ADR-010-003:** auditoria é separada.
**CA-ADR-010-004:** segredos não entram em telemetria.
**CA-ADR-010-005:** labels evitam alta cardinalidade.
**CA-ADR-010-006:** existem liveness e readiness.
**CA-ADR-010-007:** dependências possuem saúde.
**CA-ADR-010-008:** alertas têm responsável e runbook.
**CA-ADR-010-009:** filas e outbox são monitoradas.
**CA-ADR-010-010:** equipamentos são observáveis.
**CA-ADR-010-011:** SLI possuem definição.
**CA-ADR-010-012:** metas serão homologadas.
**CA-ADR-010-013:** backend permanece substituível.
**CA-ADR-010-014:** falha de telemetria não corrompe negócio.

# 24. Pendências

| PEN-ADR-010 | Pendência |
|---|---|
| PEN-ADR-010-001 | Backend de observabilidade |
| PEN-ADR-010-002 | Metas SLO |
| PEN-ADR-010-003 | Retenção |
| PEN-ADR-010-004 | Responsáveis e escala |
| PEN-ADR-010-005 | Runbooks |
| PEN-ADR-010-006 | Política de amostragem |

# 25. Aprovação

| Papel | Nome | Decisão | Data |
|---|---|---|---|
| Product Owner | Vinicius Velasco de Azevedo | Aprovado | 30/07/2026 |
| Responsável técnico | Soluções do Vale Tecnologia | Recomendado | 30/07/2026 |

## Situação do ADR

**Aprovado.** Logs, métricas, traces, saúde e alertas vendor-neutral constituem a estratégia vigente.
