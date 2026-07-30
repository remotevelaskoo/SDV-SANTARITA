# ADR-012 — ESTRATÉGIA DE DEPLOY E ROLLBACK

**Identificador:** ADR-012
**Versão:** 1.0.0
**Status:** Aprovado
**Prioridade:** P1 — Obrigatório antes da produção
**Produto:** SDV Access — Implantação Santa Rita
**Responsável pelo produto:** Vinicius Velasco de Azevedo
**Data:** 30/07/2026

---

## Controle de versões

| Versão | Data | Responsável | Alteração |
|---|---|---|---|
| 1.0.0 | 30/07/2026 | Product Owner | Criação e aprovação da estratégia de deploy e rollback |

# 1. Contexto

O SDV Access será implantado por Docker com aplicação web, workers, scheduler, PostgreSQL, serviço compatível com Redis, S3 e observabilidade.

# 2. Problema

Atualizações não podem corromper dados, executar jobs incompatíveis, expor segredos ou deixar a portaria sem caminho de recuperação.

# 3. Decisão

Adotar:

- imagens Docker imutáveis;
- build único promovido entre ambientes;
- versões fixadas;
- pipeline automatizado;
- aprovação para produção;
- backup e verificação prévia;
- migrations expandir–migrar–contrair;
- health checks e smoke tests;
- deploy coordenado de web e workers;
- roll-forward preferencial;
- rollback somente se compatível;
- registro e auditoria de cada implantação;
- topologia/provedor definidos posteriormente.

# 4. Alternativas rejeitadas

| Alternativa | Motivo |
|---|---|
| editar servidor manualmente | não reproduzível |
| build diferente por ambiente | artefato não homologado |
| `latest` sem digest | versão ambígua |
| migration destrutiva imediata | rollback inseguro |
| restaurar banco como rollback comum | perda de dados |
| deploy sem smoke test | falha tardia |

# 5. Artefato

- imagem identificada por versão e digest;
- dependências fixadas;
- sem segredo;
- usuário não privilegiado;
- metadata de commit;
- SBOM quando ferramenta definida;
- análise de vulnerabilidades;
- assinatura quando infraestrutura suportar.

# 6. Ambientes

```text
desenvolvimento → testes → homologação → produção
```

O mesmo artefato será promovido. Configurações e segredos variam por ambiente.

# 7. Pipeline

1. instalar dependências;
2. lint;
3. análise estática;
4. testes;
5. migrations em banco descartável;
6. análise de dependências;
7. build;
8. scan;
9. publicar imagem;
10. homologar;
11. aprovar;
12. implantar;
13. smoke test;
14. observar.

# 8. Migrations

Princípio:

```text
expandir → migrar dados → trocar aplicação → observar → contrair
```

- adicionar antes de remover;
- aceitar versões adjacentes durante janela;
- backfill idempotente;
- operações pesadas separadas;
- lock e duração avaliados;
- backup;
- nenhuma perda silenciosa.

# 9. Ordem de deploy

Dependerá da mudança, mas deverá registrar:

- migrations compatíveis;
- aplicação web;
- workers compatíveis;
- scheduler;
- configuração;
- invalidação de cache;
- smoke tests.

Workers antigos não processarão contrato incompatível.

# 10. Configuração

- ambiente externo à imagem;
- segredos conforme ADR-009;
- validação no startup;
- configuração versionada;
- falha segura;
- nenhum valor de produção no repositório;
- mudança crítica auditada.

# 11. Health checks

- liveness;
- readiness;
- PostgreSQL;
- filas/cache;
- S3;
- outbox;
- workers;
- scheduler;
- integrações sem bloquear readiness indevidamente.

# 12. Smoke tests

- login técnico controlado;
- health;
- consulta de implantação;
- leitura e escrita sintética permitida;
- enfileiramento;
- processamento de job;
- S3;
- auditoria;
- integração simulada.

Não usar dados pessoais reais.

# 13. Estratégia de liberação

A topologia poderá usar:

- rolling;
- blue/green;
- substituição controlada em ambiente pequeno.

A escolha depende da infraestrutura. Sempre haverá janela, plano e critérios de abortar.

# 14. Roll-forward

Preferido quando:

- dados novos já foram gravados;
- migration não é reversível;
- correção é pequena;
- voltar versão causaria incompatibilidade.

Correção terá nova imagem e rastreabilidade.

# 15. Rollback

Permitido quando:

- imagem anterior está disponível;
- schema continua compatível;
- jobs e contratos são compatíveis;
- não perde dados;
- teste foi executado;
- responsável autoriza.

Rollback não executará down migration destrutiva automaticamente.

# 16. Banco e restauração

Restauração de backup:

- é procedimento de desastre;
- exige avaliação de perda;
- considera RPO/RTO;
- reconcilia S3 e equipamentos;
- ocorre em ambiente controlado;
- é testada periodicamente;
- não é rollback rotineiro.

# 17. Filas

- pausar consumidores quando necessário;
- preservar outbox;
- drenar ou versionar jobs;
- controlar retry;
- não limpar fila como deploy;
- retomar e monitorar;
- reconciliar jobs antigos.

# 18. S3

- mudanças de chave são compatíveis;
- lifecycle não muda sem revisão;
- versionamento preservado;
- migrations de objetos são idempotentes;
- reconciliação com PostgreSQL;
- credenciais separadas.

# 19. Segredos

- injetados no runtime;
- rotação coordenada;
- imagem sem valor;
- pipeline com acesso mínimo;
- logs mascarados;
- rollback considera credencial revogada.

# 20. Segurança

- acesso de deploy individual;
- MFA quando disponível;
- aprovação;
- registro de artefato;
- segregação de função;
- ambiente protegido;
- assinatura/verificação futura;
- auditoria.

# 21. Observabilidade

Durante e após deploy:

- versão ativa;
- erros;
- latência;
- filas;
- outbox;
- conexões;
- recursos;
- falhas de equipamento;
- logs por versão;
- janela de observação.

# 22. Continuidade

- procedimento da portaria;
- contato responsável;
- contingência manual;
- backup;
- restauração;
- comunicação;
- critérios de abortar;
- pós-incidente.

ADR-008 continua bloqueador do go-live automatizado.

# 23. Consequências

Positivas:

- reproduzibilidade;
- artefato homologado;
- rollback consciente;
- menor risco de schema.

Negativas:

- migrations em etapas;
- armazenamento de imagens;
- pipeline e registro;
- disciplina operacional.

# 24. Riscos

| Risco | Mitigação |
|---|---|
| schema incompatível | expandir–migrar–contrair |
| worker antigo | contratos versionados |
| rollback perder dado | roll-forward e bloqueio |
| imagem adulterada | digest e scan |
| segredo em build | injeção runtime |
| deploy falhar em portaria | smoke, observação e contingência |

# 25. Testes

- build reproduzível;
- migration em cópia;
- versão adjacente;
- rollback da aplicação;
- roll-forward;
- worker antigo;
- restart;
- restore;
- S3;
- segredo rotacionado;
- falha de health;
- smoke.

# 26. Critérios de aceite

**CA-ADR-012-001:** imagens são imutáveis.
**CA-ADR-012-002:** mesmo artefato é promovido.
**CA-ADR-012-003:** segredos não entram na imagem.
**CA-ADR-012-004:** produção exige aprovação.
**CA-ADR-012-005:** migrations preservam versões adjacentes.
**CA-ADR-012-006:** down destrutivo não é automático.
**CA-ADR-012-007:** roll-forward é preferencial.
**CA-ADR-012-008:** rollback exige compatibilidade.
**CA-ADR-012-009:** restore não é rollback comum.
**CA-ADR-012-010:** web e workers são coordenados.
**CA-ADR-012-011:** existem health e smoke tests.
**CA-ADR-012-012:** versão ativa é observável.
**CA-ADR-012-013:** filas não são apagadas.
**CA-ADR-012-014:** deploy é auditado.
**CA-ADR-012-015:** topologia permanece desacoplada.

# 27. Rastreabilidade

- `RNF-006`, `RNF-011`, `HOM-022`;
- ADR-004 a ADR-010;
- `PEN-ARQ-012`, `013`, `019`, `020`.

# 28. Pendências

| PEN-ADR-012 | Pendência |
|---|---|
| PEN-ADR-012-001 | Provedor e topologia |
| PEN-ADR-012-002 | Registro de imagens |
| PEN-ADR-012-003 | RPO e RTO |
| PEN-ADR-012-004 | Janela de manutenção |
| PEN-ADR-012-005 | Estratégia rolling/blue-green |
| PEN-ADR-012-006 | Runbooks e responsáveis |

# 29. Aprovação

| Papel | Nome | Decisão | Data |
|---|---|---|---|
| Product Owner | Vinicius Velasco de Azevedo | Aprovado | 30/07/2026 |
| Responsável técnico | Soluções do Vale Tecnologia | Recomendado | 30/07/2026 |

# 30. Decisão resultante

Deploys usarão imagens imutáveis, migrations compatíveis, validação e recuperação explícitas.

## Situação do ADR

**Aprovado.**
