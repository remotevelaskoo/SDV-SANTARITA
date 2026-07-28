# SDV ACCESS — PRODUCT BOOK
## Volume 01 — Jornadas, Casos de Uso e Fluxos Operacionais

**Documento:** SDV-PBK-002  
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
| 1.0.0 | Julho/2026 | Soluções do Vale | Formalização das jornadas, casos de uso, estados, exceções, permissões e critérios de aceite operacionais |

---

# 1. Objetivo deste documento

Este documento dá continuidade ao Volume 01 do Product Book e detalha como o SDV Access deverá funcionar na operação diária.

O conteúdo foi estruturado a partir das telas e fluxos aprovados para:

- cadastro de pessoas;
- vínculos com imóveis;
- cadastro de moradores e inquilinos;
- pré-cadastro de visitantes, turistas e prestadores;
- análise e aprovação pela portaria;
- validação de entrada;
- reconhecimento facial;
- leitura de placas;
- contribuição ou taxa de acesso;
- caixa operacional;
- relatórios;
- administração, permissões e auditoria.

As referências visuais aprovadas representam a experiência desejada, a hierarquia das informações e o comportamento esperado da aplicação. Este documento converte essas referências em regras funcionais rastreáveis para desenvolvimento, testes e homologação.

---

# 2. Posicionamento da aplicação

O SDV Access será uma **plataforma web de gestão e controle de acesso**, acessível por navegador e preparada para operação administrativa, portaria, gestão e integrações com equipamentos físicos.

A aplicação deverá ser:

- segura;
- modular;
- responsiva;
- auditável;
- de fácil utilização;
- preparada para crescimento;
- independente de instalação local nas estações de trabalho;
- compatível com integrações externas desacopladas;
- adequada para operação contínua em ambiente de portaria.

A implantação Santa Rita será a primeira aplicação do produto, mas a estrutura funcional deverá permitir expansão futura para outros condomínios, loteamentos, empresas e organizações.

## 2.1 Plataforma web

A solução deverá funcionar prioritariamente em navegador moderno, permitindo acesso autorizado a partir de computadores, notebooks, tablets e dispositivos compatíveis.

Não deverá ser necessária a instalação de um sistema completo em cada computador da operação. Componentes locais somente poderão existir quando exigidos por equipamento físico, captura de imagem, comunicação com controladora ou integração específica.

## 2.2 Separação entre produto e implantação

O produto será denominado **SDV Access**.

A configuração aplicada ao condomínio Santa Rita será uma implantação do produto, contendo:

- usuários próprios;
- imóveis próprios;
- regras de acesso próprias;
- equipamentos próprios;
- parâmetros próprios;
- identidade institucional configurável quando aplicável;
- dados completamente segregados de outras implantações futuras.

## 2.3 Segurança como requisito estrutural

A segurança não deverá ser adicionada apenas ao final. Ela deverá fazer parte do desenho dos módulos, das permissões, do armazenamento de documentos, das integrações e dos logs.

Toda operação crítica deverá considerar:

- autenticação;
- autorização;
- menor privilégio;
- rastreabilidade;
- confirmação de ações sensíveis;
- proteção de dados pessoais;
- tratamento de falhas;
- continuidade operacional.

---

# 3. Mapa funcional da plataforma

A estrutura funcional observada nas telas aprovadas será organizada da seguinte forma:

```text
SDV Access
├── Dashboard
├── Validação de Entrada
├── Pré-Cadastros
├── Cadastros
│   ├── Imóveis
│   ├── Pessoas
│   ├── Moradores
│   ├── Inquilinos
│   ├── Visitantes
│   ├── Turistas
│   ├── Empresas
│   ├── Prestadores
│   └── Veículos
├── Controle de Acesso
│   ├── Controladora
│   ├── Reconhecimento Facial
│   ├── Leitura de Placas
│   └── Credenciais
├── Caixa
│   ├── Abertura
│   ├── Movimentações
│   ├── Contribuições
│   └── Fechamento
├── Relatórios
│   ├── Acessos
│   ├── Cadastros
│   ├── Caixa
│   ├── Equipamentos
│   └── Auditoria
└── Administração
    ├── Usuários
    ├── Perfis de Acesso
    ├── Configurações
    ├── Equipamentos
    └── Logs e Auditoria
```

## 3.1 Navegação principal

A aplicação deverá utilizar navegação lateral persistente nas áreas administrativas e operacionais.

O menu deverá ser montado conforme as permissões do usuário. Um usuário não deverá visualizar opções para as quais não possui acesso.

A interface deverá destacar claramente:

- módulo atual;
- usuário autenticado;
- função ou perfil do usuário;
- situação do caixa, quando aplicável;
- data e hora da operação;
- notificações pendentes;
- ações principais da tela.

---

# 4. Tipos de acesso e classificação de pessoas

As telas aprovadas apresentam os seguintes tipos principais:

| Tipo | Finalidade | Vínculo esperado | Vigência |
|---|---|---|---|
| Morador | Proprietário, dependente ou ocupante residente | Imóvel | Permanente ou definida |
| Inquilino | Locatário de um imóvel | Imóvel e contrato | Obrigatoriamente temporária |
| Prestador | Funcionário ou prestador de serviço | Empresa, imóvel, área ou autorização | Temporária ou parametrizada |
| Visitante | Pessoa convidada por morador ou responsável | Responsável e imóvel de destino | Temporária |
| Turista | Ocupação temporária, hospedagem ou locação curta | Imóvel e responsável | Obrigatoriamente temporária |

A classificação da pessoa não deverá substituir o vínculo. A mesma pessoa poderá permanecer como cadastro único e possuir vínculos diferentes ao longo do tempo.

Exemplo:

```text
Pessoa: João da Silva
├── Visitante do imóvel A em 2025
├── Prestador autorizado em 2026
└── Morador do imóvel B em 2027
```

---

# 5. Jornada de cadastro de pessoa

## 5.1 Objetivo

Permitir que um usuário autorizado registre uma pessoa e estabeleça seus vínculos, documentos, contato, vigência, credenciais e informações de acesso.

## 5.2 Estrutura em etapas

A tela de cadastro deverá seguir a navegação por etapas apresentada nas referências:

1. Dados pessoais;
2. Documentos e fotos;
3. Endereço e contato;
4. Informações de acesso;
5. Observações.

O usuário deverá conseguir navegar entre etapas já validadas sem perder informações.

## 5.3 Etapa 1 — Dados pessoais

Campos previstos:

- tipo de acesso;
- foto da pessoa;
- nome completo;
- nome social;
- CPF;
- RG ou documento equivalente;
- órgão emissor;
- data de nascimento;
- estado civil;
- nacionalidade;
- filiação;
- profissão;
- empresa, quando aplicável;
- tipo sanguíneo, quando adotado pela implantação;
- telefone principal;
- telefone secundário;
- e-mail.

### Regras

- o CPF deverá ser normalizado e validado quando informado;
- o sistema deverá pesquisar duplicidade antes de criar novo cadastro;
- nome social deverá ser exibido conforme configuração e necessidade legal;
- foto e documento deverão seguir política de acesso e retenção;
- campos obrigatórios poderão variar conforme o tipo de acesso;
- dados não confirmados poderão permanecer em rascunho.

## 5.4 Etapa 2 — Documentos e fotos

A etapa deverá permitir:

- envio ou captura de documento;
- visualização do arquivo;
- classificação do tipo documental;
- captura de frente e verso, quando necessária;
- captura ou envio de foto facial;
- conferência dos dados extraídos;
- substituição de imagem inadequada;
- registro da origem da captura.

Quando houver OCR, o resultado deverá ser apresentado para conferência humana. A extração automática não deverá substituir a validação do operador.

## 5.5 Etapa 3 — Endereço e contato

Para moradores, inquilinos e ocupantes vinculados, o endereço principal será herdado do imóvel.

A tela deverá informar claramente que:

- o endereço pertence ao imóvel;
- moradores vinculados ao mesmo imóvel compartilham o endereço;
- a alteração estrutural do endereço deverá ocorrer no cadastro do imóvel;
- inquilinos usam o endereço do imóvel durante a vigência do vínculo.

O sistema poderá manter endereço particular independente somente quando houver necessidade de contato ou correspondência e quando isso for formalmente definido em requisito posterior.

## 5.6 Etapa 4 — Informações de acesso

A etapa deverá permitir configurar:

- vínculo com condomínio;
- imóvel;
- morador responsável;
- data inicial;
- data final;
- acesso por prazo indeterminado, quando permitido;
- áreas autorizadas;
- horários autorizados;
- dias da semana;
- credenciais associadas;
- situação do cadastro;
- situação de sincronização com equipamentos.

## 5.7 Etapa 5 — Observações

Deverá permitir observações operacionais controladas.

Informações sensíveis não deverão ser registradas livremente sem finalidade definida. O campo deverá possuir limite de caracteres, autoria, data e histórico de alteração quando aplicável.

## 5.8 Ações do cadastro

A tela deverá oferecer:

- cancelar;
- salvar rascunho;
- salvar e ativar;
- voltar;
- acessar ações adicionais conforme permissão.

### Regras de conclusão

- rascunho não poderá liberar acesso;
- cadastro incompleto não poderá ser ativado quando faltar informação obrigatória;
- ativação deverá registrar usuário, data e hora;
- integração com controladora não deverá ocorrer antes da validação dos dados mínimos;
- falha na sincronização não deverá apagar o cadastro.

---

# 6. Jornada de vínculo com imóvel

## 6.1 Princípio central

O imóvel é a entidade central da implantação Santa Rita.

O fluxo deverá ocorrer da seguinte forma:

```text
Selecionar ou cadastrar imóvel
        ↓
Localizar ou cadastrar pessoa
        ↓
Definir tipo de vínculo
        ↓
Definir responsabilidade
        ↓
Definir vigência
        ↓
Definir permissões
        ↓
Ativar vínculo
```

## 6.2 Múltiplos moradores

Um imóvel poderá possuir vários moradores ativos.

Cada morador deverá manter:

- cadastro próprio;
- documento próprio;
- foto própria;
- credenciais próprias;
- situação própria;
- histórico de acesso próprio;
- vínculo individual com o imóvel.

A relação entre moradores poderá registrar classificações como:

- titular;
- cônjuge;
- filho;
- dependente;
- responsável;
- outro vínculo configurado.

## 6.3 Morador responsável

O imóvel deverá permitir a indicação de um responsável principal para operações que exijam referência.

A existência de responsável principal não deverá impedir que outros moradores possuam autorização de acesso própria.

## 6.4 Inquilino

O vínculo de inquilino deverá conter obrigatoriamente:

- imóvel;
- data de início;
- data de término;
- contrato ou referência, quando informado;
- responsável pelo imóvel;
- situação;
- histórico de renovação.

Após a data de término, o vínculo e as permissões derivadas deverão ser encerrados automaticamente.

## 6.5 Turista ou ocupação temporária

A modalidade turista deverá ser tratada como ocupação temporária vinculada a um imóvel.

Deverá possuir:

- responsável;
- período de permanência;
- imóvel;
- identificação;
- credenciais temporárias;
- situação de aprovação;
- regras de acesso definidas.

---

# 7. Jornada de pré-cadastro

## 7.1 Objetivo

Permitir que visitante, turista ou prestador informe previamente seus dados, reduzindo o tempo de atendimento na portaria.

## 7.2 Etapas previstas

O fluxo aprovado contém:

1. dados pessoais;
2. endereço;
3. documento;
4. selfie;
5. veículo opcional;
6. confirmação.

## 7.3 Início do pré-cadastro

O acesso deverá ocorrer por endereço seguro e temporário, QR Code ou convite emitido pelo sistema.

O convite deverá estar associado, quando aplicável, a:

- imóvel de destino;
- morador responsável;
- tipo de acesso;
- período previsto;
- finalidade;
- quantidade permitida de cadastros;
- data de expiração do link.

## 7.4 Dados pessoais

O visitante deverá informar os dados obrigatórios definidos para o tipo de acesso.

O sistema deverá apresentar informações de privacidade e finalidade antes da conclusão do envio.

## 7.5 Documento

O visitante poderá enviar fotografia do documento.

Quando OCR estiver disponível:

```text
Documento enviado
      ↓
Validação técnica da imagem
      ↓
Extração dos dados
      ↓
Apresentação para conferência
      ↓
Confirmação pelo usuário
```

A confirmação do usuário não substitui a análise da portaria quando exigida.

## 7.6 Selfie

A selfie deverá possuir critérios mínimos de qualidade, como:

- rosto visível;
- iluminação suficiente;
- ausência de obstrução relevante;
- somente uma pessoa na imagem;
- formato compatível.

A captura poderá futuramente alimentar reconhecimento facial, desde que exista base legal, finalidade, consentimento ou outra hipótese aplicável, além das proteções necessárias.

## 7.7 Veículo opcional

O pré-cadastro poderá registrar:

- placa;
- marca;
- modelo;
- cor;
- vínculo com o visitante.

O cadastro do veículo não garante liberação automática.

## 7.8 Confirmação e protocolo

Após envio, o sistema deverá gerar protocolo único.

O protocolo permitirá:

- acompanhar a situação;
- localizar o pré-cadastro;
- apresentar referência na portaria;
- consultar aprovação ou rejeição conforme política definida.

## 7.9 Situações do pré-cadastro

```text
Rascunho
   ↓
Enviado
   ↓
Aguardando análise
   ├── Aprovado
   ├── Rejeitado
   ├── Solicitação de correção
   └── Expirado
```

Pré-cadastro aprovado ainda deverá respeitar período, destino, situação do vínculo e demais regras no momento da entrada.

---

# 8. Jornada de análise pela portaria

## 8.1 Tela de consulta

A tela deverá permitir pesquisa por:

- nome;
- CPF ou documento;
- protocolo;
- placa;
- imóvel;
- responsável;
- período;
- tipo de acesso;
- situação.

## 8.2 Painel de detalhes

Ao selecionar um registro, a portaria deverá visualizar:

- foto;
- dados pessoais;
- documento;
- endereço informado;
- imóvel de destino;
- responsável;
- veículo;
- histórico da análise;
- período solicitado;
- alertas e inconsistências.

## 8.3 Ações

O operador autorizado poderá:

- visualizar;
- aprovar;
- rejeitar;
- solicitar correção;
- registrar observação;
- liberar conforme regra;
- fechar a análise sem alteração.

## 8.4 Aprovação

A aprovação deverá registrar:

- operador;
- data e hora;
- dados analisados;
- resultado;
- observação, quando aplicável;
- vigência concedida;
- credenciais geradas ou vinculadas.

## 8.5 Rejeição

A rejeição deverá exigir motivo selecionável e, quando necessário, complemento textual.

O motivo interno poderá possuir visibilidade diferente da mensagem apresentada ao solicitante.

---

# 9. Jornada de validação de entrada

## 9.1 Objetivo operacional

A tela de validação deverá concentrar em um único fluxo as informações necessárias para uma decisão rápida, segura e auditável.

## 9.2 Estrutura da validação

A sequência apresentada nas referências será:

1. identificação da pessoa;
2. validação do veículo;
3. contribuição ou taxa de acesso;
4. observações;
5. decisão final.

## 9.3 Identificação da pessoa

A interface deverá apresentar:

- foto facial;
- nome;
- tipo de acesso;
- documento;
- data de nascimento;
- imóvel;
- responsável;
- telefone;
- e-mail;
- situação do cadastro;
- situação da face;
- situação do documento;
- situação da autorização;
- validade do acesso.

Alertas críticos deverão possuir destaque visual imediato.

## 9.4 Validação do veículo

Quando houver veículo, a tela deverá apresentar:

- imagem capturada;
- placa lida;
- placa cadastrada;
- confiança da leitura;
- marca;
- modelo;
- cor;
- ano;
- proprietário ou responsável;
- tipo de vínculo;
- situação do veículo.

A divergência entre leitura e cadastro deverá impedir liberação automática e exigir tratamento pelo operador.

## 9.5 Contribuição ou taxa de acesso

A operação poderá classificar o acesso como:

- contribui;
- não contribui;
- isento.

Quando houver recebimento, deverão ser registrados:

- valor;
- forma de pagamento;
- pessoa que realizou o pagamento;
- operador;
- caixa aberto;
- data e hora;
- observação;
- vínculo com o evento de acesso.

A contribuição operacional não transforma o SDV Access em ERP financeiro completo. O módulo deverá controlar apenas os movimentos necessários à operação prevista.

## 9.6 Decisão final

A tela deverá oferecer as ações:

- negar entrada;
- salvar sem liberar;
- validar e liberar.

### Negar entrada

Deverá exigir motivo e registrar a tentativa.

### Salvar sem liberar

Deverá guardar o atendimento para continuidade, sem emitir comando de abertura.

### Validar e liberar

Deverá:

1. revalidar as condições críticas;
2. registrar o evento;
3. registrar pagamento, quando houver;
4. enviar comando ao equipamento, quando integrado;
5. registrar o resultado do comando;
6. apresentar confirmação ao operador.

---

# 10. Reconhecimento facial e leitura de placas

## 10.1 Integrações desacopladas

O núcleo do sistema não deverá depender de um fabricante específico.

Cada integração deverá utilizar adaptador próprio:

```text
SDV Access
    ↓
Serviço de integração
    ├── Adaptador BRAVA
    ├── Adaptador facial
    ├── Adaptador LPR
    └── Futuros fabricantes
```

## 10.2 Sincronização facial

A situação da sincronização deverá ser visível no cadastro.

Estados previstos:

- não enviado;
- aguardando envio;
- enviado;
- sincronizado;
- falha;
- removido;
- pendente de atualização.

A plataforma deverá armazenar o identificador externo retornado pelo equipamento sem utilizá-lo como identificador principal da pessoa.

## 10.3 Leitura de placas

A leitura de placa deverá produzir um evento contendo:

- imagem;
- texto reconhecido;
- confiança;
- data e hora;
- câmera;
- ponto de acesso;
- resultado da busca;
- decisão aplicada.

A confiança mínima para automação deverá ser configurável.

## 10.4 Funcionamento em falha de integração

Falhas de comunicação deverão:

- gerar log técnico;
- informar o operador;
- permitir contingência conforme permissão;
- entrar em fila para nova tentativa quando aplicável;
- não apagar registros já confirmados.

---

# 11. Caixa operacional

## 11.1 Abertura de caixa

A operação de recebimento somente poderá ocorrer com caixa aberto, quando essa regra estiver habilitada.

A abertura deverá registrar:

- operador;
- data e hora;
- saldo inicial;
- terminal ou ponto de operação;
- observação.

## 11.2 Movimentações

Toda movimentação deverá possuir:

- tipo;
- descrição;
- valor;
- natureza de entrada ou saída;
- operador;
- data e hora;
- acesso relacionado, quando houver;
- justificativa para cancelamento ou ajuste.

## 11.3 Fechamento

O fechamento deverá apresentar:

- saldo inicial;
- total de entradas;
- total de saídas;
- cancelamentos;
- total esperado;
- total informado;
- diferença;
- operador responsável;
- data e hora.

Diferenças deverão exigir justificativa e gerar evento de auditoria.

---

# 12. Dashboard e relatórios

## 12.1 Dashboard

O dashboard deverá apresentar informações conforme o perfil, podendo incluir:

- pessoas cadastradas;
- moradores;
- visitantes do dia;
- prestadores;
- veículos cadastrados;
- entradas do dia;
- saídas do dia;
- arrecadação do dia;
- acessos recentes;
- gráfico de entradas e saídas;
- pré-cadastros pendentes;
- alertas de integração;
- equipamentos indisponíveis.

Indicadores deverão ser originados de dados reais e rastreáveis.

## 12.2 Relatórios operacionais

Relatórios deverão permitir filtros por:

- período;
- pessoa;
- tipo de acesso;
- imóvel;
- responsável;
- veículo;
- placa;
- operador;
- resultado;
- equipamento;
- ponto de acesso;
- caixa;
- forma de pagamento.

## 12.3 Exportação

Quando autorizada, a exportação poderá ocorrer em formatos como PDF e planilha.

A exportação deverá respeitar as permissões e a proteção de dados pessoais.

---

# 13. Casos de uso principais

## UC-001 — Cadastrar imóvel

**Ator principal:** Administrador.  
**Pré-condição:** Usuário autenticado e autorizado.  
**Resultado:** Imóvel disponível para vínculos.

Fluxo principal:

1. usuário abre cadastro de imóvel;
2. informa condomínio, bloco, unidade e endereço;
3. sistema valida identificação única;
4. usuário salva;
5. sistema registra auditoria.

## UC-002 — Cadastrar morador

**Ator principal:** Administrador ou usuário autorizado.  
**Pré-condição:** Imóvel existente.  
**Resultado:** Pessoa vinculada ao imóvel como morador.

## UC-003 — Cadastrar inquilino

**Ator principal:** Administrador ou usuário autorizado.  
**Pré-condição:** Imóvel existente e período definido.  
**Resultado:** Vínculo temporário criado com expiração programada.

## UC-004 — Realizar pré-cadastro

**Ator principal:** Visitante, turista ou prestador.  
**Pré-condição:** Link válido ou fluxo público habilitado.  
**Resultado:** Protocolo gerado e enviado para análise.

## UC-005 — Aprovar pré-cadastro

**Ator principal:** Operador de portaria.  
**Pré-condição:** Pré-cadastro aguardando análise.  
**Resultado:** Autorização criada ou solicitação rejeitada.

## UC-006 — Validar pessoa na entrada

**Ator principal:** Operador de portaria.  
**Pré-condição:** Pessoa localizada por face, documento, nome, protocolo ou outra credencial.  
**Resultado:** Entrada autorizada, negada ou mantida pendente.

## UC-007 — Validar veículo

**Ator principal:** Sistema e operador.  
**Pré-condição:** Placa capturada ou informada.  
**Resultado:** Veículo reconhecido, divergente, não localizado ou bloqueado.

## UC-008 — Registrar contribuição

**Ator principal:** Operador com caixa aberto.  
**Pré-condição:** Acesso sujeito a contribuição.  
**Resultado:** Movimento financeiro operacional associado ao acesso.

## UC-009 — Liberar acesso

**Ator principal:** Operador ou automação autorizada.  
**Pré-condição:** Regras válidas.  
**Resultado:** Comando enviado e evento registrado.

## UC-010 — Negar acesso

**Ator principal:** Operador ou regra automática.  
**Pré-condição:** Condição impeditiva identificada.  
**Resultado:** Negativa registrada com motivo.

## UC-011 — Encerrar vínculo temporário

**Ator principal:** Sistema ou administrador.  
**Pré-condição:** Término da vigência ou ação autorizada.  
**Resultado:** Permissões revogadas e histórico preservado.

## UC-012 — Fechar caixa

**Ator principal:** Operador autorizado.  
**Pré-condição:** Caixa aberto.  
**Resultado:** Totais consolidados, diferença registrada e caixa encerrado.

---

# 14. Estados e transições

## 14.1 Pessoa

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

## 14.2 Vínculo

```text
Agendado
   ↓ data inicial
Ativo
   ├── Suspenso
   ├── Encerrado manualmente
   └── Expirado automaticamente
```

## 14.3 Autorização

```text
Rascunho
   ↓
Pendente
   ├── Aprovada
   ├── Rejeitada
   ├── Cancelada
   └── Expirada
```

## 14.4 Evento de acesso

```text
Detectado
   ↓
Em validação
   ├── Autorizado
   ├── Negado
   ├── Pendente
   └── Falha técnica
```

## 14.5 Caixa

```text
Fechado
   ↓ abertura
Aberto
   ├── Em conferência
   └── Fechado
```

---

# 15. Exceções operacionais

## EX-001 — Pessoa já cadastrada

O sistema deverá localizar o cadastro existente e permitir criar novo vínculo, sem duplicar a pessoa.

## EX-002 — CPF inválido

O sistema deverá impedir ativação ou exigir tratamento autorizado conforme tipo documental e regra configurada.

## EX-003 — Documento ilegível

O pré-cadastro deverá ser encaminhado para correção ou análise manual.

## EX-004 — Foto facial inadequada

A sincronização facial deverá permanecer pendente até substituição da imagem.

## EX-005 — Vínculo expirado

O sistema deverá negar a autorização derivada do vínculo, mesmo que o cadastro da pessoa permaneça ativo.

## EX-006 — Placa divergente

O operador deverá conferir o veículo e justificar qualquer alteração ou liberação excepcional.

## EX-007 — Equipamento indisponível

O sistema deverá registrar a falha e aplicar contingência configurada, sem simular sucesso inexistente.

## EX-008 — Caixa fechado

O sistema não deverá registrar recebimento financeiro sem abertura de caixa, salvo permissão e procedimento excepcional formalmente configurados.

## EX-009 — Falha ao enviar comando

O evento deverá indicar que a autorização foi validada, mas o comando físico falhou. O operador deverá receber orientação de contingência.

## EX-010 — Queda de conexão

A arquitetura deverá prever política operacional para indisponibilidade temporária, cache seguro ou operação contingencial, a ser detalhada no documento de arquitetura.

---

# 16. Matriz inicial de permissões

Legenda:

- **C:** consultar;
- **E:** executar;
- **G:** gerenciar;
- **A:** aprovar;
- **—:** sem acesso por padrão.

| Função | Dashboard | Cadastros | Pré-cadastro | Validação | Caixa | Relatórios | Usuários | Configurações | Auditoria |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| Operador de portaria | C | C limitado | C/A | E | E | C limitado | — | — | — |
| Administrador | C | G | G | E | G | G | G | G | C |
| Gestor ou síndico | C | C | C | C | C | G | C limitado | C limitado | C |
| Operador de caixa | C limitado | C limitado | — | E limitado | E | C limitado | — | — | — |
| Auditor | C | C | C | C | C | C | C | C | C |
| Morador, em portal futuro | C próprio | G próprio limitado | E próprio | C próprio | — | C próprio | — | — | — |

A matriz definitiva será configurada por permissões granulares e não apenas por perfis fixos.

---

# 17. Requisitos funcionais adicionais

**RF-021 — Selecionar tipo de acesso**  
O sistema deverá adaptar campos e regras conforme morador, inquilino, prestador, visitante ou turista.

**RF-022 — Manter vínculo entre pessoa e imóvel**  
O sistema deverá criar, alterar, suspender e encerrar vínculos preservando histórico.

**RF-023 — Compartilhar endereço do imóvel**  
O sistema deverá apresentar o endereço central do imóvel aos moradores e inquilinos vinculados.

**RF-024 — Gerenciar moradores do mesmo imóvel**  
O sistema deverá permitir visualizar e administrar múltiplos moradores em um único imóvel.

**RF-025 — Salvar rascunho**  
O sistema deverá permitir salvar cadastros incompletos sem ativar acesso.

**RF-026 — Capturar documento**  
O sistema deverá permitir envio ou captura de documentos com controle de acesso.

**RF-027 — Extrair dados por OCR**  
Quando habilitado, o sistema deverá extrair dados e exigir conferência.

**RF-028 — Capturar selfie**  
O sistema deverá permitir captura ou envio de foto facial com validação de qualidade.

**RF-029 — Gerar protocolo de pré-cadastro**  
O sistema deverá gerar identificador único após o envio.

**RF-030 — Aprovar ou rejeitar pré-cadastro**  
O operador autorizado deverá registrar decisão e motivo.

**RF-031 — Consultar histórico de acessos da pessoa**  
O sistema deverá apresentar eventos anteriores conforme permissão.

**RF-032 — Sincronizar cadastro facial**  
O sistema deverá enviar, atualizar e remover credenciais em equipamentos compatíveis.

**RF-033 — Registrar leitura de placa**  
O sistema deverá manter imagem, resultado, confiança e decisão.

**RF-034 — Abrir caixa**  
O sistema deverá permitir abertura individualizada por operador ou terminal.

**RF-035 — Registrar contribuição**  
O sistema deverá associar pagamento ao acesso e ao caixa.

**RF-036 — Fechar caixa**  
O sistema deverá consolidar movimentos e diferenças.

**RF-037 — Exibir situação de integração**  
O sistema deverá informar sincronizações pendentes, concluídas e com falha.

**RF-038 — Registrar decisão de entrada**  
Toda liberação, negativa ou pendência deverá gerar evento auditável.

**RF-039 — Manter notificações operacionais**  
O sistema deverá alertar sobre pré-cadastros, falhas e pendências conforme perfil.

**RF-040 — Aplicar permissões por ação**  
O sistema deverá controlar consulta, criação, edição, exclusão lógica, aprovação, liberação e exportação separadamente.

---

# 18. Requisitos não funcionais adicionais

**RNF-011 — Aplicação web segura**  
A plataforma deverá utilizar comunicação criptografada e sessões protegidas.

**RNF-012 — Segregação de dados**  
Dados de uma implantação não poderão ser acessados por outra implantação futura.

**RNF-013 — Tempo de resposta da portaria**  
Consultas críticas deverão ser otimizadas para uso operacional em tempo real.

**RNF-014 — Idempotência de comandos**  
Reenvios técnicos não deverão duplicar eventos, pagamentos ou liberações.

**RNF-015 — Tolerância a falhas externas**  
Falhas em equipamentos não deverão corromper os cadastros centrais.

**RNF-016 — Proteção de arquivos**  
Fotos e documentos não deverão ser expostos por endereços públicos permanentes.

**RNF-017 — Auditoria de exportação**  
Exportações de informações sensíveis deverão ser registradas.

**RNF-018 — Compatibilidade responsiva**  
Fluxos públicos de pré-cadastro deverão funcionar adequadamente em celular.

**RNF-019 — Acessibilidade**  
A interface deverá buscar contraste, legibilidade, navegação por teclado e mensagens compreensíveis.

**RNF-020 — Continuidade de operação**  
A implantação deverá possuir monitoramento, backup e procedimentos de restauração.

---

# 19. Critérios de aceite por jornada

## 19.1 Cadastro de pessoa

A jornada será aceita quando:

1. permitir seleção do tipo de acesso;
2. adaptar campos e validações;
3. impedir duplicidade indevida;
4. permitir rascunho;
5. permitir vínculo com imóvel;
6. exibir moradores vinculados;
7. preservar dados entre etapas;
8. registrar auditoria;
9. apresentar mensagens claras;
10. seguir a composição visual aprovada.

## 19.2 Pré-cadastro

A jornada será aceita quando:

1. funcionar em dispositivo móvel;
2. validar o link ou convite;
3. coletar dados por etapas;
4. permitir documento e selfie;
5. permitir veículo opcional;
6. gerar protocolo;
7. entrar na fila da portaria;
8. permitir acompanhamento da situação;
9. não liberar automaticamente sem regra aprovada;
10. proteger os dados enviados.

## 19.3 Validação de entrada

A jornada será aceita quando:

1. concentrar pessoa, veículo, contribuição e decisão;
2. exibir situação e vigência;
3. alertar bloqueios e divergências;
4. exigir motivo para negativa;
5. impedir pagamento sem caixa, quando configurado;
6. registrar evento antes ou juntamente com o comando;
7. informar falhas de equipamento;
8. permitir contingência somente a usuários autorizados;
9. manter resposta operacional rápida;
10. registrar toda ação relevante.

## 19.4 Caixa

A jornada será aceita quando:

1. permitir abertura e fechamento;
2. associar movimentos ao operador;
3. consolidar entradas, saídas e cancelamentos;
4. calcular diferença;
5. exigir justificativa quando necessário;
6. gerar relatório;
7. impedir alteração silenciosa de movimentos;
8. registrar auditoria.

---

# 20. Pendências para detalhamento posterior

Os seguintes pontos deverão ser aprofundados nos documentos específicos, sem bloquear a continuidade do Product Book:

- política detalhada de retenção de imagens e documentos;
- fabricante e protocolo definitivo da controladora;
- critérios de contingência sem internet;
- valor e regras da contribuição de acesso;
- formas de pagamento permitidas;
- regras específicas para turistas;
- política de reconhecimento facial e base legal;
- quantidade e localização dos pontos de acesso;
- regras de estacionamento;
- funcionamento de saída automática;
- política de bloqueios e listas de observação;
- canais de notificação;
- necessidade de portal do morador no MVP;
- política de multiempresa e multicliente.

Esses pontos deverão ser registrados como configuração, requisito ou ADR conforme a natureza da decisão.

---

# 21. Próximos documentos

Após esta parte, a documentação deverá avançar para:

1. Brand Book;
2. Design System;
3. especificação UX/UI do Dashboard;
4. especificação UX/UI da Validação de Entrada;
5. especificação UX/UI do Pré-Cadastro;
6. especificação UX/UI de Imóveis, Pessoas e Vínculos;
7. regras de negócio consolidadas;
8. modelo de dados;
9. APIs;
10. arquitetura técnica;
11. plano de testes;
12. implantação e manuais.

---

## Situação desta parte

Esta parte formaliza as jornadas operacionais, os casos de uso, os principais estados, as exceções, a matriz inicial de permissões e os critérios de aceite derivados das telas aprovadas.

O documento não substitui as especificações detalhadas de UX/UI, banco de dados, APIs, arquitetura ou segurança. Ele estabelece o comportamento funcional que esses documentos deverão implementar e preservar.