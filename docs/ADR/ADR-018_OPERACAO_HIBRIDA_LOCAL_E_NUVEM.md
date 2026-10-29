# ADR-018 — OPERAÇÃO HÍBRIDA LOCAL E NUVEM

**Identificador:** ADR-018  
**Versão:** 1.0.1  
**Status:** Aprovado  
**Prioridade:** P0 — Obrigatório antes da publicação  
**Produto:** SDV Access — Implantação Santa Rita  
**Decisão de produto:** continuidade da portaria sem dependência total da internet  
**Data:** 16/10/2026

---

## Controle de versões

| Versão | Data | Responsável | Alteração |
|---|---|---|---|
| 1.0.0 | 16/10/2026 | Lucas Pastorelli + Codex | Aprovação da topologia híbrida, com operação local, sincronização posterior e backup externo |
| 1.0.1 | 20/10/2026 | Product Owner | Renumeração de ADR-016 para ADR-018 por colisão com o ADR-016 dos terminais faciais; conteúdo aprovado inalterado |

# 1. Contexto

A portaria não pode interromper o atendimento apenas porque a conexão com a internet caiu. Ao mesmo tempo, manter todos os dados somente em um computador do condomínio criaria um ponto único de falha e impediria acesso remoto, cópias externas e recuperação segura.

O produto precisa distinguir três capacidades:

- **operação local:** continuar os fluxos essenciais pela rede interna do condomínio;
- **sincronização:** transportar para a nuvem as operações realizadas localmente e receber alterações autorizadas;
- **backup:** manter cópias históricas protegidas e restauráveis, independentes da sincronização.

Sincronização não substitui backup: uma exclusão indevida, corrupção ou ataque também pode ser sincronizado.

# 2. Problema

Definir uma arquitetura que:

- preserve a operação essencial durante queda da internet;
- evite perda ou duplicação de registros quando a conexão retornar;
- mantenha cópias externas recuperáveis;
- permita acesso online e acompanhamento autorizado;
- integre equipamentos locais sem expô-los diretamente à internet;
- não prometa operação offline de recursos externos ainda não homologados.

# 3. Alternativas consideradas

| Alternativa | Avaliação |
|---|---|
| Somente nuvem | Mais simples, mas torna a portaria indisponível durante queda de internet |
| Somente servidor local | Continua sem internet, mas cria risco de perda física e dificulta acesso remoto e recuperação |
| Aplicação local e nuvem em escrita livre simultânea | Alta complexidade e risco de conflitos não determinísticos |
| Nó operacional local com sincronização controlada e backup externo | Escolhida; equilibra continuidade, recuperação e governança |

# 4. Decisão

O SDV Access adotará uma **topologia híbrida por implantação**:

1. um **nó operacional local** permanecerá na rede interna do condomínio;
2. os postos da portaria acessarão esse nó pela rede local, inclusive quando a internet estiver indisponível;
3. a nuvem receberá os eventos e dados sincronizáveis, fornecerá acesso remoto autorizado e manterá serviços centrais;
4. operações aceitas durante a indisponibilidade serão armazenadas localmente em fila persistente e sincronizadas após a reconexão;
5. cada operação sincronizável terá identificador único, instante original, implantação, origem e estado de sincronização;
6. o processamento remoto será idempotente: reenviar a mesma operação não poderá duplicar o fato;
7. enquanto uma implantação estiver desconectada, alterações remotas com potencial de conflito serão bloqueadas ou tratadas como somente leitura até a reconciliação;
8. documentos e imagens seguirão política própria de fila, criptografia, retenção e confirmação de envio;
9. backups online serão executados e testados separadamente da sincronização;
10. equipamentos serão acessados por adaptador/agente na rede local, conforme ADR-007 e a homologação específica.

# 5. Autoridade e conflitos

Durante uma queda da internet, o nó local é a autoridade operacional da implantação para os fluxos liberados no modo degradado. A nuvem não realizará escrita concorrente nesses mesmos agregados sem uma regra explícita.

A reconciliação deverá:

- preservar o horário em que a operação ocorreu;
- preservar o operador e o posto de origem;
- rejeitar duplicidade pela chave idempotente;
- registrar divergência sem sobrescrever silenciosamente;
- exigir análise humana quando não houver regra determinística;
- registrar início, resultado e falha na auditoria.

Não será adotada replicação genérica “última gravação vence” para fatos de acesso, caixa, auditoria, permissões ou revogações.

# 6. Operações mínimas durante queda da internet

O modo local deverá permitir, após homologação:

- autenticação de operadores previamente autorizados, com política local de validade e revogação;
- consulta aos cadastros mínimos necessários à portaria;
- registro de entrada, saída, negativa e atendimento pendente;
- cadastro rápido e observações operacionais;
- abertura, movimentação e fechamento de caixa conforme regra aprovada;
- registro de encomendas;
- comunicação com equipamentos locais homologados;
- auditoria e fila persistente de tudo que ocorreu no período.

Funcionalidades dependentes de terceiros poderão ficar indisponíveis, como consulta de CEP, envio de mensagens, e-mail e serviços externos de análise. A interface deverá informar a limitação, sem apresentar dados antigos como atuais.

# 7. Interface do modo degradado

Quando a internet ou a nuvem estiver indisponível, o sistema exibirá de forma visível:

- estado “Modo local — aguardando sincronização”;
- horário da última sincronização concluída;
- quantidade de operações pendentes;
- serviços temporariamente indisponíveis;
- alerta específico quando uma decisão depender de dado possivelmente desatualizado.

O retorno da internet não deverá esconder erros de sincronização. Operações rejeitadas ou conflitantes permanecerão visíveis para tratamento autorizado.

# 8. Backup e restauração

O ambiente de produção deverá possuir:

- backups completos e incrementais do banco;
- cópia externa, criptografada e separada do nó local;
- retenção com versões históricas;
- proteção contra alteração e exclusão não autorizadas;
- backup independente para documentos e objetos privados;
- monitoramento de sucesso, atraso e tamanho;
- teste periódico de restauração;
- procedimento documentado para substituir o equipamento local.

O GitHub guarda o código-fonte e a documentação. Ele não será usado como backup de cadastros, documentos, imagens, caixa ou histórico operacional.

# 9. Infraestrutura local mínima

A seleção final depende de dimensionamento, mas a implantação deverá considerar:

- equipamento dedicado, não o computador de uso diário do porteiro;
- armazenamento com saúde monitorada;
- fonte de energia protegida por nobreak;
- rede local cabeada e segmentada;
- cópia das chaves e configurações em cofre seguro;
- reposição documentada do equipamento;
- acesso administrativo individual, restrito e auditável.

# 10. Segurança e privacidade

- dados locais e backups serão criptografados conforme política aprovada;
- cada nó terá identidade própria e revogável;
- sincronização usará canal autenticado e protegido;
- credenciais administrativas não serão reutilizadas nos postos;
- documentos completos, selfies e biometria não entrarão em cache amplo;
- permissões e bloqueios críticos terão política de validade local;
- relógio, antirreplay e integridade dos eventos serão verificados;
- perda ou furto do nó local acionará revogação, substituição e auditoria.

# 11. Relação com a ADR-008

Esta decisão aprova a **topologia híbrida do sistema**, mas não autoriza automaticamente uma credencial, câmera, leitor ou portão a decidir offline.

A ADR-008 continua controlando cache operacional, validade de autorização, revogação e comportamento dos equipamentos. Ela será retomada após o inventário e a prova com o hardware real. Até lá, a contingência física segue manual, limitada e auditável.

# 12. Estratégia de implementação

## Fase 1 — Fundação sem hardware

- definir nó local e ambiente de nuvem;
- criar estado de conectividade e sincronização;
- criar outbox local persistente;
- implementar idempotência e recepção na nuvem;
- implementar monitoramento do atraso;
- separar sincronização de backup;
- documentar instalação, atualização e recuperação.

## Fase 2 — Fluxos essenciais

- classificar quais operações podem ocorrer offline;
- implementar autenticação e permissões locais com validade;
- sincronizar acessos, caixa, encomendas e cadastros essenciais;
- implementar tratamento de conflitos e painel de pendências;
- testar queda e retorno da internet.

## Fase 3 — Equipamentos e homologação

- inventariar câmeras, controladoras e leitores;
- retomar a ADR-008;
- validar comportamento offline de cada adaptador;
- testar falhas de energia, rede, relógio e duplicidade;
- homologar o procedimento manual de contingência.

# 13. Critérios de aceite

**CA-ADR-018-001:** a portaria acessa os fluxos essenciais pela rede local sem internet.  
**CA-ADR-018-002:** toda operação offline recebe identificador único e permanece em fila persistente.  
**CA-ADR-018-003:** reenviar uma operação não duplica o registro.  
**CA-ADR-018-004:** a interface informa modo local, última sincronização e pendências.  
**CA-ADR-018-005:** conflitos nunca são resolvidos por sobrescrita silenciosa.  
**CA-ADR-018-006:** sincronização e backup são mecanismos independentes.  
**CA-ADR-018-007:** existe teste de restauração em equipamento substituto.  
**CA-ADR-018-008:** recursos externos indisponíveis falham de forma clara e segura.  
**CA-ADR-018-009:** decisões offline de equipamentos continuam condicionadas à ADR-008.  
**CA-ADR-018-010:** queda e retorno da internet fazem parte da homologação antes do go-live.

# 14. Métricas a definir antes da produção

- tempo máximo para continuar operando sem internet;
- intervalo e atraso máximo de sincronização;
- RPO e RTO do nó local e da nuvem;
- frequência, retenção e imutabilidade dos backups;
- validade local de sessão, permissão e revogação;
- limite de armazenamento da fila offline;
- tempo para reposição do equipamento local.

# 15. Dependências

- ADR-002 — multi-implantação e isolamento;
- ADR-004 — auditoria, eventos e outbox;
- ADR-005 — filas, cache, locks e idempotência;
- ADR-006 — armazenamento de arquivos;
- ADR-007 — adaptadores de equipamentos;
- ADR-008 — contingência e cache operacional;
- ADR-009 — gestão de segredos;
- ADR-010 — observabilidade;
- ADR-012 — deploy e rollback.

# 16. Pendências

| ID | Pendência |
|---|---|
| PEN-ADR-018-001 | Escolher e dimensionar o equipamento dedicado local |
| PEN-ADR-018-002 | Escolher provedor e região da nuvem |
| PEN-ADR-018-003 | Definir RPO, RTO e retenção dos backups |
| PEN-ADR-018-004 | Definir endereço local, certificado e descoberta pelos postos |
| PEN-ADR-018-005 | Classificar, operação por operação, o que será permitido offline |
| PEN-ADR-018-006 | Realizar inventário dos equipamentos e retomar a ADR-008 |

# 17. Aprovação

A necessidade de não depender totalmente da internet foi confirmada pelo responsável pelo projeto em 16/10/2026. A topologia híbrida é decisão vigente. Modelos específicos de hardware, provedor, prazos de retenção e metas numéricas permanecem decisões de implementação.

# 18. Decisão resultante

O SDV Access não terá a internet como ponto único de falha da operação da portaria. O condomínio contará com um nó operacional local e sincronização controlada com a nuvem, enquanto backups externos, protegidos e testados garantirão recuperação. Recursos físicos offline somente serão ativados após a homologação específica prevista na ADR-008.

## Situação do ADR

**Aprovado.**
