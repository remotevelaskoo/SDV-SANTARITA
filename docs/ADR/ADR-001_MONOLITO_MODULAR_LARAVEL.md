# ADR-001 — MONÓLITO MODULAR LARAVEL

**Identificador:** ADR-001
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
| 1.0.0 | Julho/2026 | Soluções do Vale | Proposta do monólito modular Laravel para o MVP |

---

# 1. Contexto

O SDV Access é uma plataforma web de controle de acesso com requisitos de segurança, integridade, auditabilidade, continuidade e expansão para múltiplas implantações.

O MVP Santa Rita inclui domínios relacionados, mas com ciclos próprios:

- implantações e imóveis;
- pessoas e vínculos;
- empresas e prestadores;
- veículos;
- pré-cadastros;
- autorizações;
- credenciais;
- atendimentos e eventos de acesso;
- contribuições e caixa;
- usuários e permissões;
- arquivos;
- auditoria;
- equipamentos e integrações.

Os fluxos críticos atravessam mais de um domínio e exigem consistência. Ao mesmo tempo, o produto deverá evitar uma base de código sem limites claros e permitir evolução futura.

As decisões técnicas aprovadas determinam Laravel no backend e Blade/Livewire no frontend. O Product Book afirma que módulos funcionais não autorizam microsserviços ou separação física prematura.

---

# 2. Problema

Definir como os módulos do SDV Access serão organizados e como poderão colaborar sem:

- criar complexidade distribuída antes da necessidade;
- concentrar regras em controllers, componentes Livewire ou models;
- permitir dependências circulares;
- acoplar o núcleo a fabricantes;
- perder transações importantes;
- impedir testes isolados;
- tornar futura evolução excessivamente custosa.

A decisão deverá orientar a estrutura inicial do repositório e, por isso, bloqueia o início do código.

---

# 3. Forças e restrições

## 3.1 Forças

- equipe e operação iniciais enxutas;
- necessidade de entrega incremental;
- fluxos transacionais entre módulos;
- auditabilidade ponta a ponta;
- integrações externas sujeitas a falha;
- crescimento futuro para outros clientes;
- necessidade de testes rápidos;
- implantação e suporte simplificados;
- domínio ainda em refinamento.

## 3.2 Restrições aprovadas

- Laravel;
- Blade e Livewire;
- PostgreSQL;
- armazenamento compatível com S3;
- Docker;
- Python/FastAPI apenas para OCR ou IA quando necessário;
- React fora do MVP;
- integrações desacopladas;
- histórico em lugar de exclusão destrutiva;
- imóvel como entidade central;
- pessoa, vínculo, autorização, credencial e evento separados.

---

# 4. Alternativas consideradas

## 4.1 Alternativa A — Monólito organizado apenas por tipo técnico

Exemplo conceitual:

```text
app/
├── Http/Controllers/
├── Models/
├── Services/
├── Jobs/
└── Policies/
```

### Vantagens

- alinhamento direto à estrutura inicial do Laravel;
- baixa barreira de entrada;
- pouca configuração.

### Desvantagens

- regras de um domínio espalhadas por muitas pastas;
- limites funcionais pouco visíveis;
- tendência a services genéricos;
- crescimento de dependências cruzadas;
- maior dificuldade para compreender e testar um módulo.

---

## 4.2 Alternativa B — Monólito modular por domínio

Uma aplicação Laravel implantável, organizada por módulos funcionais e camadas internas.

### Vantagens

- fronteiras explícitas;
- transações locais;
- deploy único;
- menor complexidade operacional;
- coesão por domínio;
- evolução incremental;
- possibilidade de extração futura baseada em evidência.

### Desvantagens

- exige disciplina de dependências;
- Laravel não impede sozinho acessos cruzados;
- arquitetura poderá degradar sem testes e revisão;
- alguns elementos do framework permanecerão compartilhados.

---

## 4.3 Alternativa C — Microsserviços por domínio

Serviços implantáveis, bancos e contratos de rede independentes.

### Vantagens

- escalabilidade e implantação independentes;
- isolamento operacional;
- contratos explícitos;
- autonomia por equipe quando houver escala organizacional.

### Desvantagens

- transações distribuídas;
- maior carga de infraestrutura e observabilidade;
- consistência eventual em fluxos críticos;
- duplicação de mecanismos;
- desenvolvimento local mais complexo;
- contratos e versionamento prematuros;
- custo desproporcional ao MVP e à equipe inicial.

---

## 4.4 Alternativa D — Núcleo Laravel com integrações em serviços separados desde o início

Aplicação principal e serviço independente para equipamentos, mesmo sem necessidade comprovada.

### Vantagens

- isolamento das integrações;
- escalabilidade específica;
- falhas externas parcialmente isoladas.

### Desvantagens

- adiciona rede, deploy e consistência distribuída;
- inventário dos equipamentos ainda está pendente;
- benefício não comprovado;
- adaptadores internos e workers já fornecem desacoplamento inicial.

Um agente local exigido por fabricante poderá existir futuramente sem transformar todos os módulos em serviços.

---

# 5. Matriz de avaliação

Escala: 1 — desfavorável; 5 — muito favorável.

| Critério | Peso | A — Técnico | B — Modular | C — Microsserviços | D — Serviço de integração |
|---|---:|---:|---:|---:|---:|
| Simplicidade operacional | 5 | 5 | 4 | 1 | 3 |
| Limites de domínio | 5 | 2 | 5 | 5 | 3 |
| Transações críticas | 5 | 5 | 5 | 2 | 3 |
| Manutenibilidade | 4 | 2 | 5 | 4 | 3 |
| Testabilidade | 4 | 3 | 5 | 4 | 3 |
| Entrega do MVP | 5 | 5 | 5 | 2 | 3 |
| Evolução futura | 3 | 2 | 4 | 5 | 4 |
| Custo inicial | 5 | 5 | 4 | 1 | 3 |
| Adequação ao contexto | 5 | 3 | 5 | 2 | 3 |

A alternativa B apresenta o melhor equilíbrio para o estágio atual.

---

# 6. Decisão proposta

Adotar um **monólito modular Laravel**, com:

- uma aplicação implantável para o MVP;
- um repositório principal;
- um banco PostgreSQL transacional;
- módulos organizados por domínio;
- camadas de Interface, Aplicação, Domínio e Infraestrutura;
- comunicação interna por contratos explícitos;
- efeitos externos assíncronos quando aplicável;
- workers e scheduler executados separadamente, usando o mesmo código;
- adaptadores de equipamentos atrás de portas do núcleo;
- testes arquiteturais para dependências;
- extração de serviços somente por novo ADR.

---

# 7. Unidade de implantação

## 7.1 MVP

A unidade principal será uma imagem versionada da aplicação Laravel.

Ela poderá executar papéis distintos:

```text
mesmo artefato
├── processo web
├── worker de filas
└── scheduler
```

Os processos serão escaláveis e configuráveis separadamente, mas não constituem microsserviços: compartilham versão, domínio e persistência.

## 7.2 Serviço Python opcional

Python/FastAPI somente será uma unidade adicional se o ADR-011 for retomado e aprovado para OCR ou IA.

## 7.3 Componente local futuro

Um agente local para equipamento somente será introduzido quando protocolo, rede ou fabricante o exigirem, mediante extensão ou substituição do ADR-007.

---

# 8. Módulos iniciais

| Módulo | Responsabilidade central |
|---|---|
| Implantação | organização, implantação e contexto |
| Imóveis | condomínio, bloco, imóvel e endereço |
| Pessoas | identidade, documentos e contatos |
| Vínculos | relação temporal, papel e responsabilidade |
| Empresas | empresas, prestadores e categorias |
| Veículos | cadastro e vínculos veiculares |
| Pré-cadastro | convite, versões, análise e conversão |
| Autorizações | permissão lógica e condições |
| Credenciais | meios de identificação e ciclo de vida |
| Acesso | atendimento, decisão, comando e evento |
| Contribuição | contribuição, caixa e movimentos |
| Identidade | usuários, sessões, perfis e permissões |
| Administração | configurações, catálogos e motivos |
| Auditoria | registros auditáveis |
| Arquivos | metadados, proteção e ciclo de vida |
| Integrações | equipamentos, adaptadores e operações |
| Comunicação | notificações |
| Relatórios | consultas, indicadores e exports |

Essa lista poderá ser ajustada sem novo ADR quando a mudança não alterar o estilo arquitetural ou criar dependências incompatíveis. Alterações relevantes deverão atualizar a arquitetura e o catálogo.

---

# 9. Estrutura conceitual

A estrutura física exata será definida no Manual do Desenvolvedor, respeitando:

```text
app/
├── Modules/
│   ├── Imoveis/
│   │   ├── Application/
│   │   ├── Domain/
│   │   ├── Infrastructure/
│   │   └── Interface/
│   ├── Pessoas/
│   ├── Vinculos/
│   └── ...
└── Shared/
    ├── Application/
    ├── Domain/
    └── Infrastructure/
```

## 9.1 Limites

- `Shared` deverá permanecer pequeno;
- utilitário usado uma vez continuará no módulo;
- regra de negócio não migrará para `Shared`;
- módulos não formarão cópias completas do framework;
- convenções Laravel poderão permanecer nas estruturas padrão quando forem infraestrutura transversal;
- a organização final deverá favorecer autoload, testes e ferramentas do ecossistema.

---

# 10. Responsabilidade das camadas

## 10.1 Interface

- rotas;
- controllers;
- componentes Livewire;
- requests;
- serialização;
- comandos de console de entrada.

Não decidirá regra central.

## 10.2 Aplicação

- casos de uso;
- autorização contextual;
- limites transacionais;
- DTOs;
- orquestração;
- publicação de eventos.

## 10.3 Domínio

- entidades;
- value objects;
- estados;
- políticas;
- invariantes;
- eventos de domínio;
- contratos necessários ao núcleo.

Não dependerá de HTTP, Blade, Livewire, S3 ou fabricante.

## 10.4 Infraestrutura

- Eloquent;
- PostgreSQL;
- filas;
- cache;
- S3;
- notificações;
- adaptadores;
- clientes externos.

Implementará contratos definidos pelas necessidades da aplicação ou do domínio.

---

# 11. Regras de dependência

## 11.1 Dentro do módulo

```text
Interface → Aplicação → Domínio
Infraestrutura → Aplicação/Domínio por contratos
```

O Domínio não dependerá de Interface ou Infraestrutura.

## 11.2 Entre módulos

São permitidos:

- serviço público de aplicação;
- interface de leitura;
- DTO explícito;
- evento interno documentado;
- identificador estável;
- projeção própria.

São proibidos como padrão:

- importar model interno de outro módulo;
- atualizar tabela de outro módulo;
- chamar controller ou componente Livewire;
- depender de classe marcada como interna;
- compartilhar entidade mutável;
- criar dependência circular;
- usar container de serviços como localizador genérico.

---

# 12. Comunicação entre módulos

## 12.1 Síncrona

Será usada quando:

- a resposta for necessária ao caso de uso;
- a operação fizer parte da mesma consistência transacional;
- o contrato for estável e local.

## 12.2 Assíncrona

Será usada quando:

- houver efeito externo;
- a operação puder ser retomada;
- o processamento for demorado;
- a indisponibilidade do consumidor não puder bloquear a transação principal.

## 12.3 Eventos

Eventos deverão:

- representar fato ocorrido;
- ter nome no passado;
- possuir contrato mínimo;
- carregar identificadores, não agregados inteiros;
- incluir implantação e correlação;
- ser idempotentes no consumo;
- não esconder sequência síncrona obrigatória.

O ADR-004 definirá auditoria, eventos e outbox.

---

# 13. Transações

Um caso de uso poderá alterar dados de mais de um módulo dentro da mesma transação PostgreSQL quando necessário à integridade do MVP.

Isso não autoriza acesso indiscriminado às tabelas. A orquestração deverá usar serviços públicos e manter o limite transacional explícito.

Exemplos:

- converter pré-cadastro em pessoa, vínculo e autorização;
- alterar responsável principal;
- registrar decisão e reservar comando;
- registrar contribuição e movimento de caixa.

Efeitos externos não serão executados antes da confirmação da transação.

---

# 14. Persistência

- o PostgreSQL será compartilhado fisicamente no MVP;
- cada tabela terá proprietário lógico;
- migrations serão organizadas e revisadas com o módulo responsável;
- módulo não escreverá diretamente em tabela alheia;
- constraints continuarão protegendo integridade;
- joins de leitura poderão existir em projeções autorizadas;
- relatórios não se tornarão caminho de escrita;
- separação futura de banco não será simulada prematuramente.

Schemas PostgreSQL por módulo não são exigidos nesta decisão. Sua adoção futura exigirá avaliação de migrations, permissões e ferramentas.

---

# 15. Interface com Laravel, Blade e Livewire

- rotas deverão apontar para entradas finas;
- controllers e componentes chamarão casos de uso;
- Form Requests ou validação equivalente cuidarão da forma da entrada;
- regras permanecerão no domínio/aplicação;
- Policies protegerão recursos no servidor;
- Eloquent não será tratado automaticamente como entidade de domínio em todos os casos;
- Blade refletirá o Design System;
- Livewire será mecanismo de interação, não fronteira arquitetural;
- jobs chamarão casos de uso idempotentes;
- commands do scheduler não concentrarão regras.

---

# 16. Integrações externas

O módulo de Integrações implementará adaptadores, mas a decisão de acesso permanecerá no núcleo.

```text
Acesso.Application
    → porta de comando
        → Integrações.Infrastructure
            → adaptador do fabricante
```

- nenhum módulo de domínio dependerá do SDK do fabricante;
- respostas externas serão convertidas para tipos internos;
- erros externos não escaparão sem sanitização;
- identificadores externos permanecerão secundários;
- retentativas respeitarão idempotência;
- contratos específicos serão tratados no ADR-007.

---

# 17. Código compartilhado

Poderá permanecer compartilhado:

- identificadores e tipos base;
- relógio;
- contexto de implantação;
- correlação;
- abstrações transacionais;
- paginação;
- resultado padronizado;
- contratos técnicos realmente transversais.

Não deverá permanecer compartilhado:

- regras de vínculo;
- regras de autorização;
- estados específicos;
- validações específicas de pessoa ou veículo;
- DTOs genéricos sem semântica;
- repositório universal;
- service base com múltiplas responsabilidades.

Duplicação pequena e temporária será preferível a acoplamento incorreto, com revisão posterior baseada em uso real.

---

# 18. Aplicação das fronteiras

As fronteiras serão protegidas por:

- namespaces;
- visibilidade e convenções;
- interfaces públicas;
- revisão de código;
- testes arquiteturais;
- análise estática;
- documentação de dependências;
- ownership lógico;
- proibição de ciclos.

Uma ferramenta específica de teste arquitetural poderá ser escolhida no Manual do Desenvolvedor sem alterar esta decisão, desde que seja compatível com PHP/Laravel e o pipeline.

---

# 19. Estratégia de testes

## 19.1 Por módulo

- testes unitários do domínio;
- testes de casos de uso;
- testes de persistência;
- testes de policies;
- testes de componentes;
- testes de contrato.

## 19.2 Arquiteturais

Deverão verificar:

- Domínio não depende de Laravel HTTP, Livewire ou adaptadores;
- módulo não importa internals de outro;
- dependências não formam ciclos;
- controllers e componentes não acessam banco diretamente;
- adaptadores implementam portas;
- módulos respeitam contexto de implantação.

## 19.3 Integrados

Fluxos ponta a ponta validarão a colaboração entre módulos sem exigir que todos os testes sejam integrados.

---

# 20. Consequências positivas

- menor complexidade de implantação;
- transações locais para fluxos críticos;
- fronteiras de domínio visíveis;
- testes mais focados;
- uma base técnica coerente com Laravel;
- menor custo inicial;
- desenvolvimento local simples;
- evolução incremental;
- integração desacoplada;
- caminho de extração futura baseado em métricas;
- rastreabilidade entre módulos e regras.

---

# 21. Consequências negativas

- disciplina arquitetural depende de automação e revisão;
- falha grave da aplicação poderá afetar vários módulos;
- deploy principal é conjunto;
- banco compartilhado exige governança;
- crescimento do repositório exigirá convenções;
- equipe deverá compreender camadas e contratos;
- alterações transversais poderão exigir coordenação;
- escalabilidade independente de um módulo será limitada até eventual extração.

Essas consequências são aceitas para o contexto do MVP.

---

# 22. Riscos e mitigações

| Risco | Mitigação |
|---|---|
| monólito virar código sem fronteiras | testes arquiteturais e revisão |
| excesso de abstrações | criar contratos apenas para limites reais |
| `Shared` crescer indevidamente | ownership e critérios de entrada |
| regra em Livewire/controller | casos de uso obrigatórios |
| acesso cruzado a models | namespaces, testes e revisão |
| eventos usados para esconder fluxo | contrato e critérios síncrono/assíncrono |
| dependência circular | teste automatizado |
| deploy conjunto limitar evolução | métricas e ADR de extração |
| banco compartilhado gerar acoplamento | proprietário lógico e escrita exclusiva |
| pacote modular abandonar manutenção | não depender de pacote como fundamento |

---

# 23. Segurança e privacidade

A decisão:

- mantém autorização no servidor;
- permite transações e auditoria centralizadas;
- exige contexto de implantação em todos os módulos;
- evita envio desnecessário de dados por rede;
- concentra gestão de dependências e patches;
- mantém segredos na infraestrutura;
- não autoriza acesso transversal a dados.

O monólito modular não será tratado como zona única de confiança. Cada caso de uso continuará validando usuário, implantação, recurso e ação.

---

# 24. Impacto operacional

## 24.1 Implantação

- uma imagem principal;
- processos web, worker e scheduler;
- migrations coordenadas;
- rollback ou roll-forward conjunto;
- observabilidade por módulo lógico.

## 24.2 Suporte

Logs e métricas deverão incluir módulo, operação e correlação para evitar que a unidade de implantação única reduza a capacidade de diagnóstico.

## 24.3 Escala

Web e workers poderão escalar horizontalmente. Filas poderão separar cargas por finalidade.

---

# 25. Estratégia de implementação

1. aprovar este ADR;
2. aprovar ADR-002 e ADR-003;
3. definir convenções no Manual do Desenvolvedor;
4. criar esqueleto Laravel sem módulos funcionais completos;
5. criar contexto de implantação;
6. implementar módulo piloto pequeno;
7. adicionar testes arquiteturais;
8. validar fluxo entre dois módulos;
9. criar padrões de caso de uso, DTO e evento;
10. documentar exceções;
11. revisar antes de expandir a estrutura.

O módulo piloto deverá provar a arquitetura sem antecipar desenvolvimento de todo o produto.

---

# 26. Validação

A decisão será considerada tecnicamente validada quando:

- aplicação iniciar em Docker;
- módulo piloto puder ser testado isoladamente;
- caso de uso for chamado por controller ou Livewire fino;
- regra de domínio não depender da interface;
- persistência implementar contrato;
- módulo consumir contrato público de outro;
- teste impedir dependência proibida;
- job assíncrono preservar correlação e implantação;
- pipeline executar testes arquiteturais;
- documentação permitir adicionar módulo sem ambiguidade.

---

# 27. Critérios de aceite

**CA-ADR-001-001:** existe uma única aplicação Laravel implantável no MVP.

**CA-ADR-001-002:** web, worker e scheduler usam o mesmo artefato versionado.

**CA-ADR-001-003:** os módulos são organizados por domínio.

**CA-ADR-001-004:** Interface, Aplicação, Domínio e Infraestrutura possuem responsabilidades distintas.

**CA-ADR-001-005:** o Domínio não depende de HTTP, Livewire, S3 ou fabricantes.

**CA-ADR-001-006:** controllers e componentes Livewire chamam casos de uso.

**CA-ADR-001-007:** escrita em tabela de outro módulo não é permitida.

**CA-ADR-001-008:** dependências circulares são proibidas.

**CA-ADR-001-009:** comunicação entre módulos utiliza contrato explícito.

**CA-ADR-001-010:** efeitos externos não precedem a confirmação transacional.

**CA-ADR-001-011:** integrações implementam portas do núcleo.

**CA-ADR-001-012:** contexto de implantação atravessa todos os módulos.

**CA-ADR-001-013:** fronteiras possuem teste arquitetural.

**CA-ADR-001-014:** código compartilhado não absorve regras específicas.

**CA-ADR-001-015:** microsserviços não são introduzidos sem novo ADR.

**CA-ADR-001-016:** o serviço Python permanece condicional ao ADR-011.

---

# 28. Rastreabilidade

## 28.1 Documentos

- `docs/000_DIRETRIZES_DO_PROJETO.md`;
- `docs/001_VOLUME_01_PRODUCT_BOOK_PARTE_03.md`;
- `docs/009_REGRAS_DE_NEGOCIO.md`;
- `docs/010_BANCO_DE_DADOS.md`;
- `docs/011_ARQUITETURA_DO_SISTEMA.md`;
- `docs/ADR/000_CATALOGO_DE_ADRS.md`.

## 28.2 Regras e requisitos

- `RN-055` — segregação por implantação;
- `RN-056` — separação de entidades;
- `RN-057` — transições válidas;
- `RN-079`, `RN-085`, `RN-092` — idempotência;
- `RN-088` — falha externa sem perda;
- `RN-090` a `RN-093` — integrações;
- `RN-100` — segredos e concorrência;
- `RNF-007` — arquitetura modular;
- `RNF-011` — continuidade.

---

# 29. Dependências

| ADR | Relação |
|---|---|
| ADR-002 | define isolamento transversal |
| ADR-003 | define identificadores usados nos módulos |
| ADR-004 | define eventos, auditoria e outbox |
| ADR-005 | define infraestrutura assíncrona |
| ADR-007 | detalha portas e adaptadores |
| ADR-009 | define segredos |
| ADR-012 | define implantação e rollback |

Este ADR orienta os demais, mas não aprova suas escolhas específicas.

---

# 30. Pendências

| PEN-ADR-001 | Pendência | Tratamento |
|---|---|---|
| PEN-ADR-001-001 | Estrutura física final de namespaces | Manual do Desenvolvedor |
| PEN-ADR-001-002 | Ferramenta de testes arquiteturais | prova técnica |
| PEN-ADR-001-003 | Convenção de migrations por módulo | Manual do Desenvolvedor |
| PEN-ADR-001-004 | Política de consultas cruzadas para relatórios | especificação de APIs/relatórios |
| PEN-ADR-001-005 | Necessidade futura de agente local | inventário de equipamentos e ADR-007 |

---

# 31. Aprovação

| Papel | Nome | Decisão | Data | Observações |
|---|---|---|---|---|
| Product Owner | Vinicius Velasco de Azevedo | Pendente | — | Aguardando decisão |
| Responsável técnico | Soluções do Vale Tecnologia | Recomendado | Julho/2026 | Alternativa B recomendada para o MVP |

---

# 32. Decisão resultante

Enquanto este ADR estiver **Proposto**, o monólito modular permanece arquitetura recomendada, mas a estrutura inicial do código continua bloqueada.

Se aprovado:

- o estado mudará para **Aprovado**;
- o catálogo será atualizado no mesmo commit;
- o Manual do Desenvolvedor deverá detalhar convenções;
- novos módulos deverão respeitar esta decisão;
- eventual substituição exigirá novo ADR.

---

## Situação do ADR

**Proposto.** Aguardando aprovação formal do Product Owner.
