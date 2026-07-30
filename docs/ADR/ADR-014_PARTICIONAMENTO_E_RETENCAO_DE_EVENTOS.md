# ADR-014 — PARTICIONAMENTO E RETENÇÃO DE EVENTOS

**Identificador:** ADR-014
**Versão:** 1.0.0
**Status:** Adiado
**Prioridade:** P2 — Condicional
**Produto:** SDV Access — Implantação Santa Rita
**Responsável pelo produto:** Vinicius Velasco de Azevedo
**Data:** 30/07/2026

---

## Controle de versões

| Versão | Data | Responsável | Alteração |
|---|---|---|---|
| 1.0.0 | 30/07/2026 | Product Owner | Registro e aprovação do adiamento de particionamento e retenção |

# 1. Contexto

Eventos de acesso, auditoria, outbox, integrações e evidências podem crescer continuamente. PostgreSQL suporta particionamento, mas ainda não existem volume, distribuição, consultas e retenção medidos.

# 2. Problema

Particionar cedo aumenta migrations e operação. Não particionar quando necessário pode degradar consultas, índices, backup e limpeza. Retenção não pode ser definida apenas por conveniência técnica.

# 3. Decisão

**Adiar o particionamento físico e o descarte automático de eventos.**

Enquanto adiado:

- usar tabelas normais bem indexadas;
- medir volume e consultas;
- não apagar auditoria ou eventos de negócio;
- limpar somente dados técnicos temporários com política aprovada;
- monitorar crescimento;
- preparar chaves temporais e de implantação.

# 4. Condições de retomada

- volume diário/mensal;
- tamanho de tabelas e índices;
- latência real;
- consultas;
- janela de manutenção;
- RPO/RTO;
- política de retenção;
- exigência jurídica;
- estratégia de archive;
- teste de partition pruning;
- impacto em FKs e ORM.

# 5. Alternativas futuras

| Alternativa | Uso possível |
|---|---|
| sem particionamento | volume controlado |
| partição temporal | eventos por período |
| partição por implantação | poucos tenants muito grandes |
| temporal + subpartição | escala comprovada |
| archive externo | histórico frio |

Partição por implantação não será escolhida apenas por multi-tenancy.

# 6. Tabelas candidatas

- eventos de acesso;
- auditoria;
- outbox processada;
- inbox;
- ocorrências de integração;
- capturas LPR;
- logs persistidos autorizados;
- movimentos de alta frequência.

Cadastros centrais não são candidatos iniciais.

# 7. Chave de partição

Preferência futura:

- timestamp de registro/ocorrência confiável;
- período previsível;
- compatibilidade com consultas;
- UTC;
- não usar UUIDv7 como substituto do timestamp;
- implantação em índices locais.

# 8. Retenção

Cada categoria deverá definir:

- finalidade;
- início da contagem;
- prazo;
- fundamento;
- responsável;
- legal hold;
- anonimização;
- descarte;
- evidência;
- backup.

Sem política, não há descarte automático.

# 9. Auditoria

- somente anexada;
- partição não reduz imutabilidade;
- drop de partição é operação destrutiva;
- exige autorização;
- verifica hold;
- registra contagem, período e resultado;
- backup/arquivo conforme política.

# 10. Eventos de acesso

- preservar fato;
- distinguir entrada, saída, negativa e falha;
- retenção não pode quebrar relatórios obrigatórios;
- evidência LPR possui política própria;
- anonimização poderá preservar agregado permitido.

# 11. Outbox e inbox

- pendentes nunca removidos;
- intervenção nunca ocultada;
- processados seguem janela operacional;
- idempotência permanece além da janela de duplicidade;
- limpeza reconciliada;
- particionamento não altera garantia.

# 12. Backup e archive

- partição não é backup;
- archive deve ser verificável;
- restauração testada;
- criptografia;
- catálogo de períodos;
- acesso restrito;
- compatibilidade com investigação;
- descarte também alcança archive quando exigido.

# 13. Operação

Se adotado futuramente:

- criar partições antecipadamente;
- monitorar partição default;
- automatizar com segurança;
- testar índices;
- validar migrations;
- impedir escrita fora do intervalo;
- documentar detach/archive/drop;
- observar vacuum e locks.

# 14. Testes futuros

- pruning;
- inserts de fronteira;
- fuso;
- partição ausente;
- migration;
- consulta entre períodos;
- hold;
- archive;
- restore;
- descarte;
- concorrência;
- ORM.

# 15. Consequências

Positivas:

- evita complexidade prematura;
- decisão baseada em dados;
- impede descarte sem política.

Negativas:

- tabelas crescerão até nova avaliação;
- limpeza não será instantânea;
- métricas são obrigatórias.

# 16. Riscos

| Risco | Mitigação |
|---|---|
| crescimento não percebido | métricas e alertas |
| particionamento tardio | limiares de retomada |
| drop apagar dado protegido | hold e aprovação |
| política técnica conflitar com jurídica | ownership explícito |
| archive inacessível | teste de restauração |

# 17. Critérios de aceite

**CA-ADR-014-001:** particionamento não está autorizado agora.
**CA-ADR-014-002:** tabelas usam índices e métricas.
**CA-ADR-014-003:** UUIDv7 não substitui timestamp.
**CA-ADR-014-004:** descarte automático depende de política.
**CA-ADR-014-005:** pendentes não são removidos.
**CA-ADR-014-006:** legal hold bloqueia descarte.
**CA-ADR-014-007:** partição não substitui backup.
**CA-ADR-014-008:** retomada usa volume medido.
**CA-ADR-014-009:** archive possui restauração testada.
**CA-ADR-014-010:** adoção exige nova versão deste ADR.

# 18. Rastreabilidade

- `RN-041`, `RN-046` a `RN-049`, `RN-066`, `RN-086`;
- ADR-004, ADR-006, ADR-010 e ADR-012;
- `PEN-BDD-021`, `PEN-BDD-025`, `PEN-ARQ-017`.

# 19. Pendências

| PEN-ADR-014 | Pendência |
|---|---|
| PEN-ADR-014-001 | Política de retenção |
| PEN-ADR-014-002 | Volume e crescimento |
| PEN-ADR-014-003 | Consultas e metas |
| PEN-ADR-014-004 | Estratégia de archive |
| PEN-ADR-014-005 | RPO/RTO |
| PEN-ADR-014-006 | Procedimento de descarte |

# 20. Aprovação

| Papel | Nome | Decisão | Data |
|---|---|---|---|
| Product Owner | Vinicius Velasco de Azevedo | Aprovado o adiamento | 30/07/2026 |
| Responsável técnico | Soluções do Vale Tecnologia | Recomendado | 30/07/2026 |

# 21. Condição de retomada

Voltará a **Proposto** após política de retenção e evidência de volume/desempenho.

# 22. Decisão resultante

O MVP inicia sem particionamento físico e sem descarte automático de eventos de negócio.

## Situação do ADR

**Adiado com aprovação formal.**
