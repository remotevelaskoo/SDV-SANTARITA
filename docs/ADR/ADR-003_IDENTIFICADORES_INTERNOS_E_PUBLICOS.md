# ADR-003 — IDENTIFICADORES INTERNOS E PÚBLICOS

**Identificador:** ADR-003
**Versão:** 1.0.1
**Status:** Aprovado
**Prioridade:** P0 — Bloqueador
**Produto:** SDV Access — Implantação Santa Rita
**Responsável pelo produto:** Vinicius Velasco de Azevedo
**Responsável técnico:** Soluções do Vale Tecnologia
**Data:** Julho de 2026

---

## Controle de versões

| Versão | Data | Responsável | Alteração |
|---|---|---|---|
| 1.0.0 | Julho/2026 | Soluções do Vale | Proposta da estratégia de identificadores internos, públicos e externos |
| 1.0.1 | 30/07/2026 | Product Owner | Aprovação formal da estratégia de identificadores internos, públicos e externos |

---

# 1. Contexto

O SDV Access precisa identificar entidades em:

- tabelas PostgreSQL;
- URLs autenticadas;
- pré-cadastros públicos;
- protocolos de atendimento;
- filas e eventos;
- arquivos;
- integrações com equipamentos;
- auditoria;
- imports e exports.

Os identificadores deverão funcionar em uma plataforma multi-implantação, impedir colisões, preservar rastreabilidade e não expor sequências, dados pessoais ou chaves de fabricantes.

O ADR-001 aprovou o monólito modular Laravel. O ADR-002 aprovou banco e schema compartilhados, `implantacao_id` nas entidades operacionais e isolamento em profundidade.

---

# 2. Problema

Definir:

- o tipo das chaves primárias;
- onde os identificadores são gerados;
- quando a chave interna poderá aparecer;
- quando haverá identificador público separado;
- como convites, protocolos e códigos serão formados;
- como relacionamentos impedirão cruzamento entre implantações;
- como IDs externos serão armazenados;
- como migrar dados legados;
- como validar e testar unicidade.

A decisão bloqueia migrations, models, contratos de API, eventos, filas e integração.

---

# 3. Objetivos

- unicidade sem coordenação central por sequência;
- geração segura pela aplicação;
- boa localidade de inserção;
- tipo nativo no PostgreSQL;
- uso consistente em módulos e filas;
- escopo explícito por implantação;
- ausência de dados pessoais no identificador;
- não previsibilidade nos fluxos públicos;
- independência de fabricante;
- compatibilidade com Laravel;
- rastreabilidade durante migrações.

---

# 4. Não objetivos

Este ADR não define:

- política completa de autenticação;
- formato final de URLs;
- esquema integral das tabelas;
- criptografia de documentos;
- números fiscais;
- placas como identificadores técnicos;
- IDs de sessão;
- fornecedor de equipamentos;
- formato de QR Code além de seu identificador lógico;
- numeração financeira ou fiscal regulada.

---

# 5. Base técnica

O UUID é um valor de 128 bits padronizado. O UUIDv7 utiliza um campo temporal baseado na época Unix e componentes de aleatoriedade.

O PostgreSQL oferece o tipo nativo `uuid`, capaz de armazenar UUIDs independentemente da origem. A geração poderá ocorrer fora do banco.

O ecossistema Laravel oferece geração de UUIDv7. A versão mínima do framework será definida posteriormente, mas deverá manter suporte equivalente antes da implementação.

Fontes oficiais:

- [RFC 9562 — Universally Unique IDentifiers](https://www.rfc-editor.org/rfc/rfc9562.html);
- [PostgreSQL — UUID Type](https://www.postgresql.org/docs/current/datatype-uuid.html);
- [Laravel — `Str::uuid7()`](https://laravel.com/docs/master/strings#method-str-uuid7).

---

# 6. Terminologia

| Termo | Definição |
|---|---|
| Chave interna | identidade primária persistida da entidade |
| Identificador público | identidade exposta fora do limite interno quando necessária |
| Token | segredo temporário ou revogável que concede entrada em fluxo |
| Protocolo | código de consulta ou comunicação, sem ser chave de autorização |
| Chave natural | dado do domínio que pode parecer único, como documento ou placa |
| Referência externa | identificador fornecido por fabricante ou serviço |
| Chave de idempotência | identificador de uma operação repetível |
| Correlação | identificador que conecta etapas de um fluxo |
| UUIDv7 | UUID com componente temporal e aleatoriedade, conforme RFC 9562 |

Identificador não será tratado automaticamente como segredo.

---

# 7. Alternativas para chaves internas

## 7.1 Alternativa A — Inteiro sequencial

### Vantagens

- armazenamento compacto;
- índices eficientes;
- simplicidade histórica;
- leitura fácil em suporte.

### Desvantagens

- previsível;
- coordenação pela sequência;
- colisão em importação ou combinação de bancos;
- incentivo à exposição indevida;
- menor portabilidade futura.

---

## 7.2 Alternativa B — UUIDv4

### Vantagens

- amplamente suportado;
- aleatório;
- geração distribuída;
- baixa previsibilidade.

### Desvantagens

- inserções aleatórias nos índices;
- menor ordenação temporal;
- pior localidade que UUIDv7.

---

## 7.3 Alternativa C — UUIDv7

### Vantagens

- padrão aberto;
- geração distribuída;
- ordenação temporal aproximada;
- melhor localidade de índice que UUIDv4;
- tipo nativo `uuid`;
- suporte no ecossistema aprovado.

### Desvantagens

- maior que inteiro;
- revela aproximadamente o instante embutido;
- não substitui autorização;
- exige padronização da geração;
- relógio e monotonicidade exigem testes.

---

## 7.4 Alternativa D — ULID

### Vantagens

- ordenável;
- representação textual amigável;
- geração distribuída.

### Desvantagens

- não utiliza o tipo nativo `uuid`;
- requer convenções adicionais;
- menor alinhamento com a base aprovada;
- benefício insuficiente frente ao UUIDv7.

---

# 8. Matriz de avaliação

Escala: 1 — desfavorável; 5 — muito favorável.

| Critério | Peso | Inteiro | UUIDv4 | UUIDv7 | ULID |
|---|---:|---:|---:|---:|---:|
| Geração distribuída | 4 | 1 | 5 | 5 | 5 |
| Localidade de índice | 4 | 5 | 2 | 4 | 4 |
| Tipo nativo PostgreSQL | 4 | 5 | 5 | 5 | 2 |
| Portabilidade de dados | 4 | 2 | 5 | 5 | 4 |
| Compatibilidade Laravel | 4 | 5 | 5 | 5 | 4 |
| Não sequencialidade | 3 | 1 | 5 | 4 | 4 |
| Ordenação aproximada | 3 | 5 | 1 | 5 | 5 |
| Padronização aberta | 3 | 5 | 5 | 5 | 4 |

UUIDv7 apresenta o melhor equilíbrio para as chaves internas.

---

# 9. Decisão proposta

Adotar:

- UUIDv7 para chaves primárias das entidades de aplicação;
- tipo PostgreSQL `uuid`;
- geração na aplicação Laravel por serviço único;
- `id` como nome padrão da chave primária;
- UUID em formato canônico textual nas fronteiras;
- `implantacao_id` separado nas entidades operacionais;
- chaves compostas de integridade quando necessário ao isolamento;
- identificador público separado somente quando houver requisito específico;
- tokens públicos criptograficamente aleatórios e armazenados por hash;
- protocolos aleatórios, opacos e sem autoridade por si só;
- referências externas armazenadas separadamente;
- chaves naturais nunca usadas como chave primária;
- nenhum identificador como substituto de autorização.

---

# 10. Geração de UUIDv7

## 10.1 Origem

UUIDv7 será gerado pela aplicação no início da criação da entidade.

Isso permite:

- identificar a entidade antes da persistência;
- correlacionar logs e eventos;
- criar relações em memória;
- evitar dependência de retorno de sequência;
- testar geração de forma isolada.

## 10.2 Serviço único

A geração deverá passar por abstração compartilhada mínima, permitindo:

- produção;
- relógio controlado em testes;
- validação de versão;
- detecção de implementação incompatível;
- futura troca controlada.

Não haverá geradores diferentes por módulo.

## 10.3 Banco

O banco poderá possuir default defensivo somente se a estratégia de migration exigir. A aplicação continuará sendo a origem padrão. A geração duplicada ou ambígua será evitada.

---

# 11. Relógio, ordenação e monotonicidade

UUIDv7 oferece ordenação temporal aproximada, mas:

- não substitui `created_at`;
- não é prova de instante do negócio;
- não será usado para auditoria temporal;
- não definirá ordem absoluta entre nós;
- não substituirá sequência de evento quando uma ordem estrita for exigida;
- relógios de hosts deverão ser sincronizados;
- testes deverão cobrir múltiplos IDs no mesmo milissegundo;
- regressão do relógio não poderá gerar colisão.

Consultas por período usarão timestamps e índices próprios.

---

# 12. Chave interna e URLs autenticadas

O UUIDv7 interno poderá aparecer em URLs autenticadas quando:

- o usuário já estiver autenticado;
- a implantação estiver resolvida;
- policy validar o recurso;
- a existência não for revelada fora do contexto;
- logs e interface não o tratarem como segredo.

Exemplo conceitual:

```text
/imoveis/{uuid}
/pessoas/{uuid}
```

Conhecer o UUID não concede acesso. A aplicação sempre validará usuário, implantação, recurso e ação.

---

# 13. Identificador público separado

Uma coluna `public_id` ou entidade equivalente será criada somente quando:

- o recurso for referenciado sem autenticação;
- a chave precisar ser rotacionada ou revogada;
- o identificador interno não deva atravessar o limite;
- a exposição temporal do UUIDv7 for inadequada;
- o contrato externo exigir estabilidade independente;
- houver risco de correlação entre contextos.

## 13.1 Formato

Quando for apenas identidade pública estável:

- UUIDv4 ou valor aleatório equivalente;
- tipo e índice documentados;
- unicidade no escopo correto;
- ausência de dados pessoais.

Quando conceder acesso:

- usar token secreto separado;
- nunca confiar apenas em `public_id`.

Não serão adicionados dois identificadores a todas as tabelas sem necessidade.

---

# 14. Tokens públicos

Aplicam-se a:

- convites de pré-cadastro;
- correção de solicitação;
- recuperação de acesso;
- links temporários;
- URLs assinadas quando aplicável.

## 14.1 Regras

- gerar com fonte criptograficamente segura;
- entropia adequada ao risco;
- armazenar somente hash quando a verificação permitir;
- exibir valor em claro apenas na emissão;
- possuir implantação;
- possuir finalidade;
- possuir validade;
- possuir limite de uso;
- permitir revogação;
- usar comparação segura;
- não registrar o valor em logs;
- invalidar após uso quando for uso único.

Token não será UUIDv7 por padrão.

---

# 15. Protocolos

Protocolos serão usados para comunicação e pesquisa controlada, não para autorização.

## 15.1 Requisitos

- aleatórios e não sequenciais;
- não conter CPF, telefone, placa ou nome;
- não conter identificação direta de imóvel;
- não codificar `implantacao_id`;
- evitar caracteres ambíguos;
- ser normalizados para entrada;
- possuir índice;
- ter unicidade no escopo definido;
- ser combinados com validação adicional quando consultados publicamente.

## 15.2 Formato conceitual

```text
PREFIXO-CODIGO_ALEATORIO
```

O prefixo poderá indicar o tipo funcional, sem indicar cliente ou dado pessoal. O tamanho, alfabeto e checksum serão definidos no contrato de UX/API com prova de colisão e usabilidade.

---

# 16. Identificadores de implantação

Implantações terão:

- `id` interno UUIDv7;
- código institucional ou slug somente quando necessário;
- possível identificador público para resolução de domínio;
- domínio ou subdomínio como configuração, não como chave primária.

Slugs:

- não serão autoridade;
- poderão mudar com histórico ou redirecionamento;
- terão unicidade apropriada;
- não serão usados como FK.

---

# 17. Integridade multi-implantação

Entidades operacionais terão:

```text
id uuid PRIMARY KEY
implantacao_id uuid NOT NULL
```

Para relacionamentos críticos, será adotado padrão que permita ao PostgreSQL impedir referência cruzada:

```text
UNIQUE (implantacao_id, id)
FOREIGN KEY (implantacao_id, entidade_id)
  REFERENCES entidades (implantacao_id, id)
```

## 17.1 Decisão

- `id` permanece chave primária globalmente única;
- `(implantacao_id, id)` será chave candidata para integridade;
- FKs operacionais críticas incluirão implantação;
- a aplicação também validará o contexto;
- exceções deverão ser documentadas;
- o desenho será provado em migration piloto.

O custo adicional de índices é aceito pela proteção contra vínculo cruzado.

---

# 18. Chaves naturais

Não serão chaves primárias:

- CPF e outros documentos;
- placa;
- e-mail;
- telefone;
- código do imóvel;
- tag;
- código de equipamento;
- protocolo;
- identificador externo.

Esses valores:

- podem mudar;
- possuem escopos diferentes;
- podem conter dados pessoais;
- podem ser reutilizados historicamente;
- podem não existir;
- exigem normalização.

Serão protegidos por constraints e índices próprios conforme as regras do domínio.

---

# 19. Referências externas

IDs de fabricantes, controladoras e serviços serão armazenados em entidade própria ou estrutura equivalente.

Campos conceituais:

- implantação;
- adaptador;
- sistema externo;
- tipo da entidade;
- ID interno;
- ID externo;
- versão;
- estado;
- vigência;
- metadados sanitizados.

## 19.1 Regras

- ID externo nunca substitui `id`;
- unicidade inclui implantação e adaptador;
- tipo e tamanho serão limitados;
- valor será tratado como string;
- mudanças preservarão histórico;
- ausência de ID externo não invalida a entidade interna;
- payload não poderá escolher implantação livremente;
- IDs externos não serão expostos sem necessidade.

---

# 20. Chaves de idempotência

Chaves de idempotência serão distintas do ID da entidade.

## 20.1 Formação

Poderão ser:

- fornecidas pelo cliente confiável;
- geradas pela aplicação;
- derivadas de evento externo estável em namespace controlado.

## 20.2 Escopo

A unicidade deverá incluir:

```text
implantacao + operação + chave
```

## 20.3 Regras

- não conter segredo;
- tamanho limitado;
- normalização documentada;
- hash quando o valor externo for grande ou sensível;
- resposta ou resultado associado;
- período de retenção definido;
- repetição com payload divergente deverá falhar.

---

# 21. Identificadores de correlação

Cada requisição ou fluxo relevante terá `correlation_id`.

- preferencialmente UUIDv7;
- criado na borda se ausente ou inválido;
- propagado para casos de uso, filas e integrações;
- não usado como chave da entidade;
- não usado como autorização;
- não conter dado pessoal;
- incluído em logs e erros sanitizados;
- separado de `causation_id` quando eventos exigirem relação causal.

Cabeçalhos externos serão validados antes de reutilização.

---

# 22. Eventos, comandos e auditoria

- eventos terão UUIDv7 próprio;
- comandos terão UUIDv7 próprio;
- tentativas terão identidade própria;
- evento não reutilizará ID do agregado;
- auditoria terá identidade imutável;
- referências a entidades usarão UUID interno;
- snapshots não substituirão FKs quando integridade for necessária;
- correlação conectará o fluxo;
- identificador de equipamento permanecerá externo e secundário.

Essa separação preserva `RN-056` e `RN-078`.

---

# 23. Arquivos

Arquivos terão UUIDv7 interno no banco.

A chave do objeto S3:

- usará identificadores opacos;
- incluirá prefixo opaco de implantação;
- não conterá nomes, documentos ou placas;
- não será autoridade de acesso;
- poderá ser diferente do ID do arquivo;
- permanecerá privada.

URLs temporárias serão geradas pelo provedor e não serão persistidas como identificador permanente.

---

# 24. QR Codes, tags e credenciais

O identificador físico ou apresentado de uma credencial será separado da entidade `credencial`.

## 24.1 Credencial

- `id` UUIDv7 interno;
- tipo;
- estado;
- vigência;
- sujeito;
- referência protegida.

## 24.2 Material da credencial

- tag poderá possuir serial normalizado;
- QR Code poderá carregar token ou referência assinada;
- placa continuará dado operacional normalizado;
- template biométrico terá referência protegida;
- código não será chave primária;
- revogação não apagará histórico.

O material secreto não será registrado em logs.

---

# 25. APIs

## 25.1 Representação

UUIDs serão serializados no formato textual canônico.

## 25.2 Entrada

- validar sintaxe;
- limitar tamanho antes de parsing;
- não aceitar identificador como autorização;
- resolver dentro da implantação;
- responder de forma segura a recurso inexistente ou fora do contexto.

## 25.3 Contratos

- tipo de identificador será documentado;
- alteração do formato público exigirá versionamento;
- IDs internos e externos terão campos distintos;
- APIs não aceitarão ID de fabricante no lugar do ID SDV;
- listas serão paginadas sem depender apenas do UUID para ordem funcional.

---

# 26. Interface e suporte

UUIDs não deverão ser o principal texto apresentado ao operador.

A interface usará:

- nome;
- unidade;
- placa;
- protocolo;
- código operacional aprovado;
- trecho curto do identificador apenas para suporte, quando necessário.

Não haverá truncamento usado como chave de busca única. Copiar identificador completo poderá ser permitido apenas em áreas técnicas autorizadas.

---

# 27. Logs e privacidade

- IDs internos podem aparecer em logs estruturados;
- tokens nunca aparecerão;
- protocolos poderão aparecer quando necessário e classificados;
- documentos e placas serão mascarados;
- IDs externos serão sanitizados;
- correlação será preferida para suporte;
- URLs serão limpas de query strings sensíveis;
- UUIDv7 não será descrito como anônimo;
- identificadores não serão usados para rastreamento fora da finalidade.

O componente temporal do UUIDv7 reforça que ele não deve ser tratado como segredo.

---

# 28. Migração de dados legados

Cada registro importado receberá novo UUIDv7 interno.

A origem será preservada em mapeamento:

```text
lote
origem
tipo
id_legado
id_sdv
implantacao
resultado
```

## 28.1 Regras

- ID legado não será usado como chave primária;
- colisões serão detectadas por origem e tipo;
- relacionamentos serão reconstruídos pelo mapa;
- IDs legados não serão expostos por padrão;
- importação será idempotente;
- reconciliação verificará totais e amostras;
- falha não deixará FKs parcialmente convertidas.

---

# 29. Índices e desempenho

- chave primária em `id`;
- índice candidato em `(implantacao_id, id)` quando usado por FK;
- índices compostos iniciarão por implantação quando os filtros assim exigirem;
- UUID será armazenado no tipo nativo, não em `varchar`;
- protocolo terá índice próprio;
- token armazenado por hash terá índice de hash lógico adequado;
- referência externa terá índice por implantação, adaptador e valor;
- planos de consulta serão medidos;
- UUID não substituirá índices de timestamps e estados.

A estratégia evita otimização prematura, mas protege as consultas multi-implantação.

---

# 30. Segurança e ameaças

| Ameaça | Controle |
|---|---|
| enumerar inteiro sequencial | UUIDv7 e autorização |
| considerar UUID um segredo | policies e documentação |
| usar token em texto no banco | hash |
| protocolo conceder acesso | validação adicional |
| ID externo substituir chave SDV | mapeamento separado |
| FK cruzar implantação | chave composta |
| documento virar PK | UUID interno |
| URL revelar dado pessoal | identificador opaco |
| log capturar token | sanitização |
| correlação controlada pelo atacante | validação ou substituição |
| colisão em importação | namespace por origem |
| timestamp do UUID revelar contexto | ID público separado quando necessário |

---

# 31. Consequências positivas

- chaves consistentes em todos os módulos;
- geração antes da persistência;
- melhor localidade que UUIDv4;
- tipo nativo no PostgreSQL;
- portabilidade entre bancos;
- baixo risco de colisão;
- proteção estrutural multi-implantação;
- IDs externos desacoplados;
- fluxos públicos com credenciais próprias;
- imports rastreáveis;
- correlação uniforme.

---

# 32. Consequências negativas

- UUID ocupa mais espaço que inteiro;
- índices compostos aumentam armazenamento;
- suporte humano não memoriza IDs;
- UUIDv7 revela tempo aproximado;
- geração exige relógio confiável;
- tokens e protocolos adicionam entidades ou colunas;
- relações compostas tornam migrations mais detalhadas;
- alguns pacotes poderão assumir chaves inteiras;
- testes precisarão controlar geradores.

Esses custos são aceitos em favor de integridade, portabilidade e isolamento.

---

# 33. Riscos e mitigações

| Risco | Mitigação |
|---|---|
| versões diferentes de UUID | serviço único e teste |
| relógio retroceder | biblioteca confiável e teste |
| pacote Laravel incompatível | prova antes da adoção |
| UUID usado como autorização | policies obrigatórias |
| duplicar `public_id` sem necessidade | critério explícito |
| token com pouca entropia | gerador criptográfico |
| protocolo difícil de digitar | alfabeto e teste de UX |
| índice excessivo | revisão por consulta real |
| FK composta esquecida | padrão de migration e teste |
| ID legado vazar | mapeamento privado |

---

# 34. Estratégia de implementação

1. aprovar este ADR;
2. definir serviço de geração UUIDv7;
3. provar suporte no baseline Laravel/PHP;
4. criar migration piloto com `uuid`;
5. criar constraint `(implantacao_id, id)`;
6. provar FK composta entre dois módulos;
7. criar casts e validações;
8. definir factories determinísticas;
9. criar gerador de token seguro;
10. definir protocolo com UX/API;
11. implementar mapeamento de IDs externos;
12. testar importação e idempotência;
13. documentar convenções no Manual do Desenvolvedor.

---

# 35. Validação

A decisão será validada quando:

- IDs gerados forem UUIDv7 válidos;
- geração concorrente não produzir colisões;
- inserções preservarem desempenho aceitável;
- FK composta impedir implantação cruzada;
- URL autenticada exigir policy;
- token persistir somente hash;
- protocolo não revelar dado;
- ID externo mapear sem substituir chave interna;
- job e evento preservarem UUID e correlação;
- importação reconstruir relações;
- testes executarem no pipeline.

---

# 36. Critérios de aceite

**CA-ADR-003-001:** chaves internas usam UUIDv7.

**CA-ADR-003-002:** PostgreSQL armazena IDs no tipo `uuid`.

**CA-ADR-003-003:** a aplicação é a origem padrão dos UUIDs.

**CA-ADR-003-004:** existe um gerador central testável.

**CA-ADR-003-005:** UUID não substitui `created_at`.

**CA-ADR-003-006:** conhecer o ID não concede acesso.

**CA-ADR-003-007:** identificador público separado exige justificativa.

**CA-ADR-003-008:** tokens públicos usam aleatoriedade criptográfica.

**CA-ADR-003-009:** tokens verificáveis são armazenados por hash.

**CA-ADR-003-010:** protocolos não contêm dados pessoais ou imóvel.

**CA-ADR-003-011:** entidades operacionais mantêm `implantacao_id`.

**CA-ADR-003-012:** FKs críticas impedem referência cruzada.

**CA-ADR-003-013:** chaves naturais não são chaves primárias.

**CA-ADR-003-014:** IDs externos permanecem secundários.

**CA-ADR-003-015:** idempotência e correlação têm IDs próprios.

**CA-ADR-003-016:** eventos, comandos e auditoria possuem identidade própria.

**CA-ADR-003-017:** arquivos não usam dados pessoais na chave.

**CA-ADR-003-018:** APIs distinguem IDs SDV e externos.

**CA-ADR-003-019:** logs não registram tokens.

**CA-ADR-003-020:** migração preserva mapeamento legado.

**CA-ADR-003-021:** o baseline técnico prova suporte ao UUIDv7.

**CA-ADR-003-022:** mudanças futuras no formato exigem decisão controlada.

---

# 37. Rastreabilidade

## 37.1 Documentos

- `docs/000_DIRETRIZES_DO_PROJETO.md`;
- `docs/009_REGRAS_DE_NEGOCIO.md`;
- `docs/010_BANCO_DE_DADOS.md`;
- `docs/011_ARQUITETURA_DO_SISTEMA.md`;
- `docs/ADR/000_CATALOGO_DE_ADRS.md`;
- `docs/ADR/ADR-001_MONOLITO_MODULAR_LARAVEL.md`;
- `docs/ADR/ADR-002_MULTI_IMPLANTACAO_E_ISOLAMENTO.md`.

## 37.2 Regras

- `RN-003` — identificação do imóvel;
- `RN-014` — documento duplicado;
- `RN-034`, `RN-035` — placa;
- `RN-047` — auditoria;
- `RN-055` — segregação;
- `RN-056` — identidade própria das entidades;
- `RN-064` — duplicidade segura;
- `RN-067` a `RN-069` — convite e protocolo;
- `RN-079`, `RN-085`, `RN-092` — idempotência;
- `RN-090` — referência externa secundária.

---

# 38. Dependências

| ADR | Relação |
|---|---|
| ADR-001 | organização modular já aprovada |
| ADR-002 | escopo e integridade por implantação |
| ADR-004 | IDs de auditoria, eventos e outbox |
| ADR-005 | idempotência e correlação em filas |
| ADR-006 | identificadores de arquivos |
| ADR-007 | referências externas |
| ADR-009 | tokens e segredos |

---

# 39. Pendências

| PEN-ADR-003 | Pendência | Tratamento |
|---|---|---|
| PEN-ADR-003-001 | Baseline mínimo de Laravel/PHP | Manual do Desenvolvedor |
| PEN-ADR-003-002 | Default defensivo no PostgreSQL | migration piloto |
| PEN-ADR-003-003 | Implementação física das FKs compostas | prova PostgreSQL |
| PEN-ADR-003-004 | Formato final dos protocolos | especificação de APIs e teste de UX |
| PEN-ADR-003-005 | Critérios por recurso para `public_id` | especificação de APIs |
| PEN-ADR-003-006 | Entropia e retenção por tipo de token | segurança |
| PEN-ADR-003-007 | Estratégia detalhada de IDs legados Santa Rita | plano de migração |

---

# 40. Aprovação

| Papel | Nome | Decisão | Data | Observações |
|---|---|---|---|---|
| Product Owner | Vinicius Velasco de Azevedo | Aprovado | 30/07/2026 | UUIDv7 interno, tokens seguros e referências externas secundárias aprovados |
| Responsável técnico | Soluções do Vale Tecnologia | Recomendado | Julho/2026 | UUIDv7 interno, tokens seguros e referências externas secundárias |

---

# 41. Decisão resultante

Com este ADR **Aprovado**:

- o catálogo será atualizado no mesmo commit;
- novas entidades usarão UUIDv7;
- o isolamento usará chaves compostas quando aplicável;
- tokens, protocolos e IDs externos seguirão esta separação;
- o Manual do Desenvolvedor definirá implementação e testes;
- alteração estrutural exigirá novo ADR ou substituição formal.

---

## Situação do ADR

**Aprovado.** UUIDv7, tokens seguros, protocolos opacos e referências externas secundárias constituem a estratégia vigente.
