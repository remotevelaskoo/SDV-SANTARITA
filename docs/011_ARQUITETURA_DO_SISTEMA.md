# SDV ACCESS — ARQUITETURA DO SISTEMA
## Arquitetura lógica, modular, operacional e de implantação

**Documento:** SDV-ARQ-011
**Versão:** 1.1.0
**Status:** Aprovado
**Produto:** SDV Access — Implantação Santa Rita
**Empresa proprietária:** Soluções do Vale Tecnologia
**Responsável pelo produto:** Vinicius Velasco de Azevedo
**Data:** Julho de 2026

---

## Controle de versões

| Versão | Data | Responsável | Alteração |
|---|---|---|---|
| 1.0.0 | Julho/2026 | Soluções do Vale | Definição inicial da arquitetura do sistema |
| 1.0.1 | 28/07/2026 | Product Owner | Aprovação formal da arquitetura do sistema |
| 1.1.0 | 12/08/2026 | Product Owner | Fluxos protegidos de conferência, importação assistida e limite da integração biométrica |

---

# 1. Objetivo

Este documento define a arquitetura de referência do SDV Access para o MVP da implantação Santa Rita e para a evolução futura do produto.

Seus objetivos são:

- consolidar as decisões técnicas aprovadas;
- definir limites entre módulos e responsabilidades;
- preservar o imóvel como entidade central;
- orientar a implementação em Laravel, Blade e Livewire;
- estabelecer a estratégia de dados, arquivos, filas, integrações e auditoria;
- definir segurança, observabilidade, testes e implantação;
- evitar acoplamento prematuro a fabricantes e serviços externos;
- indicar decisões que exigem ADR;
- registrar riscos e pendências sem transformá-los em decisões implícitas.

Este documento não inicia o desenvolvimento, não contém credenciais, não escolhe fabricantes e não substitui as especificações funcionais, visuais ou de dados já aprovadas.

---

# 2. Fontes e precedência

Esta arquitetura deriva de:

- `docs/000_DIRETRIZES_DO_PROJETO.md`;
- `docs/001_VOLUME_01_PRODUCT_BOOK_PARTE_01.md`;
- `docs/001_VOLUME_01_PRODUCT_BOOK_PARTE_02.md`;
- `docs/001_VOLUME_01_PRODUCT_BOOK_PARTE_03.md`;
- `docs/002_BRAND_BOOK.md`;
- `docs/003_DESIGN_SYSTEM.md`;
- `docs/004_UX_UI_DASHBOARD.md`;
- `docs/005_UX_UI_VALIDACAO.md`;
- `docs/006_UX_UI_PRE_CADASTRO.md`;
- `docs/007_UX_UI_CADASTRO_IMOVEL.md`;
- `docs/008_ADMINISTRACAO.md`;
- `docs/009_REGRAS_DE_NEGOCIO.md`;
- `docs/010_BANCO_DE_DADOS.md`;
- referências visuais oficiais em `docs/references/`.

Em caso de conflito, prevalecem as diretrizes oficiais, regras de negócio consolidadas e decisões aprovadas. Referências visuais não alteram silenciosamente a arquitetura.

---

# 3. Decisões técnicas invariáveis

| Camada | Decisão aprovada |
|---|---|
| Backend | Laravel |
| Frontend | Blade e Livewire |
| Banco de dados | PostgreSQL |
| Arquivos | armazenamento compatível com S3 |
| OCR e IA | Python/FastAPI somente quando necessário |
| Implantação | Docker |
| React | fora do MVP, salvo nova decisão arquitetural formal |

A menção a React e MySQL na referência visual `01-cadastro-pessoa-dados.png` permanece superada pela precedência documental e não será implementada.

---

# 4. Atributos arquiteturais prioritários

| Prioridade | Atributo | Consequência arquitetural |
|---:|---|---|
| 1 | Segurança | menor privilégio, proteção de dados e segredos |
| 2 | Integridade | transações, idempotência e históricos |
| 3 | Auditabilidade | correlação e trilha de operações relevantes |
| 4 | Disponibilidade operacional | contingência, filas e recuperação |
| 5 | Modularidade | limites explícitos e dependências controladas |
| 6 | Manutenibilidade | padrões consistentes e testes automatizados |
| 7 | Desempenho | consultas indexadas, paginação e processamento assíncrono |
| 8 | Portabilidade | containers e serviços substituíveis por contrato |
| 9 | Observabilidade | logs, métricas, traces e alertas acionáveis |
| 10 | Evolução multi-implantação | segregação desde a primeira implantação |

---

# 5. Estilo arquitetural

## 5.1 Decisão proposta

O MVP será implementado como **monólito modular Laravel**, com uma única aplicação implantável e fronteiras internas de domínio explícitas.

Essa decisão:

- reduz complexidade operacional inicial;
- mantém transações locais para fluxos críticos;
- permite evolução incremental;
- não converte módulos funcionais em microsserviços;
- não impede extrações futuras quando houver necessidade comprovada;
- mantém integrações externas e processamento Python atrás de contratos.

## 5.2 Restrições

- módulos não acessarão internamente tabelas de outros módulos como forma padrão de integração;
- regras de negócio não permanecerão em controllers ou componentes Livewire;
- integrações não serão chamadas diretamente por views ou models;
- comunicação assíncrona não eliminará validações transacionais necessárias;
- extração de serviço exigirá ADR, métricas e justificativa operacional.

---

# 6. Visão de contexto

```text
Morador / Solicitante
        │ navegador
        ▼
SDV Access Web ◄──── Operador da Portaria
        ▲             Gestor / Administrador / Auditor
        │
        ├── PostgreSQL
        ├── Armazenamento S3 compatível
        ├── Serviço de filas e cache
        ├── E-mail ou canal de notificação
        ├── Adaptadores de equipamentos
        │    ├── Controladora
        │    ├── Reconhecimento facial
        │    └── LPR / câmeras
        └── Python/FastAPI opcional
             └── OCR ou IA autorizada
```

O navegador acessa a plataforma web. Equipamentos e serviços externos interagem por adaptadores, filas, callbacks ou APIs autenticadas, nunca por acesso direto ao banco de dados.

---

# 7. Visão de containers

| Container lógico | Tecnologia | Responsabilidade |
|---|---|---|
| Aplicação web | Laravel, Blade, Livewire | interface, API, casos de uso e domínio |
| Workers | Laravel Queue | tarefas assíncronas, integração e notificações |
| Scheduler | Laravel Scheduler | expirações, reconciliação e rotinas |
| Banco | PostgreSQL | dados relacionais e transacionais |
| Cache e filas | solução compatível aprovada | cache efêmero, locks e filas |
| Objetos | S3 compatível | documentos, fotos e evidências |
| Serviço OCR/IA | Python/FastAPI opcional | processamento especializado |
| Proxy de entrada | solução de infraestrutura | TLS, roteamento e limites |
| Observabilidade | soluções a definir | logs, métricas, traces e alertas |

Aplicação web, workers e scheduler poderão usar a mesma imagem da aplicação, com comandos e escalabilidade independentes.

---

# 8. Camadas da aplicação

| Camada | Responsabilidade | Não deverá |
|---|---|---|
| Interface | rotas, controllers, Blade, Livewire e serialização | conter regra de negócio central |
| Aplicação | casos de uso, autorização, transações e orquestração | depender de fabricante |
| Domínio | entidades, políticas, estados, invariantes e eventos | depender de HTTP ou UI |
| Infraestrutura | banco, S3, filas, notificações e adaptadores | definir regra funcional |

## 8.1 Fluxo padrão

```text
Entrada HTTP/Livewire
  → autenticação
  → autorização
  → validação de entrada
  → caso de uso
  → regra de domínio
  → transação e persistência
  → evento/auditoria
  → resposta
```

Chamadas externas deverão ocorrer depois da confirmação transacional, por fila, quando o fluxo não exigir resposta síncrona imediata.

---

# 9. Módulos do núcleo

| Código | Módulo | Responsabilidades principais |
|---|---|---|
| ARQ-MOD-001 | Implantação | organização, implantação e contexto |
| ARQ-MOD-002 | Imóveis | condomínio, bloco, imóvel e endereço |
| ARQ-MOD-003 | Pessoas | identidade, documentos e contatos |
| ARQ-MOD-004 | Vínculos | natureza, papel, responsabilidade e vigência |
| ARQ-MOD-005 | Empresas | empresas, prestadores e categorias |
| ARQ-MOD-006 | Veículos | cadastro, placa e vínculos veiculares |
| ARQ-MOD-007 | Pré-cadastro | convites, versões, análise e conversão |
| ARQ-MOD-008 | Autorizações | permissões de acesso e condições |
| ARQ-MOD-009 | Credenciais | face, placa, tag, QR Code e sincronização |
| ARQ-MOD-010 | Acesso | atendimento, decisão, comando e evento |
| ARQ-MOD-011 | Contribuição | contribuição, caixa e movimentos |
| ARQ-MOD-012 | Identidade e acesso | usuários, sessões, perfis e permissões |
| ARQ-MOD-013 | Administração | configurações, catálogos e motivos |
| ARQ-MOD-014 | Auditoria | eventos auditáveis e alterações |
| ARQ-MOD-015 | Arquivos | metadados, proteção, acesso e retenção |
| ARQ-MOD-016 | Integrações | equipamentos, adaptadores e operações |
| ARQ-MOD-017 | Comunicação | notificações e destinatários |
| ARQ-MOD-018 | Relatórios | consultas, exports e indicadores |

Os módulos são fronteiras lógicas no mesmo repositório e processo implantável do MVP.

---

# 10. Dependências entre módulos

## 10.1 Regras

- o módulo Implantação fornece o contexto comum;
- Imóveis não depende de Pessoas;
- Vínculos referencia Pessoas e Imóveis por contratos internos;
- Autorizações referencia sujeitos e destinos sem incorporar seus cadastros;
- Credenciais referencia sujeitos, mas mantém ciclo próprio;
- Acesso consulta vínculos, autorizações e credenciais por serviços de aplicação;
- Integrações recebe comandos do módulo Acesso e publica resultados;
- Auditoria recebe fatos dos demais módulos sem controlar seus fluxos;
- Relatórios consulta projeções autorizadas, sem alterar entidades operacionais;
- Administração fornece configurações versionadas, sem absorver regras dos módulos.

## 10.2 Formas permitidas

- chamada de serviço de aplicação público;
- consulta por interface de leitura;
- evento interno após transação;
- identificador estável;
- DTO explícito;
- outbox quando houver efeito assíncrono relevante.

## 10.3 Formas proibidas

- controller chamando tabela de módulo alheio;
- componente Livewire executando regra de integração;
- acesso de equipamento ao PostgreSQL;
- regra central escondida em observer genérico;
- eventos sem contrato ou finalidade;
- dependência circular entre módulos.

---

# 11. Interface web com Blade e Livewire

## 11.1 Blade

Blade deverá estruturar layouts, páginas, componentes visuais e estados definidos no Design System.

## 11.2 Livewire

Livewire deverá ser usado em interações que se beneficiem de atualização reativa no servidor, como:

- filtros e paginação;
- formulários em etapas;
- validações progressivas;
- modais e painéis;
- filas operacionais;
- atualização controlada de estado.

## 11.3 Limites

- componente Livewire não será unidade de domínio;
- ações deverão chamar casos de uso;
- validação de interface não substituirá validação de domínio;
- payloads deverão ser mínimos;
- dados sensíveis não ficarão em propriedades públicas sem necessidade;
- upload seguirá fluxo privado;
- operações críticas terão proteção contra repetição;
- telas preservarão responsividade e acessibilidade aprovadas.

Alpine.js poderá ser usado apenas para comportamento visual local e leve, sem criar uma segunda arquitetura de aplicação.

---

# 12. APIs e contratos

## 12.1 Categorias

- endpoints internos da interface web;
- API para integrações autorizadas;
- callbacks ou webhooks de equipamentos;
- API interna opcional para OCR/IA;
- endpoints de saúde sem dados sensíveis.

## 12.2 Padrões

- versionamento explícito para contratos externos;
- autenticação e escopo por cliente ou equipamento;
- validação estrita;
- identificadores opacos;
- idempotência em operações mutáveis;
- paginação em coleções;
- correlação em toda requisição;
- erros sanitizados;
- datas em formato inequívoco e UTC;
- documentação de contrato antes da integração.

APIs não serão criadas apenas para espelhar tabelas.

---

# 13. Persistência e transações

O PostgreSQL é a fonte transacional do núcleo.

## 13.1 Diretrizes

- migrations versionadas no repositório;
- constraints para invariantes estruturais;
- transações delimitadas pelo caso de uso;
- concorrência otimista em entidades críticas;
- locks explícitos apenas quando necessários;
- paginação e índices alinhados às consultas;
- queries complexas encapsuladas em objetos de leitura;
- nenhuma regra de segregação dependerá somente da interface.

## 13.2 Modelos Laravel

Eloquent poderá representar persistência, mas:

- mass assignment será restrito;
- casts não substituirão validação;
- relacionamentos não autorizarão automaticamente acesso;
- exclusão em cascata será evitada em dados históricos;
- scopes de implantação terão testes de isolamento;
- side effects não serão ocultados em eventos de model sem contrato.

---

# 14. Segregação por implantação

Toda requisição autenticada deverá resolver um contexto de implantação autorizado.

O contexto será aplicado a:

- consultas e comandos;
- cache;
- filas;
- arquivos;
- logs;
- exports;
- configurações;
- integrações;
- métricas quando contiverem dimensão de cliente.

O identificador recebido do navegador não será considerado suficiente; a associação do usuário com a implantação deverá ser validada.

Row-Level Security permanece defesa adicional opcional, sujeita a ADR e prova de compatibilidade.

---

# 15. Autenticação e autorização

## 15.1 Autenticação

- usuário individual;
- sessões protegidas;
- regeneração de sessão após login;
- recuperação segura;
- limitação de tentativas;
- revogação após inativação;
- MFA conforme política a aprovar.

## 15.2 Autorização

A autorização interna será avaliada por:

```text
usuário
 + implantação
 + perfis
 + exceções
 + restrições
 + vigência
 + recurso
 + ação
 + contexto
 = decisão efetiva
```

Policies, gates ou serviços equivalentes deverão aplicar a decisão no servidor. Ocultar um botão não constitui autorização.

## 15.3 Operações críticas

Poderão exigir:

- permissão específica;
- justificativa;
- reautenticação;
- confirmação adicional;
- aprovação por outro usuário;
- auditoria reforçada.

---

# 16. Segurança de aplicação

Controles mínimos:

- proteção CSRF;
- escape de saída;
- prevenção de SQL injection por bindings;
- validação de upload;
- verificação de tipo, tamanho e conteúdo;
- objetos privados no S3;
- URLs temporárias;
- cabeçalhos de segurança;
- TLS;
- cookies seguros;
- limitação de taxa;
- proteção contra enumeração;
- mascaramento de documentos;
- gestão externa de segredos;
- dependências atualizadas;
- análise de vulnerabilidades;
- logs sem credenciais ou biometria.

O princípio de defesa em profundidade será aplicado sem presumir que a rede interna seja confiável.

---

# 17. Arquivos e armazenamento S3

## 17.1 Fluxo

```text
Solicitação autorizada
  → validação de metadados
  → upload privado
  → verificação de integridade/malware
  → registro no PostgreSQL
  → associação à entidade
  → acesso temporário auditável
```

## 17.2 Regras

- binários não serão armazenados no PostgreSQL;
- chaves de objeto não conterão dados pessoais;
- substituição preservará versão anterior conforme retenção;
- status do arquivo distinguirá envio, validação, quarentena e disponibilidade;
- limpeza de órfãos será controlada;
- falha parcial será reconciliada;
- provedor será acessado por abstração do framework;
- retenção definitiva depende de política aprovada.
- leitura operacional ocorrerá por autorização no backend e proxy autenticado ou URL assinada de curta duração;
- abertura de foto, selfie ou documento sensível produzirá evento de auditoria sem registrar conteúdo ou URL;
- a interface tratará indisponibilidade, quarentena e expiração da autorização de leitura sem tornar o objeto público.

---

# 18. Filas, eventos e jobs

## 18.1 Uso de filas

Filas serão usadas para:

- sincronização com equipamentos;
- notificações;
- processamento de arquivos;
- OCR ou IA;
- exports;
- reconciliação;
- rotinas demoradas;
- projeções e indicadores quando necessário.

## 18.2 Requisitos

- payload mínimo e sem segredo;
- implantação e correlação explícitas;
- idempotência;
- limite de tentativas;
- backoff;
- timeout;
- fila de falhas;
- alerta;
- possibilidade de reprocessamento autorizado;
- visibilidade de estado operacional.

## 18.3 Outbox

Eventos que precisem sobreviver à confirmação da transação deverão utilizar padrão outbox ou mecanismo equivalente. A implementação definitiva será registrada em ADR.

---

# 19. Scheduler e rotinas temporais

O scheduler deverá coordenar:

- expiração de vínculos;
- expiração de autorizações e convites;
- reconciliação de comandos desconhecidos;
- retentativas autorizadas;
- limpeza técnica conforme retenção;
- geração agendada de relatórios;
- verificação de integridade;
- alertas de vencimento.

Rotinas deverão ser idempotentes, observar o fuso da implantação quando aplicável e impedir execução concorrente indevida.

---

# 20. Integrações com equipamentos

## 20.1 Arquitetura de portas e adaptadores

```text
Núcleo de Acesso
    │ contrato estável
    ▼
Serviço de Integração
    │
    ├── Adaptador Controladora A
    ├── Adaptador Facial B
    ├── Adaptador LPR C
    └── Simulador homologável
```

## 20.2 Contrato mínimo

Cada adaptador deverá declarar:

- identidade e versão;
- capacidades;
- autenticação;
- operações suportadas;
- timeouts;
- política de retentativa;
- formato de erros;
- idempotência;
- correlação;
- callbacks;
- limites;
- estado de saúde.

## 20.3 Regras

- identificador externo é secundário;
- segredo não entra no frontend;
- comando e confirmação são distintos;
- timeout produz resultado desconhecido;
- retentativa não duplica efeito;
- payloads persistidos serão sanitizados;
- indisponibilidade não corrompe o núcleo;
- simulador deverá cobrir sucesso, recusa, timeout e duplicidade;
- integração específica só será implementada após inventário técnico.

O equipamento BRAVAS atualmente considerado pela implantação será tratado como adaptador externo, sem dependência do núcleo. A visualização humana de uma selfie no pré-cadastro não cria uma operação de sincronização. O eventual envio de foto ou template exigirá fluxo próprio, fila, idempotência, confirmação, revogação e reconciliação, e permanece bloqueado enquanto a ADR-013 estiver adiada.

---

# 21. Decisão de acesso

O caso de uso de decisão deverá:

1. identificar implantação, atendimento e ponto;
2. carregar sujeito e evidências;
3. validar cadastro e vínculos;
4. validar autorização e vigência;
5. validar credencial;
6. validar veículo e placa quando aplicável;
7. aplicar horários, destino e restrições;
8. avaliar contribuição e caixa sem criar autorização;
9. registrar decisão e motivos;
10. criar comando quando autorizado;
11. publicar operação de integração;
12. reconciliar confirmação;
13. registrar evento de acesso.

O resultado deverá permanecer reconstruível, inclusive quando regras ou configurações mudarem posteriormente.

---

# 22. Contingência operacional

A arquitetura deverá suportar:

- salvar atendimento pendente;
- negar sem enviar comando;
- liberação manual autorizada;
- registro de indisponibilidade;
- confirmação desconhecida;
- reconciliação posterior;
- cache operacional apenas quando aprovado;
- funcionamento degradado claramente sinalizado.

Não será presumida operação offline completa. Política de cache, tempo de tolerância, autoridade local e reconciliação dependem do inventário de equipamentos e de ADR.

---

# 23. OCR e IA com Python/FastAPI

## 23.1 Critério de ativação

O serviço Python somente será introduzido quando:

- houver caso de uso aprovado;
- Laravel não for a opção adequada;
- benefício e custo estiverem registrados;
- segurança e retenção estiverem definidas;
- existir caminho manual obrigatório ou contingencial.

## 23.2 Contrato

- comunicação autenticada;
- rede restrita;
- arquivos por referência temporária ou transferência protegida;
- correlação;
- timeout;
- resultado com confiança;
- versão do mecanismo;
- ausência de decisão automática não autorizada;
- descarte do material temporário.

OCR será assistivo. Selfie não criará credencial biométrica automaticamente.

## 23.3 Importação assistida de fontes legadas

A capacidade futura poderá receber documentos, imagens, planilhas ou exportações legadas em lote. O Laravel continuará responsável por autenticação, autorização, orquestração, área de preparação, validação canônica e decisão humana. Python/FastAPI somente será introduzido se a ADR-011 for retomada e os critérios de ativação forem atendidos.

O fluxo arquitetural será:

```text
fonte autorizada
  → armazenamento privado e validação do arquivo
  → extração assistida e resultado com confiança
  → normalização e candidatos a duplicidade
  → revisão humana obrigatória
  → validação de domínio no Laravel
  → importação transacional auditada
  → reconciliação do lote
```

Nenhum resultado de IA poderá conceder acesso, criar credencial, formar vínculo ou alterar cadastro canônico sem revisão e aprovação explícitas.

---

# 24. Cache

Cache poderá ser usado para:

- catálogos de baixa mutação;
- configurações publicadas;
- sessões;
- rate limiting;
- locks distribuídos;
- resultados de leitura não sensíveis;
- capacidades de adaptadores.

## 24.1 Restrições

- chave incluirá implantação;
- cache não será fonte definitiva;
- invalidação acompanhará publicação;
- dado sensível terá uso mínimo e proteção;
- decisão crítica revalidará dados necessários;
- cache de equipamento ou contingência exigirá ADR;
- indisponibilidade do cache não deverá corromper dados.

---

# 25. Auditoria e rastreabilidade

Operações relevantes produzirão evento de auditoria com:

- implantação;
- ator;
- ação;
- entidade;
- identificador;
- origem;
- instante;
- correlação;
- resultado;
- motivo;
- valores anteriores e posteriores quando aplicável.

Auditoria:

- será somente anexada;
- não dependerá de texto livre como única evidência;
- mascarará dados sensíveis;
- distinguirá usuário, sistema e integração;
- não será confundida com log técnico;
- terá falha tratada sem apagar a operação original;
- seguirá retenção a aprovar.

---

# 26. Logs, métricas e traces

## 26.1 Logs

Logs estruturados deverão incluir nível, serviço, ambiente, correlação, implantação quando permitido, operação e erro sanitizado.

## 26.2 Métricas

Categorias mínimas:

- latência e erros HTTP;
- tempo de consultas;
- filas, tentativas e falhas;
- comandos e confirmações;
- disponibilidade de equipamentos;
- uploads e processamentos;
- autenticação e bloqueios;
- jobs e scheduler;
- capacidade de banco e armazenamento.

## 26.3 Traces

Correlação deverá acompanhar:

```text
requisição
 → caso de uso
 → transação
 → outbox/fila
 → adaptador
 → equipamento
 → callback
 → evento
```

Identificadores de correlação não conterão dados pessoais.

---

# 27. Tratamento de erros

Erros serão classificados em:

| Classe | Tratamento |
|---|---|
| Validação | mensagem orientativa, sem log de erro técnico |
| Regra de negócio | resultado estruturado e auditável quando relevante |
| Autorização | resposta segura sem revelar recurso |
| Concorrência | preservar entrada e solicitar nova leitura |
| Integração | retentativa ou intervenção conforme contrato |
| Infraestrutura | alerta, correlação e resposta sanitizada |
| Segurança | contenção, auditoria e resposta genérica |

Stack traces e detalhes internos não serão exibidos ao usuário em produção.

---

# 28. Disponibilidade e continuidade

## 28.1 Diretrizes

- aplicação stateless quando possível;
- múltiplas réplicas web compatíveis;
- workers escaláveis por fila;
- health checks distintos para vida e prontidão;
- encerramento gracioso;
- backups protegidos;
- restauração testada;
- migrations compatíveis com atualização controlada;
- reconciliação de operações incompletas;
- plano de contingência operacional.

## 28.2 Dependências críticas

| Dependência | Efeito de indisponibilidade |
|---|---|
| PostgreSQL | bloqueia operação transacional |
| Cache/filas | degrada sessões, jobs ou integração conforme uso |
| S3 | impede novos arquivos e evidências |
| Equipamentos | exige contingência |
| Notificação | adia comunicação, sem perder evento |
| OCR/IA | utiliza conferência manual |

RPO, RTO e disponibilidade-alvo permanecem pendentes.

---

# 29. Docker e topologia de implantação

## 29.1 Serviços previstos

```text
proxy
application-web
application-worker
application-scheduler
postgresql
cache-queue
object-storage ou endpoint S3
observability
python-ocr-ai (opcional)
```

## 29.2 Regras

- imagens imutáveis;
- configuração por ambiente;
- segredos fora da imagem;
- usuário não privilegiado;
- volumes apenas onde necessários;
- rede interna restrita;
- health checks;
- logs enviados para saída padronizada;
- versões fixadas;
- build reproduzível;
- banco e S3 com políticas de backup independentes.

Docker Compose poderá atender desenvolvimento e ambientes simples. A orquestração de produção será definida conforme infraestrutura e metas operacionais.

---

# 30. Ambientes

| Ambiente | Finalidade | Dados |
|---|---|---|
| Desenvolvimento | construção local | sintéticos |
| Testes automatizados | execução isolada | factories |
| Homologação | validação funcional e integrada | anonimizados ou sintéticos |
| Produção | operação Santa Rita | reais protegidos |

## 30.1 Regras

- credenciais distintas;
- buckets e bancos distintos;
- integrações com simuladores fora de produção;
- acesso restrito;
- promoção por artefato;
- nenhuma cópia irrestrita de produção;
- dados reais fora de produção somente por processo formal.

---

# 31. Configuração e segredos

Configurações serão classificadas como:

- ambiente;
- implantação;
- módulo;
- adaptador;
- segredo.

Configurações de negócio relevantes serão versionadas no domínio. Segredos usarão variável protegida, Docker secret, cofre ou solução equivalente e nunca retornarão à interface após gravação.

Rotação deverá preservar disponibilidade, autoria, instante e resultado do teste.

---

# 32. Estratégia de testes

| Nível | Objetivo |
|---|---|
| Unitário | invariantes e políticas de domínio |
| Aplicação | casos de uso e transações |
| Integração | PostgreSQL, S3, filas e adaptadores |
| Componente | Livewire, controllers e policies |
| Contrato | APIs, webhooks e equipamentos |
| Ponta a ponta | jornadas críticas aprovadas |
| Segurança | autenticação, autorização e isolamento |
| Desempenho | cenários e volumes definidos |
| Recuperação | backup, restauração e reconciliação |

## 32.1 Cenários obrigatórios

- isolamento entre implantações;
- duplicidade e concorrência;
- vigência e expiração;
- autorização, comando e confirmação separados;
- timeout e retentativa;
- idempotência;
- arquivo privado;
- menor privilégio;
- auditoria;
- falha de equipamento;
- continuidade manual;
- acessibilidade e responsividade.

---

# 33. Integração e entrega contínuas

O pipeline deverá incluir:

1. instalação reproduzível;
2. lint e padrões;
3. análise estática;
4. testes;
5. verificação de migrations;
6. análise de dependências;
7. build da imagem;
8. análise da imagem;
9. publicação em registro autorizado;
10. implantação controlada;
11. smoke test;
12. rollback ou roll-forward documentado.

Nenhum segredo será incorporado ao build. A aprovação para produção deverá ser rastreável.

---

# 34. Desempenho e escalabilidade

## 34.1 Princípios

- paginação no servidor;
- filtros indexados;
- evitar consultas N+1;
- jobs para processamento pesado;
- limites de upload;
- timeouts explícitos;
- pool de conexões compatível;
- escalabilidade horizontal de web e workers;
- métricas antes de otimização;
- particionamento somente por necessidade medida.

## 34.2 Operações críticas

Prioridade de desempenho:

1. decisão e registro de acesso;
2. consulta de pessoa, imóvel, placa e autorização;
3. fila operacional da portaria;
4. comandos e callbacks;
5. auditoria e exportações;
6. dashboards.

Metas numéricas deverão ser definidas antes da homologação de carga.

---

# 35. Privacidade e retenção

A arquitetura deverá aplicar:

- minimização;
- finalidade;
- classificação;
- controle de acesso;
- mascaramento;
- registro de uso relevante;
- retenção por categoria;
- anonimização ou descarte controlado;
- bloqueio de descarte quando necessário;
- privacidade por padrão.

Biometria, documentos, selfies, evidências LPR, exports e backups exigem política específica antes de automatizar retenção ou exclusão.

---

# 36. Evolução arquitetural

Extração futura de um módulo somente será considerada quando houver:

- limite de domínio estável;
- necessidade independente de escala ou disponibilidade;
- equipe e operação compatíveis;
- contrato versionado;
- dados e transações compreendidos;
- observabilidade;
- estratégia de falhas;
- custo justificado.

Possíveis candidatos futuros incluem processamento de mídia/IA, hub de integrações e relatórios pesados. Isso não constitui decisão de microsserviços.

---

# 37. ADRs necessários

| ADR proposto | Tema | Momento |
|---|---|---|
| ADR-001 | Monólito modular Laravel | antes da estrutura de código |
| ADR-002 | Multi-implantação e isolamento | antes das migrations |
| ADR-003 | UUID e identificadores públicos | antes das migrations |
| ADR-004 | Auditoria e outbox | antes dos fluxos críticos |
| ADR-005 | Filas, cache e locks | antes da infraestrutura |
| ADR-006 | S3, arquivos e retenção | antes de uploads |
| ADR-007 | Portas e adaptadores de equipamentos | antes da primeira integração |
| ADR-008 | Contingência e cache operacional | após inventário de equipamentos |
| ADR-009 | Segredos e rotação | antes da produção |
| ADR-010 | Observabilidade | antes da homologação integrada |
| ADR-011 | Python/FastAPI para OCR ou IA | somente se ativado |
| ADR-012 | Estratégia de deploy e rollback | antes da primeira implantação |

A numeração será ratificada no catálogo oficial de ADRs.

---

# 38. Rastreabilidade arquitetural

| Decisão | Regras/requisitos relacionados |
|---|---|
| monólito modular | `RNF-007`, módulos do Volume 01 |
| implantação segregada | `RN-055` |
| limites de entidades | `RN-056` |
| histórico e estados | `RN-005`, `RN-021`, `RN-057`, `RN-063`, `RN-094`, `RN-095` |
| arquivos privados | `RN-065`, `RN-066` |
| decisão, comando e confirmação | `RN-077` a `RN-082` |
| idempotência | `RN-079`, `RN-085`, `RN-092` |
| integrações desacopladas | `RN-040`, `RN-090` a `RN-093` |
| menor privilégio | `RN-050` a `RN-054`, `RN-097` a `RN-100` |
| auditoria | `RN-046` a `RN-049` |
| Docker e continuidade | `RNF-006`, `RNF-011`, `HOM-022` |

---

# 39. Riscos arquiteturais

| Risco | Probabilidade | Impacto | Tratamento |
|---|---:|---:|---|
| fabricante indefinido | Alta | Alto | adaptadores e simulador |
| regra de contingência indefinida | Alta | Alto | ADR antes do go-live |
| política de privacidade incompleta | Alta | Alto | bloquear biometria e descarte automático |
| excesso de regra em componentes Livewire | Média | Alto | casos de uso e testes |
| acoplamento entre módulos | Média | Alto | contratos e revisão arquitetural |
| duplicidade em retentativas | Média | Alto | idempotência e outbox |
| logs com dados sensíveis | Média | Alto | padrão estruturado e sanitização |
| ausência de metas operacionais | Alta | Médio | definir SLO, RPO e RTO |
| migrations incompatíveis com operação | Média | Alto | estratégia expandir/migrar/contrair |
| dependência excessiva de serviço externo | Média | Médio | fallback e circuit breaker quando aplicável |
| filas sem monitoramento | Média | Alto | métricas, alertas e fila de falhas |
| crescimento de eventos e auditoria | Média | Médio | medir e planejar retenção/partição |

---

# 40. Pendências abertas

| PEN-ARQ | Pendência | Impacto |
|---|---|---|
| PEN-ARQ-001 | Inventário de equipamentos, protocolos e licenças | integrações |
| PEN-ARQ-002 | Política de contingência e operação offline | acesso |
| PEN-ARQ-003 | Serviço de filas e cache | infraestrutura |
| PEN-ARQ-004 | Provedor S3 e política de objetos | arquivos |
| PEN-ARQ-005 | Cofre e rotação de segredos | segurança |
| PEN-ARQ-006 | MFA, sessão e senha | identidade |
| PEN-ARQ-007 | Catálogo e precedência de permissões | autorização |
| PEN-ARQ-008 | Política de privacidade, biometria e retenção | dados sensíveis |
| PEN-ARQ-009 | Escopo de OCR no MVP | serviço Python |
| PEN-ARQ-010 | Regras definitivas de contribuição e caixa | domínio financeiro |
| PEN-ARQ-011 | Metas de latência e volume | desempenho |
| PEN-ARQ-012 | SLO, RPO e RTO | continuidade |
| PEN-ARQ-013 | Topologia e provedor de produção | implantação |
| PEN-ARQ-014 | Solução de observabilidade e alertas | operação |
| PEN-ARQ-015 | Estratégia de e-mail e notificações | comunicação |
| PEN-ARQ-016 | Row-Level Security | defesa adicional |
| PEN-ARQ-017 | Particionamento de eventos e auditoria | escala |
| PEN-ARQ-018 | Processo e fonte de migração Santa Rita | dados |
| PEN-ARQ-019 | Domínio, TLS e gestão de certificados | infraestrutura |
| PEN-ARQ-020 | Política de atualização sem indisponibilidade | deploy |
| PEN-ARQ-021 | Aprovação do uso de Alpine.js como apoio visual | frontend |
| PEN-ARQ-022 | Versões mínimas de Laravel, PHP, PostgreSQL e Docker | baseline técnico |

---

# 41. Decisões consolidadas

Ficam propostas para aprovação:

- monólito modular Laravel para o MVP;
- Blade e Livewire como interface;
- regras de negócio fora de controllers e componentes;
- PostgreSQL como fonte transacional;
- segregação por implantação em todas as camadas;
- arquivos privados em S3 compatível;
- web, workers e scheduler com responsabilidades distintas;
- processamento assíncrono idempotente;
- padrão outbox para efeitos críticos;
- portas e adaptadores para equipamentos;
- simulador como parte da homologação;
- Python/FastAPI opcional e restrito;
- autenticação e autorização no servidor;
- auditoria separada de logs técnicos;
- observabilidade estruturada;
- Docker com imagens imutáveis;
- ambientes isolados;
- testes em múltiplos níveis;
- ADR obrigatório para decisões estruturais;
- microsserviços fora do MVP sem nova justificativa e aprovação;
- React fora do MVP.

---

# 42. Critérios de aceite

**CA-ARQ-001:** as tecnologias aprovadas são preservadas.
**CA-ARQ-002:** React e microsserviços não são introduzidos no MVP.
**CA-ARQ-003:** o imóvel permanece central no domínio.
**CA-ARQ-004:** pessoa, vínculo, autorização, credencial e evento mantêm limites próprios.
**CA-ARQ-005:** a arquitetura define módulos e dependências permitidas.
**CA-ARQ-006:** controllers e Livewire não concentram regras de negócio.
**CA-ARQ-007:** dados estão segregados por implantação.
**CA-ARQ-008:** PostgreSQL é a fonte transacional do núcleo.
**CA-ARQ-009:** arquivos permanecem privados e fora do banco.
**CA-ARQ-010:** autorização, comando e confirmação física são distintos.
**CA-ARQ-011:** operações assíncronas possuem idempotência, retentativa e observabilidade.
**CA-ARQ-012:** integrações dependem de contratos e adaptadores.
**CA-ARQ-013:** falhas externas não corrompem o núcleo.
**CA-ARQ-014:** Python/FastAPI somente é ativado por necessidade aprovada.
**CA-ARQ-015:** menor privilégio é aplicado no servidor.
**CA-ARQ-016:** auditoria e logs técnicos têm finalidades separadas.
**CA-ARQ-017:** Docker e ambientes estão definidos conceitualmente.
**CA-ARQ-018:** segurança, continuidade e observabilidade estão incluídas desde o desenho.
**CA-ARQ-019:** estratégia de testes cobre isolamento, concorrência e integrações.
**CA-ARQ-020:** decisões pendentes não são tratadas como definições.
**CA-ARQ-021:** decisões estruturais possuem ADR previsto.
**CA-ARQ-022:** a arquitetura permite evolução futura sem complexidade prematura.

---

# 43. Aprovação

| Papel | Nome | Decisão | Data | Observações |
|---|---|---|---|---|
| Product Owner | Vinicius Velasco de Azevedo | Aprovado | 28/07/2026 | Arquitetura aprovada como referência para ADRs, contratos e planejamento técnico |
| Soluções do Vale | Representante designado | Ciente | 28/07/2026 | Aprovação registrada no repositório oficial |

---

# 44. Próximos documentos

Após a aprovação desta arquitetura deverão ser produzidos:

1. catálogo de ADRs e os ADRs bloqueadores;
2. especificação de APIs e contratos;
3. especificação de infraestrutura e implantação;
4. plano de testes e homologação;
5. plano técnico de desenvolvimento do MVP.

A implementação deverá começar somente após o fechamento dos documentos e pendências bloqueadoras aplicáveis à primeira etapa técnica.

---

## Situação do documento

Este documento encontra-se **aprovado**. A arquitetura preserva as decisões técnicas vigentes e estabelece a base para ADRs, contratos e planejamento técnico, sem autorizar silenciosamente escolhas ainda pendentes.
