# ADR-007 — PORTAS E ADAPTADORES DE EQUIPAMENTOS

**Identificador:** ADR-007
**Versão:** 1.0.0
**Status:** Aprovado
**Prioridade:** P0 — Bloqueador
**Produto:** SDV Access — Implantação Santa Rita
**Responsável pelo produto:** Vinicius Velasco de Azevedo
**Responsável técnico:** Soluções do Vale Tecnologia
**Data:** 30/07/2026

---

## Controle de versões

| Versão | Data | Responsável | Alteração |
|---|---|---|---|
| 1.0.0 | 30/07/2026 | Product Owner | Criação e aprovação da arquitetura de portas e adaptadores |

---

# 1. Contexto

O SDV Access deverá integrar controladoras, reconhecimento facial, LPR, câmeras e outros equipamentos ainda não inventariados. Fabricantes possuem protocolos, capacidades, autenticação e semânticas diferentes.

O núcleo deverá decidir autorizações sem depender diretamente de SDK, endpoint ou modelo de fabricante.

# 2. Problema

Definir uma fronteira estável que permita:

- substituir fabricantes;
- testar sem hardware;
- tratar falhas e timeouts;
- impedir IDs externos como chaves internas;
- preservar decisão, comando e confirmação separadamente;
- evoluir capacidades sem condicionar o domínio.

# 3. Decisão

Adotar arquitetura de **portas e adaptadores**:

```text
Núcleo de Acesso
  → porta estável
    → serviço de integração
      → adaptador por fabricante/protocolo
        → equipamento
```

O núcleo define as portas. A infraestrutura implementa os adaptadores.

# 4. Alternativas rejeitadas

| Alternativa | Motivo |
|---|---|
| SDK no módulo Acesso | acopla domínio ao fabricante |
| API genérica sem capacidades | esconde incompatibilidades |
| banco acessado pelo equipamento | viola segurança e domínio |
| serviço separado obrigatório no MVP | complexidade prematura |
| automação por placa/face sem decisão central | viola regras aprovadas |

# 5. Portas mínimas

- consultar saúde;
- declarar capacidades;
- cadastrar ou atualizar credencial;
- remover ou revogar credencial;
- solicitar comando;
- consultar resultado;
- reconciliar estado;
- receber evento/callback;
- testar conexão.

Porta não implementada deverá retornar capacidade ausente, não sucesso simulado.

# 6. Capacidades

Cada adaptador declarará capacidades versionadas, como:

- abertura remota;
- confirmação física;
- cadastro facial;
- remoção facial;
- LPR;
- cache local;
- eventos de entrada/saída;
- consulta de estado;
- idempotência nativa;
- callback;
- polling.

A interface somente oferecerá operação compatível.

# 7. Contrato de comando

Campos mínimos:

- UUIDv7 interno;
- implantação;
- ponto de acesso;
- operação;
- correlação;
- chave idempotente;
- instante e expiração;
- parâmetros permitidos;
- origem;
- ator quando manual.

Segredos e models Laravel não entram no contrato.

# 8. Resultados

Estados mínimos:

- pendente;
- enviado;
- aceito pelo adaptador;
- recusado;
- confirmado pelo equipamento;
- falha técnica;
- confirmação desconhecida;
- expirado;
- intervenção necessária.

Aceite do adaptador não significa abertura física.

# 9. Timeout e reconciliação

- timeout produz resultado desconhecido;
- não repetir comando cegamente;
- consultar estado quando suportado;
- correlacionar callback tardio;
- registrar tentativas;
- reconciliar por job;
- exigir intervenção quando não houver prova.

# 10. Idempotência

- chave por implantação, adaptador e operação;
- outbox antes da fila;
- adaptador traduz sem perder a chave;
- consumidor registra resultado;
- duplicidade retorna o resultado conhecido;
- payload divergente falha;
- equipamento sem idempotência exige proteção local.

# 11. Identificadores externos

- armazenados separadamente;
- escopo por implantação, adaptador e tipo;
- tratados como string;
- nunca substituem UUID SDV;
- mudanças preservam histórico;
- callback resolve equipamento e implantação por credencial confiável.

# 12. Segurança

- credencial por integração/implantação;
- segredo protegido conforme ADR-009;
- TLS quando suportado;
- rede restrita;
- allowlist quando aplicável;
- payload validado;
- rate limiting;
- logs sanitizados;
- nenhuma credencial no frontend;
- acesso administrativo auditado.

# 13. Callbacks

- endpoint autenticado;
- implantação resolvida pelo equipamento;
- proteção contra replay;
- idempotência;
- limite de tamanho;
- timestamp externo preservado;
- correlação;
- resposta rápida;
- processamento assíncrono;
- payload bruto somente se necessário e protegido.

# 14. Simulador

Cada contrato deverá possuir simulador capaz de reproduzir:

- sucesso;
- recusa;
- timeout;
- callback tardio;
- duplicidade;
- indisponibilidade;
- payload inválido;
- capacidade ausente;
- confirmação desconhecida.

Simulador integra homologação e desenvolvimento, mas não substitui teste com hardware real.

# 15. SDKs

- confinados ao adaptador;
- versão fixada;
- licença revisada;
- dependências auditadas;
- erros convertidos para tipos internos;
- atualização testada por contrato;
- nenhuma classe do SDK atravessa a porta.

# 16. Processo local opcional

Agente local somente será criado se rede ou fabricante exigir. Ele deverá:

- ter autenticação própria;
- não acessar PostgreSQL;
- comunicar-se por contrato;
- operar com menor privilégio;
- possuir atualização e observabilidade;
- ser aprovado por extensão deste ADR.

# 17. Observabilidade

Métricas:

- disponibilidade;
- latência;
- comandos;
- confirmações;
- timeouts;
- falhas;
- callbacks;
- retentativas;
- divergências;
- fila por equipamento.

Labels não conterão placas, documentos ou pessoas.

# 18. Testes

- contrato por adaptador;
- capacidades;
- autenticação;
- idempotência;
- isolamento por implantação;
- timeout;
- callback;
- reconciliação;
- segredo ausente em logs;
- hardware real em homologação;
- contingência conforme ADR-008.

# 19. Consequências

Positivas:

- núcleo independente;
- simuladores;
- substituição de fabricante;
- falhas explícitas;
- contratos testáveis.

Negativas:

- camada adicional;
- mapeamento por fabricante;
- manutenção de simuladores;
- inventário técnico obrigatório.

# 20. Riscos

| Risco | Mitigação |
|---|---|
| contrato genérico demais | capacidades e tipos específicos |
| SDK vazar ao núcleo | teste arquitetural |
| timeout duplicar abertura | idempotência e reconciliação |
| callback forjado | autenticação e replay protection |
| fabricante não confirmar abertura | resultado desconhecido explícito |
| hardware divergir do simulador | homologação real |

# 21. Implementação

1. inventariar equipamentos;
2. definir contrato base;
3. criar simulador;
4. implementar adaptador piloto;
5. provar outbox e fila;
6. homologar idempotência;
7. testar hardware;
8. documentar capacidades;
9. configurar alertas.

# 22. Critérios de aceite

**CA-ADR-007-001:** núcleo não depende de fabricante.
**CA-ADR-007-002:** cada adaptador declara capacidades.
**CA-ADR-007-003:** SDK não atravessa a porta.
**CA-ADR-007-004:** IDs externos são secundários.
**CA-ADR-007-005:** decisão, comando e confirmação são distintos.
**CA-ADR-007-006:** timeout gera resultado desconhecido.
**CA-ADR-007-007:** comandos são idempotentes.
**CA-ADR-007-008:** callbacks são autenticados.
**CA-ADR-007-009:** segredos não chegam ao frontend.
**CA-ADR-007-010:** falha externa não corrompe o núcleo.
**CA-ADR-007-011:** existe simulador contratual.
**CA-ADR-007-012:** hardware real integra homologação.
**CA-ADR-007-013:** implantação acompanha toda operação.
**CA-ADR-007-014:** agente local exige decisão complementar.
**CA-ADR-007-015:** observabilidade não expõe dados pessoais.

# 23. Rastreabilidade

- `RN-038`, `RN-040`, `RN-077` a `RN-080`;
- `RN-086` a `RN-093`;
- ADR-001, ADR-002, ADR-003, ADR-004, ADR-005 e ADR-009.

# 24. Pendências

| PEN-ADR-007 | Pendência |
|---|---|
| PEN-ADR-007-001 | Inventário de fabricantes, modelos e firmware |
| PEN-ADR-007-002 | Protocolos, licenças e SDKs |
| PEN-ADR-007-003 | Capacidades reais de confirmação |
| PEN-ADR-007-004 | Topologia de rede Santa Rita |
| PEN-ADR-007-005 | Necessidade de agente local |
| PEN-ADR-007-006 | Limites e timeouts por equipamento |

# 25. Aprovação

| Papel | Nome | Decisão | Data | Observações |
|---|---|---|---|---|
| Product Owner | Vinicius Velasco de Azevedo | Aprovado | 30/07/2026 | Portas, adaptadores, capacidades e simulador aprovados |
| Responsável técnico | Soluções do Vale Tecnologia | Recomendado | 30/07/2026 | Arquitetura desacoplada recomendada |

## Situação do ADR

**Aprovado.** Portas e adaptadores constituem a integração obrigatória com equipamentos.
