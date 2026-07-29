# SDV ACCESS — UX/UI DE IMÓVEIS, PESSOAS E VÍNCULOS
## Cadastro central, ocupação, vigência, veículos e histórico

**Documento:** SDV-UXI-007  
**Versão:** 1.0.1  
**Status:** Aprovado  
**Produto:** SDV Access — Implantação Santa Rita  
**Marca proprietária:** Soluções do Vale Tecnologia  
**Responsável pelo produto:** Vinicius Velasco de Azevedo  
**Data:** Julho de 2026

---

## Controle de versões

| Versão | Data | Responsável | Alteração |
|---|---|---|---|
| 1.0.0 | Julho/2026 | Soluções do Vale | Especificação inicial de imóveis, pessoas, vínculos, vigências e veículos |
| 1.0.1 | 28/07/2026 | Product Owner | Aprovação formal da especificação UX/UI de Imóveis, Pessoas e Vínculos |

---

# 1. Objetivo

Este documento especifica a experiência de:

- cadastro e consulta de imóveis;
- cadastro único de pessoas;
- vínculo entre pessoa e imóvel;
- moradores, proprietários, inquilinos e outros ocupantes;
- responsável principal;
- vigências;
- veículos;
- credenciais e sincronizações;
- histórico de ocupação e acessos;
- encerramento e inativação rastreáveis.

O imóvel é a entidade central do domínio. A interface deverá tornar essa centralidade compreensível sem transformar pessoa, vínculo, autorização ou credencial em cópias do imóvel.

---

# 2. Fontes e rastreabilidade

## 2.1 Referências visuais

| Referência | Conteúdo |
|---|---|
| `REF-UXI-001` — `docs/references/ChatGPT Image 27 de jul. de 2026, 14_01_27.png` | Cadastro de pessoa, tipo de acesso, dados pessoais, vínculo, veículos, acessos e sincronização |
| `REF-UXI-002` — `docs/references/ChatGPT Image 27 de jul. de 2026, 14_05_13.png` | Endereço central do imóvel, moradores vinculados, locação e resumo |
| `REF-UXI-003` — `docs/references/ChatGPT Image 27 de jul. de 2026, 14_07_54.png` | Variação refinada de endereço, vínculos, locação e ações |
| `REF-UXI-004` — `docs/references/01-cadastro-pessoa-dados.png` | Prancha institucional com visão de cadastro, documento, OCR e selfie |

As referências detalham o cadastro de pessoa dentro do contexto de imóvel. Não existe referência completa de uma tela isolada de criação do imóvel. Por isso:

- os comportamentos de domínio do imóvel são especificados neste documento;
- a composição visual da tela estrutural do imóvel deverá ser prototipada;
- a tela de pessoa e vínculo deverá preservar a composição já aprovada;
- nenhuma tela genérica de mercado deverá ser adotada como substituta.

## 2.2 Regras de negócio

| Identificador | Relação |
|---|---|
| `RN-001` a `RN-006` | Imóvel, endereço, unicidade, ocupação, histórico e situação |
| `RN-007` a `RN-014` | Pessoa única, vínculos, vigência e duplicidade |
| `RN-015` a `RN-018` | Moradores, responsável e veículos |
| `RN-019` a `RN-022` | Inquilinos, término, renovação e conflito |
| `RN-034` a `RN-038` | Veículos e placas |
| `RN-045` | Credenciais com estado e vigência próprios |
| `RN-046` a `RN-049` | Auditoria |

## 2.3 Requisitos

| Identificador | Relação |
|---|---|
| `RF-004` | Pesquisar por pessoa, documento, imóvel e placa |
| `RF-005` | Cadastrar e manter imóvel |
| `RF-006` | Cadastrar e vincular morador |
| `RF-007` | Cadastrar inquilino com vigência |
| `RF-011` | Cadastrar e vincular veículo |
| `RF-017` e `RF-018` | Usuários e perfis autorizados |
| `RF-021` | Adaptar campos por tipo de acesso |
| `RF-022` | Manter vínculo com histórico |
| `RF-023` | Compartilhar endereço do imóvel |
| `RF-024` | Gerenciar moradores do mesmo imóvel |
| `RF-025` | Salvar rascunho |
| `RF-026` a `RF-028` | Documento, OCR e selfie |
| `RF-031` | Consultar histórico de acessos |
| `RF-032` | Sincronizar cadastro facial |
| `RF-037` | Exibir situação de integração |
| `RF-040` | Permissões por ação |

## 2.4 Casos de uso e exceções

- `UC-001 — Cadastrar imóvel`;
- `UC-002 — Cadastrar morador`;
- `UC-003 — Cadastrar inquilino`;
- `UC-011 — Encerrar vínculo temporário`;
- `EX-001 — Pessoa já cadastrada`;
- `EX-002 — CPF inválido`;
- `EX-004 — Foto facial inadequada`;
- `EX-005 — Vínculo expirado`.

## 2.5 Componentes do Design System

- `DS-CMP-001 — Sidebar`;
- `DS-CMP-002 — Operational Header`;
- `DS-CMP-003` a `DS-CMP-010 — Ações e formulários`;
- `DS-CMP-011 — Badge de status`;
- `DS-CMP-012 — Alerta`;
- `DS-CMP-015 — Skeleton e progresso`;
- `DS-CMP-016 — Card`;
- `DS-CMP-017 — Tabela`;
- `DS-CMP-018 — Lista de atividade`;
- `DS-CMP-020 — Breadcrumb`;
- `DS-CMP-021 — Tabs`;
- `DS-CMP-022 — Stepper`;
- `DS-CMP-024 — Modal`;
- `DS-CMP-025 — Drawer`;
- `DS-CMP-027 — Seletor de tipo de acesso`;
- `DS-CMP-028 — Resumo de pessoa`;
- `DS-CMP-029 — Painel de vínculo`;
- `DS-CMP-030 — Cartão de veículo`;
- `DS-CMP-032 — Estado de sincronização`.

---

# 3. Modelo mental

```text
Condomínio ou organização
└── Bloco opcional
    └── Imóvel
        ├── Endereço
        ├── Vínculos
        │   ├── Proprietário
        │   ├── Morador
        │   ├── Inquilino
        │   └── Outro ocupante
        ├── Responsabilidades
        ├── Veículos
        ├── Autorizações relacionadas
        └── Histórico

Pessoa
├── Dados próprios
├── Documentos e fotos
├── Vínculos com imóveis
├── Credenciais
├── Veículos relacionados
└── Histórico de acessos
```

O imóvel e a pessoa são cadastros distintos ligados por vínculos versionados.

---

# 4. Conceitos que a interface deverá separar

| Conceito | Significado |
|---|---|
| Imóvel | unidade central com identificação e endereço |
| Pessoa | identidade única |
| Vínculo | relação da pessoa com o imóvel |
| Natureza | proprietário, morador, inquilino ou outro |
| Papel familiar | titular, cônjuge, filho, dependente ou outro |
| Responsabilidade | capacidade de responder por operações do imóvel |
| Autorização | permissão de acesso |
| Credencial | face, placa, tag, QR Code ou código |
| Veículo | bem identificado e relacionado |
| Evento | tentativa, entrada, saída ou decisão |

Um rótulo visual não poderá fundir esses conceitos.

---

# 5. Usuários e permissões

## 5.1 Administrador

Poderá:

- cadastrar e alterar estrutura;
- criar e manter imóveis;
- localizar pessoas;
- criar vínculos;
- definir responsável;
- encerrar, suspender e renovar;
- administrar veículos;
- consultar sincronizações e histórico.

## 5.2 Operador autorizado

Poderá consultar e executar ações limitadas, sem alterar estrutura ou permissões críticas.

## 5.3 Gestor ou síndico

Poderá consultar imóveis, ocupação e histórico, com alterações limitadas conforme perfil.

## 5.4 Auditor

Consulta sem edição.

## 5.5 Morador em portal futuro

Poderá gerir apenas dados próprios e vínculos autorizados, em escopo ainda fora do MVP.

## 5.6 Permissões granulares mínimas

| Permissão conceitual | Finalidade |
|---|---|
| `property.view` | consultar imóvel |
| `property.create` | cadastrar imóvel |
| `property.update` | alterar imóvel |
| `property.status` | alterar situação |
| `person.view` | consultar pessoa |
| `person.create` | cadastrar pessoa |
| `person.update` | alterar dados |
| `person.view_sensitive` | visualizar dados protegidos |
| `link.create` | criar vínculo |
| `link.update` | alterar vínculo |
| `link.suspend` | suspender |
| `link.end` | encerrar |
| `link.renew` | renovar |
| `property.responsible.manage` | gerir responsável |
| `vehicle.manage` | gerir veículo |
| `credential.manage` | gerir credencial |
| `access.history.view` | consultar acessos |

---

# 6. Arquitetura da experiência

```text
Imóveis
├── Lista e pesquisa
├── Cadastro estrutural
├── Detalhe do imóvel
│   ├── Resumo
│   ├── Pessoas e vínculos
│   ├── Veículos
│   ├── Autorizações
│   └── Histórico
└── Ações

Pessoas
├── Lista e pesquisa
├── Cadastro em cinco etapas
├── Vínculos e acessos
├── Veículos
├── Sincronização
└── Histórico
```

---

# 7. Lista de imóveis

## 7.1 Conteúdo

Deverá permitir pesquisa e filtros por:

- condomínio;
- bloco;
- unidade;
- código;
- endereço;
- situação;
- responsável;
- pessoa vinculada;
- placa;
- ocupação.

## 7.2 Colunas

- identificação do imóvel;
- bloco;
- endereço resumido;
- situação;
- responsável principal;
- ocupantes ativos;
- veículos ativos;
- atualização;
- ações.

## 7.3 Ações

- visualizar;
- editar;
- gerir vínculos;
- gerir veículos;
- alterar situação;
- consultar histórico.

## 7.4 Estados

- carregando;
- lista;
- vazio;
- sem resultado;
- erro;
- sem permissão;
- dados desatualizados.

---

# 8. Cadastro estrutural do imóvel

## 8.1 Princípio

O imóvel deve existir antes dos vínculos.

## 8.2 Campos-base

- condomínio ou organização;
- bloco, quando aplicável;
- unidade;
- número ou código;
- complemento estrutural;
- CEP;
- logradouro;
- número;
- complemento;
- bairro;
- cidade;
- estado;
- situação;
- observações estruturais controladas.

## 8.3 Identificação única

Durante a digitação ou envio:

- normalizar componentes;
- verificar conflito;
- apresentar imóvel semelhante;
- impedir duplicidade ativa;
- permitir tratamento autorizado de legado.

## 8.4 Bloco opcional

Se a implantação não usar blocos:

- campo não será exigido;
- identificação única será recalculada;
- interface não exibirá lacuna.

## 8.5 Endereço

- pertence ao imóvel;
- é compartilhado por vínculos residenciais;
- alterações afetam a apresentação aos vinculados;
- mudança estrutural gera auditoria;
- histórico de endereço deverá ser preservado conforme regra futura.

## 8.6 Situação

Estados iniciais:

- em implantação;
- ativo;
- inativo;
- bloqueado.

Situação do imóvel não será confundida com ocupação ou autorização individual.

## 8.7 Ações

- cancelar;
- salvar rascunho, se aplicável;
- salvar;
- salvar e abrir vínculos;
- alterar situação.

## 8.8 Limite visual

Como a tela isolada de imóvel não está representada integralmente nas imagens, sua grade, distribuição e responsividade deverão ser aprovadas em protótipo específico antes da implementação.

---

# 9. Detalhe do imóvel

## 9.1 Cabeçalho

- identificação;
- endereço;
- situação;
- condomínio;
- bloco;
- ações autorizadas;
- data de atualização.

## 9.2 Resumo

- responsável principal;
- quantidade de vínculos ativos;
- moradores;
- inquilinos;
- veículos;
- alertas;
- vigências próximas do término.

## 9.3 Áreas

- Pessoas e vínculos;
- Veículos;
- Autorizações relacionadas;
- Histórico;
- Dados estruturais.

## 9.4 Alertas

- sem responsável quando obrigatório;
- conflito de vigência;
- vínculo prestes a expirar;
- placa conflitante;
- imóvel bloqueado;
- sincronização pendente;
- dado incompleto.

---

# 10. Cadastro de pessoa — estrutura

## 10.1 Referência

A tela aprovada possui:

- breadcrumb;
- título “Cadastro de Pessoa”;
- tipo de acesso em cartões;
- cinco etapas;
- painel lateral “Vínculos e Acessos”;
- veículos;
- histórico;
- sincronização;
- ações inferiores.

## 10.2 Etapas

1. Dados pessoais;
2. Documentos e fotos;
3. Endereço e contato;
4. Informações de acesso;
5. Observações.

## 10.3 Ações globais

- voltar;
- ações;
- cancelar;
- salvar rascunho;
- salvar e ativar cadastro.

## 10.4 Painel contextual

Deverá exibir, conforme etapa e viewport:

- imóvel;
- pessoas vinculadas;
- resumo;
- veículos;
- histórico;
- sincronização.

O painel informa contexto, mas não duplica edição.

---

# 11. Seletor de tipo de acesso

## 11.1 Opções da referência

- morador;
- inquilino;
- prestador;
- visitante;
- turista.

## 11.2 Distinção

O seletor é uma entrada para regras e formulário. Ele não substitui a natureza do vínculo.

Exemplo:

- “Morador” pode possuir natureza de proprietário ou ocupante;
- “Inquilino” exige vínculo temporal;
- “Prestador” pode exigir empresa;
- “Visitante” exige responsável e destino;
- “Turista” exige período.

## 11.3 Mudança

Ao mudar:

- recalcular campos;
- manter dados comuns;
- avisar sobre dados exclusivos;
- revalidar vínculo;
- não alterar automaticamente registros já ativos sem fluxo específico.

---

# 12. Etapa 1 — Dados pessoais

## 12.1 Campos

- foto;
- nome completo;
- nome social;
- CPF;
- RG ou documento;
- órgão emissor;
- data de nascimento;
- estado civil;
- nacionalidade;
- filiação;
- profissão;
- empresa;
- tipo sanguíneo, quando aprovado;
- e-mail;
- telefone principal;
- telefone secundário.

## 12.2 Obrigatoriedade

Varia por:

- tipo;
- natureza;
- finalidade;
- implantação;
- documento;
- base legal.

## 12.3 Duplicidade

Ao informar documento:

- normalizar;
- pesquisar cadastro existente;
- não revelar dados sem permissão;
- apresentar correspondência ao usuário autorizado;
- permitir selecionar pessoa;
- impedir cópia indevida.

## 12.4 Foto

Ação “Remover” deverá significar:

- substituir foto atual;
- retirar foto ativa quando permitido;
- preservar histórico ou evidência conforme política.

Não autoriza apagar arquivo auditável sem regra.

## 12.5 Informações adicionais

Campos sensíveis, como tipo sanguíneo, somente serão exibidos e coletados com finalidade e permissão aprovadas.

---

# 13. Etapa 2 — Documentos e fotos

## 13.1 Composição

- tipo documental;
- frente e verso;
- upload ou captura;
- pré-visualização;
- foto facial;
- origem;
- OCR opcional;
- conferência;
- situação.

## 13.2 OCR

- resultado sugerido;
- campos comparáveis;
- correção humana;
- origem preservada;
- falha não bloqueia análise manual;
- confirmação não equivale a documento validado.

## 13.3 Estados

- não enviado;
- enviado;
- em análise;
- validado;
- rejeitado;
- correção necessária;
- expirado;
- substituído.

## 13.4 Proteção

- arquivo privado;
- URL temporária;
- visualização por permissão;
- acesso auditável;
- nenhum download implícito.

---

# 14. Etapa 3 — Endereço e contato

## 14.1 Endereço do imóvel

Para vínculo residencial:

- o endereço é carregado do imóvel;
- a interface identifica “Endereço principal do imóvel”;
- mostra que é compartilhado;
- não cria cópia na pessoa;
- alteração estrutural exige permissão de imóvel;
- mudança afeta a exibição de todos os vínculos.

## 14.2 Referência visual

A imagem mostra campos editáveis dentro do cadastro de pessoa. Para respeitar o domínio:

- usuários com `property.update` poderão editar em contexto claramente identificado;
- usuários sem permissão verão somente leitura;
- o salvamento deverá atingir o imóvel, não a pessoa;
- a interface pedirá confirmação de impacto;
- a auditoria registrará a alteração do imóvel.

## 14.3 Contato

Telefone e e-mail pertencem à pessoa e permanecem editáveis conforme permissão.

## 14.4 Endereço particular

Somente será coletado:

- com finalidade;
- em seção separada;
- sem alterar o imóvel;
- conforme requisito futuro.

---

# 15. Moradores do mesmo imóvel

## 15.1 Tabela

Conforme referência:

- nome;
- papel ou parentesco;
- natureza;
- CPF mascarado;
- status;
- ações.

## 15.2 Ação “Adicionar morador ao imóvel”

Fluxo:

1. pesquisar pessoa;
2. selecionar existente ou iniciar cadastro;
3. definir natureza;
4. definir papel;
5. definir responsabilidade;
6. definir vigência;
7. definir autorização;
8. revisar;
9. ativar.

## 15.3 Ação de lixeira

Deverá ser substituída ou esclarecida como:

- encerrar vínculo;
- suspender vínculo;
- desvincular com histórico.

O rótulo e a confirmação deverão descrever consequência.

## 15.4 Múltiplos moradores

Cada pessoa mantém:

- cadastro;
- foto;
- documento;
- vínculo;
- autorização;
- credenciais;
- veículos;
- histórico.

---

# 16. Etapa 4 — Informações de acesso

## 16.1 Campos

- implantação;
- imóvel;
- responsável;
- natureza do vínculo;
- papel;
- data de início;
- data de término;
- prazo indeterminado quando permitido;
- áreas;
- horários;
- dias;
- situação;
- credenciais;
- sincronização.

## 16.2 Independência

Ativar pessoa não ativa automaticamente:

- vínculo;
- autorização;
- credencial;
- sincronização;
- veículo.

## 16.3 Vigência

- data inicial;
- data final obrigatória para temporários;
- fuso;
- estado agendado;
- expiração automática;
- alerta de proximidade.

## 16.4 Áreas e horários

Devem usar seleções claras e herança rastreável. Permissões derivadas precisam indicar sua origem.

---

# 17. Etapa 5 — Observações

## 17.1 Finalidade

Registrar informação operacional controlada.

## 17.2 Regras

- limite definido;
- autoria;
- data;
- histórico;
- sem HTML;
- sem dados sensíveis sem finalidade;
- não substituir campos estruturados;
- visibilidade por perfil.

---

# 18. Painel “Vínculos e Acessos”

## 18.1 Conteúdo

- tipo selecionado;
- imóvel;
- responsável;
- início;
- término;
- prazo indeterminado;
- status da pessoa;
- status do vínculo;
- status da autorização.

## 18.2 Consistência

O painel deverá atualizar conforme as etapas, mas distinguir dados salvos de alterações ainda não persistidas.

## 18.3 Alertas

- imóvel não selecionado;
- responsável ausente;
- conflito;
- término inválido;
- pessoa inativa;
- vínculo pendente.

---

# 19. Proprietário, morador, titular e responsável

## 19.1 Natureza

- proprietário;
- morador;
- inquilino;
- outro ocupante.

## 19.2 Papel

- titular;
- cônjuge;
- filho;
- dependente;
- outro.

## 19.3 Responsabilidade

- responsável principal;
- responsável adicional;
- sem responsabilidade administrativa.

## 19.4 Regra visual

Cada conceito terá campo e rótulo próprio. A expressão da referência “Proprietário ou morador” não poderá ser armazenada como uma única classificação ambígua.

## 19.5 Responsável principal

- indicar claramente;
- garantir regra de unicidade ou coexistência aprovada;
- pedir confirmação ao substituir;
- preservar histórico;
- não revogar acesso dos demais automaticamente.

---

# 20. Inquilino e locação

## 20.1 Campos

- imóvel;
- data de início;
- data de término;
- contrato ou referência;
- responsável pelo imóvel;
- situação;
- observação.

## 20.2 Período

- obrigatório;
- término posterior ao início;
- fuso definido;
- alerta de expiração;
- revogação automática das permissões derivadas.

## 20.3 Conflito

Sobreposição deverá:

- ser detectada;
- identificar vínculos afetados;
- explicar regra;
- bloquear ou exigir decisão autorizada;
- registrar justificativa.

## 20.4 Renovação

Renovar deverá:

- preservar período anterior;
- criar nova versão;
- registrar responsável;
- recalcular permissões;
- sincronizar credenciais;
- informar falhas.

## 20.5 Término

Após término:

- vínculo expirado;
- autorizações derivadas inválidas;
- pessoa pode permanecer ativa;
- histórico preservado;
- credenciais independentes avaliadas.

---

# 21. Turista e ocupação temporária

Deverá possuir:

- imóvel;
- responsável;
- período;
- situação;
- credenciais temporárias;
- autorização;
- regras específicas.

A composição reutiliza vínculo temporal, sem tratar turista como morador permanente.

---

# 22. Prestador

Deverá separar:

- pessoa;
- empresa;
- atividade;
- destino;
- período;
- documentação;
- autorização;
- credenciais.

Empresa inativa impede nova autorização, mas não apaga histórico.

---

# 23. Veículos cadastrados

## 23.1 Cartão

Conforme referência:

- placa;
- marca;
- modelo;
- cor;
- ano;
- proprietário;
- situação;
- editar;
- encerrar ou desvincular.

## 23.2 Vínculo

O veículo poderá relacionar-se a:

- pessoa;
- imóvel;
- ambos;
- empresa;
- autorização temporária.

A interface deverá mostrar todos os vínculos relevantes.

## 23.3 Placa

- normalizada;
- duplicidade verificada;
- histórico preservado;
- situação própria;
- conflito sinalizado.

## 23.4 Remoção

O ícone de lixeira não autoriza exclusão física. A ação deverá informar:

- inativar veículo;
- encerrar vínculo;
- remover associação;
- consequência sobre LPR.

## 23.5 “Ver todos”

Direciona à lista filtrada pelo imóvel ou pessoa, preservando contexto.

---

# 24. Sincronização com controladora e facial

## 24.1 Estados

- não enviado;
- aguardando;
- enviado;
- sincronizado;
- falha;
- removido;
- atualização pendente.

## 24.2 Painel

Deverá exibir:

- equipamento ou adaptador;
- estado;
- última tentativa;
- identificador externo protegido;
- erro sanitizado;
- ação autorizada;
- próxima tentativa.

## 24.3 Envio

“Enviar para controladora”:

- depende de dados mínimos;
- revalida autorização;
- usa fila e idempotência;
- não altera identificador interno;
- mostra processamento;
- não declara sincronizado antes do retorno.

## 24.4 Falha

Não apaga cadastro nem vínculo. Permite:

- nova tentativa;
- correção;
- consulta técnica;
- contingência conforme regra.

---

# 25. Histórico de acessos

## 25.1 Resumo na tela

Conforme referência:

- data;
- hora;
- direção;
- ponto de acesso;
- resultado.

## 25.2 “Ver todos”

Direciona ao histórico filtrado pela pessoa ou imóvel, conforme contexto e permissão.

## 25.3 Privacidade

O histórico exibido deve respeitar:

- perfil;
- implantação;
- necessidade;
- retenção;
- proteção de terceiros.

---

# 26. Histórico de ocupação e alterações

## 26.1 Conteúdo

- vínculo criado;
- ativado;
- alterado;
- suspenso;
- renovado;
- encerrado;
- expirado;
- responsável alterado;
- imóvel alterado;
- veículo relacionado;
- credencial sincronizada;
- operador;
- data e hora.

## 26.2 Consulta

Permitir filtros por:

- pessoa;
- imóvel;
- natureza;
- situação;
- período;
- operador.

## 26.3 Imutabilidade

Eventos históricos não serão editados por usuário operacional.

---

# 27. Ações de salvamento

## 27.1 Cancelar

- avisa sobre alterações;
- permite permanecer;
- descarta somente estado não salvo;
- não encerra registro existente.

## 27.2 Salvar rascunho

- permite incompletude controlada;
- não ativa vínculo;
- não gera autorização;
- não sincroniza;
- identifica pendências.

## 27.3 Salvar e ativar cadastro

Deverá:

1. validar pessoa;
2. validar imóvel;
3. validar vínculo;
4. validar vigência;
5. verificar duplicidades;
6. confirmar permissões;
7. registrar auditoria;
8. ativar apenas entidades aprovadas;
9. enfileirar sincronização quando aplicável;
10. mostrar resultado por entidade.

## 27.4 Resultado parcial

Exemplo:

**Pessoa e vínculo salvos. A sincronização facial está pendente.**

Não tratar falha de integração como falha do cadastro já confirmado.

---

# 28. Estados e transições

## 28.1 Imóvel

```text
Em implantação
   ├── Ativo
   ├── Inativo
   └── Bloqueado
```

## 28.2 Pessoa

```text
Rascunho
   ↓
Pendente de validação
   ├── Ativa
   ├── Rejeitada
   └── Inativa

Ativa
   ├── Bloqueada
   ├── Inativa
   └── Pendente de atualização
```

## 28.3 Vínculo

```text
Agendado
   ↓
Ativo
   ├── Suspenso
   ├── Encerrado
   └── Expirado
```

## 28.4 Veículo

```text
Rascunho
   ├── Ativo
   ├── Inativo
   ├── Bloqueado
   └── Temporário
```

---

# 29. Exceções

## 29.1 Pessoa existente

Localizar e criar novo vínculo.

## 29.2 Documento duplicado

Impedir novo cadastro ou encaminhar tratamento autorizado.

## 29.3 Imóvel duplicado

Apresentar correspondência e impedir criação indevida.

## 29.4 Imóvel inativo ou bloqueado

Impedir novos vínculos ou exigir procedimento formal.

## 29.5 Vínculo incompatível

Explicar conflito e não ativar silenciosamente.

## 29.6 Término no passado

Impedir ativação normal ou registrar histórico por fluxo de migração.

## 29.7 Falha de sincronização

Salvar cadastro e mostrar pendência.

## 29.8 Alteração concorrente

- detectar versão;
- preservar alterações do usuário;
- comparar;
- recarregar;
- não sobrescrever silenciosamente.

---

# 30. Responsividade

## 30.1 Desktop

- sidebar persistente;
- formulário principal;
- painel contextual lateral;
- tipo de acesso em cartões;
- etapas horizontais;
- ações inferiores.

## 30.2 Tablet

- sidebar recolhível;
- formulário principal;
- painel contextual abaixo ou em drawer;
- cartões do tipo de acesso em grade;
- etapas compactas.

## 30.3 Celular

- uma coluna;
- tipo de acesso rolável apenas se acessível ou empilhado;
- etapa atual e progresso;
- painel contextual dentro do fluxo;
- tabelas convertidas em cartões;
- ações empilhadas;
- nenhuma perda de contexto.

## 30.4 Ordem móvel

1. tipo;
2. etapa;
3. campos;
4. contexto;
5. alertas;
6. ações.

---

# 31. Acessibilidade

- título e cabeçalhos;
- stepper anunciado;
- seleção de tipo por teclado;
- rótulos persistentes;
- erros associados;
- foco visível;
- foto com alternativa;
- tabelas acessíveis;
- botões de ícone nomeados;
- nenhum estado apenas por cor;
- modal com foco;
- zoom de 200%;
- texto ampliado;
- redução de movimento;
- mensagens de salvamento anunciadas;
- ordem de leitura responsiva.

---

# 32. Conteúdo e microcopy

## 32.1 Rótulos

- Cadastro de Pessoa;
- Tipo de acesso;
- Dados pessoais;
- Documentos e fotos;
- Endereço e contato;
- Informações de acesso;
- Observações;
- Vínculos e acessos;
- Pessoas vinculadas a este imóvel;
- Informações de locação;
- Veículos cadastrados;
- Histórico de acessos;
- Sincronização com controladora e facial;
- Salvar rascunho;
- Salvar e ativar cadastro.

## 32.2 Ações de histórico

| Evitar | Preferir |
|---|---|
| Excluir morador | Encerrar vínculo |
| Excluir veículo | Inativar veículo ou remover vínculo |
| Remover inquilino | Encerrar locação |
| Apagar foto | Substituir foto ou retirar foto ativa |

## 32.3 Mensagens

| Situação | Mensagem |
|---|---|
| Duplicidade | Já existe uma pessoa com este documento. Selecione o cadastro existente para criar um vínculo. |
| Vínculo expirado | O vínculo expirou. O cadastro da pessoa permanece preservado. |
| Endereço compartilhado | Este endereço pertence ao imóvel e é compartilhado pelos vínculos residenciais. |
| Sincronização pendente | Cadastro salvo. A sincronização ainda está pendente. |
| Conflito | O período informado conflita com outro vínculo. Confira antes de continuar. |

---

# 33. Segurança e privacidade

- menor privilégio;
- segregação por implantação;
- documentos e fotos privados;
- mascaramento;
- histórico de visualização quando aplicável;
- nenhuma autorização apenas no frontend;
- uploads validados;
- observações sanitizadas;
- dados sensíveis por finalidade;
- alterações críticas auditadas;
- downloads protegidos;
- identificadores externos não expostos como chave principal;
- cache segregado.

---

# 34. Auditoria

Registrar:

- criação e alteração do imóvel;
- alteração de endereço;
- pessoa;
- documento;
- foto;
- vínculo;
- responsabilidade;
- vigência;
- renovação;
- suspensão;
- encerramento;
- veículo;
- credencial;
- sincronização;
- operador;
- origem;
- valores anterior e posterior;
- data e hora.

---

# 35. Desempenho e observabilidade

## 35.1 Busca

- rápida;
- paginada;
- normalizada;
- sem revelar dados indevidos;
- com resultados suficientes para evitar duplicidade.

## 35.2 Tela

- carregar contexto progressivamente;
- não bloquear cadastro por histórico extenso;
- limitar acessos recentes;
- carregar arquivos sob demanda;
- manter estado ao trocar etapa.

## 35.3 Telemetria

Medir:

- duplicidades evitadas;
- tempo de cadastro;
- abandono;
- erros por etapa;
- conflitos;
- expirações;
- sincronizações;
- falhas;
- atualizações concorrentes.

---

# 36. Diretrizes para Blade e Livewire

## 36.1 Componentização

```text
PropertyManagement
├── PropertyList
├── PropertyForm
├── PropertySummary
├── PropertyLinks
├── PropertyVehicles
└── PropertyHistory

PersonRegistration
├── AccessTypeSelector
├── PersonalDataStep
├── DocumentsStep
├── AddressAndContactStep
├── AccessInformationStep
├── NotesStep
├── LinksContext
├── VehiclesContext
├── SyncStatus
└── AccessHistory
```

## 36.2 Estado

- pessoa e imóvel possuem identificadores separados;
- vínculo possui identificador próprio;
- etapa não guarda autorização implícita;
- servidor valida transições;
- ações usam Policies ou Gates;
- salvamento trata concorrência;
- integração usa serviço desacoplado;
- filas idempotentes.

## 36.3 Atualização

Editar endereço do imóvel a partir da pessoa deverá chamar operação explícita do imóvel, com permissão e confirmação de impacto.

---

# 37. Contrato funcional de dados

## 37.1 Imóvel

- implantação;
- condomínio;
- bloco;
- unidade;
- código;
- endereço;
- situação;
- versão;
- histórico.

## 37.2 Pessoa

- identidade;
- dados pessoais;
- contatos;
- documentos;
- fotos;
- situação;
- versão.

## 37.3 Vínculo

- pessoa;
- imóvel;
- natureza;
- papel;
- responsabilidade;
- início;
- término;
- situação;
- versão;
- origem.

## 37.4 Autorização

- vínculo;
- áreas;
- horários;
- dias;
- vigência;
- situação;
- origem.

## 37.5 Veículo

- identificação;
- placa;
- atributos;
- situação;
- vínculos;
- histórico.

## 37.6 Credencial

- pessoa ou veículo;
- tipo;
- vigência;
- estado;
- sincronização;
- identificador externo.

---

# 38. Cenários de teste

## 38.1 Imóvel

- cadastrar com bloco;
- sem bloco;
- duplicidade;
- alterar endereço;
- inativar;
- bloquear;
- sem responsável;
- vários ocupantes.

## 38.2 Pessoa

- nova;
- existente;
- CPF inválido;
- documento duplicado;
- rascunho;
- ativação;
- dados sensíveis.

## 38.3 Vínculos

- proprietário;
- morador;
- responsável;
- inquilino;
- turista;
- múltiplos;
- agendado;
- expirado;
- suspenso;
- encerrado;
- conflito.

## 38.4 Veículos

- pessoa;
- imóvel;
- ambos;
- placa duplicada;
- inativação;
- desvinculação.

## 38.5 Integração

- sincronização;
- pendente;
- falha;
- retentativa;
- remoção;
- atualização concorrente.

## 38.6 Responsividade e acessibilidade

- desktop;
- tablet;
- celular;
- teclado;
- leitor de tela;
- zoom;
- modal;
- tabela;
- stepper;
- mensagens.

## 38.7 Segurança

- ação sem permissão;
- acesso cruzado;
- arquivo direto;
- alteração de imóvel pelo formulário da pessoa;
- requisição Livewire adulterada;
- conflito de versão.

---

# 39. Critérios de aceite

## 39.1 Imóvel

**CA-UXI-001:** imóvel possui identificação única dentro da implantação.  
**CA-UXI-002:** bloco é opcional conforme configuração.  
**CA-UXI-003:** endereço pertence ao imóvel.  
**CA-UXI-004:** alteração de endereço registra impacto e auditoria.  
**CA-UXI-005:** situação do imóvel é independente dos vínculos.  

## 39.2 Pessoa e cadastro

**CA-UXI-006:** cadastro único evita duplicidade.  
**CA-UXI-007:** cinco etapas preservam dados.  
**CA-UXI-008:** campos variam por tipo.  
**CA-UXI-009:** rascunho não ativa acesso.  
**CA-UXI-010:** documentos e fotos são protegidos.  
**CA-UXI-011:** OCR não substitui validação.  

## 39.3 Vínculos

**CA-UXI-012:** vínculo possui natureza, papel, responsabilidade e situação separados.  
**CA-UXI-013:** múltiplos moradores mantêm cadastros independentes.  
**CA-UXI-014:** inquilino exige período.  
**CA-UXI-015:** expiração revoga permissões derivadas.  
**CA-UXI-016:** renovação preserva histórico.  
**CA-UXI-017:** conflitos são sinalizados.  
**CA-UXI-018:** encerramento não exclui pessoa.  

## 39.4 Veículos e credenciais

**CA-UXI-019:** veículo apresenta vínculos e situação próprios.  
**CA-UXI-020:** placa é normalizada e verificada.  
**CA-UXI-021:** remoção visual não causa exclusão destrutiva.  
**CA-UXI-022:** sincronização apresenta estados reais.  
**CA-UXI-023:** falha de integração não apaga cadastro.  

## 39.5 Visuais, responsivos e acessíveis

**CA-UXI-024:** cadastro de pessoa preserva composição das referências.  
**CA-UXI-025:** painel contextual mantém vínculos, veículos e histórico.  
**CA-UXI-026:** tela estrutural do imóvel é prototipada antes da implementação.  
**CA-UXI-027:** fluxo funciona nos viewports homologados.  
**CA-UXI-028:** ações funcionam por teclado.  
**CA-UXI-029:** estados não dependem apenas de cor.  

## 39.6 Segurança e auditoria

**CA-UXI-030:** permissões são aplicadas no servidor.  
**CA-UXI-031:** dados são segregados.  
**CA-UXI-032:** ações relevantes registram autor e alterações.  
**CA-UXI-033:** arquivos não possuem acesso público permanente.  
**CA-UXI-034:** alteração concorrente não sobrescreve silenciosamente.  

---

# 40. Pendências abertas

| PEN-UXI | Pendência | Impacto | Encaminhamento |
|---|---|---|---|
| PEN-UXI-001 | Prototipar lista, cadastro e detalhe estrutural do imóvel | Não há tela completa nas referências | UX/UI antes da implementação |
| PEN-UXI-002 | Separar definitivamente proprietário, morador, titular, responsável e parentesco | Campos e regras | `PEN-009` do Product Book |
| PEN-UXI-003 | Definir comportamento final dos ícones de remoção | Histórico e microcopy | `PEN-018` |
| PEN-UXI-004 | Definir identificação única real da Santa Rita | Cadastro do imóvel | `PEN-004` |
| PEN-UXI-005 | Confirmar estados e transições do imóvel | Ações e permissões | Regras de negócio |
| PEN-UXI-006 | Definir histórico de endereço | Auditoria e exibição | Modelo de dados |
| PEN-UXI-007 | Definir campos obrigatórios por tipo | Formulários | Regras de negócio |
| PEN-UXI-008 | Definir finalidade do tipo sanguíneo e demais dados sensíveis | Privacidade | LGPD |
| PEN-UXI-009 | Definir política de documentos e fotos | Retenção e acesso | `PEN-005` e `PEN-006` |
| PEN-UXI-010 | Definir conflitos de vigência incompatíveis | Inquilinos e ocupação | Regra de negócio |
| PEN-UXI-011 | Definir regra de responsável principal | Vínculos e substituição | Regra de negócio |
| PEN-UXI-012 | Definir modelo definitivo de vínculo do veículo | Pessoa, imóvel ou ambos | Modelo de dados |
| PEN-UXI-013 | Definir áreas, horários e herança | Autorização | Regras de acesso |
| PEN-UXI-014 | Confirmar fabricante e estados de sincronização | Painel técnico | `PEN-001` |
| PEN-UXI-015 | Definir limite e visibilidade das observações | Privacidade | Produto |
| PEN-UXI-016 | Definir política de endereço particular | Etapa 3 | Requisito futuro |
| PEN-UXI-017 | Aprovar protótipos responsivos | Aceite visual | Prototipação |
| PEN-UXI-018 | Definir metas de pesquisa e carregamento | Desempenho | RNF |
| PEN-UXI-019 | Definir edição concorrente e versionamento | UX e integridade | Arquitetura |
| PEN-UXI-020 | Definir como vínculos históricos aparecem ao operador | Consulta | UX/UI e regras |

---

# 41. Decisões consolidadas

Ficam consolidados:

- imóvel como entidade central;
- endereço armazenado no imóvel;
- pessoa como cadastro único;
- vínculo como entidade independente;
- natureza, papel e responsabilidade separados;
- cinco etapas no cadastro de pessoa;
- painel contextual com vínculos, veículos, histórico e sincronização;
- múltiplos moradores com identidade e acesso próprios;
- inquilino com período obrigatório;
- expiração automática;
- renovação e encerramento rastreáveis;
- veículos com situação e vínculos próprios;
- remoção visual sem exclusão destrutiva;
- sincronização desacoplada do cadastro;
- falha externa sem perda de dados;
- tela estrutural do imóvel condicionada a protótipo;
- Blade e Livewire como base futura;
- autorização e validação no servidor.

## 41.1 Registro de aprovação

| Papel | Nome | Decisão | Data | Observações |
|---|---|---|---|---|
| Product Owner | Vinicius Velasco de Azevedo | Aprovado | 28/07/2026 | UX/UI de Imóveis, Pessoas e Vínculos aprovada como referência para prototipação, testes e implementação futura |
| Soluções do Vale | Representante designado | Ciente | 28/07/2026 | Aprovação registrada no repositório oficial |

---

# 42. Próximo documento

Após a aprovação desta especificação, deverá ser produzido:

**`docs/008_ADMINISTRACAO.md`**

O próximo documento deverá detalhar:

- usuários;
- perfis;
- permissões;
- configurações;
- equipamentos;
- pontos de acesso;
- motivos parametrizados;
- auditoria;
- menor privilégio;
- operações críticas.

---

## Situação do documento

Esta especificação consolida a experiência de imóveis, pessoas, vínculos, vigências, veículos e históricos e encontra-se **aprovada**. As pendências de prototipação do imóvel, classificações, regras temporais, privacidade e sincronização permanecem rastreadas e deverão ser resolvidas antes da implementação definitiva dos elementos afetados, sem invalidar esta aprovação documental.
