# SDV ACCESS — PRODUCT BOOK
## Volume 01 — Visão do Produto e Requisitos de Negócio

**Documento:** SDV-PBK-001  
**Versão:** 1.0.0  
**Status:** Em elaboração  
**Produto:** SDV Access — Implantação Santa Rita  
**Empresa proprietária:** Soluções do Vale  
**Responsável pelo produto:** Vinicius Velasco de Azevedo  
**Data:** Julho de 2026

---

## Controle de versões

| Versão | Data | Responsável | Alteração |
|---|---|---|---|
| 1.0.0 | Julho/2026 | Soluções do Vale | Estrutura inicial do Product Book |

---

## 1. Apresentação

O SDV Access é uma plataforma de gestão de acessos concebida para centralizar cadastros, validações, autorizações, registros de entrada e saída, auditoria e integração com equipamentos físicos de controle de acesso.

Na implantação Santa Rita, o produto deverá seguir as telas, fluxos, identidade visual, disposição de campos, hierarquia de informações e padrões de navegação representados nas imagens aprovadas durante o processo de definição do projeto.

As imagens aprovadas constituem referência funcional e visual. Nenhuma implementação deverá substituí-las por layouts genéricos, reinterpretar campos sem justificativa formal ou alterar a experiência aprovada sem registro de mudança.

---

## 2. Visão do produto

O SDV Access deverá operar como camada central de decisão entre pessoas, propriedades, autorizações e equipamentos físicos.

Seu papel não se limita ao armazenamento de cadastros. A plataforma deverá:

- organizar os vínculos entre propriedades, moradores, inquilinos, visitantes, prestadores, empresas e veículos;
- permitir validação rápida pela portaria;
- registrar todas as movimentações relevantes;
- aplicar regras de vigência e permissão;
- manter histórico de vínculos e acessos;
- integrar-se a controladoras, reconhecimento facial, OCR e leitura de placas;
- disponibilizar informações para administração, auditoria e relatórios.

---

## 3. Objetivo geral

Desenvolver uma plataforma segura, escalável e operacionalmente simples para gerenciar acessos de pessoas e veículos, mantendo rastreabilidade completa e reduzindo processos manuais.

---

## 4. Objetivos específicos

O sistema deverá:

1. centralizar cadastros;
2. reduzir duplicidades;
3. permitir localização rápida de pessoas e propriedades;
4. registrar entradas e saídas;
5. controlar períodos de vigência;
6. permitir pré-cadastro;
7. validar visitantes e prestadores;
8. gerenciar veículos;
9. integrar-se a equipamentos físicos;
10. registrar logs de auditoria;
11. controlar usuários, perfis e permissões;
12. oferecer base para futuras expansões.

---

## 5. Princípios do produto

### 5.1 A propriedade como entidade central

O cadastro principal do produto será a propriedade.

```text
Condomínio ou organização
└── Bloco, setor ou agrupamento opcional
    └── Propriedade
        ├── Moradores
        ├── Inquilinos
        ├── Outros ocupantes
        ├── Visitantes
        ├── Prestadores vinculados
        └── Veículos
```

O endereço pertence à propriedade. Pessoas serão vinculadas a ela conforme sua função, período e situação.

### 5.2 Fonte única da verdade

Cada entidade deverá possuir um cadastro principal. Os demais módulos deverão referenciar esse cadastro, evitando cópias independentes.

### 5.3 Histórico em vez de exclusão destrutiva

Sempre que houver relevância jurídica, operacional ou de auditoria, o sistema deverá preservar histórico.

### 5.4 Separação entre cadastro e autorização

Estar cadastrado não significa possuir acesso autorizado. Cadastro, vigência, credencial, permissão e evento de acesso são conceitos distintos.

### 5.5 Aderência às telas aprovadas

As telas aprovadas por imagem terão precedência visual e funcional durante o detalhamento de UX/UI. Divergências deverão ser documentadas antes da implementação.

---

## 6. Público-alvo

O produto é aplicável a:

- condomínios residenciais;
- condomínios comerciais;
- loteamentos;
- empresas;
- indústrias;
- universidades;
- hospitais;
- clubes;
- organizações com controle de entrada e saída.

A implantação Santa Rita deverá ser tratada como aplicação inicial do produto, sem impedir sua evolução futura para arquitetura multiunidade e multicliente.

---

## 7. Personas principais

### 7.1 Operador de portaria

Necessita consultar, validar e liberar acessos com rapidez, reduzindo digitação e decisões subjetivas.

### 7.2 Administrador do sistema

Gerencia usuários, perfis, permissões, parâmetros, equipamentos e cadastros estruturais.

### 7.3 Gestor ou síndico

Consulta informações operacionais, históricos, indicadores e auditoria.

### 7.4 Morador ou responsável pela propriedade

Mantém vínculos autorizados, visitantes e informações relacionadas à propriedade, conforme permissões disponibilizadas.

### 7.5 Visitante

Pode realizar pré-cadastro e apresentar dados para validação.

### 7.6 Prestador de serviço

Possui vínculo com empresa, atividade, período de autorização e documentação aplicável.

---

## 8. Escopo funcional inicial

A versão inicial deverá contemplar:

- autenticação;
- dashboard;
- validação;
- pré-cadastro;
- propriedades;
- moradores;
- inquilinos;
- outros ocupantes;
- visitantes;
- empresas;
- prestadores;
- veículos;
- entradas e saídas;
- usuários;
- perfis;
- permissões;
- logs;
- relatórios operacionais;
- integrações de acesso previstas para a implantação.

---

## 9. Fora do escopo inicial

Permanecem fora do MVP, salvo aprovação posterior:

- aplicativo móvel nativo;
- cobrança bancária automática;
- PIX integrado;
- marketplace;
- inteligência comportamental avançada;
- chat interno;
- controle patrimonial;
- gestão completa de ocorrências;
- ERP financeiro completo.

---

## 10. Arquitetura funcional

O fluxo macro do produto será:

```text
Cadastro
  ↓
Validação de dados
  ↓
Definição de vínculo
  ↓
Definição de vigência
  ↓
Definição de permissão
  ↓
Emissão ou associação de credencial
  ↓
Solicitação ou tentativa de acesso
  ↓
Decisão do SDV Access
  ↓
Comando ao equipamento
  ↓
Registro do evento
  ↓
Auditoria e relatório
```

---

# 11. Catálogo inicial de regras de negócio

## 11.1 Propriedades

**RN-001 — Existência prévia da propriedade**  
Uma propriedade deve existir antes que moradores, inquilinos, visitantes vinculados ou veículos residenciais sejam associados a ela.

**RN-002 — Endereço centralizado**  
O endereço será armazenado na propriedade, não repetido em cada pessoa vinculada.

**RN-003 — Identificação única**  
Cada propriedade deverá possuir identificação única dentro da organização, composta conforme a estrutura aprovada, como bloco, unidade, número ou código interno.

**RN-004 — Múltiplos ocupantes**  
Uma propriedade poderá possuir vários ocupantes ativos simultaneamente.

**RN-005 — Histórico de ocupação**  
A troca de ocupantes não deverá apagar vínculos anteriores. O sistema deverá preservar o histórico de início e término.

**RN-006 — Situação da propriedade**  
A propriedade deverá possuir estado operacional, como ativa, inativa, bloqueada ou em implantação, conforme parametrização futura.

---

## 11.2 Pessoas e vínculos

**RN-007 — Cadastro único de pessoa**  
Uma pessoa não deverá ser duplicada apenas por possuir mais de um vínculo.

**RN-008 — Vínculos independentes**  
A mesma pessoa poderá possuir vínculos distintos em períodos ou propriedades diferentes, quando permitido.

**RN-009 — Classificação do vínculo**  
Cada vínculo deverá identificar sua natureza, como morador, proprietário, inquilino, dependente, funcionário, prestador ou outro.

**RN-010 — Situação do vínculo**  
O vínculo deverá possuir situação própria, independente da situação cadastral da pessoa.

**RN-011 — Vigência**  
Vínculos temporários deverão conter data de início e data de término.

**RN-012 — Expiração automática**  
Ao término da vigência, o vínculo deverá perder validade automaticamente, sem depender de ação manual.

**RN-013 — Dados obrigatórios**  
A obrigatoriedade dos campos deverá variar conforme o tipo de pessoa e a tela aprovada.

**RN-014 — Documento duplicado**  
O sistema deverá impedir ou alertar sobre documentos únicos já vinculados a outra pessoa.

---

## 11.3 Moradores

**RN-015 — Vínculo com propriedade**  
Todo morador deverá estar vinculado a pelo menos uma propriedade.

**RN-016 — Responsável principal**  
Uma propriedade poderá possuir um ou mais responsáveis, mas deverá existir regra clara para identificar o responsável principal quando necessário.

**RN-017 — Acesso independente**  
A permissão de acesso de um morador deverá ser controlada individualmente.

**RN-018 — Veículos do morador**  
Os veículos poderão ser vinculados ao morador, à propriedade ou a ambos, conforme modelo definitivo de dados.

---

## 11.4 Inquilinos

**RN-019 — Período obrigatório**  
O vínculo de inquilino deverá possuir início e término obrigatórios.

**RN-020 — Encerramento automático**  
Ao final do contrato ou período informado, o acesso deverá ser suspenso automaticamente.

**RN-021 — Renovação rastreável**  
Prorrogações deverão gerar histórico de alteração, sem apagar os dados anteriores.

**RN-022 — Conflito de vigência**  
O sistema deverá alertar quando houver vínculos incompatíveis ou sobrepostos conforme regra da propriedade.

---

## 11.5 Visitantes

**RN-023 — Responsável pelo visitante**  
Todo visitante autorizado deverá possuir um responsável identificado.

**RN-024 — Propriedade de destino**  
Todo visitante deverá estar associado a uma propriedade ou destino válido.

**RN-025 — Autorização limitada**  
A autorização deverá conter período, quantidade de acessos ou condição de validade.

**RN-026 — Pré-cadastro não é liberação automática**  
O pré-cadastro registra dados, mas não garante acesso até que a validação exigida seja concluída.

**RN-027 — Reutilização controlada**  
Visitantes recorrentes poderão reutilizar cadastro pessoal, mas novas visitas deverão gerar novas autorizações quando aplicável.

**RN-028 — Documento e imagem**  
A captura de documento e imagem deverá seguir os campos e etapas das telas aprovadas.

---

## 11.6 Prestadores e empresas

**RN-029 — Prestador vinculado à empresa**  
Quando aplicável, o prestador deverá estar vinculado a uma empresa cadastrada.

**RN-030 — Autorização por atividade**  
A autorização poderá registrar serviço, local, responsável, período e observações.

**RN-031 — Documentação obrigatória**  
O sistema poderá exigir documentos conforme categoria do prestador.

**RN-032 — Vigência temporária**  
Prestadores deverão possuir período de autorização definido, salvo categorias permanentes aprovadas.

**RN-033 — Empresa inativa**  
A inativação da empresa deverá impedir novas autorizações, sem apagar registros históricos.

---

## 11.7 Veículos

**RN-034 — Placa como identificador operacional**  
A placa deverá ser normalizada para busca e integração, preservando a apresentação adequada na interface.

**RN-035 — Duplicidade de placa**  
Uma mesma placa não deverá gerar cadastros ativos conflitantes sem alerta.

**RN-036 — Vínculo de veículo**  
O veículo deverá possuir vínculo com pessoa, propriedade, empresa ou autorização temporária.

**RN-037 — Situação do veículo**  
O veículo deverá possuir situação própria, como ativo, inativo, bloqueado ou temporário.

**RN-038 — Leitura de placa**  
A leitura automática não deverá liberar o acesso sem consulta às permissões válidas.

---

## 11.8 Acesso

**RN-039 — Cadastro não implica autorização**  
A existência do cadastro não garante acesso.

**RN-040 — Decisão centralizada**  
O SDV Access deverá ser responsável pela decisão de autorização, ainda que o equipamento mantenha cache operacional.

**RN-041 — Registro de tentativa**  
Tentativas autorizadas, negadas, manuais ou com falha deverão ser registradas.

**RN-042 — Entrada e saída**  
Os eventos deverão distinguir direção, ponto de acesso e método de identificação.

**RN-043 — Liberação manual**  
Toda liberação manual deverá identificar o operador e o motivo.

**RN-044 — Acesso excepcional**  
Exceções deverão possuir justificativa, responsável e validade.

**RN-045 — Credenciais**  
Credenciais como face, placa, QR Code, tag ou código deverão possuir estado e vigência próprios.

---

## 11.9 Auditoria

**RN-046 — Operações auditáveis**  
Criações, alterações, inativações, permissões, acessos e liberações deverão gerar registros auditáveis.

**RN-047 — Conteúdo mínimo do log**  
O log deverá conter data, hora, usuário, operação, entidade, identificador, origem e dados relevantes da alteração.

**RN-048 — Imutabilidade lógica**  
Registros de auditoria não deverão ser editáveis por usuários operacionais.

**RN-049 — Valor anterior e posterior**  
Alterações relevantes deverão registrar os valores antes e depois da operação.

---

## 11.10 Usuários e permissões

**RN-050 — Usuário individual**  
Cada operador deverá utilizar credencial própria.

**RN-051 — Perfil de acesso**  
Permissões deverão ser atribuídas por perfil e, quando necessário, por exceção individual.

**RN-052 — Menor privilégio**  
O usuário deverá receber somente as permissões necessárias para sua função.

**RN-053 — Ações críticas**  
Operações críticas poderão exigir confirmação adicional ou permissão específica.

**RN-054 — Inativação imediata**  
Usuários inativados não poderão iniciar novas sessões.

---

# 12. Requisitos funcionais iniciais

**RF-001 — Autenticar usuário**  
O sistema deverá permitir login de usuários autorizados.

**RF-002 — Recuperar acesso**  
O sistema deverá disponibilizar processo seguro de recuperação de senha.

**RF-003 — Exibir dashboard**  
O sistema deverá apresentar painel conforme perfil e layout aprovado.

**RF-004 — Pesquisar cadastros**  
O sistema deverá permitir pesquisas por nome, documento, propriedade, placa e outros campos previstos nas telas.

**RF-005 — Cadastrar propriedade**  
O sistema deverá permitir cadastro e manutenção de propriedades.

**RF-006 — Cadastrar morador**  
O sistema deverá permitir cadastro e vínculo de moradores.

**RF-007 — Cadastrar inquilino**  
O sistema deverá permitir cadastro com vigência obrigatória.

**RF-008 — Cadastrar visitante**  
O sistema deverá permitir cadastro, pré-cadastro e autorização.

**RF-009 — Cadastrar empresa**  
O sistema deverá permitir cadastro de empresas prestadoras.

**RF-010 — Cadastrar prestador**  
O sistema deverá permitir vínculo com empresa e período de autorização.

**RF-011 — Cadastrar veículo**  
O sistema deverá permitir registro e vínculo de veículos.

**RF-012 — Validar acesso**  
O sistema deverá consultar situação, vigência, permissão e credencial.

**RF-013 — Registrar entrada**  
O sistema deverá registrar eventos de entrada.

**RF-014 — Registrar saída**  
O sistema deverá registrar eventos de saída.

**RF-015 — Registrar acesso negado**  
O sistema deverá manter histórico de negativas.

**RF-016 — Realizar liberação manual**  
Usuários autorizados poderão liberar acesso com justificativa.

**RF-017 — Gerenciar usuários**  
O sistema deverá permitir criação, alteração e inativação de usuários.

**RF-018 — Gerenciar perfis**  
O sistema deverá permitir configuração de perfis e permissões.

**RF-019 — Consultar auditoria**  
Usuários autorizados deverão consultar logs e alterações.

**RF-020 — Gerar relatórios**  
O sistema deverá gerar relatórios operacionais com filtros.

---

# 13. Requisitos não funcionais iniciais

**RNF-001 — Segurança**  
A aplicação deverá adotar boas práticas de autenticação, autorização, proteção de sessão e armazenamento seguro.

**RNF-002 — LGPD**  
Dados pessoais deverão ser tratados conforme necessidade, finalidade, controle de acesso e retenção definida.

**RNF-003 — Responsividade**  
As telas deverão funcionar nas resoluções previstas, preservando o padrão visual aprovado.

**RNF-004 — Desempenho operacional**  
Consultas utilizadas pela portaria deverão priorizar resposta rápida.

**RNF-005 — Disponibilidade**  
A arquitetura deverá ser compatível com operação contínua e procedimentos de recuperação.

**RNF-006 — Escalabilidade**  
O sistema deverá permitir crescimento de cadastros, eventos e unidades.

**RNF-007 — Manutenibilidade**  
O código deverá seguir arquitetura modular e documentação permanente.

**RNF-008 — Observabilidade**  
A aplicação deverá registrar erros, eventos técnicos e integrações relevantes.

**RNF-009 — Compatibilidade de integração**  
Integrações deverão ser desacopladas das regras centrais do produto.

**RNF-010 — Backup**  
A implantação deverá possuir política de backup e restauração validada.

---

# 14. Diretrizes técnicas aprovadas

- Backend: Laravel.
- Frontend: Blade e Livewire.
- Banco de dados: PostgreSQL.
- Armazenamento de arquivos: serviço compatível com S3.
- OCR e recursos específicos de IA: Python/FastAPI quando necessário.
- Implantação: Docker.
- React não será utilizado no MVP, salvo revisão formal de arquitetura.
- O desenvolvimento somente deverá avançar após aprovação das telas correspondentes.

---

# 15. Critérios gerais de aceite

Uma funcionalidade será considerada pronta quando:

1. estiver aderente à tela aprovada;
2. cumprir as regras de negócio relacionadas;
3. aplicar as permissões previstas;
4. gerar auditoria quando necessário;
5. possuir validações e mensagens adequadas;
6. passar pelos testes funcionais;
7. não introduzir regressões conhecidas;
8. possuir documentação atualizada.

---

# 16. Rastreabilidade documental

Este Product Book será referência para:

- especificação de regras de negócio;
- UX/UI;
- modelo de dados;
- APIs;
- arquitetura;
- testes;
- implantação;
- manuais de usuário;
- registros de decisão arquitetural.

Toda regra receberá identificador permanente. Alterações deverão preservar rastreabilidade e histórico de versão.

---

## Situação desta parte

Esta parte formaliza a visão do produto, o escopo inicial, os princípios arquiteturais, o primeiro catálogo de regras de negócio e os requisitos iniciais.

Os próximos arquivos do Volume 01 ampliarão:

- jornadas completas;
- casos de uso;
- estados e transições;
- exceções operacionais;
- matriz de permissões;
- critérios de homologação;
- riscos e dependências;
- roadmap e encerramento do volume.
