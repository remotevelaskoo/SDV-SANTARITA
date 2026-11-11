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

Esta decisão aprova a **topologia híbrida do sistema**, mas não autoriza automaticamente uma crede…2287 tokens truncated…e `.env.example` no backend, com segredos protegidos. A chave secreta do Supabase nunca é enviada ao visitante. Não habilite sem o projeto de banco isolado, catálogo real, aviso de privacidade e teste com o nó local.

A URL do convite tem formato `https://DOMINIO/#convite=SEGREDO`. O fragmento não entra na requisição inicial nem nos cabeçalhos de referência. O frontend envia o convite no cabeçalho somente à API do portal. O recibo também usa fragmento e não apresenta nome, CPF ou imagens. Não instalar analytics, pixels ou scripts de terceiros que possam ler esses fragmentos.

## Operação local

Depois de incorporar a branch ao nó local e aplicar sua migração:

```sh
php artisan pre-cadastro:sincronizar-nuvem
```

O scheduler existente executará o comando a cada minuto **somente** com `SDV_PRE_CADASTRO_NUVEM_ENABLED=true`. Configure `schedule:run` no servidor. Use a credencial exclusiva do nó; não copie a chave administrativa do Supabase para o computador da portaria.

A fila de análise já existente recebe a solicitação e seus arquivos no armazenamento privado. O comando não cria pessoa autorizada, credencial facial, cobrança ou liberação física. O resultado de uma decisão local é confirmado na nuvem com versão crescente; falhas ficam pendentes para nova tentativa.
