# ADR-002 — MULTI-IMPLANTAÇÃO E ISOLAMENTO

**Identificador:** ADR-002
**Versão:** 1.0.0
**Status:** Proposto
**Prioridade:** P0 — Bloqueador
**Produto:** SDV Access — Implantação Santa Rita
**Responsável pelo produto:** Vinicius Velasco de Azevedo
**Responsável técnico:** Soluções do Vale Tecnologia
**Data:** Julho de 2026

---

## Controle de versões

| Versão | Data | Responsável | Alteração |
|---|---|---|---|
| 1.0.0 | Julho/2026 | Soluções do Vale | Proposta da estratégia de multi-implantação e isolamento |

---

# 1. Contexto

Santa Rita é a primeira implantação do SDV Access, mas o produto deverá atender futuramente outros condomínios, loteamentos, empresas e organizações.

O sistema armazenará:

- identidades e documentos;
- vínculos com imóveis;
- autorizações;
- credenciais;
- eventos de acesso;
- imagens e arquivos;
- usuários e permissões;
- configurações;
- integrações;
- auditoria.

Esses dados não poderão ser expostos ou alterados entre implantações sem autoridade explícita. O isolamento deverá existir desde a primeira versão, mesmo quando houver apenas um cliente em produção.

O ADR-001 aprovou um monólito modular Laravel com PostgreSQL compartilhado como base transacional do MVP. Este ADR define como o contexto de implantação atravessará todas as camadas.

---

# 2. Problema

Definir uma estratégia multi-implantação que:

- impeça acesso cruzado;
- mantenha consultas e transações simples;
- suporte usuários autorizados em mais de uma implantação;
- segrege banco, cache, filas, arquivos, logs e integrações;
- permita evolução operacional;
- seja verificável por testes;
- não introduza complexidade desproporcional ao MVP;
- possibilite migração futura para isolamento físico maior quando necessário.

A decisão bloqueia migrations, autenticação, autorização, cache, filas e estrutura dos arquivos.

---

# 3. Terminologia

| Termo | Definição |
|---|---|
| Organização | titular institucional de uma ou mais implantações |
| Implantação | contexto segregado de operação do SDV Access |
| Condomínio | estrutura do domínio dentro de uma implantação |
| Tenant | termo técnico equivalente ao contexto de implantação |
| Contexto de implantação | identidade validada da implantação ativa |
| Entidade global | dado técnico ou institucional não pertencente a uma única implantação |
| Entidade operacional | dado de negócio pertencente a uma implantação |
| Acesso cruzado | leitura ou alteração indevida entre implantações |
| Administrador global | papel futuro e excepcional, fora da administração normal da implantação |

Na interface e na documentação funcional será preferido o termo **implantação**. `tenant` poderá existir apenas em conceitos técnicos internos.

---

# 4. Forças e restrições

## 4.1 Forças

- segurança e privacidade;
- menor privilégio;
- auditabilidade;
- simplicidade operacional inicial;
- transações entre módulos;
- consultas e relatórios por implantação;
- evolução futura;
- custo de backup e restauração;
- compatibilidade com Laravel e PostgreSQL;
- possibilidade de clientes com exigências diferentes.

## 4.2 Restrições

- PostgreSQL é o banco aprovado;
- o MVP usa monólito modular;
- cada operação relevante deverá ser auditável;
- integrações permanecerão desacopladas;
- arquivos ficam em armazenamento S3 compatível;
- histórico não será apagado;
- identificadores externos não substituem chaves internas;
- a implantação não poderá ser escolhida apenas por parâmetro não validado do navegador.

---

# 5. Alternativas consideradas

## 5.1 Alternativa A — Banco e schema compartilhados

Todas as implantações utilizam as mesmas tabelas. Entidades operacionais possuem `implantacao_id`.

### Vantagens

- migrations únicas;
- transações simples;
- custo operacional menor;
- uso eficiente de conexões;
- relatórios autorizados entre implantações possíveis;
- onboarding simples;
- compatibilidade direta com o monólito modular.

### Desvantagens

- erro de filtro pode causar vazamento;
- restauração de uma implantação é mais complexa;
- índices precisam considerar o tenant;
- exige disciplina em todas as camadas.

---

## 5.2 Alternativa B — Schema PostgreSQL por implantação

Cada implantação usa um schema com tabelas equivalentes.

### Vantagens

- separação lógica maior;
- nomes e tabelas isolados;
- possibilidade de operações por schema.

### Desvantagens

- migrations repetidas;
- `search_path` exige cuidado;
- pools e jobs ficam mais complexos;
- tabelas globais e autenticação exigem desenho adicional;
- ferramentas Laravel podem demandar customização;
- crescimento do número de schemas aumenta a operação.

---

## 5.3 Alternativa C — Banco de dados por implantação

Cada implantação possui banco PostgreSQL próprio.

### Vantagens

- forte isolamento físico/lógico;
- backup e restauração por implantação;
- possibilidade de escala e residência específicas;
- menor raio de impacto de algumas falhas.

### Desvantagens

- provisionamento e migrations mais complexos;
- conexões dinâmicas;
- transações entre dados globais e locais;
- observabilidade e suporte multiplicados;
- maior custo;
- usuários multi-implantação mais complexos;
- excessivo para o primeiro MVP.

---

## 5.4 Alternativa D — Estratégia híbrida

Banco compartilhado como padrão, com possibilidade de banco dedicado para implantações específicas.

### Vantagens

- flexibilidade comercial e regulatória;
- isolamento maior quando necessário;
- evolução gradual.

### Desvantagens

- duas topologias desde o início;
- testes, migrations e suporte duplicados;
- roteamento de conexão mais complexo;
- risco de comportamentos diferentes;
- custo prematuro sem cliente que o exija.

---

# 6. Matriz de avaliação

Escala: 1 — desfavorável; 5 — muito favorável.

| Critério | Peso | A — Compartilhado | B — Schema | C — Banco | D — Híbrido |
|---|---:|---:|---:|---:|---:|
| Isolamento por padrão | 5 | 3 | 4 | 5 | 4 |
| Simplicidade do MVP | 5 | 5 | 3 | 2 | 2 |
| Operação e migrations | 5 | 5 | 2 | 2 | 1 |
| Transações | 4 | 5 | 4 | 3 | 3 |
| Custo inicial | 4 | 5 | 3 | 2 | 2 |
| Restauração por implantação | 3 | 2 | 3 | 5 | 4 |
| Compatibilidade Laravel | 4 | 5 | 3 | 3 | 2 |
| Evolução futura | 3 | 4 | 3 | 4 | 5 |
| Testabilidade | 4 | 5 | 3 | 3 | 2 |

A alternativa A apresenta o melhor equilíbrio para o MVP, desde que o isolamento seja protegido em profundidade.

---

# 7. Decisão proposta

Adotar para o MVP:

- **um banco PostgreSQL compartilhado**;
- **um schema operacional compartilhado**;
- `implantacao_id` obrigatório nas entidades operacionais;
- contexto de implantação validado no servidor;
- unicidades, relacionamentos e índices com escopo de implantação;
- filtros e políticas de aplicação obrigatórios;
- testes automatizados de isolamento;
- chaves de cache, filas, arquivos, exports e integrações com implantação;
- acesso global excepcional e auditado;
- Row-Level Security avaliada como defesa adicional, não como requisito inicial;
- possibilidade de evolução para estratégia híbrida somente por novo ADR.

---

# 8. Entidades globais e operacionais

## 8.1 Globais permitidas

O conjunto global deverá ser mínimo e explicitamente documentado, podendo incluir:

- organizações;
- implantações;
- catálogo técnico de permissões;
- versões da aplicação;
- tipos técnicos verdadeiramente universais;
- registros de administração global futura.

## 8.2 Operacionais

Deverão possuir implantação explícita:

- condomínios, blocos e imóveis;
- pessoas e documentos, salvo futura decisão de identidade global;
- vínculos;
- empresas;
- veículos;
- pré-cadastros;
- autorizações;
- credenciais;
- atendimentos e eventos;
- contribuições e caixas;
- usuários no escopo operacional ou suas associações;
- perfis;
- configurações;
- equipamentos;
- arquivos;
- auditoria;
- notificações;
- exports.

## 8.3 Regra de classificação

Uma entidade somente será global quando:

- não possuir proprietário de implantação;
- não contiver dado operacional de cliente;
- houver necessidade real de compartilhamento;
- o impacto de segurança estiver documentado.

Conveniência técnica não transforma dado operacional em global.

---

# 9. Identidade de pessoas

Para o MVP, o cadastro de pessoa será isolado por implantação.

Consequências:

- a mesma pessoa poderá existir em implantações distintas;
- deduplicação ocorrerá dentro da implantação;
- documentos normalizados terão unicidade no escopo definido;
- uma busca em Santa Rita não revelará existência em outro cliente;
- consolidação global de identidade fica fora do MVP;
- eventual identidade compartilhada exigirá novo ADR de privacidade e domínio.

Essa decisão prioriza isolamento, finalidade e menor exposição.

---

# 10. Usuários e associação às implantações

Uma identidade de autenticação poderá ser associada a uma ou mais implantações por relação explícita.

## 10.1 Regras

- acesso à implantação exige associação ativa;
- perfis e exceções serão avaliados no escopo da implantação;
- trocar a implantação ativa exigirá nova resolução de contexto;
- permissões não serão transportadas automaticamente entre implantações;
- sessão deverá registrar implantação ativa;
- ações deverão revalidar o contexto, não confiar apenas na sessão visual;
- usuário inativo globalmente não poderá acessar nenhuma implantação;
- inativação local removerá apenas o acesso correspondente, quando o modelo permitir.

O desenho físico da identidade será detalhado após ADR-003 e na especificação de autenticação.

---

# 11. Resolução do contexto

## 11.1 Fontes possíveis

- subdomínio;
- domínio personalizado;
- rota protegida;
- seleção após autenticação;
- credencial técnica de integração;
- token de convite.

## 11.2 Processo

```text
requisição
  → identificar candidato a implantação
  → carregar implantação
  → validar status
  → validar associação/credencial
  → criar contexto imutável
  → autorizar recurso e ação
  → executar caso de uso
```

## 11.3 Restrições

- `implantacao_id` enviado em formulário não concede acesso;
- o contexto não será alterado no meio do caso de uso;
- jobs e comandos deverão reconstruir contexto validado;
- contexto ausente causará falha segura;
- domínio inválido não revelará dados da implantação;
- convites deverão carregar implantação protegida e validada.

---

# 12. Persistência

## 12.1 Coluna de implantação

Entidades operacionais deverão possuir:

```text
implantacao_id NOT NULL
```

Exceções deverão ser justificadas no modelo de dados.

## 12.2 Chaves estrangeiras

Relacionamentos operacionais deverão impedir referências cruzadas. Estratégias possíveis:

- chave estrangeira composta por `implantacao_id` e `id`;
- constraint adicional;
- chave estrangeira normal mais validação estrutural complementar.

A forma física será definida com prova técnica no ADR-003 e nas migrations. A aplicação sozinha não deverá ser a única barreira quando o PostgreSQL puder garantir a integridade.

## 12.3 Unicidade

Exemplos:

```text
(implantacao_id, codigo_imovel)
(implantacao_id, tipo_documento, documento_normalizado)
(implantacao_id, placa_normalizada, estado_aplicavel)
(implantacao_id, chave_idempotencia)
```

Unicidade global somente será usada quando o domínio realmente exigir.

---

# 13. Consultas e escrita

## 13.1 Leitura

- toda consulta operacional incluirá contexto;
- repositories e query objects receberão implantação;
- paginação e filtros manterão o escopo;
- relações não deverão carregar entidade de outra implantação;
- contagens e dashboards não misturarão clientes;
- exports preservarão o contexto validado.

## 13.2 Escrita

- `implantacao_id` será atribuído pelo servidor;
- entrada do usuário não poderá substituí-lo;
- atualização verificará que entidade pertence ao contexto;
- operações em lote validarão cada alvo;
- importações terão implantação fixa por lote;
- criação por integração usará a implantação da credencial técnica.

## 13.3 Falha segura

Recurso fora do contexto deverá responder como não encontrado ou não autorizado conforme política de segurança, sem confirmar sua existência.

---

# 14. Laravel e proteção na aplicação

A implementação deverá combinar:

- middleware de resolução;
- objeto imutável de contexto;
- services e casos de uso que exijam contexto;
- scopes ou builders seguros;
- policies;
- validação de associação;
- factories e helpers de teste;
- proibição de consultas operacionais sem contexto;
- análise arquitetural.

Global scopes poderão ser usados como proteção adicional, mas não deverão:

- esconder comportamento de comandos globais;
- impedir tarefas administrativas legítimas;
- ser removidos indiscriminadamente;
- substituir policies;
- tornar testes incapazes de detectar vazamento.

Consultas sem scope deverão usar API explícita, restrita e auditada.

---

# 15. Row-Level Security

## 15.1 Posição

PostgreSQL Row-Level Security — RLS é uma defesa adicional desejável, mas sua adoção no MVP permanece condicionada.

## 15.2 Prova necessária

Deverá validar:

- configuração segura do tenant por conexão/transação;
- compatibilidade com pool de conexões;
- workers;
- scheduler;
- migrations;
- administração global;
- testes;
- ferramentas de suporte;
- prevenção de contexto residual;
- desempenho.

## 15.3 Decisão provisória

- isolamento na aplicação e nas constraints é obrigatório;
- RLS não será simulada de forma incompleta;
- se a prova for aprovada, este ADR ganhará versão ou ADR complementar;
- ausência inicial de RLS não reduz os testes obrigatórios.

---

# 16. Cache

Toda chave de cache operacional deverá incluir:

```text
ambiente + implantação + módulo + recurso + versão
```

## 16.1 Regras

- nenhuma chave dependerá apenas do identificador da entidade;
- tags, quando disponíveis, incluirão implantação;
- invalidação será local ao contexto;
- cache global somente para dados realmente globais;
- dados sensíveis terão armazenamento mínimo;
- troca de implantação não reutilizará cache do usuário anterior;
- locks distribuídos incluirão implantação;
- rate limits poderão combinar usuário, implantação, IP e operação.

---

# 17. Filas e jobs

Todo job operacional deverá carregar:

- identificador da implantação;
- correlação;
- identificador da operação;
- versão do contrato;
- dados mínimos.

Ao processar:

1. carregar implantação;
2. validar estado;
3. estabelecer contexto;
4. validar recurso;
5. executar idempotentemente;
6. limpar contexto ao finalizar;
7. registrar resultado.

O job não confiará em contexto residual do worker. Falha de uma implantação não deverá bloquear indefinidamente as demais; filas dedicadas poderão ser criadas quando volume ou criticidade justificar.

---

# 18. Arquivos S3

Chaves de objetos deverão usar prefixo opaco por implantação:

```text
ambiente/{implantacao_opaca}/{categoria}/{identificador_opaco}
```

## 18.1 Regras

- nome não conterá CPF, placa, pessoa ou imóvel;
- metadado no PostgreSQL terá `implantacao_id`;
- URL temporária exigirá autorização no contexto;
- upload será associado pelo servidor;
- listagem de bucket não será exposta;
- exports ficarão no prefixo da implantação;
- regras de retenção respeitarão implantação e categoria;
- um bucket por implantação não é exigido no MVP;
- bucket dedicado futuro poderá ser suportado por configuração e ADR-006.

---

# 19. Integrações e equipamentos

Cada equipamento, adaptador e credencial técnica pertencerá a uma implantação ou a um escopo explicitamente aprovado.

- callback resolverá implantação pela credencial e pelo equipamento;
- payload não poderá escolher implantação livremente;
- identificador externo terá unicidade por implantação e adaptador;
- comando levará contexto e correlação;
- webhook inválido falhará antes de buscar dados operacionais;
- segredo será separado por implantação;
- filas e estados de sincronização manterão escopo;
- simuladores não misturarão dados entre cenários.

---

# 20. Auditoria e logs

## 20.1 Auditoria

Eventos de auditoria operacionais terão `implantacao_id`. Acesso administrativo global registrará:

- ator;
- autoridade;
- implantações acessadas;
- finalidade;
- justificativa;
- instante;
- resultado.

## 20.2 Logs técnicos

- poderão incluir identificador opaco da implantação;
- não incluirão nome do cliente quando desnecessário;
- não conterão documentos ou segredos;
- consultas operacionais usarão correlação;
- acesso aos logs seguirá menor privilégio;
- agregação não autoriza exposição entre clientes.

---

# 21. Administração global

A administração normal ocorrerá dentro da implantação.

Um futuro administrador global:

- terá papel separado;
- não receberá acesso implícito por ser desenvolvedor ou suporte;
- usará credencial individual;
- exigirá justificativa quando acessar dados operacionais;
- terá ações auditadas;
- poderá receber acesso temporário;
- não aparecerá como perfil comum da implantação;
- não poderá alterar dados sem caso de uso específico.

A interface de administração global está fora do MVP, salvo necessidade formal.

---

# 22. Relatórios e indicadores

- relatórios operacionais serão filtrados por implantação;
- fórmula e período serão definidos por indicador;
- export terá implantação registrada;
- relatórios entre implantações exigirão permissão global e finalidade;
- agregados não deverão permitir reidentificação indevida;
- consultas analíticas futuras não acessarão produção sem contrato;
- materialização e data warehouse ficam fora desta decisão.

---

# 23. Backup, restauração e portabilidade

## 23.1 Backup

O banco compartilhado será protegido como conjunto, com criptografia, retenção e testes de restauração.

## 23.2 Restauração por implantação

Restauração seletiva exigirá processo lógico:

1. restaurar backup em ambiente isolado;
2. selecionar registros pela implantação;
3. validar dependências;
4. reconciliar arquivos;
5. importar por operação controlada;
6. auditar;
7. homologar.

## 23.3 Portabilidade futura

O `implantacao_id` explícito facilita:

- exportar dados;
- mover cliente para banco dedicado;
- anonimizar conjuntos;
- reconciliar S3;
- validar integridade.

Uma ferramenta de migração para banco dedicado será criada apenas quando houver demanda.

---

# 24. Exclusão e encerramento de implantação

Encerrar uma implantação não apagará dados imediatamente.

O fluxo deverá:

- bloquear novos acessos;
- revogar credenciais e integrações;
- interromper jobs;
- preservar histórico;
- impedir login operacional;
- aplicar retenção contratual e legal;
- permitir export autorizado;
- registrar aprovação;
- descartar dados somente por processo específico.

Exclusão em cascata da implantação será proibida no fluxo operacional.

---

# 25. Segurança e ameaças

| Ameaça | Controle |
|---|---|
| ID de implantação alterado no formulário | contexto atribuído pelo servidor |
| consulta sem filtro | APIs de persistência e testes arquiteturais |
| relação cruzada | constraints e validação |
| chave de cache colidida | namespace por implantação |
| worker reutilizar contexto | estabelecimento e limpeza por job |
| callback escolher tenant | resolução por credencial/equipamento |
| URL de arquivo cruzada | autorização e expiração |
| export de outro cliente | contexto, permissão e auditoria |
| suporte com acesso irrestrito | papel excepcional e justificativa |
| log revelar cliente | identificadores opacos e sanitização |
| importação misturar dados | implantação fixa por lote |
| bypass de global scope | API restrita e teste |

---

# 26. Testes obrigatórios

## 26.1 Isolamento de leitura

Para toda consulta relevante:

- criar dados equivalentes em duas implantações;
- autenticar no contexto A;
- confirmar visibilidade somente de A;
- tentar identificador conhecido de B;
- confirmar falha segura.

## 26.2 Isolamento de escrita

- impedir criação com FK de outra implantação;
- impedir atualização cruzada;
- impedir operação em lote mista;
- impedir vínculo cruzado;
- impedir arquivo cruzado.

## 26.3 Infraestrutura

- cache sem colisão;
- job restabelece contexto;
- worker limpa contexto;
- filas preservam implantação;
- arquivos usam prefixo correto;
- callbacks resolvem implantação;
- auditoria registra implantação;
- exports permanecem isolados.

## 26.4 Segurança

Os testes de isolamento deverão fazer parte do pipeline e não apenas da homologação manual.

---

# 27. Observabilidade

Métricas operacionais poderão ser segmentadas por identificador opaco de implantação, respeitando cardinalidade e privacidade.

Alertas deverão detectar:

- consulta operacional sem contexto;
- job sem implantação;
- tentativa de vínculo cruzado;
- callback sem equipamento válido;
- erro de autorização entre implantações;
- cache com chave fora do padrão;
- export global;
- acesso administrativo excepcional.

Não serão usados dados pessoais como labels de métricas.

---

# 28. Consequências positivas

- uma base e migrations para o MVP;
- transações simples;
- menor custo operacional;
- onboarding direto;
- contexto explícito em todas as camadas;
- possibilidade de usuários multi-implantação;
- testes determinísticos;
- arquivos e integrações segregados;
- caminho para evolução híbrida;
- isolamento alinhado ao domínio.

---

# 29. Consequências negativas

- isolamento depende de múltiplas barreiras lógicas;
- restauração seletiva é mais trabalhosa;
- índices compostos serão maiores;
- toda consulta exige disciplina;
- administração global demanda cuidado;
- erro estrutural pode afetar mais de um cliente;
- eventual migração para banco dedicado exigirá ferramenta;
- RLS não estará garantida até prova técnica.

Essas consequências são aceitas para o MVP com os controles definidos.

---

# 30. Riscos e mitigações

| Risco | Mitigação |
|---|---|
| desenvolvedor esquecer o filtro | abstração, contexto obrigatório e testes |
| `implantacao_id` vindo da entrada | atribuição exclusiva no servidor |
| global scope removido | API explícita e revisão |
| contexto residual em worker | limpeza obrigatória e teste |
| FK simples permitir cruzamento | constraint composta ou equivalente |
| relatório global indevido | permissão excepcional |
| backup compartilhado dificultar restauração | procedimento e export por tenant |
| crescimento exigir isolamento físico | métricas e ADR de evolução |
| RLS mal configurada criar falsa segurança | prova antes da adoção |
| entidade global acumular dados de cliente | regra de classificação e revisão |

---

# 31. Estratégia de implementação

1. aprovar este ADR;
2. aprovar ADR-003;
3. definir `implantacoes` e associações de usuário;
4. implementar contexto imutável;
5. definir padrão de chaves e constraints;
6. criar middleware e policies;
7. criar helpers de teste com duas implantações;
8. implementar módulo piloto;
9. provar jobs, cache e arquivos;
10. executar teste de vazamento;
11. avaliar RLS;
12. documentar acesso administrativo;
13. incluir verificações no pipeline.

Nenhum módulo operacional deverá ser considerado pronto sem teste de isolamento.

---

# 32. Validação

A decisão será validada quando:

- duas implantações coexistirem no mesmo banco de homologação;
- consultas e escritas cruzadas falharem;
- constraints impedirem relacionamentos inválidos;
- usuário multi-implantação trocar contexto com segurança;
- job não reutilizar contexto anterior;
- cache e locks não colidirem;
- arquivos só forem acessados no contexto correto;
- callback resolver implantação pela credencial;
- auditoria registrar o tenant;
- export permanecer isolado;
- pipeline executar a suíte de isolamento.

---

# 33. Critérios de aceite

**CA-ADR-002-001:** entidades operacionais possuem implantação explícita.

**CA-ADR-002-002:** entidades globais formam conjunto mínimo e documentado.

**CA-ADR-002-003:** o contexto é resolvido e validado no servidor.

**CA-ADR-002-004:** parâmetros do navegador não concedem acesso à implantação.

**CA-ADR-002-005:** unicidades operacionais incluem o escopo correto.

**CA-ADR-002-006:** relacionamentos cruzados são impedidos.

**CA-ADR-002-007:** consultas e escritas exigem contexto.

**CA-ADR-002-008:** usuários multi-implantação possuem associação explícita.

**CA-ADR-002-009:** permissões são avaliadas por implantação.

**CA-ADR-002-010:** pessoas são isoladas por implantação no MVP.

**CA-ADR-002-011:** cache e locks incluem implantação.

**CA-ADR-002-012:** jobs estabelecem e limpam o contexto.

**CA-ADR-002-013:** arquivos possuem prefixo e autorização por implantação.

**CA-ADR-002-014:** integrações resolvem implantação por credencial técnica.

**CA-ADR-002-015:** auditoria registra implantação e acesso global excepcional.

**CA-ADR-002-016:** exports permanecem segregados.

**CA-ADR-002-017:** encerramento não executa exclusão destrutiva.

**CA-ADR-002-018:** testes com duas implantações fazem parte do pipeline.

**CA-ADR-002-019:** RLS permanece condicionada a prova técnica.

**CA-ADR-002-020:** isolamento físico futuro exigirá novo ADR.

---

# 34. Rastreabilidade

## 34.1 Documentos

- `docs/000_DIRETRIZES_DO_PROJETO.md`;
- `docs/001_VOLUME_01_PRODUCT_BOOK_PARTE_01.md`;
- `docs/009_REGRAS_DE_NEGOCIO.md`;
- `docs/010_BANCO_DE_DADOS.md`;
- `docs/011_ARQUITETURA_DO_SISTEMA.md`;
- `docs/ADR/000_CATALOGO_DE_ADRS.md`;
- `docs/ADR/ADR-001_MONOLITO_MODULAR_LARAVEL.md`.

## 34.2 Regras

- `RN-007` — pessoa única no contexto;
- `RN-014` — documento duplicado;
- `RN-035` — duplicidade de placa;
- `RN-046` a `RN-049` — auditoria;
- `RN-050` a `RN-054` — usuários;
- `RN-055` — segregação por implantação;
- `RN-064` — duplicidade segura;
- `RN-066` — arquivos protegidos;
- `RN-090` — identificador externo secundário;
- `RN-092` — fila idempotente;
- `RN-097` a `RN-100` — permissões e segurança.

---

# 35. Dependências

| ADR | Relação |
|---|---|
| ADR-001 | estilo arquitetural já aprovado |
| ADR-003 | identificadores e chaves compostas |
| ADR-004 | auditoria e eventos com implantação |
| ADR-005 | cache, locks, filas e idempotência |
| ADR-006 | estratégia S3 por implantação |
| ADR-007 | credenciais e equipamentos por implantação |
| ADR-009 | segredos segregados |
| ADR-010 | observabilidade sem exposição |
| ADR-012 | backup, deploy e restauração |

---

# 36. Pendências

| PEN-ADR-002 | Pendência | Tratamento |
|---|---|---|
| PEN-ADR-002-001 | Estratégia física de FK com implantação | ADR-003 e prova PostgreSQL |
| PEN-ADR-002-002 | Adoção de Row-Level Security | prova técnica |
| PEN-ADR-002-003 | Modelo final da identidade de usuário | especificação de autenticação |
| PEN-ADR-002-004 | Domínio ou subdomínio por implantação | infraestrutura e APIs |
| PEN-ADR-002-005 | Processo de restauração seletiva | ADR-012 e manual operacional |
| PEN-ADR-002-006 | Requisitos para banco dedicado futuro | demanda contratual |
| PEN-ADR-002-007 | Política de suporte e administração global | segurança e operação |

---

# 37. Aprovação

| Papel | Nome | Decisão | Data | Observações |
|---|---|---|---|---|
| Product Owner | Vinicius Velasco de Azevedo | Pendente | — | Aguardando decisão |
| Responsável técnico | Soluções do Vale Tecnologia | Recomendado | Julho/2026 | Banco e schema compartilhados com isolamento em profundidade |

---

# 38. Decisão resultante

Enquanto este ADR estiver **Proposto**, a alternativa de banco e schema compartilhados permanece recomendada, mas migrations operacionais continuam bloqueadas.

Se aprovado:

- o catálogo será atualizado no mesmo commit;
- entidades operacionais exigirão implantação;
- contexto será obrigatório em todas as camadas;
- o Manual do Desenvolvedor detalhará os padrões;
- ADR-003 definirá identificadores e integridade física;
- eventual mudança para schema ou banco dedicado exigirá novo ADR.

---

## Situação do ADR

**Proposto.** Aguardando aprovação formal do Product Owner.
