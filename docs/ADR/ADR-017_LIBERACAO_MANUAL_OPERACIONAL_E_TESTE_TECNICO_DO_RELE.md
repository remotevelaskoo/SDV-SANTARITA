# ADR-017 — LIBERAÇÃO MANUAL OPERACIONAL E TESTE TÉCNICO DO RELÉ

**Identificador:** ADR-017  
**Versão:** 1.1.0  
**Status:** Aprovado — implementação liberada; operação condicionada à homologação e à aprovação formal  
**Prioridade:** P1 — Obrigatório antes de acionar pontos reais pela portaria  
**Produto:** SDV Access — Implantação Santa Rita  
**Responsável pelo produto:** Vinicius Velasco de Azevedo  
**Responsável técnico:** Soluções do Vale Tecnologia  
**Data:** 10/10/2026

---

## Controle de versões

| Versão | Data | Responsável | Alteração |
|---|---|---|---|
| 1.0.0 | 10/10/2026 | Product Owner | Separação entre teste técnico do relé e liberação manual operacional; postos e designação de operadores |
| 1.1.0 | 10/10/2026 | Product Owner | Postos passam a definir equipamentos preferenciais, com alcance ampliado por permissão; exceção simplificada por checkbox |

# 1. Contexto

O ADR-016 estabeleceu a integração direta do terminal facial com o ponto de acesso, e o relé
foi acionado com sucesso em bancada (ADR-016 §21). A portaria precisa agora liberar uma
entrada ou saída manualmente pelo terminal do ponto. O mesmo comando técnico (abrir a porta do
relé) passaria a servir a dois propósitos muito diferentes: testar o equipamento e liberar
uma pessoa.

# 2. Problema

Sem separação explícita:

- um teste de bancada poderia virar acesso sem pessoa, motivo ou evento;
- a permissão administrativa de teste daria poder operacional de liberar pessoas;
- o operador poderia acionar qualquer terminal da implantação;
- "aceito pelo terminal" seria lido como passagem confirmada.

# 3. Decisão

Manter **dois fluxos distintos**, com permissões, configurações e registros próprios:

| | Teste técnico do relé | Liberação manual operacional |
|---|---|---|
| Onde | Gestão › Equipamentos | Modo Portaria |
| Para quê | bancada e manutenção | liberar entrada ou saída |
| Permissão | `equipamentos.liberar-acesso` | `acessos.liberar-manualmente` (e `acessos.liberar-excepcionalmente` para o fluxo excepcional) |
| Configuração | `SDV_INTEGRACAO_TESTE_RELE` | `SDV_INTEGRACAO_LIBERACAO_OPERACIONAL` |
| Terminal | "Em homologação" | "Ativo", em ponto real ativo |
| Origem da operação | `teste_bancada` | `liberacao_manual` |
| Evento de acesso | nunca | sempre (`historico_acessos`) |
| Pessoa | não se aplica | obrigatória; sem cadastro, só pelo fluxo excepcional |

A abertura automática decidida pelo SDV (`SDV_INTEGRACAO_ABERTURA_REMOTA`) é um terceiro fluxo,
não alterado por este ADR.

## 3.1 Postos e designação de operadores

- **Posto** é o contexto operacional e possui pontos e equipamentos preferenciais;
- o equipamento do posto é o terminal vigente de cada ponto (vínculo exclusivo do ADR-016);
- o operador é designado a postos com início e fim; trocar de função encerra a designação;
- a Guarita Principal pode operar todos os relés autorizados da implantação;
- nos demais postos, os equipamentos locais aparecem primeiro; operador com permissão ampliada
  pode selecionar outro ponto da implantação mediante confirmação destacada;
- posto não substitui permissão: o backend combina operador, posto, implantação e equipamento.

## 3.2 Equipamento elegível para a liberação manual

Somente terminais:

- da implantação ativa; preferencialmente de um ponto do posto ativo ou, com permissão ampliada, de outro ponto autorizado;
- com terminal e ponto ativos, terminal online e certificado confiável;
- com abertura remota **homologada** para o firmware atual e relé homologado;
- compatíveis com a direção escolhida (terminal e ponto).

A tela mostra nomes amigáveis ("Facial Entrada Principal"), nunca só IP ou série. Havendo um
único equipamento compatível, ele vem pré-selecionado e visível.

## 3.3 Resolução no backend

O navegador envia ids e dados operacionais. O backend relê, na implantação atual, posto,
pessoa, equipamento, ponto, endereço, porta do relé, credencial, certificado, adaptador,
capacidade, situação e direção. IP, senha, endpoint ou porta vindos do navegador não existem
no contrato.

## 3.4 Comando, idempotência e resultado

- outbox com chave idempotente por confirmação; a mesma chave com outro conteúdo é recusada;
- uma tentativa só; timeout vira "confirmação desconhecida" e nunca é reenviado às cegas;
- comando expira se não for enviado a tempo;
- estados apresentados: solicitada, enviada, aceita pelo terminal, recusada, confirmação
  desconhecida, falha, cancelada ou expirada;
- **"aceita pelo terminal" não é passagem física**; a confirmação exigirá sensor homologado.

## 3.5 Evento de acesso

A decisão da portaria (`resultado = liberado`) e o efeito técnico (`resultado_tecnico`) ficam
separados no mesmo evento, com pessoa, imóvel, operador, implantação, posto, equipamento, ponto,
direção, motivo, data e hora, correlation ID, chave idempotente, origem `liberacao_manual` e,
quando houver, identificação e justificativa excepcionais.

## 3.6 Liberação excepcional

A excepcionalidade é ativada por checkbox explícito, com permissão própria e confirmação
destacada. Para agilizar, não exige justificativa textual no fluxo padrão: o sistema grava
automaticamente o motivo `liberacao_excepcional_operador`. Identificação ou ocorrência é
associada quando disponível, e uma observação complementar pode ser opcional ou exigida por
configuração administrativa. O evento permanece marcado como excepcional e gera auditoria
restrita (`liberacao_manual_excepcional_solicitada`). Não se mistura com acesso normal.

# 4. Alternativas consideradas

| Alternativa | Decisão | Justificativa |
|---|---|---|
| Reutilizar o teste do relé na portaria | Rejeitada | sem pessoa, motivo nem evento; permissão administrativa viraria operacional |
| Permitir equipamento externo ao posto sem permissão ou confirmação | Rejeitada | viola menor privilégio e aumenta o risco de acionar o ponto errado |
| Permitir outros equipamentos com permissão ampliada e confirmação destacada | Adotada | garante cobertura entre guaritas sem perder rastreabilidade |
| Liberar sem pessoa pelo fluxo normal | Rejeitada | mistura exceção com rotina e enfraquece a auditoria |
| Fluxos separados com postos e designação | Adotada | contexto operacional explícito e rastreável |

# 5. Consequências

**Positivas:** equipamentos priorizados por posto; cobertura operacional entre guaritas mediante permissão; evento de acesso completo; exceção visível e rápida; teste e operação com chaves independentes.

**Negativas:** cadastro adicional de postos e designações; nenhum terminal fica elegível até
existir ponto real ativo, terminal ativo e relé homologado; a autorização exibida ainda é o
vínculo vigente, porque o módulo de autorizações não existe.

# 6. Pendências

| Identificador | Pendência | Bloqueia |
|---|---|---|
| PEN-ADR-017-001 | Sensor ou evento do ponto para confirmar a passagem física | Exibir passagem confirmada |
| PEN-ADR-017-002 | Contato seco homologado na cancela/catraca real (PEN-ADR-016-005) | Ligar `SDV_INTEGRACAO_LIBERACAO_OPERACIONAL` |
| PEN-ADR-017-003 | Módulo de autorizações com validade e restrições próprias | Autorização além do vínculo vigente |
| PEN-ADR-017-004 | Aprovação formal do piloto operacional | Uso em produção |
| PEN-ADR-017-005 | Contingência e operação sem rede (ADR-008) | Go-live |

# 7. Critérios de aceite

**CA-ADR-017-001:** teste do relé nunca gera evento de acesso.  
**CA-ADR-017-002:** liberação manual sempre gera evento de acesso com origem `liberacao_manual`.  
**CA-ADR-017-003:** a permissão de teste do relé não libera acesso.  
**CA-ADR-017-004:** operador vê primeiro os terminais do posto e somente aciona outros terminais quando possuir permissão ampliada, com confirmação destacada.  
**CA-ADR-017-005:** nenhum dado de conexão é aceito do navegador.  
**CA-ADR-017-006:** "aceita pelo terminal" não é apresentada como passagem confirmada.  
**CA-ADR-017-007:** liberação excepcional exige permissão, checkbox e confirmação, grava motivo padronizado e aceita observação opcional conforme configuração.  
**CA-ADR-017-008:** a funcionalidade fica desligada por padrão.

# 8. Rastreabilidade

ADR-002, ADR-004, ADR-005, ADR-007, ADR-008, ADR-009, ADR-013 e ADR-016; documento
[SDV-OPE-017](../017_LIBERACAO_MANUAL_OPERACIONAL.md) e [SDV-OPE-018](../018_POSTOS_OPERACIONAIS_E_CADASTRO_ASSISTIDO_POR_CAMERA.md).
