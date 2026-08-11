# SDV ACCESS — BANCO DE DADOS
## Modelo conceitual e lógico de referência

**Documento:** SDV-BDD-010  
**Versão:** 1.0.1
**Status:** Aprovado
**Produto:** SDV Access — Implantação Santa Rita  
**Empresa proprietária:** Soluções do Vale Tecnologia  
**Responsável pelo produto:** Vinicius Velasco de Azevedo  
**Data:** Julho de 2026

---

## Controle de versões

| Versão | Data | Responsável | Alteração |
|---|---|---|---|
| 1.0.0 | Julho/2026 | Soluções do Vale | Definição inicial do modelo conceitual e lógico de dados |
| 1.0.1 | 28/07/2026 | Product Owner | Aprovação formal do modelo conceitual e lógico de dados |

---

# 1. Objetivo

Este documento define o modelo conceitual e lógico de referência do banco de dados do SDV Access, orientado ao PostgreSQL e às regras aprovadas para a implantação Santa Rita.

Seus objetivos são:

- transformar o domínio aprovado em entidades, relacionamentos e restrições;
- manter o imóvel como entidade central;
- separar pessoa, vínculo, autorização, credencial, atendimento, comando e evento;
- assegurar segregação por implantação;
- preservar vigências, estados e históricos;
- definir chaves, unicidades, índices e regras de integridade;
- suportar auditoria, idempotência e integrações desacopladas;
- orientar migrations, models, serviços, testes e relatórios futuros;
- registrar decisões pendentes sem pressupor requisitos.

Este documento não contém migrations executáveis, código Laravel, credenciais, dados reais ou decisões de infraestrutura não aprovadas.

---

# 2. Documentos de origem

O modelo deriva dos seguintes documentos:

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
- imagens oficiais disponíveis em `docs/references/`.

Em caso de conflito, prevalecem as diretrizes e decisões técnicas aprovadas, seguidas pelo catálogo de regras de negócio.

---

# 3. Escopo

## 3.1 Incluído

- organização e implantação;
- estrutura condominial e imóveis;
- pessoas, documentos e contatos;
- vínculos e responsabilidades;
- empresas e prestadores;
- veículos e vínculos veiculares;
- convites e pré-cadastros;
- autorizações e credenciais;
- pontos de acesso, equipamentos e adaptadores;
- atendimentos, decisões, comandos e eventos de acesso;
- contribuição e caixa em nível conceitual;
- usuários, perfis e permissões;
- configurações versionadas;
- arquivos e evidências;
- auditoria, exportações e notificações;
- critérios de integridade, segurança, retenção e migração.

## 3.2 Não incluído

- SQL definitivo;
- migrations Laravel;
- modelo físico particionado;
- dimensionamento de infraestrutura;
- escolha de fornecedor S3;
- contrato específico de fabricantes;
- política jurídica definitiva de retenção;
- dados analíticos ou data warehouse;
- implementação de OCR, biometria ou LPR.

---

# 4. Princípios do modelo

1. **Imóvel central:** pessoas e veículos se relacionam ao imóvel por vínculos próprios.
2. **Identidade única:** uma pessoa não é duplicada por mudança de papel ou imóvel.
3. **Entidades independentes:** cadastro, vínculo, autorização, credencial e evento possuem identidade e ciclo de vida próprios.
4. **Segregação:** dados operacionais pertencem explicitamente a uma implantação.
5. **Histórico:** encerramento, inativação e versionamento substituem exclusão destrutiva.
6. **Temporalidade:** vigências usam início inclusivo e término exclusivo.
7. **Auditoria:** mudanças relevantes registram ator, origem, instante e valores aplicáveis.
8. **Menor privilégio:** acesso a dados depende de permissão e escopo.
9. **Privacidade:** arquivos e dados sensíveis permanecem privados.
10. **Idempotência:** operações repetíveis possuem chave própria e efeito único.
11. **Desacoplamento:** identificadores externos nunca substituem chaves internas.
12. **Explicabilidade:** decisões de autorização e permissão devem ser reconstruíveis.

---

# 5. Padrões técnicos

## 5.1 Banco e codificação

- SGBD: PostgreSQL;
- codificação: UTF-8;
- timestamps persistidos em UTC;
- fuso de apresentação definido na implantação;
- datas civis sem horário armazenadas como `date`;
- valores monetários armazenados como `numeric`, nunca ponto flutuante;
- documentos e placas possuem valor de apresentação e valor normalizado;
- JSON deve ser reservado a payloads variáveis, snapshots e metadados, não a relacionamentos centrais.

## 5.2 Identificadores

- chaves primárias deverão ser UUID;
- identificadores públicos deverão ser opacos e não sequenciais;
- chaves estrangeiras usarão o mesmo tipo da chave referenciada;
- protocolo público terá identificador próprio, não derivado da chave primária;
- identificadores externos serão armazenados em tabela de referência externa.

A versão de UUID e sua estratégia de geração deverão ser formalizadas em ADR antes das migrations.

## 5.3 Nomenclatura

- nomes físicos em `snake_case`;
- tabelas no plural;
- chaves primárias nomeadas `id`;
- chaves estrangeiras terminadas em `_id`;
- timestamps padrão: `created_at` e `updated_at`;
- estados representados por código estável;
- nomes físicos finais serão ratificados na implementação sem alterar a semântica deste documento.

## 5.4 Colunas transversais

Entidades mutáveis deverão possuir, conforme aplicável:

| Coluna conceitual | Finalidade |
|---|---|
| `id` | identidade interna |
| `implantacao_id` | segregação |
| `status` | estado corrente |
| `versao` | concorrência otimista |
| `created_at` | criação |
| `created_by` | ator responsável |
| `updated_at` | última alteração |
| `updated_by` | ator da última alteração |
| `inactivated_at` | inativação lógica |
| `inactivated_by` | ator da inativação |
| `motivo_id` | motivo estruturado |
| `observacao` | complemento autorizado |

Nem todas as tabelas receberão todas as colunas; tabelas imutáveis e associativas terão desenho específico.

---

# 6. Segregação por organização e implantação

## 6.1 Entidades

| Entidade | Finalidade | Relacionamentos principais |
|---|---|---|
| `organizacoes` | titular institucional do uso do produto | possui implantações |
| `implantacoes` | contexto segregado de operação | pertence à organização |
| `implantacao_configuracoes` | configuração versionada | pertence à implantação |

## 6.2 Regras

- toda tabela operacional deverá possuir `implantacao_id` direto, salvo tabela global expressamente documentada;
- chaves únicas deverão incluir `implantacao_id` quando a unicidade for local;
- relacionamentos entre entidades operacionais deverão impedir cruzamento entre implantações;
- consultas, filas, caches, arquivos, exports e logs deverão carregar o contexto da implantação;
- usuários com acesso a mais de uma implantação terão associação explícita por escopo;
- a aplicação não dependerá apenas de filtros da interface para segregação.

Row-Level Security poderá ser usada como defesa adicional, após ADR e validação de compatibilidade com Laravel, filas e rotinas administrativas.

---

# 7. Estrutura condominial e imóvel

## 7.1 Entidades

| Entidade | Campos conceituais mínimos | Observações |
|---|---|---|
| `condominios` | implantação, nome, código, status | contexto imobiliário |
| `blocos` | condomínio, nome, código, ordem, status | agrupamento opcional |
| `imoveis` | condomínio, bloco opcional, código, unidade, tipo, status | entidade central |
| `enderecos_imoveis` | imóvel, endereço estruturado, vigência | histórico do endereço |
| `imovel_responsabilidades` | imóvel, vínculo, tipo, vigência | responsável principal e demais responsabilidades |

## 7.2 Integridade

- o código operacional do imóvel será único no condomínio ou na implantação, conforme decisão Santa Rita;
- o bloco será opcional no modelo, mas obrigatório quando a configuração da implantação exigir;
- endereço não será repetido em cadastros de ocupantes;
- mudança de endereço criará novo período ou versão;
- imóvel utilizado historicamente não será excluído;
- a responsabilidade deverá apontar para vínculo válido da pessoa com o imóvel;
- somente uma responsabilidade principal poderá estar vigente por imóvel se a regra final exigir singularidade.

---

# 8. Pessoas, documentos e contatos

## 8.1 Entidades

| Entidade | Campos conceituais mínimos |
|---|---|
| `pessoas` | nome, nome social, nascimento, status, versão |
| `pessoa_documentos` | pessoa, tipo, valor cifrado ou protegido, valor normalizado para busca, país, status, vigência |
| `pessoa_contatos` | pessoa, tipo, valor, principal, verificado, vigência |
| `pessoa_enderecos` | pessoa, finalidade, endereço estruturado, vigência |
| `pessoa_arquivos` | pessoa, arquivo, finalidade, status |

## 8.2 Regras

- pessoa deverá ser reutilizada em diferentes vínculos;
- documento normalizado terá unicidade condicionada por tipo, país, implantação e estado aplicável;
- a busca por duplicidade não deverá expor a pessoa encontrada sem autorização;
- nome social e demais dados de identidade deverão respeitar apresentação e acesso aprovados;
- documento não será usado como chave primária;
- endereço da pessoa só será coletado com finalidade definida e não substituirá o endereço do imóvel;
- campos sensíveis serão classificados antes da implementação;
- alteração de documento crítico deverá preservar histórico e auditoria.

---

# 9. Vínculos, papéis e responsabilidades

## 9.1 Entidades

| Entidade | Finalidade |
|---|---|
| `vinculos` | relação temporal da pessoa com imóvel, empresa ou contexto |
| `vinculo_periodos` | versões ou períodos de vigência |
| `vinculo_papeis` | papel familiar ou operacional |
| `tipos_vinculo` | natureza parametrizada e histórica |
| `tipos_papel` | catálogo de papéis |
| `tipos_responsabilidade` | catálogo de responsabilidades |

## 9.2 Estrutura

Um vínculo deverá conter:

- implantação;
- pessoa;
- natureza;
- imóvel ou empresa conforme o tipo;
- estado;
- início;
- término;
- origem;
- responsável pela criação;
- versão.

## 9.3 Integridade temporal

- término deverá ser posterior ao início;
- vínculo futuro poderá estar `agendado`;
- vínculo expirado não será reativado por sobrescrita;
- renovação criará novo período rastreável;
- sobreposição proibida será validada por serviço de domínio e, quando tecnicamente viável, por restrição de exclusão no PostgreSQL;
- encerramento do vínculo não excluirá autorizações ou eventos históricos;
- natureza, papel e responsabilidade serão armazenados separadamente.

---

# 10. Empresas e prestadores

| Entidade | Finalidade |
|---|---|
| `empresas` | cadastro de empresa prestadora ou relacionada |
| `empresa_documentos` | identificadores e documentos empresariais |
| `empresa_contatos` | contatos da empresa |
| `prestador_empresas` | relação temporal entre pessoa e empresa |
| `categorias_prestador` | requisitos por atividade |
| `prestador_documentos` | documentação exigida por categoria |

A inativação da empresa impedirá novas autorizações, sem apagar vínculos, documentos, atendimentos ou acessos anteriores.

---

# 11. Veículos

| Entidade | Campos conceituais mínimos |
|---|---|
| `veiculos` | placa exibida, placa normalizada, país, tipo, marca, modelo, cor, status |
| `veiculo_vinculos` | veículo, pessoa, imóvel, empresa ou autorização, tipo, vigência |
| `veiculo_arquivos` | veículo, arquivo, finalidade |

## 11.1 Regras

- placa será normalizada para comparação;
- duplicidade ativa conflitante será impedida ou encaminhada para análise;
- veículo poderá existir sem credencial LPR;
- vínculo veicular terá vigência própria;
- placa lida não substituirá cadastro sem confirmação;
- alteração de placa deverá preservar a placa e os eventos anteriores;
- o alvo definitivo do vínculo principal permanece pendente.

---

# 12. Convites e pré-cadastro

## 12.1 Entidades

| Entidade | Finalidade |
|---|---|
| `convites_pre_cadastro` | token, escopo, validade, limite e cancelamento |
| `pre_cadastros` | solicitação e estado corrente |
| `pre_cadastro_versoes` | snapshot de cada submissão |
| `pre_cadastro_transicoes` | histórico de estados |
| `pre_cadastro_pessoas` | dados propostos para pessoa |
| `pre_cadastro_veiculos` | veículos informados |
| `pre_cadastro_arquivos` | documentos e selfie |
| `pre_cadastro_analises` | decisão, motivo interno e mensagem pública |

## 12.2 Regras

- token será armazenado de forma segura, preferencialmente como hash;
- protocolo será único, opaco e não conterá dado pessoal;
- versão enviada será imutável;
- correção criará nova versão;
- motivo interno e mensagem pública ocuparão campos separados;
- aprovação não criará evento de entrada;
- conversão para pessoa, vínculo e autorização será transacional e rastreável;
- idempotência impedirá conversão duplicada;
- OCR será armazenado como sugestão, acompanhado de origem, confiança e versão do processamento.

---

# 13. Autorizações

| Entidade | Finalidade |
|---|---|
| `autorizacoes` | permissão lógica de acesso |
| `autorizacao_destinos` | imóveis, áreas ou pontos permitidos |
| `autorizacao_horarios` | janelas e recorrências |
| `autorizacao_usos` | limites e consumos |
| `autorizacao_transicoes` | histórico de estado |
| `autorizacao_excecoes` | exceções justificadas e temporais |

Uma autorização deverá referenciar:

- sujeito autorizado, normalmente pessoa ou veículo;
- responsável;
- destino;
- origem;
- período;
- condições de uso;
- estado;
- justificativa quando excepcional.

A autorização não armazenará credencial como se fosse a própria permissão e não será considerada prova de abertura física.

---

# 14. Credenciais

| Entidade | Finalidade |
|---|---|
| `credenciais` | meio lógico de identificação |
| `credencial_vinculos` | associação temporal ao sujeito |
| `credencial_dados_protegidos` | material sensível ou referência segura |
| `credencial_transicoes` | ciclo de vida |
| `credencial_sincronizacoes` | distribuição a equipamentos |

Tipos previstos incluem face, placa, tag, QR Code e código, sem implicar habilitação automática.

Dados biométricos não deverão ser armazenados até definição de finalidade, base legal, formato, criptografia, acesso, retenção e exclusão. Quando um fabricante mantiver o template, o núcleo deverá preferir referência externa protegida em vez de cópia desnecessária.

---

# 15. Acesso físico e atendimento

## 15.1 Entidades

| Entidade | Finalidade |
|---|---|
| `locais_acesso` | área física ou lógica |
| `pontos_acesso` | portão, porta, cancela ou passagem |
| `atendimentos_acesso` | contexto operacional de validação |
| `identificacoes_acesso` | documentos, placas, tags ou faces observadas |
| `decisoes_acesso` | resultado lógico e razões |
| `comandos_acesso` | solicitação ao equipamento |
| `eventos_acesso` | fatos de tentativa, entrada, saída, negativa ou falha |
| `eventos_acesso_correlacoes` | relação entre eventos internos e externos |

## 15.2 Separação obrigatória

```text
Atendimento
  → identificação
  → avaliação
  → decisão lógica
  → comando
  → confirmação ou resultado desconhecido
  → evento de acesso
```

Cada etapa terá identificador, estado, instante e correlação próprios.

## 15.3 Regras

- toda tentativa relevante produzirá registro;
- direção e ponto de acesso serão obrigatórios no evento;
- decisão guardará regras avaliadas e motivos do resultado;
- comando terá chave de idempotência;
- timeout resultará em confirmação desconhecida até reconciliação;
- liberação manual registrará operador e motivo;
- salvar sem liberar não criará comando;
- pagamento não substituirá autorização;
- evento confirmado será imutável, admitindo evento compensatório ou anotação auditada;
- datas do equipamento serão preservadas separadamente da data de recebimento.

---

# 16. Equipamentos e integrações

| Entidade | Finalidade |
|---|---|
| `adaptadores_integracao` | tipo, fabricante, protocolo e capacidades |
| `equipamentos` | equipamento cadastrado e ponto associado |
| `equipamento_capacidades` | funções efetivamente disponíveis |
| `equipamento_credenciais` | referência protegida a segredos |
| `referencias_externas` | mapeamento entre entidade interna e ID externo |
| `operacoes_integracao` | fila lógica, correlação, tentativas e resultado |
| `integracao_ocorrencias` | erros e respostas sanitizadas |

## 16.1 Regras

- segredo não será armazenado em texto aberto nem devolvido ao frontend;
- payload bruto só será persistido quando necessário, sanitizado e sujeito à retenção;
- `referencias_externas` terá unicidade por implantação, adaptador, tipo e ID externo;
- operação terá chave de idempotência;
- tentativas serão registradas sem duplicar o efeito de negócio;
- capacidades impedirão oferta de função incompatível;
- indisponibilidade externa não alterará fatos internos já confirmados.

---

# 17. Evidências LPR, OCR e IA

| Entidade | Finalidade |
|---|---|
| `capturas_lpr` | imagem, leitura original, confiança, câmera e instante |
| `leituras_lpr` | candidatos e resultado selecionado |
| `processamentos_ocr` | entrada, saída, confiança e versão |
| `processamentos_ia` | metadados técnicos de execução quando necessários |

- saída automática será evidência, não verdade cadastral;
- correção humana preservará valor original e autor da correção;
- versão do modelo ou mecanismo será registrada quando disponível;
- arquivos permanecerão no armazenamento compatível com S3;
- o PostgreSQL guardará metadados e referências, não o binário principal;
- limiar de automação será configuração versionada.

---

# 18. Contribuição e caixa

## 18.1 Entidades conceituais

| Entidade | Finalidade |
|---|---|
| `caixas` | definição do caixa operacional |
| `sessoes_caixa` | abertura e fechamento por operador |
| `contribuicoes` | obrigação ou classificação relacionada ao atendimento |
| `movimentos_caixa` | recebimento, estorno, ajuste ou isenção |
| `formas_pagamento` | catálogo versionável |
| `conciliacoes_caixa` | conferência de valores |

## 18.2 Integridade

- dinheiro usará moeda e precisão definidas;
- movimento concluído exigirá sessão aberta quando aplicável;
- mesma chave idempotente não poderá gerar dois recebimentos;
- estorno será movimento compensatório;
- fechamento não apagará divergências;
- contribuição e autorização permanecerão independentes;
- regras financeiras detalhadas aguardam decisão de produto.

---

# 19. Usuários, perfis e permissões

| Entidade | Finalidade |
|---|---|
| `usuarios` | identidade de autenticação individual |
| `usuario_implantacoes` | escopo de acesso às implantações |
| `perfis` | conjunto nomeado de permissões |
| `permissoes` | ação estável sobre recurso |
| `perfil_permissoes` | concessões ou restrições do perfil |
| `usuario_perfis` | atribuição temporal |
| `usuario_excecoes_permissao` | exceção individual justificada |
| `sessoes_usuario` | sessões e revogações |

## 19.1 Regras

- cada operador terá usuário próprio;
- senha será armazenada apenas por mecanismo seguro do framework;
- concessões terão implantação, vigência e origem;
- exceção individual terá justificativa;
- usuário não poderá autoelevar privilégios;
- inativação revogará novas sessões e deverá permitir revogação das existentes;
- decisão efetiva deverá ser explicável;
- precedência entre concessão e negação aguarda definição.

---

# 20. Configurações e catálogos

| Entidade | Finalidade |
|---|---|
| `configuracoes` | definição estável da chave configurável |
| `configuracao_versoes` | valores, vigência, autor e publicação |
| `catalogos` | definição de catálogo parametrizado |
| `catalogo_itens` | item estável |
| `catalogo_item_versoes` | nome, regras, ordem e vigência |
| `motivos` | motivos estruturados por operação |

- configuração publicada não será sobrescrita;
- reversão criará nova versão;
- valores secretos serão referenciados por cofre ou mecanismo protegido;
- item já utilizado será inativado, não excluído;
- referências históricas deverão continuar legíveis;
- configurações estruturais que alterem arquitetura exigirão ADR.

---

# 21. Arquivos e armazenamento

| Entidade | Campos conceituais mínimos |
|---|---|
| `arquivos` | implantação, chave do objeto, provedor, bucket lógico, nome, MIME, tamanho, hash, classificação, status |
| `arquivo_vinculos` | arquivo, entidade, finalidade |
| `arquivo_acessos` | solicitante, finalidade, instante e resultado |
| `arquivo_retencoes` | regra, início, término e bloqueio de descarte |

## 21.1 Regras

- objeto será privado por padrão;
- banco armazenará referência, metadados e hash;
- acesso ocorrerá mediante autorização ou URL temporária;
- chave do objeto não conterá dado pessoal em claro;
- substituição criará novo arquivo ou versão;
- detecção de malware terá estado explícito;
- descarte somente ocorrerá após política aprovada e registro auditável;
- falha no armazenamento não deixará entidade principal em estado falsamente concluído.

---

# 22. Auditoria

## 22.1 Entidades

| Entidade | Finalidade |
|---|---|
| `auditoria_eventos` | cabeçalho imutável da operação |
| `auditoria_alteracoes` | campo, valor anterior e posterior protegidos |
| `auditoria_contextos` | IP, agente, correlação, origem e metadados |

## 22.2 Conteúdo mínimo

- implantação;
- ator humano, sistema ou integração;
- ação;
- entidade e identificador;
- data e hora;
- origem;
- identificador de correlação;
- motivo quando aplicável;
- resultado;
- alterações relevantes.

Logs de auditoria serão somente anexados. Dados sensíveis deverão ser mascarados, cifrados ou omitidos conforme classificação. A política de retenção permanece pendente.

---

# 23. Notificações, exportações e relatórios

| Entidade | Finalidade |
|---|---|
| `notificacoes` | solicitação e estado de comunicação |
| `notificacao_destinatarios` | destinatários e resultado |
| `exportacoes` | solicitante, filtros, escopo e estado |
| `exportacao_arquivos` | artefato gerado e expiração |
| `relatorio_execucoes` | parâmetros, período e resultado |

Exportações deverão registrar permissão, filtros, quantidade, arquivo e expiração. O arquivo gerado será privado e sujeito a retenção menor ou igual à permitida para os dados de origem.

---

# 24. Relacionamentos centrais

```text
Organização
└── Implantação
    └── Condomínio
        └── Bloco (opcional)
            └── Imóvel
                ├── Vínculo ── Pessoa
                │   ├── Papel
                │   └── Responsabilidade
                ├── Autorização ── Pessoa/Veículo
                └── Vínculo de veículo ── Veículo

Pessoa
├── Documentos
├── Contatos
├── Vínculos
├── Credenciais
└── Pré-cadastros de origem

Atendimento de acesso
├── Identificações
├── Decisão
├── Contribuição
├── Comando ── Equipamento
└── Evento de acesso
```

## 24.1 Cardinalidades essenciais

| Origem | Relação | Destino |
|---|---|---|
| organização | 1:N | implantações |
| implantação | 1:N | condomínios |
| condomínio | 1:N | imóveis |
| bloco | 1:N | imóveis |
| pessoa | 1:N | vínculos |
| imóvel | 1:N | vínculos |
| vínculo | 1:N | períodos |
| pessoa | N:N temporal | imóveis, por vínculos |
| veículo | N:N temporal | pessoas/imóveis, por vínculos veiculares |
| pessoa ou veículo | 1:N | autorizações |
| sujeito | 1:N | credenciais |
| atendimento | 1:N | identificações e comandos |
| atendimento | 1:N | eventos correlacionados |
| equipamento | 1:N | comandos e ocorrências |

---

# 25. Estados e transições

Estados correntes serão armazenados na entidade para consulta operacional. Transições relevantes serão registradas em tabela histórica com:

- estado anterior;
- estado posterior;
- instante;
- ator;
- origem;
- motivo;
- versão da entidade;
- correlação.

Não será permitido alterar estado por atualização genérica sem validar a transição de domínio.

Os estados canônicos definidos no documento 009 aplicam-se a imóvel, pessoa, vínculo, pré-cadastro, atendimento, comando e sincronização. Estados adicionais somente poderão ser introduzidos com rastreabilidade e, quando estruturais, ADR.

---

# 26. Exclusão, inativação e anonimização

## 26.1 Regra geral

- entidades de negócio utilizadas não terão exclusão física pelo fluxo operacional;
- registros serão inativados, encerrados, expirados, revogados ou substituídos;
- `ON DELETE CASCADE` será evitado em fatos e históricos;
- `ON DELETE RESTRICT` ou ausência de exclusão será preferida para entidades referenciadas;
- tabelas técnicas transitórias poderão ter descarte controlado.

## 26.2 Dados pessoais

Anonimização, bloqueio ou descarte decorrente de obrigação jurídica deverão:

- respeitar retenção e litígio;
- preservar integridade referencial;
- manter evidência mínima permitida;
- gerar auditoria;
- não reescrever fatos de acesso de forma enganosa.

A estratégia definitiva depende da política de privacidade e retenção.

---

# 27. Concorrência, transações e idempotência

## 27.1 Concorrência

- entidades críticas terão coluna de versão;
- atualização exigirá versão esperada;
- conflito não sobrescreverá silenciosamente dados;
- reservas de unicidade e decisões críticas usarão transação;
- bloqueio pessimista será limitado a operações que realmente o exijam.

## 27.2 Transações mínimas

Deverão ser atômicas, entre outras:

- conversão aprovada do pré-cadastro;
- criação de pessoa e primeiro vínculo;
- mudança de responsável principal;
- decisão de acesso e reserva de comando;
- recebimento e movimento de caixa;
- publicação de configuração;
- atribuição crítica de permissão.

## 27.3 Idempotência

Chaves idempotentes serão exigidas para:

- submissão e conversão de pré-cadastro;
- decisão e comando de acesso;
- sincronização com equipamento;
- contribuição e recebimento;
- processamento de webhook;
- importação e exportação quando aplicável.

Cada chave deverá ser única no escopo da implantação, operação e período técnico definido.

---

# 28. Índices e desempenho

## 28.1 Índices obrigatórios conceituais

- todas as chaves estrangeiras usadas em consulta;
- `implantacao_id` combinado com identificadores operacionais;
- documento normalizado;
- placa normalizada;
- protocolo de pré-cadastro;
- estado e vigência de vínculos;
- estado e vigência de autorizações;
- eventos por ponto e instante;
- eventos por pessoa, veículo ou credencial;
- comandos por equipamento e estado;
- operações por idempotência e correlação;
- auditoria por entidade, ator e instante;
- filas por estado e próxima tentativa.

## 28.2 Diretrizes

- índices compostos deverão seguir filtros reais;
- índices parciais poderão representar registros ativos;
- busca textual deverá usar recurso nativo do PostgreSQL quando necessário;
- índices não deverão conter dados sensíveis em claro sem avaliação;
- particionamento de eventos e auditoria será decidido por volume medido;
- toda otimização deverá preservar segregação por implantação.

---

# 29. Restrições e validações

## 29.1 No banco

Deverão ser preferencialmente garantidos pelo PostgreSQL:

- presença de chaves;
- integridade referencial;
- unicidade no escopo;
- início anterior ao término;
- valores monetários não negativos quando a natureza exigir;
- estados pertencentes a catálogo válido;
- unicidade de idempotência;
- impossibilidade de vínculos cruzarem implantações;
- singularidade vigente quando formalmente definida.

## 29.2 Na aplicação

Permanecerão no domínio da aplicação:

- transições condicionais complexas;
- decisão de acesso;
- precedência de permissões;
- compatibilidade entre vínculos;
- obrigatoriedade variável por categoria;
- capacidades de adaptadores;
- políticas de privacidade;
- validação de arquivos;
- orquestração com serviços externos.

Validação na aplicação não substitui restrição estrutural possível no banco.

---

# 30. Segurança e classificação

## 30.1 Classes propostas

| Classe | Exemplos | Tratamento |
|---|---|---|
| Pública | nomes institucionais autorizados | acesso controlado pelo produto |
| Interna | configurações não secretas | autenticação e escopo |
| Pessoal | nome, contato, documento | permissão, mascaramento e auditoria |
| Sensível | biometria, imagens específicas | proteção reforçada e finalidade |
| Segredo | chaves e tokens de integração | cofre ou cifra, nunca log |

## 30.2 Controles

- criptografia em trânsito e em repouso;
- credenciais técnicas fora do código;
- mascaramento em logs e interfaces;
- acesso a arquivos com curta validade;
- trilha de consulta de dados críticos quando definida;
- cópias de segurança protegidas;
- restauração testada;
- ambientes não produtivos sem dados reais, salvo processo formal de anonimização.

---

# 31. Retenção e volume

Cada categoria deverá possuir:

- base e finalidade;
- período de retenção;
- evento inicial da contagem;
- tratamento após vencimento;
- exceção por obrigação ou litígio;
- responsável pela política.

Categorias mínimas:

- cadastro e vínculos;
- documentos e imagens;
- biometria;
- pré-cadastro;
- eventos de acesso;
- evidências LPR;
- auditoria;
- integrações;
- caixa e contribuições;
- exports;
- backups.

Enquanto a política não for aprovada, nenhuma rotina definitiva de descarte deverá ser implementada.

---

# 32. Migração e qualidade de dados

## 32.1 Etapas previstas

1. inventariar fontes legadas;
2. classificar campos e proprietários;
3. mapear valores para catálogos;
4. normalizar documentos, placas, datas e contatos;
5. detectar duplicidades;
6. relacionar pessoas aos imóveis por vínculo;
7. validar vigências;
8. importar em ambiente de homologação;
9. reconciliar totais e amostras;
10. registrar relatório e aprovação;
11. executar corte controlado;
12. preservar origem e lote de importação.

## 32.2 Entidades de apoio

- `importacao_lotes`;
- `importacao_registros`;
- `importacao_erros`;
- `importacao_mapeamentos`.

Importação não deverá contornar regras essenciais de segregação, integridade ou auditoria.

---

# 33. Backup, restauração e continuidade

O desenho físico deverá definir:

- política de backup completo e incremental;
- retenção e proteção dos backups;
- criptografia;
- restauração por ambiente;
- testes periódicos;
- objetivos RPO e RTO;
- reconciliação com objetos S3;
- contingência para integrações;
- monitoramento de crescimento, locks, conexões e replicação.

Metas numéricas permanecem pendentes e não serão presumidas neste documento.

---

# 34. Rastreabilidade

| Decisão de dados | Regras relacionadas |
|---|---|
| imóvel central e endereço próprio | `RN-001` a `RN-006`, `RN-060` |
| pessoa única e vínculos temporais | `RN-007` a `RN-022`, `RN-061` a `RN-064` |
| documentos e arquivos protegidos | `RN-028`, `RN-065`, `RN-066` |
| veículos e placas normalizadas | `RN-018`, `RN-034` a `RN-038` |
| entidades de acesso separadas | `RN-039` a `RN-045`, `RN-056`, `RN-077` a `RN-089` |
| auditoria imutável | `RN-046` a `RN-049` |
| usuários e permissões | `RN-050` a `RN-054`, `RN-097` a `RN-100` |
| segregação por implantação | `RN-055` |
| pré-cadastro versionado | `RN-067` a `RN-076` |
| integrações desacopladas | `RN-090` a `RN-093`, `RN-100` |
| configuração versionada | `RN-094` a `RN-096` |

---

# 35. Decisões consolidadas

Ficam consolidados para o modelo:

- PostgreSQL como banco relacional;
- UUID como categoria de chave interna, com versão pendente de ADR;
- implantação explícita nas entidades operacionais;
- imóvel como entidade central;
- pessoa única e vínculos temporais independentes;
- natureza, papel e responsabilidade separados;
- histórico de endereço, vínculo, estado e configuração;
- autorização, credencial, atendimento, decisão, comando e evento separados;
- protocolo público opaco;
- referências externas secundárias;
- arquivos privados em armazenamento compatível com S3;
- metadados e referências de arquivos no PostgreSQL;
- auditoria somente anexada;
- concorrência otimista;
- idempotência em operações críticas;
- inativação e versionamento em lugar de exclusão destrutiva;
- timestamps em UTC e fuso configurado por implantação;
- valores monetários em tipo decimal;
- JSON limitado a dados variáveis e snapshots;
- migrations somente após aprovação desta especificação e dos ADRs necessários.

---

# 36. Pendências abertas

| PEN-BDD | Pendência | Impacto |
|---|---|---|
| PEN-BDD-001 | Identificação real e unicidade dos imóveis Santa Rita | chave natural e índices |
| PEN-BDD-002 | Existência e obrigatoriedade de blocos | estrutura condominial |
| PEN-BDD-003 | Catálogos definitivos de vínculo, papel e responsabilidade | vínculos |
| PEN-BDD-004 | Regra de responsável principal único ou múltiplo | restrição temporal |
| PEN-BDD-005 | Conflitos de vigência proibidos | constraints e validação |
| PEN-BDD-006 | Campos obrigatórios por categoria | nulabilidade e validação |
| PEN-BDD-007 | Política de documentos, imagens e biometria | proteção e retenção |
| PEN-BDD-008 | Finalidade do endereço pessoal no pré-cadastro | pessoa e privacidade |
| PEN-BDD-009 | Modelagem de turista e visitante | Resolvida: turista usa destino praia sem imóvel; visitante exige imóvel, responsável e vigência |
| PEN-BDD-010 | Documentos por categoria de prestador | prestadores |
| PEN-BDD-011 | Alvo principal do vínculo de veículo | cardinalidade |
| PEN-BDD-012 | Limiar e fluxo de automação LPR | configuração e evidências |
| PEN-BDD-013 | Regras completas de contribuição e caixa | modelo financeiro |
| PEN-BDD-014 | Fluxo de contingência | atendimento e eventos |
| PEN-BDD-015 | Fabricantes, contratos e capacidades | integração |
| PEN-BDD-016 | Motivos parametrizados | catálogos |
| PEN-BDD-017 | Permissões granulares e precedência | autorização interna |
| PEN-BDD-018 | Sessão, senha e MFA | segurança |
| PEN-BDD-019 | Proteção do último administrador | restrição administrativa |
| PEN-BDD-020 | Publicação de configurações | versionamento |
| PEN-BDD-021 | Política de retenção e anonimização | descarte e particionamento |
| PEN-BDD-022 | Metas de desempenho, RPO e RTO | dimensionamento |
| PEN-BDD-023 | Versão e geração de UUID | ADR e migrations |
| PEN-BDD-024 | Uso de Row-Level Security | ADR de segurança |
| PEN-BDD-025 | Particionamento de eventos e auditoria | modelo físico |
| PEN-BDD-026 | Fonte e estratégia de migração de dados Santa Rita | importação |
| PEN-BDD-027 | Provedor S3 e gestão de chaves | arquivos |
| PEN-BDD-028 | Estratégia de cofre de segredos | integrações |

---

# 37. ADRs necessários

Antes ou durante o modelo físico deverão ser formalizados:

| ADR | Tema |
|---|---|
| ADR-001 | Estratégia de multi-implantação e isolamento |
| ADR-002 | Identificadores UUID e identificadores públicos |
| ADR-003 | Auditoria e imutabilidade lógica |
| ADR-004 | Armazenamento S3 e ciclo de vida de arquivos |
| ADR-005 | Integrações, filas, correlação e idempotência |
| ADR-006 | Proteção de segredos |
| ADR-007 | Biometria e referências externas, se habilitadas |
| ADR-008 | Particionamento e retenção de eventos, se necessário |

A numeração deverá ser ajustada ao catálogo oficial de ADRs quando ele for criado, sem perda da rastreabilidade temática.

---

# 38. Critérios de aceite

**CA-BDD-001:** o imóvel permanece a entidade central.  
**CA-BDD-002:** pessoa, vínculo, autorização, credencial e evento são entidades distintas.  
**CA-BDD-003:** natureza, papel e responsabilidade não são confundidos.  
**CA-BDD-004:** toda entidade operacional está segregada por implantação.  
**CA-BDD-005:** unicidades incluem o escopo correto.  
**CA-BDD-006:** vigências e estados preservam histórico.  
**CA-BDD-007:** renovação não sobrescreve período anterior.  
**CA-BDD-008:** aprovação, decisão, comando e confirmação física permanecem separados.  
**CA-BDD-009:** operações críticas possuem estratégia idempotente.  
**CA-BDD-010:** integrações usam referências externas secundárias.  
**CA-BDD-011:** arquivos são privados e referenciados no banco.  
**CA-BDD-012:** auditoria registra ator, entidade, operação, instante e alteração aplicável.  
**CA-BDD-013:** exclusão destrutiva não é o fluxo normal de negócio.  
**CA-BDD-014:** concorrência não permite sobrescrita silenciosa.  
**CA-BDD-015:** índices conceituais cobrem buscas e operação crítica.  
**CA-BDD-016:** dados sensíveis e segredos possuem tratamento explícito.  
**CA-BDD-017:** pendências não são convertidas em decisões definitivas.  
**CA-BDD-018:** o modelo rastreia as regras `RN-001` a `RN-100`.  
**CA-BDD-019:** o documento não introduz React, MySQL ou acoplamento a fabricante.  
**CA-BDD-020:** migrations somente serão produzidas após aprovação e detalhamento técnico necessário.

---

# 39. Entregáveis posteriores à aprovação

Após a aprovação deste documento poderão ser produzidos:

1. catálogo oficial de ADRs;
2. diagrama entidade-relacionamento detalhado;
3. dicionário físico de dados;
4. migrations Laravel;
5. factories e seeders sem dados pessoais reais;
6. constraints, índices e políticas;
7. plano de migração Santa Rita;
8. testes automatizados de integridade;
9. procedimentos de backup e restauração.

---

# 40. Aprovação

| Papel | Nome | Decisão | Data | Observações |
|---|---|---|---|---|
| Product Owner | Vinicius Velasco de Azevedo | Aprovado | 28/07/2026 | Modelo conceitual e lógico aprovado como referência para o detalhamento técnico |
| Soluções do Vale | Representante designado | Ciente | 28/07/2026 | Aprovação registrada no repositório oficial |

---

# 41. Próximo documento

Após a aprovação desta especificação, o próximo documento deverá detalhar a arquitetura da aplicação e suas decisões estruturais, mantendo Laravel, Blade, Livewire, PostgreSQL, armazenamento compatível com S3, Docker e integrações desacopladas.

O nome e a numeração do próximo arquivo deverão seguir o índice documental oficial vigente.

---

## Situação do documento

Este documento encontra-se **aprovado** como modelo conceitual e lógico de referência. A aprovação permite avançar para o detalhamento técnico e para os ADRs necessários, sem autorizar a implementação das áreas afetadas por pendências abertas antes das respectivas definições.
