# SDV ACCESS — CATÁLOGO DE ADRs
## Registro e governança de decisões arquiteturais

**Documento:** SDV-ADR-CAT-000
**Versão:** 1.0.4
**Status:** Aprovado
**Produto:** SDV Access — Implantação Santa Rita
**Empresa proprietária:** Soluções do Vale Tecnologia
**Responsável pelo produto:** Vinicius Velasco de Azevedo
**Data:** Julho de 2026

---

## Controle de versões

| Versão | Data | Responsável | Alteração |
|---|---|---|---|
| 1.0.0 | Julho/2026 | Soluções do Vale | Criação do catálogo e unificação dos ADRs propostos |
| 1.0.1 | 28/07/2026 | Product Owner | Aprovação formal do catálogo e da governança de ADRs |
| 1.0.2 | 28/07/2026 | Product Owner | Registro da aprovação do ADR-001 |
| 1.0.3 | 28/07/2026 | Soluções do Vale | Vinculação da proposta do ADR-002 ao catálogo |
| 1.0.4 | 28/07/2026 | Product Owner | Registro da aprovação do ADR-002 |

---

# 1. Objetivo

Este documento estabelece o catálogo oficial e a governança dos Architecture Decision Records — ADRs do SDV Access.

Seus objetivos são:

- atribuir identificadores permanentes às decisões arquiteturais;
- registrar contexto, decisão, alternativas e consequências;
- impedir mudanças estruturais silenciosas;
- consolidar os ADRs propostos nos documentos de banco de dados e arquitetura;
- definir prioridade e dependências;
- manter rastreabilidade com regras, requisitos e pendências;
- orientar a ordem de produção e aprovação dos ADRs;
- preservar decisões substituídas sem apagar o histórico.

Este catálogo não substitui o conteúdo individual de cada ADR.

---

# 2. Fontes

O catálogo deriva de:

- `docs/000_DIRETRIZES_DO_PROJETO.md`;
- `docs/001_VOLUME_01_PRODUCT_BOOK_PARTE_03.md`;
- `docs/009_REGRAS_DE_NEGOCIO.md`;
- `docs/010_BANCO_DE_DADOS.md`;
- `docs/011_ARQUITETURA_DO_SISTEMA.md`.

Os identificadores provisórios registrados nos documentos 010 e 011 são normalizados por este catálogo. A rastreabilidade temática é preservada mesmo quando a numeração original muda.

---

# 3. Definição de ADR

Um ADR é um registro permanente de uma decisão estrutural relevante, incluindo:

- problema e contexto;
- forças e restrições;
- alternativas consideradas;
- decisão;
- justificativa;
- consequências positivas e negativas;
- riscos;
- implicações de segurança e operação;
- estratégia de adoção;
- evidências de validação;
- relações com outros ADRs;
- estado e histórico.

ADRs explicam **por que** uma escolha foi feita. Manuais e especificações detalham **como** implementá-la.

---

# 4. Quando um ADR é obrigatório

Deverá ser criado ou atualizado um ADR quando a decisão:

- alterar tecnologia aprovada;
- definir estilo arquitetural;
- modificar fronteiras de módulos;
- definir segregação entre implantações;
- afetar segurança, privacidade ou segredos;
- definir persistência, filas, cache ou armazenamento;
- introduzir serviço externo;
- definir contrato de integração estrutural;
- alterar estratégia de deploy, continuidade ou observabilidade;
- criar dependência difícil de substituir;
- possuir custo significativo de reversão;
- contrariar ou substituir decisão anterior;
- resolver pendência marcada como arquitetural.

Mudanças locais, reversíveis e sem impacto estrutural poderão ser registradas em documentação técnica ou no código, sem ADR próprio.

---

# 5. Estados

| Estado | Significado |
|---|---|
| Proposto | conteúdo em elaboração ou revisão |
| Aprovado | decisão vigente e autorizada |
| Rejeitado | alternativa analisada e não adotada |
| Adiado | decisão necessária, mas sem condição de conclusão |
| Substituído | decisão histórica trocada por outro ADR |
| Obsoleto | contexto deixou de existir sem substituição direta |

## 5.1 Transições

```text
Proposto → Aprovado
Proposto → Rejeitado
Proposto → Adiado
Adiado → Proposto
Aprovado → Substituído
Aprovado → Obsoleto
```

Um ADR aprovado não será editado para inverter sua decisão. A mudança deverá criar novo ADR e marcar o anterior como substituído.

---

# 6. Identificação e arquivos

## 6.1 Padrão

- identificador: `ADR-NNN`;
- arquivo: `docs/ADR/ADR-NNN_TITULO_EM_SNAKE_CASE.md`;
- numeração com três dígitos;
- números não serão reutilizados;
- título curto e orientado à decisão;
- referências usarão o identificador, não somente o nome do arquivo.

## 6.2 Catálogo

Este arquivo usa o identificador administrativo `SDV-ADR-CAT-000` e não ocupa a sequência de decisões.

---

# 7. Estrutura obrigatória de cada ADR

Cada ADR deverá conter:

1. identificação e status;
2. data e responsáveis;
3. contexto;
4. problema;
5. forças e restrições;
6. alternativas;
7. decisão proposta;
8. justificativa;
9. consequências positivas;
10. consequências negativas;
11. riscos e mitigações;
12. segurança e privacidade;
13. impacto operacional;
14. estratégia de implementação;
15. validação;
16. rastreabilidade;
17. dependências;
18. pendências;
19. aprovação;
20. histórico.

Itens não aplicáveis deverão ser marcados explicitamente, não omitidos sem justificativa.

---

# 8. Papéis e responsabilidades

| Papel | Responsabilidade |
|---|---|
| Product Owner | aprovar impacto de produto e prioridade |
| Responsável técnico | propor, analisar e manter a decisão |
| Segurança/privacidade | revisar decisões com dados ou riscos relevantes |
| Operação | validar implantação, monitoramento e continuidade |
| Desenvolvimento | avaliar implementabilidade e testes |
| Homologação | validar critérios e evidências |

Uma mesma pessoa poderá exercer mais de um papel no início, mas os papéis deverão permanecer registrados separadamente.

---

# 9. Processo de decisão

1. identificar a necessidade;
2. abrir ADR como Proposto;
3. vincular requisitos, regras e pendências;
4. levantar alternativas reais;
5. avaliar consequências e reversibilidade;
6. executar prova técnica quando necessária;
7. revisar segurança, dados e operação;
8. registrar recomendação;
9. obter aprovação;
10. implementar de forma rastreável;
11. validar evidências;
12. atualizar documentos dependentes;
13. monitorar consequências.

Urgência operacional não autoriza apagar etapas; decisões emergenciais deverão ser registradas retrospectivamente assim que o serviço estiver estabilizado.

---

# 10. Critérios de priorização

| Prioridade | Definição |
|---|---|
| P0 — Bloqueador | necessário antes da estrutura inicial ou de operação crítica |
| P1 — Obrigatório do MVP | necessário antes do módulo relacionado |
| P2 — Condicional | necessário somente se a capacidade for ativada |
| P3 — Evolução | decisão futura sem bloquear o MVP |

## 10.1 Ordem dentro da prioridade

1. segurança e segregação;
2. integridade e dados;
3. estrutura de código;
4. integrações;
5. implantação e continuidade;
6. otimizações condicionais.

---

# 11. Catálogo oficial

| ADR | Título | Prioridade | Estado | Documento de origem | Bloqueia |
|---|---|---:|---|---|---|
| [ADR-001](ADR-001_MONOLITO_MODULAR_LARAVEL.md) | Monólito modular Laravel | P0 | Aprovado | 011 | estrutura do código |
| [ADR-002](ADR-002_MULTI_IMPLANTACAO_E_ISOLAMENTO.md) | Multi-implantação e isolamento | P0 | Aprovado | 010, 011 | banco, autenticação e cache |
| ADR-003 | Identificadores internos e públicos | P0 | Proposto | 010, 011 | migrations e APIs |
| ADR-004 | Auditoria, eventos e outbox | P0 | Proposto | 010, 011 | fluxos críticos e filas |
| ADR-005 | Filas, cache, locks e idempotência | P0 | Proposto | 010, 011 | processamento assíncrono |
| ADR-006 | Armazenamento S3 e ciclo de vida de arquivos | P1 | Proposto | 010, 011 | uploads e evidências |
| ADR-007 | Portas e adaptadores de equipamentos | P0 | Proposto | 010, 011 | integração Santa Rita |
| ADR-008 | Contingência e cache operacional | P1 | Adiado | 011 | homologação e go-live |
| ADR-009 | Gestão e rotação de segredos | P0 | Proposto | 010, 011 | integrações e produção |
| ADR-010 | Observabilidade | P1 | Proposto | 011 | homologação integrada |
| ADR-011 | Python/FastAPI para OCR ou IA | P2 | Adiado | 010, 011 | somente capacidade de OCR/IA |
| ADR-012 | Estratégia de deploy e rollback | P1 | Proposto | 011 | primeira implantação |
| ADR-013 | Biometria e referências externas | P2 | Adiado | 010 | reconhecimento facial |
| ADR-014 | Particionamento e retenção de eventos | P2 | Adiado | 010, 011 | escala futura |

O estado `Adiado` indica dependência de informação, demanda ou volume ainda não confirmados; não representa aprovação da solução.

---

# 12. Normalização dos identificadores provisórios

## 12.1 Documento 010 — Banco de Dados

| Referência provisória | Catálogo oficial |
|---|---|
| ADR-001 — Multi-implantação | ADR-002 |
| ADR-002 — UUID e identificadores | ADR-003 |
| ADR-003 — Auditoria | ADR-004 |
| ADR-004 — S3 e arquivos | ADR-006 |
| ADR-005 — Integrações, filas e idempotência | ADR-005 e ADR-007 |
| ADR-006 — Segredos | ADR-009 |
| ADR-007 — Biometria | ADR-013 |
| ADR-008 — Particionamento | ADR-014 |

## 12.2 Documento 011 — Arquitetura

As referências `ADR-001` a `ADR-012` do documento 011 correspondem diretamente a `ADR-001` a `ADR-012` deste catálogo.

O desdobramento do tema provisório de integrações do documento 010 em `ADR-005` e `ADR-007` separa infraestrutura assíncrona de contrato com equipamentos.

---

# 13. Dependências entre ADRs

```text
ADR-001 Monólito modular
├── ADR-004 Auditoria e outbox
├── ADR-005 Filas, cache e locks
└── ADR-007 Portas e adaptadores

ADR-002 Multi-implantação
├── ADR-003 Identificadores
├── ADR-006 Arquivos
├── ADR-009 Segredos
└── ADR-010 Observabilidade

ADR-007 Portas e adaptadores
├── ADR-008 Contingência
└── ADR-013 Biometria

ADR-004 + ADR-005 + ADR-009
└── ADR-012 Deploy e rollback

ADR-011 OCR/IA e ADR-014 Particionamento
└── condicionais
```

Uma dependência não exige que todos os ADRs sejam aprovados na mesma data, mas impede implementar a decisão dependente com pressupostos não registrados.

---

# 14. ADRs bloqueadores da fundação técnica

Antes da estrutura inicial do sistema:

- `ADR-001` — Monólito modular Laravel;
- `ADR-002` — Multi-implantação e isolamento;
- `ADR-003` — Identificadores internos e públicos;
- `ADR-004` — Auditoria, eventos e outbox;
- `ADR-005` — Filas, cache, locks e idempotência;
- `ADR-009` — Gestão e rotação de segredos.

Antes da integração com equipamentos:

- `ADR-007` — Portas e adaptadores de equipamentos;
- `ADR-008` — Contingência e cache operacional, após inventário técnico.

Antes da produção:

- `ADR-006` — S3 e ciclo de vida de arquivos;
- `ADR-010` — Observabilidade;
- `ADR-012` — Deploy e rollback.

---

# 15. ADRs condicionais

## 15.1 OCR e IA

`ADR-011` somente será retomado se OCR ou IA integrarem uma entrega aprovada.

## 15.2 Biometria

`ADR-013` depende de finalidade, base legal, fabricante, formato, retenção, segurança e homologação.

## 15.3 Particionamento

`ADR-014` depende de volume, retenção, desempenho medido e estratégia de manutenção. Não haverá particionamento preventivo sem evidência.

---

# 16. Rastreabilidade com regras

| ADR | Regras principais |
|---|---|
| ADR-001 | `RN-056`, `RN-088` |
| ADR-002 | `RN-055`, `RN-064`, `RN-100` |
| ADR-003 | `RN-069`, `RN-090` |
| ADR-004 | `RN-046` a `RN-049`, `RN-057`, `RN-078` |
| ADR-005 | `RN-079`, `RN-085`, `RN-092`, `RN-093` |
| ADR-006 | `RN-028`, `RN-065`, `RN-066`, `RN-086` |
| ADR-007 | `RN-040`, `RN-077` a `RN-080`, `RN-090` a `RN-093` |
| ADR-008 | `RN-080`, `RN-088`, `RN-089` |
| ADR-009 | `RN-066`, `RN-100` |
| ADR-010 | `RN-041`, `RN-047`, `RN-086`, `RN-093` |
| ADR-011 | `RN-074`, `RN-075` |
| ADR-012 | `RN-088`, `RN-100` |
| ADR-013 | `RN-045`, `RN-065`, `RN-066`, `RN-075` |
| ADR-014 | `RN-041`, `RN-046` a `RN-049`, `RN-086` |

---

# 17. Rastreabilidade com pendências

| ADR | Pendências relacionadas |
|---|---|
| ADR-002 | `PEN-BDD-024`, `PEN-ARQ-016` |
| ADR-003 | `PEN-BDD-023` |
| ADR-005 | `PEN-ARQ-003` |
| ADR-006 | `PEN-RNG-020`, `PEN-BDD-007`, `PEN-BDD-021`, `PEN-BDD-027`, `PEN-ARQ-004`, `PEN-ARQ-008` |
| ADR-007 | `PEN-RNG-013`, `PEN-BDD-015`, `PEN-ARQ-001` |
| ADR-008 | `PEN-RNG-012`, `PEN-BDD-014`, `PEN-ARQ-002` |
| ADR-009 | `PEN-BDD-028`, `PEN-ARQ-005` |
| ADR-010 | `PEN-ARQ-014` |
| ADR-011 | `PEN-019` do Product Book, `PEN-ARQ-009` |
| ADR-012 | `PEN-ARQ-012`, `PEN-ARQ-013`, `PEN-ARQ-019`, `PEN-ARQ-020` |
| ADR-013 | `PEN-RNG-005`, `PEN-BDD-007`, `PEN-ARQ-008` |
| ADR-014 | `PEN-BDD-021`, `PEN-BDD-025`, `PEN-ARQ-017` |

---

# 18. Divergência de numeração documental

## 18.1 Contradição registrada

`docs/000_DIRETRIZES_DO_PROJETO.md` previu:

- `011_APIS.md`;
- `012_ARQUITETURA.md`.

Na evolução aprovada do projeto foi criado:

- `docs/011_ARQUITETURA_DO_SISTEMA.md`.

O documento 011 foi formalmente aprovado e não deverá ser renomeado ou renumerado silenciosamente.

## 18.2 Tratamento provisório

- preservar `docs/011_ARQUITETURA_DO_SISTEMA.md`;
- reservar o próximo número principal disponível para APIs;
- registrar a escolha definitiva em atualização controlada das diretrizes;
- atualizar referências dependentes;
- não criar dois documentos com o mesmo número.

## 18.3 Pendência

**PEN-ADR-CAT-001:** decidir se a especificação de APIs será `docs/012_APIS.md` e atualizar a lista oficial de documentos, preservando o histórico da previsão original.

---

# 19. Manutenção do catálogo

Ao criar ou alterar um ADR:

- atualizar a tabela do catálogo;
- registrar estado e data;
- revisar dependências;
- atualizar rastreabilidade;
- atualizar documentos afetados;
- manter o número reservado mesmo se rejeitado;
- indicar o ADR substituto quando aplicável;
- revisar pendências resolvidas;
- registrar evidências após implementação.

O catálogo deverá ser atualizado no mesmo commit do novo ADR ou da mudança de estado correspondente.

---

# 20. Revisão e aprovação

Um ADR será considerado pronto para decisão quando:

- problema estiver delimitado;
- alternativas forem reais e comparáveis;
- decisão for verificável;
- impactos estiverem registrados;
- riscos e segurança forem analisados;
- dependências estiverem identificadas;
- plano de adoção existir;
- critérios de validação forem objetivos;
- responsáveis estiverem definidos;
- contradições estiverem registradas.

A aprovação de um ADR não aprova automaticamente orçamento, fornecedor, acesso externo ou tratamento de dados ainda pendente.

---

# 21. Critérios de aceite do catálogo

**CA-ADR-CAT-001:** o catálogo possui identificador e regra de numeração.
**CA-ADR-CAT-002:** estados e transições estão definidos.
**CA-ADR-CAT-003:** ADR aprovado não é reescrito para inverter decisão.
**CA-ADR-CAT-004:** os ADRs propostos nos documentos 010 e 011 estão normalizados.
**CA-ADR-CAT-005:** não existem identificadores oficiais duplicados.
**CA-ADR-CAT-006:** prioridades P0 a P3 possuem significado.
**CA-ADR-CAT-007:** ADRs bloqueadores estão identificados.
**CA-ADR-CAT-008:** ADRs condicionais não são tratados como aprovados.
**CA-ADR-CAT-009:** dependências entre ADRs estão registradas.
**CA-ADR-CAT-010:** regras e pendências possuem rastreabilidade.
**CA-ADR-CAT-011:** a divergência de numeração documental está explícita.
**CA-ADR-CAT-012:** o documento aprovado 011 não é alterado silenciosamente.
**CA-ADR-CAT-013:** o catálogo orienta atualização dos documentos dependentes.
**CA-ADR-CAT-014:** decisões estruturais futuras deverão passar por ADR.

---

# 22. Decisões consolidadas

Ficam propostas para aprovação:

- diretório oficial `docs/ADR/`;
- catálogo administrativo com número 000;
- decisões identificadas por `ADR-NNN`;
- sequência permanente e não reutilizável;
- estados Proposto, Aprovado, Rejeitado, Adiado, Substituído e Obsoleto;
- mudança de decisão aprovada por novo ADR;
- catálogo inicial de `ADR-001` a `ADR-014`;
- seis ADRs P0 para a fundação técnica;
- separação entre filas/idempotência e adaptadores de equipamentos;
- ADRs de OCR/IA, biometria e particionamento como condicionais;
- divergência dos documentos 011/012 registrada, não corrigida silenciosamente;
- atualização conjunta do catálogo e de cada ADR.

---

# 23. Aprovação

| Papel | Nome | Decisão | Data | Observações |
|---|---|---|---|---|
| Product Owner | Vinicius Velasco de Azevedo | Aprovado | 28/07/2026 | Catálogo, governança, sequência e normalização aprovados |
| Soluções do Vale | Representante designado | Ciente | 28/07/2026 | Aprovação registrada no repositório oficial |

---

# 24. Próximo ADR

Após a aprovação deste catálogo, deverá ser produzido:

**`docs/ADR/ADR-001_MONOLITO_MODULAR_LARAVEL.md`**

O ADR-001 deverá confirmar ou rejeitar formalmente o monólito modular proposto no documento de arquitetura antes da estrutura inicial do código.

---

## Situação do documento

Este catálogo encontra-se **aprovado**. Os ADRs listados mantêm seus estados Proposto ou Adiado e não são considerados aprovados apenas por constarem neste documento.
