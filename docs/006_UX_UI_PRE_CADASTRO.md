# SDV ACCESS — UX/UI DO PRÉ-CADASTRO
## Fluxo público, análise pela portaria e acompanhamento

**Documento:** SDV-UXP-006  
**Versão:** 1.1.0
**Status:** Aprovado  
**Produto:** SDV Access — Implantação Santa Rita  
**Marca proprietária:** Soluções do Vale Tecnologia  
**Responsável pelo produto:** Vinicius Velasco de Azevedo  
**Data:** Julho de 2026

---

## Controle de versões

| Versão | Data | Responsável | Alteração |
|---|---|---|---|
| 1.0.0 | Julho/2026 | Soluções do Vale | Especificação inicial do Pré-Cadastro e da análise pela portaria |
| 1.0.1 | 28/07/2026 | Product Owner | Aprovação formal da especificação UX/UI do Pré-Cadastro |
| 1.1.0 | 12/08/2026 | Product Owner | Conferência visual de arquivos e revelação controlada de documento durante a análise pela portaria |

---

# 1. Objetivo

Este documento especifica a experiência do Pré-Cadastro do SDV Access, incluindo:

- acesso público por convite seguro;
- coleta de dados em seis etapas;
- proteção e transparência sobre dados pessoais;
- documento e OCR assistido;
- selfie;
- veículo opcional;
- confirmação e protocolo;
- acompanhamento;
- consulta e análise pela portaria;
- aprovação, rejeição e solicitação de correção;
- expiração e retomada;
- responsividade, acessibilidade e segurança.

O Pré-Cadastro reduz o tempo de atendimento, mas não concede acesso automaticamente. Toda entrada continua sujeita à validação das condições vigentes.

---

# 2. Fontes e rastreabilidade

## 2.1 Referência visual

**REF-UXP-001:** `docs/references/ChatGPT Image 27 de jul. de 2026, 13_55_44.png`

A referência apresenta dois contextos:

1. **Pré-Cadastro — Visitante/Turista**, com boas-vindas e seis etapas;
2. **Portaria — Consulta e Aprovação do Pré-Cadastro**, com fila, filtros, tabela e drawer de detalhes.

Elementos observados:

- identidade SDV Access;
- experiência pública clara;
- progresso de 1 a 6;
- dados pessoais;
- endereço;
- fotografia de documento;
- retorno de extração;
- selfie;
- veículo opcional;
- confirmação com protocolo;
- acompanhamento da situação;
- navegação administrativa lateral;
- contadores por estado;
- busca e filtros;
- detalhe com foto, documento, endereço e veículo;
- ações de aprovar e rejeitar.

Os dados, protocolos, endereços, imagens, contagens e datas presentes na referência são ilustrativos.

## 2.2 Regras de negócio

| Identificador | Relação |
|---|---|
| `RN-007` e `RN-014` | Cadastro único e tratamento de documento duplicado |
| `RN-023` | Responsável pelo visitante |
| `RN-024` | Imóvel ou destino válido |
| `RN-025` | Autorização limitada |
| `RN-026` | Pré-cadastro não é liberação automática |
| `RN-027` | Reutilização controlada |
| `RN-028` | Documento e imagem conforme fluxo aprovado |
| `RN-029` a `RN-033` | Prestador, empresa, documentação e vigência |
| `RN-034` a `RN-038` | Veículo e placa |
| `RN-046` a `RN-049` | Auditoria |

## 2.3 Requisitos

| Identificador | Relação |
|---|---|
| `RF-008` | Cadastrar, pré-cadastrar e autorizar visitante |
| `RF-010` | Cadastrar prestador |
| `RF-011` | Cadastrar veículo |
| `RF-021` | Adaptar campos por tipo de acesso |
| `RF-025` | Salvar rascunho |
| `RF-026` | Capturar documento |
| `RF-027` | Extrair dados por OCR com conferência |
| `RF-028` | Capturar selfie |
| `RF-029` | Gerar protocolo |
| `RF-030` | Aprovar ou rejeitar |
| `RF-037` | Exibir situação de integração |
| `RF-039` | Notificar pendências |
| `RF-040` | Aplicar permissões por ação |
| `RNF-002` | LGPD |
| `RNF-011` | Aplicação web segura |
| `RNF-016` | Proteção de arquivos |
| `RNF-018` | Responsividade móvel |
| `RNF-019` | Acessibilidade |

## 2.4 Casos de uso e exceções

- `UC-004 — Realizar pré-cadastro`;
- `UC-005 — Aprovar pré-cadastro`;
- `EX-001 — Pessoa já cadastrada`;
- `EX-002 — CPF inválido`;
- `EX-003 — Documento ilegível`;
- `EX-004 — Foto facial inadequada`.

## 2.5 Componentes do Design System

- `DS-CMP-003 — Botão`;
- `DS-CMP-004 — Grupo de ações`;
- `DS-CMP-005 — Campo de texto`;
- `DS-CMP-006 — Seleção`;
- `DS-CMP-007 — Autocomplete`;
- `DS-CMP-008 — Data e período`;
- `DS-CMP-009 — Checkbox, radio e switch`;
- `DS-CMP-010 — Upload e captura`;
- `DS-CMP-011 — Badge de status`;
- `DS-CMP-012 — Alerta`;
- `DS-CMP-013 — Toast`;
- `DS-CMP-014 — Estado vazio`;
- `DS-CMP-015 — Skeleton e progresso`;
- `DS-CMP-016 — Card`;
- `DS-CMP-017 — Tabela`;
- `DS-CMP-018 — Lista de atividade`;
- `DS-CMP-022 — Stepper`;
- `DS-CMP-023 — Paginação`;
- `DS-CMP-024 — Modal`;
- `DS-CMP-025 — Drawer`;
- `DS-CMP-036 — Protocolo`.

---

# 3. Públicos e objetivos

## 3.1 Visitante

Deseja informar seus dados antecipadamente e reduzir o tempo de atendimento.

## 3.2 Turista

Deseja registrar uma ocupação temporária vinculada a imóvel, responsável e período.

## 3.3 Prestador

Deseja informar identificação, empresa, atividade, período e documentação exigida.

## 3.4 Operador de portaria

Deseja localizar, revisar e decidir solicitações com rapidez, preservando histórico e justificativa.

## 3.5 Responsável ou morador

Poderá emitir ou originar convite, acompanhar solicitações próprias e receber notificações, conforme escopo futuro e permissões aprovadas.

---

# 4. Princípios da jornada

**UXP-PR-001 — Mobile-first**  
O fluxo público será projetado prioritariamente para celular.

**UXP-PR-002 — Transparência**  
Finalidade, dados coletados e situação deverão ser explicados.

**UXP-PR-003 — Menor coleta**  
Somente dados necessários ao tipo de acesso serão solicitados.

**UXP-PR-004 — Progresso preservado**  
Retorno entre etapas não deverá perder dados válidos.

**UXP-PR-005 — Automação assistida**  
OCR auxilia, mas não substitui conferência.

**UXP-PR-006 — Aprovação separada**  
Envio, aprovação e entrada são eventos distintos.

**UXP-PR-007 — Correção rastreável**  
Correções não apagarão a versão analisada.

**UXP-PR-008 — Segurança proporcional**  
Convites, arquivos e acompanhamento serão protegidos sem tornar o fluxo impraticável.

---

# 5. Contextos da experiência

```text
Emissão do convite
        ↓
Pré-Cadastro público
        ↓
Protocolo e acompanhamento
        ↓
Fila da portaria
        ↓
Análise e decisão
        ├── Aprovado
        ├── Rejeitado
        ├── Correção solicitada
        └── Expirado
        ↓
Validação de Entrada
```

O documento especifica os três contextos centrais:

1. fluxo público;
2. acompanhamento;
3. análise administrativa.

---

# 6. Convite e acesso seguro

## 6.1 Formas de acesso

- link temporário;
- QR Code;
- convite emitido pelo sistema;
- retomada por mecanismo seguro;
- acompanhamento por protocolo e fator adicional, quando aprovado.

## 6.2 Dados do convite

O convite poderá carregar:

- implantação;
- imóvel de destino;
- responsável;
- tipo de acesso;
- finalidade;
- período previsto;
- quantidade de cadastros;
- expiração;
- emissor;
- regras aplicáveis.

Esses dados não deverão ser expostos integralmente na URL.

## 6.3 Validação

Antes de iniciar:

- verificar existência;
- assinatura ou token;
- expiração;
- cancelamento;
- quantidade utilizada;
- implantação;
- vínculo do emissor;
- tipo permitido;
- integridade.

## 6.4 Estados do convite

- válido;
- expirado;
- cancelado;
- já utilizado;
- limite atingido;
- inválido;
- indisponível.

## 6.5 Mensagens

| Estado | Mensagem recomendada |
|---|---|
| Expirado | Este convite expirou. Solicite um novo convite ao responsável. |
| Cancelado | Este convite foi cancelado e não pode mais ser utilizado. |
| Utilizado | Este convite já foi utilizado. Acompanhe a solicitação existente. |
| Inválido | Não foi possível validar este convite. Confira o endereço recebido. |

Nenhuma mensagem deverá revelar dados do responsável ou imóvel antes da validação necessária.

---

# 7. Tela de boas-vindas

## 7.1 Conteúdo

Conforme a referência:

- marca;
- identificação da implantação;
- imagem institucional autorizada;
- título “Bem-vindo”;
- explicação breve;
- benefícios;
- ação “Iniciar Pré-Cadastro”;
- ação “Já possui cadastro? Acompanhar situação”.

## 7.2 Informações obrigatórias

Antes de iniciar, informar:

- finalidade geral;
- responsável pelo tratamento;
- dados que poderão ser solicitados;
- necessidade de análise;
- ausência de liberação automática;
- acesso ao aviso de privacidade;
- canal de contato.

## 7.3 Imagem

A imagem da portaria ou implantação:

- deverá possuir autorização de uso;
- não deverá revelar vulnerabilidades físicas;
- deverá ter alternativa textual adequada;
- poderá ser omitida em conexão lenta sem bloquear o fluxo.

---

# 8. Estrutura das seis etapas

## 8.1 Ordem

1. Dados pessoais;
2. Endereço;
3. Documento;
4. Selfie;
5. Veículo opcional;
6. Confirmação.

## 8.2 Stepper

O stepper deverá indicar:

- etapa atual;
- número total;
- etapas concluídas;
- etapas com erro;
- navegação permitida.

Em celular:

- apresentar “Etapa X de 6”;
- exibir nome da etapa;
- usar barra de progresso;
- não comprimir seis rótulos lado a lado.

## 8.3 Navegação

Cada etapa deverá possuir:

- voltar;
- continuar;
- salvar rascunho quando aplicável;
- ajuda contextual;
- validação antes do avanço.

## 8.4 Preservação

Os dados deverão ser preservados:

- ao voltar;
- após erro de validação;
- em atualização de página quando houver rascunho;
- durante upload;
- em retomada segura.

Dados sensíveis não deverão permanecer indefinidamente no navegador.

---

# 9. Etapa 1 — Dados pessoais

## 9.1 Campos-base

Conforme a referência e o tipo de acesso:

- nome completo;
- nome social, quando aplicável;
- CPF;
- RG ou documento;
- data de nascimento;
- telefone;
- e-mail;
- tipo de acesso;
- nacionalidade ou documento estrangeiro, quando definido.

## 9.2 Tipo de acesso

O tipo poderá ser predefinido pelo convite ou selecionado entre opções permitidas:

- visitante;
- turista;
- prestador.

Alterar o tipo:

- recalcula campos;
- preserva dados comuns;
- avisa antes de descartar dados exclusivos;
- não permite tipo fora do convite.

## 9.3 Validações

- nome completo obrigatório conforme regra;
- CPF normalizado e validado quando aplicável;
- documento alternativo para estrangeiro;
- data válida e não futura;
- telefone em formato aceito;
- e-mail válido quando obrigatório;
- idade e responsável legal conforme política futura.

## 9.4 Pessoa existente

Quando houver possível duplicidade:

- não revelar cadastro sem verificação;
- permitir vincular a solicitação ao cadastro existente por fluxo seguro;
- pedir confirmação de dados;
- não criar cópia;
- registrar nova autorização ou visita;
- manter análise pela portaria.

## 9.5 Mensagens

| Situação | Mensagem |
|---|---|
| CPF inválido | Confira o CPF informado. |
| Data inválida | Informe uma data de nascimento válida. |
| Possível cadastro existente | Encontramos dados semelhantes. Confirme as informações para continuar. |
| Telefone inválido | Informe um telefone válido com DDD. |

---

# 10. Etapa 2 — Endereço

## 10.1 Distinção obrigatória

O endereço desta etapa é o **endereço informado pela pessoa**, quando sua coleta possuir finalidade aprovada.

Ele não é:

- endereço do imóvel de destino;
- vínculo com o imóvel;
- autorização;
- fonte para alterar o cadastro do imóvel;
- endereço residencial herdado por morador ou inquilino.

## 10.2 Campos da referência

- CEP;
- endereço;
- número;
- complemento;
- bairro;
- cidade;
- estado.

## 10.3 Busca por CEP

Quando disponível:

- preencher dados como sugestão;
- permitir correção;
- indicar falha;
- não impedir continuação quando consulta externa estiver indisponível e a entrada manual for permitida;
- não considerar retorno externo como dado confirmado.

## 10.4 Finalidade

A obrigatoriedade e retenção dependem de `PEN-010` do Product Book.

Até sua resolução:

- a etapa permanece na especificação por constar na referência;
- o campo deverá ser identificado como endereço informado;
- sua coleta em produção dependerá de finalidade e base legal aprovadas;
- tipos que não exigirem endereço poderão receber etapa simplificada ou não aplicável;
- nenhum dado será copiado para o imóvel.

## 10.5 Endereço de destino

O destino será obtido do convite ou selecionado em fluxo protegido e exibido separadamente como:

**Destino da visita**

Não deverá ser confundido com o endereço desta etapa.

---

# 11. Etapa 3 — Documento e OCR

## 11.1 Composição

- orientação;
- tipos aceitos;
- upload ou captura;
- frente e verso quando necessário;
- pré-visualização;
- qualidade;
- substituição;
- dados extraídos quando OCR estiver habilitado;
- confirmação humana.

## 11.2 Captura

Orientações:

- documento inteiro visível;
- iluminação suficiente;
- ausência de reflexo;
- texto legível;
- sem dedos sobre dados;
- fundo contrastante;
- formato e tamanho aceitos.

## 11.3 Estados do arquivo

- não enviado;
- enviando;
- enviado;
- verificando;
- qualidade adequada;
- ilegível;
- formato inválido;
- excede limite;
- falha;
- removido.

## 11.4 OCR

```text
Arquivo protegido
      ↓
Validação técnica
      ↓
Extração
      ↓
Campos sugeridos
      ↓
Conferência da pessoa
      ↓
Análise da portaria
```

## 11.5 Conferência

- destacar campos extraídos;
- permitir correção;
- identificar origem automática;
- preservar valor extraído e confirmado para auditoria quando necessário;
- não marcar documento como validado somente pelo OCR.

## 11.6 OCR indisponível

O fluxo deverá continuar com análise manual. A ausência do OCR não impedirá o envio de documento legível.

## 11.7 Documento ilegível

Permitir:

- substituir imediatamente;
- enviar para análise manual quando a regra permitir;
- receber solicitação de correção posterior.

---

# 12. Etapa 4 — Selfie

## 12.1 Composição

- orientação;
- área de câmera ou upload;
- enquadramento;
- captura;
- pré-visualização;
- repetir;
- resultado de qualidade;
- aviso de privacidade específico quando aplicável.

## 12.2 Critérios

- um rosto;
- rosto visível;
- iluminação adequada;
- foco;
- ausência de obstrução relevante;
- proporção aceita;
- imagem recente conforme política.

## 12.3 Permissão da câmera

Estados:

- não solicitada;
- concedida;
- negada;
- câmera indisponível;
- dispositivo incompatível.

Sempre que permitido, oferecer upload como alternativa.

## 12.4 Biometria

Coletar selfie não implica automaticamente:

- criação de credencial biométrica;
- reconhecimento facial;
- sincronização com equipamento;
- consentimento válido para qualquer finalidade.

O uso biométrico dependerá de política, finalidade, base legal e proteção aprovadas.

## 12.5 Qualidade inadequada

- explicar problema;
- permitir repetir;
- não usar linguagem julgadora;
- não prosseguir para sincronização;
- permitir análise humana quando previsto.

---

# 13. Etapa 5 — Veículo opcional

## 13.1 Campos

- placa;
- marca;
- modelo;
- cor;
- tipo, quando definido.

## 13.2 Opcionalidade

A etapa deverá indicar claramente que:

- informar veículo é opcional quando permitido;
- veículo não garante acesso;
- placa será conferida;
- divergência exigirá análise.

## 13.3 Múltiplos veículos

A referência apresenta opção “Adicionar outro veículo”. Sua disponibilidade depende de regra aprovada.

Se habilitada:

- limitar quantidade;
- validar placas individualmente;
- permitir remover antes do envio;
- manter um identificador por veículo;
- não criar cadastros ativos conflitantes.

## 13.4 Placa

- normalização;
- formato compatível com padrões aceitos;
- entrada manual;
- sem depender de máscara rígida;
- alerta de possível duplicidade sem expor terceiro.

---

# 14. Etapa 6 — Confirmação

## 14.1 Revisão

Antes do envio, apresentar resumo:

- tipo;
- destino;
- responsável;
- período;
- dados pessoais;
- endereço informado, quando coletado;
- documento enviado;
- selfie;
- veículo;
- aviso de privacidade;
- declarações obrigatórias.

Dados sensíveis deverão ser mascarados no resumo quando possível.

## 14.2 Edição

Cada grupo deverá permitir voltar à etapa correspondente sem perder dados.

## 14.3 Declaração

A confirmação deverá:

- declarar veracidade conforme texto jurídico aprovado;
- indicar finalidade;
- informar análise pela portaria;
- informar que não há liberação automática;
- registrar versão do aviso aceito;
- não usar checkbox pré-selecionado.

## 14.4 Envio

Ao enviar:

- bloquear repetição;
- mostrar progresso;
- validar convite;
- validar todas as etapas;
- persistir arquivos;
- criar protocolo idempotente;
- registrar versão dos dados;
- colocar na fila correta;
- retornar resultado verificável.

## 14.5 Falha no envio

- preservar dados com segurança;
- informar o que ocorreu;
- permitir nova tentativa;
- consultar se o protocolo já foi criado;
- não criar duplicidade.

---

# 15. Protocolo

## 15.1 Resultado

Após sucesso:

- ícone de confirmação;
- mensagem “Pré-cadastro realizado com sucesso”;
- explicação de que haverá análise;
- protocolo;
- ação de copiar;
- ação de finalizar;
- ação de acompanhar;
- orientações de segurança.

## 15.2 Regras

O protocolo deverá:

- ser único;
- não conter CPF, imóvel ou dado previsível;
- ser legível;
- aceitar cópia;
- possuir proteção contra enumeração;
- não funcionar sozinho para expor dados sensíveis.

## 15.3 Mensagem recomendada

**Pré-cadastro enviado para análise. O protocolo não representa autorização de entrada.**

---

# 16. Rascunho e retomada

## 16.1 Rascunho

Poderá ser:

- local e temporário antes de dados sensíveis;
- persistido no servidor por token seguro;
- associado ao convite;
- expirado conforme política.

## 16.2 Retomada

Exigirá:

- token ou mecanismo seguro;
- convite ainda válido ou regra específica;
- verificação adicional quando necessário;
- revalidação dos dados;
- indicação da etapa pendente.

## 16.3 Expiração

Ao expirar:

- impedir novo envio;
- explicar;
- orientar novo convite;
- aplicar política de descarte;
- preservar auditoria mínima.

---

# 17. Acompanhamento

## 17.1 Acesso

Poderá usar:

- link seguro;
- protocolo com fator adicional;
- convite;
- canal autenticado futuro.

Protocolo isolado não deverá expor dados completos.

## 17.2 Conteúdo

- protocolo;
- tipo;
- destino minimizado;
- data de envio;
- situação;
- última atualização;
- ação necessária;
- mensagem pública;

## 17.3 Situações

- rascunho;
- enviado;
- aguardando análise;
- aprovado;
- rejeitado;
- correção solicitada;
- corrigido;
- expirado;
- cancelado.

## 17.4 Aprovado

Mensagem:

**Pré-cadastro aprovado. A entrada ainda será validada na portaria conforme período e regras vigentes.**

## 17.5 Rejeitado

Exibir:

- mensagem pública aprovada;
- possibilidade de nova solicitação quando permitida;
- canal de contato;
- sem expor observação interna.

## 17.6 Correção solicitada

Exibir:

- itens a corrigir;
- prazo;
- ação de editar;
- versão anterior protegida;
- nova submissão.

---

# 18. Estados e transições

```text
Convite válido
   ↓
Rascunho
   ↓
Enviado
   ↓
Aguardando análise
   ├── Aprovado
   ├── Rejeitado
   ├── Correção solicitada
   │       ↓
   │   Em correção
   │       ↓
   │   Reenviado
   │       └── Aguardando análise
   ├── Cancelado
   └── Expirado
```

Transições deverão registrar ator, instante, origem, motivo e versão dos dados quando aplicável.

---

# 19. Portaria — Lista de pré-cadastros

## 19.1 Estrutura

Conforme a referência:

- app shell administrativo;
- título “Pré-Cadastros”;
- abas ou filtros rápidos por situação;
- contadores;
- busca;
- tipo de acesso;
- período;
- filtros adicionais;
- atualização;
- tabela;
- paginação;
- drawer de detalhes.

## 19.2 Filtros rápidos

- aguardando aprovação;
- aprovados;
- rejeitados;
- todos.

“Correção solicitada”, “reenviados” e “expirados” deverão estar disponíveis em filtros adicionais ou conforme volume.

## 19.3 Busca e filtros

Pesquisar por:

- nome;
- CPF ou documento;
- protocolo;
- placa;
- imóvel;
- responsável;
- período;
- tipo;
- situação.

## 19.4 Colunas

Conforme referência:

- pessoa;
- tipo;
- documento mascarado;
- data do cadastro;
- veículo;
- protocolo;
- status;
- ações.

Colunas adicionais poderão ser configuradas sem comprometer a referência:

- destino;
- responsável;
- prazo;
- alerta.

## 19.5 Ordenação

Por padrão:

- solicitações aguardando análise;
- mais antigas primeiro, quando houver SLA;
- prioridade explícita quando aprovada.

A ordenação deverá ser visível.

## 19.6 Ações por linha

- visualizar;
- aprovar;
- rejeitar.

Solicitar correção deverá estar no drawer ou em ação claramente identificada.

Ícones deverão possuir nome acessível e tooltip.

---

# 20. Portaria — Drawer de detalhes

## 20.1 Cabeçalho

- protocolo;
- status;
- fechar;
- data de envio;
- prazo;
- alertas.

## 20.2 Abas

Conforme a referência:

- Detalhes;
- Histórico.

## 20.3 Detalhes

- selfie;
- dados pessoais;
- tipo;
- telefone;
- documento;
- endereço informado;
- destino;
- responsável;
- período;
- veículo;
- inconsistências;
- origem do convite.

Todos os dados efetivamente preenchidos pelo solicitante deverão estar disponíveis ao operador autorizado no drawer, respeitando mascaramento, finalidade e menor privilégio. O resumo da fila não substitui a ficha completa de conferência.

## 20.3.1 Edição controlada pela portaria

Antes da aprovação, operador com permissão específica poderá corrigir dados textuais da solicitação quando a divergência puder ser comprovada durante o atendimento.

A edição deverá:

- exigir permissão específica, distinta do acesso geral à fila;
- ser permitida somente enquanto o pré-cadastro estiver em estado compatível com análise (aguardando);
- exigir justificativa;
- preservar a versão submetida pelo solicitante;
- registrar campo, valor anterior, valor novo, operador, data e hora;
- revalidar os dados no backend, não confiando apenas na validação do formulário;
- impedir aprovação enquanto houver alteração não salva;
- controlar concorrência por versão, recusando salvar sobre uma versão desatualizada;
- não alterar automaticamente pessoa, imóvel, vínculo, autorização ou credencial;
- encaminhar ao fluxo "Solicitar correção" documentos, selfies ou informações que dependam de novo envio pelo solicitante — nunca substituí-los diretamente.

## 20.3.2 Visualização protegida de foto e documento

O operador autorizado deverá conseguir abrir a selfie, a foto e o documento efetivamente enviados pelo solicitante. Exibir somente o nome do arquivo, um ícone, um estado de upload ou um item marcado no checklist não atende à conferência operacional.

A visualização deverá:

- ocorrer no drawer ou em modal protegido, sem abandonar a análise;
- usar proxy autenticado ou URL temporária de curta duração, sem endereço público permanente;
- permitir ampliação controlada para conferência;
- identificar indisponibilidade, validação pendente, arquivo inválido e quarentena;
- apresentar a versão original submetida e, após correção, permitir consultar as versões preservadas conforme permissão;
- registrar ator, instante, pré-cadastro, tipo de arquivo, contexto e resultado do acesso;
- não oferecer download por padrão.

Visualizar uma selfie para conferência humana não cria credencial biométrica e não autoriza envio a controladora ou fornecedor.

## 20.3.3 Revelação controlada de CPF ou documento

Na fila, no dashboard e no resumo inicial, o CPF ou documento deverá permanecer mascarado. No detalhe da análise, o operador autorizado poderá revelar temporariamente o valor integral para comparar o cadastro com o documento apresentado.

A ação deverá:

- ser explícita e exigir permissão própria;
- informar a finalidade de conferência de identidade;
- voltar ao mascaramento ao fechar o drawer, expirar a janela ou concluir a análise;
- gerar auditoria sem gravar o documento integral no evento;
- respeitar a implantação, o registro em análise e o menor privilégio;
- não permitir exportação ou revelação em massa.

## 20.4 Histórico

Exibir:

- envio;
- análises;
- visualizações relevantes;
- correção solicitada;
- reenvio;
- aprovação;
- rejeição;
- expiração;
- operador;
- data e hora;
- motivo conforme permissão.

## 20.5 Responsividade

Em celular ou tablet estreito, o drawer ocupará tela completa, com foco gerenciado e ações acessíveis.

---

# 21. Análise pela portaria

## 21.1 Checklist

O operador deverá conferir:

- pessoa;
- possível duplicidade;
- documento;
- selfie;
- comparação visual entre a pessoa, a selfie e o documento apresentado, quando aplicável;
- destino;
- responsável;
- período;
- tipo;
- veículo;
- empresa e atividade para prestador;
- alertas;
- divergências.

O operador deverá conseguir abrir os valores preenchidos, não apenas indicadores de completude do checklist.

## 21.2 Resultado da análise

Estados:

- sem análise;
- em análise;
- pendente de correção;
- aprovado;
- rejeitado;
- expirado durante análise;
- conflito de atualização.

## 21.3 Concorrência

Se outro operador estiver analisando:

- indicar bloqueio ou edição concorrente;
- impedir decisão duplicada;
- permitir consulta;
- informar atualização;
- revalidar antes de concluir.

---

# 22. Aprovação

## 22.1 Fluxo

1. operador abre o registro;
2. confere checklist;
3. seleciona aprovar;
4. define ou confirma vigência;
5. confirma destino e responsável;
6. define credenciais ou encaminhamento permitido;
7. registra observação quando necessária;
8. sistema revalida;
9. registra operador, data, dados analisados e resultado;
10. atualiza situação;
11. notifica conforme canal aprovado.

## 22.2 “Aprovar e liberar”

A referência usa o rótulo “Aprovar e liberar”. Para preservar `RN-026`, o rótulo recomendado nesta especificação é:

**Aprovar pré-cadastro**

Se “liberar” for mantido por decisão formal, deverá significar apenas liberar a solicitação para a etapa de Validação de Entrada, nunca abrir acesso físico ou garantir entrada.

## 22.3 Resultado

Mensagem interna:

**Pré-cadastro aprovado. A entrada continuará sujeita à validação na portaria.**

---

# 23. Rejeição

## 23.1 Fluxo

1. selecionar rejeitar;
2. modal apresenta contexto;
3. escolher motivo;
4. informar complemento quando exigido;
5. definir mensagem pública;
6. revalidar;
7. registrar decisão;
8. notificar.

## 23.2 Separação de conteúdo

- motivo estruturado;
- observação interna;
- mensagem pública;
- possibilidade de nova solicitação.

Observação interna não será enviada automaticamente.

## 23.3 Resultado

O registro permanece consultável e auditável. Rejeição não exclui arquivos imediatamente; retenção seguirá política.

---

# 24. Solicitação de correção

## 24.1 Fluxo

1. operador seleciona itens;
2. descreve correção;
3. define prazo;
4. registra;
5. solicitante recebe acesso seguro;
6. edita apenas campos autorizados;
7. reenvia;
8. volta à fila.

## 24.2 Campos

Poderão ser marcados:

- dados pessoais;
- endereço;
- documento;
- selfie;
- veículo;
- destino ou período, quando permitido.

## 24.3 Versionamento

- preservar submissão anterior;
- identificar alterações;
- permitir comparação;
- registrar autor e momento;
- não alterar cadastro principal sem processo autorizado.

---

# 25. Endereço e domínio do imóvel

## 25.1 Regra central

O endereço estrutural pertence ao imóvel.

## 25.2 Endereço informado no pré-cadastro

Se coletado, será entidade ou conjunto de dados separado, com:

- finalidade;
- origem;
- data;
- retenção;
- acesso;
- vínculo apenas à solicitação ou pessoa conforme decisão futura.

## 25.3 Proibição

O sistema não deverá:

- atualizar imóvel pelo pré-cadastro;
- assumir que endereço informado é o destino;
- copiar endereço para todos os moradores;
- transformar visitante em ocupante;
- usar endereço sem finalidade aprovada.

---

# 26. Privacidade e proteção de dados

## 26.1 Transparência

Informar:

- controlador e canal;
- finalidade;
- dados coletados;
- compartilhamentos necessários;
- retenção;
- direitos;
- consequências de não informar;
- uso de biometria, se aplicável;
- versão do aviso.

## 26.2 Minimização

Campos variam por:

- tipo;
- finalidade;
- convite;
- regra da implantação;
- idade;
- categoria documental.

## 26.3 Arquivos

- armazenamento privado compatível com S3;
- URLs temporárias;
- verificação de formato e conteúdo;
- limite;
- metadados mínimos;
- proteção contra execução;
- acesso auditado;
- descarte conforme política.

## 26.4 Exibição

- mascaramento;
- menor privilégio;
- miniatura protegida;
- nenhum dado em URL;
- nenhum dado em logs de frontend;
- nenhum arquivo em cache público.

## 26.5 Consentimento

Quando utilizado, deverá ser:

- específico;
- informado;
- registrável;
- versionado;
- revogável quando aplicável.

Consentimento não será assumido como única base legal sem avaliação.

---

# 27. Segurança do fluxo público

- token aleatório e expirável;
- proteção contra enumeração;
- rate limiting;
- proteção CSRF quando aplicável;
- validação no servidor;
- detecção de automação abusiva conforme risco;
- upload seguro;
- cabeçalhos de segurança;
- sessão pública separada da sessão administrativa;
- nenhum segredo no cliente;
- idempotência no envio;
- mensagens sem confirmação de cadastro de terceiros;
- auditoria de eventos relevantes;
- revogação do convite;
- segregação por implantação.

CAPTCHA ou mecanismo equivalente somente deverá ser introduzido quando o risco justificar e a acessibilidade estiver preservada.

---

# 28. Estados visuais do fluxo público

## 28.1 Carregando

- skeleton ou indicador localizado;
- layout estável;
- texto em processos demorados;
- botão protegido contra repetição.

## 28.2 Erro de campo

- mensagem próxima;
- resumo ao enviar;
- foco no primeiro erro;
- dado preservado.

## 28.3 Erro de serviço externo

CEP, OCR ou câmera indisponível não deverão apagar dados. Oferecer alternativa quando possível.

## 28.4 Sem conexão

- informar;
- preservar rascunho permitido;
- não declarar envio;
- permitir nova tentativa;
- consultar protocolo antes de repetir.

## 28.5 Manutenção

- mensagem institucional;
- impacto;
- canal;
- retorno esperado somente se conhecido;
- não expor detalhe técnico.

---

# 29. Responsividade

## 29.1 Celular

- uma etapa por tela;
- largura total;
- ação principal evidente;
- teclado adequado ao campo;
- captura de documento e selfie integrada;
- resumo em cartões;
- áreas de toque mínimas;
- sem rolagem horizontal.

## 29.2 Tablet

- uma coluna principal;
- largura limitada para leitura;
- pré-visualizações maiores;
- drawer administrativo em tela completa quando necessário.

## 29.3 Desktop público

- painel institucional lateral;
- formulário principal;
- uma etapa por vez;
- largura de leitura controlada.

A prancha de seis telas lado a lado representa a sequência, não o layout real simultâneo.

## 29.4 Portaria

- sidebar persistente em desktop;
- tabela e drawer lado a lado;
- filtros reorganizados em tablet;
- tabela convertida ou rolada de modo acessível em celular.

---

# 30. Acessibilidade

O fluxo deverá:

- possuir título por etapa;
- anunciar progresso;
- permitir teclado;
- manter foco visível;
- associar rótulos e erros;
- não depender de placeholder;
- oferecer alternativa à câmera;
- permitir upload;
- orientar sem linguagem visual exclusiva;
- possuir contraste;
- respeitar zoom e texto ampliado;
- fornecer instruções antes de upload;
- anunciar sucesso e falha;
- permitir tempo suficiente;
- evitar expiração silenciosa;
- não usar CAPTCHA inacessível;
- manter ordem lógica no drawer;
- devolver foco ao fechar sobreposição.

---

# 31. Conteúdo e microcopy

## 31.1 Títulos

- Faça seu pré-cadastro;
- Dados pessoais;
- Endereço informado;
- Documento;
- Selfie;
- Veículo opcional;
- Confirmação;
- Pré-cadastro enviado;
- Acompanhar situação;
- Pré-Cadastros;
- Detalhes;
- Histórico.

## 31.2 Mensagens

| Situação | Mensagem |
|---|---|
| Envio concluído | Pré-cadastro enviado para análise. |
| Sem autorização | O envio não garante a entrada. A portaria validará as condições aplicáveis. |
| OCR falhou | Não foi possível extrair os dados. Confira o documento e continue manualmente. |
| Foto inadequada | Não foi possível validar a qualidade da imagem. Tire outra foto ou envie um arquivo. |
| Correção | Há informações que precisam ser corrigidas antes de uma nova análise. |
| Aprovado | Pré-cadastro aprovado. A entrada ainda será validada na portaria. |
| Rejeitado | A solicitação não foi aprovada. Consulte a orientação apresentada. |

## 31.3 Linguagem

- frases curtas;
- voz ativa;
- sem jargão técnico;
- sem promessas de liberação;
- sem culpabilizar;
- diferença clara entre envio, aprovação e entrada.

---

# 32. Notificações

## 32.1 Eventos

- envio;
- correção solicitada;
- reenvio;
- aprovação;
- rejeição;
- expiração;
- cancelamento.

## 32.2 Conteúdo

- marca;
- implantação;
- protocolo;
- situação;
- ação segura;
- validade;
- canal oficial;
- sem documento completo.

## 32.3 Canais

Poderão incluir e-mail ou mensageria após decisão formal. O canal não altera a fonte oficial da situação no sistema.

---

# 33. Auditoria

Registrar:

- convite emitido, usado, cancelado ou expirado;
- início e salvamento;
- aviso de privacidade apresentado;
- versão aceita;
- arquivos enviados e substituídos;
- resultado técnico;
- extração e correção;
- envio;
- protocolo;
- acesso de análise;
- decisão;
- motivo;
- correção;
- notificação;
- expiração;
- usuário, origem e instante.

Logs não deverão armazenar o conteúdo integral de documentos ou selfies.

---

# 34. Desempenho e observabilidade

## 34.1 Experiência

- primeira tela leve;
- imagens otimizadas sem comprometer análise;
- upload com progresso;
- processamento assíncrono quando necessário;
- nenhum bloqueio indefinido;
- retomada após falha.

## 34.2 Telemetria

Medir:

- convite inválido;
- abandono por etapa;
- tempo por etapa;
- falha de upload;
- qualidade inadequada;
- OCR;
- envio duplicado evitado;
- tempo até análise;
- correções;
- resultado;
- erro por dispositivo.

## 34.3 Privacidade

Telemetria não deverá conter documento, imagem, protocolo completo ou dado pessoal desnecessário.

---

# 35. Diretrizes para Blade e Livewire

## 35.1 Componentização pública

```text
PublicPreRegistration
├── InvitationValidation
├── Welcome
├── PersonalDataStep
├── AddressStep
├── DocumentStep
├── SelfieStep
├── VehicleStep
├── ConfirmationStep
└── ProtocolResult
```

## 35.2 Componentização da portaria

```text
PreRegistrationQueue
├── StatusTabs
├── SearchAndFilters
├── ResultsTable
├── DetailDrawer
├── ReviewChecklist
├── ApprovalDialog
├── RejectionDialog
└── CorrectionDialog
```

## 35.3 Estado

- servidor como fonte de verdade;
- etapa válida registrada;
- token protegido;
- arquivos fora do estado serializado quando necessário;
- validação por etapa e no envio;
- idempotência;
- concorrência na análise;
- permissões no servidor.

## 35.4 JavaScript

Restrito a:

- câmera;
- pré-visualização;
- foco;
- comportamento responsivo;
- melhoria progressiva.

OCR, autorização, vínculo e decisão não residirão no navegador.

---

# 36. Contrato funcional de dados

## 36.1 Convite

- identificador;
- implantação;
- emissor;
- destino;
- responsável;
- tipo;
- finalidade;
- período;
- limite;
- expiração;
- situação.

## 36.2 Solicitação

- identificador interno;
- protocolo;
- tipo;
- pessoa;
- endereço informado;
- documento;
- selfie;
- veículos;
- destino;
- responsável;
- período;
- situação;
- versão;
- aviso de privacidade;
- origem.

## 36.3 Análise

- operador;
- início;
- checklist;
- alertas;
- decisão;
- motivo;
- observação interna;
- mensagem pública;
- vigência;
- credenciais;
- instante.

## 36.4 Histórico

- transição;
- ator;
- origem;
- instante;
- versão;
- campos alterados;
- notificações.

---

# 37. Cenários de teste

## 37.1 Convite

- válido;
- expirado;
- cancelado;
- limite atingido;
- alterado;
- de outra implantação;
- tentativa de enumeração.

## 37.2 Dados pessoais

- visitante;
- turista;
- prestador;
- pessoa existente;
- CPF inválido;
- documento estrangeiro;
- telefone e e-mail inválidos.

## 37.3 Endereço

- CEP localizado;
- CEP não localizado;
- serviço indisponível;
- entrada manual;
- etapa não aplicável;
- separação do destino.

## 37.4 Documento e selfie

- upload válido;
- ilegível;
- formato inválido;
- grande;
- malware;
- OCR correto;
- OCR divergente;
- OCR indisponível;
- câmera negada;
- upload alternativo;
- selfie inadequada.

## 37.5 Veículo

- sem veículo;
- um veículo;
- múltiplos;
- placa inválida;
- possível duplicidade.

## 37.6 Envio

- sucesso;
- clique repetido;
- timeout;
- protocolo criado com resposta perdida;
- convite expirado no final;
- sessão pública expirada;
- retomada.

## 37.7 Portaria

- fila vazia;
- filtros;
- múltiplos operadores;
- aprovar;
- rejeitar;
- corrigir;
- expirar;
- decisão duplicada;
- sem permissão.

## 37.8 Responsividade e acessibilidade

- celulares homologados;
- tablet;
- desktop;
- teclado;
- leitor de tela;
- zoom;
- câmera;
- tempo de sessão;
- mensagens.

## 37.9 Segurança e privacidade

- arquivo direto;
- protocolo enumerado;
- convite adulterado;
- CSRF;
- rate limit;
- upload executável;
- dados de outra implantação;
- mensagem revelando pessoa existente;
- log com dado sensível.

---

# 38. Critérios de aceite

## 38.1 Fluxo público

**CA-UXP-001:** convite inválido ou expirado não inicia solicitação.  
**CA-UXP-002:** o fluxo apresenta seis etapas na ordem aprovada.  
**CA-UXP-003:** dados permanecem ao voltar e após erro.  
**CA-UXP-004:** campos variam conforme tipo de acesso.  
**CA-UXP-005:** pessoa existente não é duplicada indevidamente.  
**CA-UXP-006:** endereço informado permanece separado do imóvel de destino.  
**CA-UXP-007:** documento e selfie possuem captura, substituição e estados de qualidade.  
**CA-UXP-008:** OCR exige conferência e possui alternativa manual.  
**CA-UXP-009:** veículo é opcional quando a regra permitir.  
**CA-UXP-010:** confirmação informa que envio não garante acesso.  
**CA-UXP-011:** envio idempotente gera um único protocolo.  
**CA-UXP-012:** protocolo não expõe dados previsíveis.  

## 38.2 Acompanhamento

**CA-UXP-013:** situação pode ser consultada por mecanismo seguro.  
**CA-UXP-014:** aprovado continua sujeito à Validação de Entrada.  
**CA-UXP-015:** rejeição separa mensagem pública e observação interna.  
**CA-UXP-016:** correção preserva versões.  
**CA-UXP-017:** expiração apresenta orientação adequada.  

## 38.3 Portaria

**CA-UXP-018:** lista permite busca e filtros previstos.  
**CA-UXP-019:** drawer apresenta detalhes e histórico.  
**CA-UXP-020:** operador confere checklist antes da decisão.  
**CA-UXP-021:** aprovação registra operador, dados, vigência e resultado.  
**CA-UXP-022:** rejeição exige motivo.  
**CA-UXP-023:** análise concorrente não gera decisão duplicada.  
**CA-UXP-024:** permissões são aplicadas no servidor.  

## 38.4 Visuais, responsivos e acessíveis

**CA-UXP-025:** composição preserva a referência oficial.  
**CA-UXP-026:** fluxo público é mobile-first.  
**CA-UXP-027:** prancha de etapas não é implementada como seis telas simultâneas.  
**CA-UXP-028:** toda jornada funciona por teclado.  
**CA-UXP-029:** câmera possui alternativa quando aplicável.  
**CA-UXP-030:** foco, contraste, erros e progresso atendem ao Design System.  

## 38.5 Segurança e privacidade

**CA-UXP-031:** arquivos são privados e acessados por autorização.  
**CA-UXP-032:** dados são segregados por implantação.  
**CA-UXP-033:** coleta apresenta finalidade e aviso versionado.  
**CA-UXP-034:** mensagens não revelam cadastro de terceiros.  
**CA-UXP-035:** envio, análise e alterações são auditáveis.  
**CA-UXP-036:** dados ilustrativos da referência não são fixados no produto.  

---

# 39. Pendências abertas

| PEN-UXP | Pendência | Impacto | Encaminhamento |
|---|---|---|---|
| PEN-UXP-001 | Definir finalidade, obrigatoriedade e retenção do endereço informado | Etapa 2 e LGPD | `PEN-010` do Product Book |
| PEN-UXP-002 | Definir base legal e política para selfie e biometria | Etapa 4 e reconhecimento facial | `PEN-005` |
| PEN-UXP-003 | Definir política de retenção de documentos e imagens | Arquivos e descarte | `PEN-006` |
| PEN-UXP-004 | Definir campos obrigatórios por tipo | Formulários e validação | Regras de negócio |
| PEN-UXP-005 | Definir regras específicas de turista | Período, destino e documentação | `PEN-007` |
| PEN-UXP-006 | Definir documentos exigidos para prestador | Etapa 3 e análise | Regras de negócio |
| PEN-UXP-007 | Definir se OCR entra no MVP | Processamento e prazo | `PEN-019` |
| PEN-UXP-008 | Definir tipos, formatos e limites de arquivo | Upload e segurança | Arquitetura |
| PEN-UXP-009 | Definir quantidade de veículos | Etapa 5 | Produto |
| PEN-UXP-010 | Definir prazo de rascunho e retomada | Retenção e experiência | Produto e LGPD |
| PEN-UXP-011 | Definir canais de notificação | Acompanhamento | `PEN-011` |
| PEN-UXP-012 | Definir mecanismo seguro de acompanhamento | Protocolo e privacidade | Segurança e UX |
| PEN-UXP-013 | Definir motivos de rejeição | Análise | Regras de negócio |
| PEN-UXP-014 | Definir campos e prazo de correção | Reenvio | Produto |
| PEN-UXP-015 | Definir SLA e prioridade da fila | Ordenação e alertas | Operação |
| PEN-UXP-016 | Validar o rótulo “Aprovar e liberar” | Pode contradizer `RN-026` | Product Owner |
| PEN-UXP-017 | Definir política para menores de idade | Dados e responsável | Jurídico e produto |
| PEN-UXP-018 | Aprovar imagem pública da implantação | Boas-vindas | `PEN-BR-012` |
| PEN-UXP-019 | Definir ferramenta ou serviço de CEP | Etapa 2 | Decisão técnica |
| PEN-UXP-020 | Definir controles antiabuso | Segurança pública | Avaliação de risco |
| PEN-UXP-021 | Definir navegadores e dispositivos suportados | Captura e responsividade | Matriz de compatibilidade |
| PEN-UXP-022 | Aprovar protótipos por viewport | Aceite visual final | Prototipação |

---

# 40. Decisões consolidadas

Ficam consolidados:

- fluxo público em seis etapas;
- experiência mobile-first;
- convite seguro e temporário;
- protocolo único e não previsível;
- pré-cadastro não concede acesso;
- acompanhamento protegido;
- pessoa existente reutilizada sem duplicação;
- OCR apenas como assistência;
- selfie separada de credencial biométrica;
- veículo opcional não garante liberação;
- endereço informado separado do imóvel de destino;
- lista da portaria com filtros, tabela e drawer;
- aprovação, rejeição e correção auditáveis;
- correção preserva versões;
- mensagem pública separada de observação interna;
- arquivos privados;
- permissões administrativas no servidor;
- Blade e Livewire como base da implementação futura.

## 40.1 Registro de aprovação

| Papel | Nome | Decisão | Data | Observações |
|---|---|---|---|---|
| Product Owner | Vinicius Velasco de Azevedo | Aprovado | 28/07/2026 | UX/UI do Pré-Cadastro aprovada como referência para prototipação, testes e implementação futura |
| Soluções do Vale | Representante designado | Ciente | 28/07/2026 | Aprovação registrada no repositório oficial |

---

# 41. Próximo documento

Após a aprovação desta especificação, deverá ser produzido:

**`docs/007_UX_UI_CADASTRO_IMOVEL.md`**

O próximo documento deverá detalhar:

- imóvel como entidade central;
- cadastro de pessoa;
- dados pessoais;
- documentos e fotos;
- endereço do imóvel;
- informações de acesso;
- observações;
- moradores do mesmo imóvel;
- proprietário, titular e responsável;
- inquilinos e vigência;
- veículos;
- histórico e sincronização.

---

## Situação do documento

Esta especificação consolida o fluxo público, o acompanhamento e a análise do Pré-Cadastro e encontra-se **aprovada**. As pendências de privacidade, endereço, biometria, documentos, notificações e prototipação permanecem rastreadas e deverão ser resolvidas antes da implementação definitiva dos elementos afetados, sem invalidar esta aprovação documental.
