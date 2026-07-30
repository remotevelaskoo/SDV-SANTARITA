# ADR-008 — CONTINGÊNCIA E CACHE OPERACIONAL

**Identificador:** ADR-008
**Versão:** 1.0.0
**Status:** Adiado
**Prioridade:** P1 — Obrigatório antes do go-live
**Produto:** SDV Access — Implantação Santa Rita
**Responsável pelo produto:** Vinicius Velasco de Azevedo
**Data:** 30/07/2026

---

## Controle de versões

| Versão | Data | Responsável | Alteração |
|---|---|---|---|
| 1.0.0 | 30/07/2026 | Product Owner | Registro e aprovação do adiamento condicionado ao inventário técnico |

# 1. Contexto

A portaria precisa responder a indisponibilidade de internet, aplicação, fila, controladora, leitor facial ou LPR. Alguns equipamentos podem manter cache local; outros dependem de comando online.

# 2. Problema

Não é possível definir operação offline segura sem conhecer equipamentos, autonomia, sincronização, revogação e capacidade de registrar eventos.

# 3. Decisão

**Adiar a escolha da estratégia de cache operacional e offline** até concluir inventário e prova técnica.

Enquanto adiado:

- não autorizar offline automático;
- não distribuir listas completas sem política;
- manter contingência manual autorizada;
- registrar atendimento, justificativa e resultado conhecido;
- reconciliar posteriormente;
- exibir degradação de forma explícita.

# 4. Alternativas a validar

| Alternativa | Condição |
|---|---|
| operação somente online | equipamento e conectividade suficientes |
| cache limitado por credencial | revogação, TTL e criptografia comprovados |
| controlador local | agente e sincronização aprovados |
| contingência manual | procedimento físico e permissões definidos |

# 5. Requisitos para retomar

- fabricante, modelo e firmware;
- topologia de rede;
- capacidade de cache;
- limite de registros;
- criptografia;
- eventos offline;
- revogação;
- relógio;
- sincronização;
- comportamento após retorno;
- SLA de conectividade;
- procedimento da portaria.

# 6. Princípios invariáveis

- cache não cria autorização;
- autorização expirada não é renovada offline;
- bloqueio crítico deve propagar no menor tempo aprovado;
- decisão local deve ser explicável;
- evento offline deve ser reconciliado;
- segredo não ficará exposto no equipamento;
- perda do equipamento deve ter resposta;
- contingência exige menor privilégio.

# 7. Contingência manual provisória

- operador autorizado;
- ponto de acesso;
- pessoa/veículo identificado;
- motivo estruturado;
- justificativa;
- instante;
- direção;
- resultado conhecido;
- correlação posterior;
- auditoria.

Liberação física sem confirmação será registrada como resultado desconhecido.

# 8. Dados proibidos no cache sem decisão

- documentos completos;
- selfies;
- templates biométricos;
- tokens de convite;
- observações sensíveis;
- credenciais administrativas;
- histórico amplo de acessos.

# 9. Revogação

A futura estratégia deverá definir:

- bloqueio emergencial;
- janela máxima de propagação;
- lista de revogação;
- expiração local;
- equipamento desconectado;
- conflito após reconexão;
- prova de remoção.

# 10. Reconciliação

Após retorno:

1. identificar período degradado;
2. coletar eventos;
3. deduplicar;
4. preservar horário original;
5. comparar comandos e confirmações;
6. registrar divergências;
7. exigir intervenção;
8. auditar conclusão.

# 11. Segurança

- dados mínimos;
- criptografia;
- identidade do equipamento;
- assinatura ou autenticidade;
- antirreplay;
- rotação;
- wipe/revogação;
- acesso físico considerado;
- logs sanitizados.

# 12. Testes exigidos

- queda de internet;
- queda da aplicação;
- queda do equipamento;
- relógio incorreto;
- credencial expirada;
- pessoa bloqueada;
- evento duplicado;
- equipamento perdido;
- retorno parcial;
- divergência de estado;
- reconciliação.

# 13. Consequências

Positivas:

- evita decisão insegura;
- preserva escopo;
- torna bloqueadores explícitos.

Negativas:

- go-live automatizado depende do inventário;
- contingência provisória é manual;
- disponibilidade offline não é garantida.

# 14. Riscos

| Risco | Mitigação |
|---|---|
| adiamento ser tratado como aprovação | status e proibições explícitas |
| operação manual sem auditoria | fluxo obrigatório |
| cache futuro desatualizado | TTL e revogação |
| duplicidade após retorno | idempotência |
| evento offline perdido | armazenamento e reconciliação homologados |

# 15. Critérios de aceite

**CA-ADR-008-001:** acesso offline automático não está aprovado.
**CA-ADR-008-002:** cache não cria autorização.
**CA-ADR-008-003:** contingência manual exige permissão e justificativa.
**CA-ADR-008-004:** resultado desconhecido não vira sucesso.
**CA-ADR-008-005:** dados sensíveis não são distribuídos sem decisão.
**CA-ADR-008-006:** inventário é bloqueador.
**CA-ADR-008-007:** revogação e TTL serão homologados.
**CA-ADR-008-008:** eventos offline serão reconciliados.
**CA-ADR-008-009:** testes de falha são obrigatórios.
**CA-ADR-008-010:** retomada exige nova versão deste ADR.

# 16. Rastreabilidade

- `RN-040`, `RN-077` a `RN-080`, `RN-088`, `RN-089`;
- ADR-005 e ADR-007;
- `PEN-ARQ-001`, `PEN-ARQ-002`.

# 17. Pendências

| PEN-ADR-008 | Pendência |
|---|---|
| PEN-ADR-008-001 | Inventário de equipamentos |
| PEN-ADR-008-002 | Topologia e SLA de rede |
| PEN-ADR-008-003 | Capacidades offline |
| PEN-ADR-008-004 | Procedimento físico de contingência |
| PEN-ADR-008-005 | Política de revogação |

# 18. Aprovação

| Papel | Nome | Decisão | Data |
|---|---|---|---|
| Product Owner | Vinicius Velasco de Azevedo | Aprovado o adiamento | 30/07/2026 |
| Responsável técnico | Soluções do Vale Tecnologia | Recomendado | 30/07/2026 |

# 19. Condição de retomada

Este ADR voltará a **Proposto** após inventário técnico e prova com hardware.

# 20. Decisão resultante

Nenhuma operação offline automática está autorizada. A contingência provisória é manual, limitada, auditável e reconciliável.

## Situação do ADR

**Adiado com aprovação formal.**
