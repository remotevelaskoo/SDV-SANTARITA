# SDV ACCESS — UX/UI DA VALIDAÇÃO DE ENTRADA
## Especificação funcional, visual, responsiva e operacional

**Documento:** SDV-UXV-005  
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
| 1.0.0 | Julho/2026 | Soluções do Vale | Especificação inicial da Validação de Entrada baseada na referência visual aprovada |
| 1.0.1 | 28/07/2026 | Product Owner | Aprovação formal da especificação UX/UI da Validação de Entrada |

---

# 1. Objetivo

Este documento especifica a experiência da tela de **Validação de Entrada** do SDV Access.

A tela deverá concentrar, em uma única jornada operacional:

1. identificação da pessoa;
2. validação do vínculo, autorização e vigência;
3. identificação e validação do veículo;
4. classificação e registro da contribuição, quando aplicável;
5. observações;
6. decisão de negar, salvar sem liberar ou validar e liberar;
7. envio e confirmação do comando ao equipamento;
8. registro auditável do resultado.

O objetivo é permitir decisão rápida e segura, sem ocultar divergências, falhas técnicas ou etapas pendentes.

---

# 2. Fontes e rastreabilidade

## 2.1 Referência visual principal

**REF-UXV-001:** `docs/references/06-validacao-entrada.png`

**REF-UXV-002:** `docs/references/ChatGPT Image 27 de jul. de 2026, 13_49_55.png`

Os dois arquivos são cópias idênticas. A referência estabelece:

- menu lateral azul-marinho;
- cabeçalho “Validação de Entrada”;
- situação de caixa e operador;
- quatro seções numeradas;
- identificação da pessoa;
- situação do cadastro e validade;
- imagem e dados do veículo;
- situação e confiança da leitura LPR;
- contribuição ou taxa de acesso;
- observações;
- três ações finais com hierarquia semântica.

Os nomes, documentos, placas, valores, horários e imagens presentes na referência são ilustrativos.

## 2.2 Regras de negócio

| Identificador | Relação |
|---|---|
| `RN-023` a `RN-028` | Visitante, responsável, destino e autorização |
| `RN-034` a `RN-038` | Veículo, placa, vínculo e leitura |
| `RN-039` | Cadastro não implica autorização |
| `RN-040` | Decisão centralizada no SDV Access |
| `RN-041` | Registro de todas as tentativas |
| `RN-042` | Direção, ponto e método de identificação |
| `RN-043` | Liberação manual com operador e motivo |
| `RN-044` | Exceção com justificativa, responsável e validade |
| `RN-045` | Estado e vigência próprios das credenciais |
| `RN-046` a `RN-049` | Auditoria e imutabilidade lógica |

## 2.3 Requisitos

| Identificador | Relação |
|---|---|
| `RF-004` | Pesquisa de pessoa, documento, imóvel e placa |
| `RF-012` | Validar situação, vigência, permissão e credencial |
| `RF-013` | Registrar entrada |
| `RF-015` | Registrar acesso negado |
| `RF-016` | Liberação manual autorizada e justificada |
| `RF-031` | Consultar histórico da pessoa |
| `RF-033` | Registrar leitura de placa |
| `RF-035` | Registrar contribuição |
| `RF-037` | Exibir situação de integração |
| `RF-038` | Registrar decisão de entrada |
| `RF-040` | Aplicar permissões por ação |
| `RNF-004` e `RNF-013` | Desempenho operacional |
| `RNF-014` | Idempotência |
| `RNF-015` | Tolerância a falhas externas |
| `RNF-016` | Proteção de arquivos |
| `RNF-019` | Acessibilidade |
| `RNF-020` | Continuidade |

## 2.4 Casos de uso e exceções

- `UC-006 — Validar pessoa na entrada`;
- `UC-007 — Validar veículo`;
- `UC-008 — Registrar contribuição`;
- `UC-009 — Liberar acesso`;
- `UC-010 — Negar acesso`;
- `EX-004 — Foto facial inadequada`;
- `EX-005 — Vínculo expirado`;
- `EX-006 — Placa divergente`;
- `EX-007 — Equipamento indisponível`;
- `EX-008 — Caixa fechado`;
- `EX-009 — Falha ao enviar comando`;
- `EX-010 — Queda de conexão`.

## 2.5 Componentes do Design System

- `DS-CMP-001 — Sidebar`;
- `DS-CMP-002 — Operational Header`;
- `DS-CMP-003 — Botão`;
- `DS-CMP-004 — Grupo de ações`;
- `DS-CMP-005` a `DS-CMP-010 — Formulários e captura`;
- `DS-CMP-011 — Badge de status`;
- `DS-CMP-012 — Alerta`;
- `DS-CMP-013 — Toast`;
- `DS-CMP-015 — Skeleton e progresso`;
- `DS-CMP-016 — Card`;
- `DS-CMP-018 — Lista de atividade`;
- `DS-CMP-024 — Modal`;
- `DS-CMP-025 — Drawer`;
- `DS-CMP-028 — Resumo de pessoa`;
- `DS-CMP-030 — Cartão de veículo`;
- `DS-CMP-031 — Comparador LPR`;
- `DS-CMP-032 — Estado de sincronização`;
- `DS-CMP-033 — Decisão de acesso`;
- `DS-CMP-034 — Contribuição`.

---

# 3. Usuários e permissões

## 3.1 Operador de portaria

Poderá, conforme perfil:

- localizar pessoa ou autorização;
- consultar dados necessários;
- validar pessoa e veículo;
- registrar contribuição;
- negar;
- salvar sem liberar;
- validar e liberar;
- aplicar contingência autorizada;
- consultar resultado do atendimento.

## 3.2 Administrador

Poderá possuir todas as ações operacionais, mas o uso cotidiano de credencial administrativa na portaria deverá ser evitado.

## 3.3 Operador de caixa

Poderá registrar contribuição e executar ações de validação limitadas, conforme matriz granular.

## 3.4 Gestor e auditor

Por padrão, terão consulta ao histórico ou à decisão, não execução de liberação.

## 3.5 Permissões granulares mínimas

| Permissão conceitual | Finalidade |
|---|---|
| `validation.view` | abrir e consultar a validação |
| `validation.search` | pesquisar pessoa, autorização e veículo |
| `validation.view_sensitive` | visualizar documento, foto e contato |
| `validation.deny` | negar entrada |
| `validation.save_pending` | salvar sem liberar |
| `validation.release` | validar e solicitar liberação |
| `validation.override` | aplicar exceção operacional |
| `validation.contingency` | executar contingência |
| `contribution.record` | registrar contribuição |
| `vehicle.change_during_validation` | alterar vínculo ou veículo durante atendimento |
| `access.history.view` | consultar histórico |

Os nomes técnicos finais poderão mudar, mas a granularidade deverá ser preservada.

---

# 4. Pré-condições

Para iniciar a jornada:

- usuário autenticado;
- implantação selecionada;
- ponto de acesso identificado;
- direção definida como entrada;
- relógio e fuso disponíveis;
- permissões carregadas;
- situação das integrações conhecida;
- caixa identificado quando a contribuição estiver habilitada;
- tentativa ou atendimento possuir identificador único.

A indisponibilidade de equipamento ou internet não deverá impedir a abertura da tela, mas deverá alterar claramente as ações permitidas e o modo operacional.

---

# 5. Fluxo principal

```text
Abrir Validação de Entrada
        ↓
Identificar pessoa, autorização ou protocolo
        ↓
Carregar cadastro, vínculos, credenciais e alertas
        ↓
Validar veículo, quando houver
        ↓
Classificar contribuição, quando aplicável
        ↓
Registrar observações
        ↓
Revalidar condições críticas
        ├── Negar entrada
        ├── Salvar sem liberar
        └── Validar e liberar
                ↓
        Registrar decisão
                ↓
        Registrar contribuição
                ↓
        Enviar comando
                ↓
        Registrar confirmação ou falha
                ↓
        Apresentar resultado
```

---

# 6. Estados da jornada

```text
Iniciada
   ↓
Aguardando identificação
   ↓
Em validação
   ├── Pendente de correção
   ├── Pronta para decisão
   ├── Negada
   ├── Salva sem liberação
   └── Autorizada
          ↓
      Comando pendente
          ├── Abertura confirmada
          ├── Comando recusado
          ├── Falha técnica
          └── Confirmação desconhecida
```

“Autorizada” não significa “abertura confirmada”.

---

# 7. Layout de desktop

## 7.1 Estrutura

```text
┌──────────────┬─────────────────────────────────────────────────┐
│ Navegação    │ Cabeçalho: título, caixa, alertas e operador    │
│ lateral      ├─────────────────────────────────────────────────┤
│              │ 1. Identificação da pessoa                     │
│              ├─────────────────────────────────────────────────┤
│              │ 2. Veículo e leitura LPR                        │
│              ├─────────────────────────────────────────────────┤
│              │ 3. Contribuição / taxa de acesso                │
│              ├─────────────────────────────────────────────────┤
│              │ 4. Observações                                  │
│              ├──────────────┬────────────────┬─────────────────┤
│              │ Negar entrada│ Salvar sem     │ Validar e       │
│              │              │ liberar        │ liberar         │
└──────────────┴──────────────┴────────────────┴─────────────────┘
```

## 7.2 Hierarquia

As seções numeradas deverão:

- manter ordem;
- possuir título;
- indicar bloqueio ou conclusão;
- organizar dados em cartões;
- evitar navegação por abas que esconda alertas;
- permitir rolagem da página sem perder contexto.

## 7.3 Barra de decisão

As três ações finais deverão permanecer visíveis no término do fluxo. Fixação durante a rolagem poderá ser usada se:

- não encobrir conteúdo;
- respeitar teclado e zoom;
- informar por que uma ação está desabilitada;
- não gerar acionamento acidental.

---

# 8. Cabeçalho operacional

Deverá apresentar:

- título “Validação de Entrada”;
- subtítulo orientativo;
- ponto de acesso;
- data e hora;
- situação do caixa;
- operador;
- notificações;
- estado de conectividade ou integração quando crítico.

## 8.1 Caixa

Estados:

- aberto;
- fechado;
- em conferência;
- indisponível;
- não aplicável.

O nome do operador do caixa deverá ser apresentado quando necessário para evitar recebimento em caixa incorreto.

## 8.2 Ponto de acesso

O ponto não poderá ser inferido apenas pela estação sem confirmação configurada. A tela deverá exibir o ponto ao qual o comando será enviado.

---

# 9. Identificação inicial

## 9.1 Métodos

A pessoa poderá ser localizada por:

- face;
- documento;
- nome;
- protocolo;
- placa;
- QR Code;
- tag;
- código;
- seleção a partir de pré-cadastro.

Métodos disponíveis dependerão da configuração.

## 9.2 Estado sem pessoa

A tela deverá apresentar:

- campo de pesquisa;
- métodos disponíveis;
- instrução curta;
- leituras recentes autorizadas;
- estado das integrações;
- nenhum dado fictício.

## 9.3 Múltiplos resultados

Cada resultado deverá permitir distinguir:

- nome;
- foto autorizada;
- documento parcialmente mascarado;
- tipo de acesso;
- imóvel;
- responsável;
- vigência;
- status.

O sistema não deverá escolher automaticamente entre cadastros ambíguos.

## 9.4 Não localizado

Possíveis ações:

- refazer pesquisa;
- localizar pré-cadastro;
- iniciar cadastro ou atendimento permitido;
- registrar tentativa como não identificada;
- aplicar contingência autorizada.

A ação disponível dependerá de permissão e regra operacional.

---

# 10. Seção 1 — Identificação da pessoa

## 10.1 Composição

Conforme a referência:

- retrato;
- ação “Ver foto facial”;
- ação “Ver documento”;
- nome completo;
- tipo de acesso;
- CPF ou documento;
- data de nascimento;
- unidade ou imóvel;
- responsável;
- telefone;
- e-mail;
- status do cadastro;
- situação da validação;
- validade do acesso;
- acesso ao cadastro completo.

## 10.2 Status independentes

A interface deverá distinguir:

| Estado | Pergunta respondida |
|---|---|
| Cadastro | A pessoa está cadastrada e ativa? |
| Vínculo | Existe relação válida com imóvel, empresa ou responsável? |
| Autorização | Há permissão válida para este acesso? |
| Credencial | O meio apresentado está ativo e vigente? |
| Documento | O documento atende à validação exigida? |
| Face | A credencial facial está válida e sincronizada? |
| Evento | Qual foi o resultado desta tentativa? |

Não utilizar um único “Cadastro ativo” para representar todos os estados.

## 10.3 Alertas críticos

Devem aparecer antes da decisão:

- cadastro bloqueado;
- vínculo suspenso ou expirado;
- autorização ausente, rejeitada ou expirada;
- documento pendente ou inválido;
- face não sincronizada;
- pessoa em contexto de observação conforme política;
- responsável ou destino inconsistente.

## 10.4 Visualização de foto e documento

- abrir em modal ou drawer protegido;
- registrar acesso quando aplicável;
- impedir URL pública permanente;
- permitir zoom controlado;
- mascarar dados conforme perfil;
- devolver foco ao acionador;
- não oferecer download sem permissão específica.

## 10.5 Cadastro completo

“Ver cadastro completo” abre contexto de consulta. Alterações durante a validação:

- exigem permissão;
- não podem perder o atendimento;
- devem provocar revalidação;
- precisam ser auditadas;
- não podem liberar automaticamente.

---

# 11. Seção 2 — Veículo e LPR

## 11.1 Composição

Quando houver veículo:

- imagem capturada;
- momento da captura;
- placa lida;
- placa cadastrada;
- situação da placa;
- marca;
- modelo;
- cor;
- ano;
- proprietário ou responsável;
- vínculo;
- confiança;
- câmera;
- ponto de acesso;
- última leitura.

## 11.2 Resultado LPR

Estados:

- placa reconhecida e compatível;
- reconhecida sem cadastro;
- divergente;
- baixa confiança;
- ilegível;
- leitura pendente;
- equipamento indisponível;
- sem veículo.

## 11.3 Confiança

A confiança deverá:

- ser exibida com valor e interpretação;
- usar limiar configurado;
- não ser tratada como garantia;
- apresentar origem;
- impedir automação quando abaixo do limite aprovado.

## 11.4 Divergência

Quando placa lida e cadastrada divergirem:

- destaque de atenção ou perigo;
- exibição lado a lado;
- bloqueio de liberação automática;
- conferência pelo operador;
- motivo obrigatório para exceção;
- auditoria;
- preservação da imagem e leitura originais.

## 11.5 Alterar placa ou veículo

A ação observada na referência deverá:

- depender de permissão;
- abrir fluxo controlado;
- diferenciar correção cadastral de seleção de outro veículo;
- manter leitura original;
- registrar valores anterior e posterior;
- revalidar autorização;
- não sobrescrever evidência LPR.

## 11.6 Sem veículo

A seção permanece identificável e informa “Acesso sem veículo” ou “Nenhum veículo associado”, conforme origem. Não deverá simular leitura.

---

# 12. Seção 3 — Contribuição ou taxa de acesso

## 12.1 Classificação

Opções:

- contribui;
- não contribui;
- isento.

As regras que habilitam cada opção permanecem parametrizadas.

## 12.2 Contribui

Campos:

- valor;
- forma de pagamento;
- recebido de;
- observação;
- caixa;
- operador;
- resumo do pagamento.

## 12.3 Não contribui

Deverá registrar:

- classificação;
- motivo quando exigido;
- operador;
- data e hora.

“Não contribui” não deverá ser descrito como isenção se forem conceitos distintos.

## 12.4 Isento

Deverá registrar:

- regra ou motivo;
- responsável pela classificação;
- validade quando aplicável;
- operador.

## 12.5 Resumo

Pode conter:

- valor;
- desconto aprovado;
- total;
- forma;
- situação.

Desconto somente poderá aparecer se houver regra aprovada. A referência visual não constitui aprovação de política de desconto.

## 12.6 Caixa fechado

Se recebimento depender de caixa aberto:

- opção “contribui” poderá ser selecionada;
- registro financeiro não será concluído;
- ação de liberar deverá ser bloqueada ou encaminhada à contingência aprovada;
- mensagem deverá orientar abertura ou troca de operador;
- exceção dependerá de permissão e justificativa.

## 12.7 Relação com acesso

Pagamento:

- não cria autorização;
- não corrige vínculo;
- não substitui documento;
- não garante abertura;
- deve ser associado ao atendimento e evento.

---

# 13. Seção 4 — Observações

## 13.1 Finalidade

Registrar informação operacional necessária ao atendimento.

## 13.2 Regras

- limite inicial conforme referência: 200 caracteres, sujeito a confirmação;
- contador visível;
- autoria e data;
- proteção contra conteúdo indevido;
- histórico quando alterada;
- proibição de dados sensíveis sem finalidade;
- não aceitar HTML;
- não utilizar como substituto de motivo estruturado.

## 13.3 Informação de apoio

O painel “Informações importantes” poderá lembrar:

- conferir dados antes de liberar;
- alterações relevantes são auditadas;
- acesso será vinculado ao caixa quando aplicável;
- negativa exige motivo.

Textos deverão ser configurados e aprovados, não usados para esconder regras.

---

# 14. Pré-validação da decisão

Antes de habilitar “Validar e liberar”, o sistema deverá avaliar:

- pessoa localizada;
- cadastro;
- vínculo;
- vigência;
- autorização;
- credencial;
- ponto de acesso;
- horário e área;
- veículo e divergência;
- contribuição;
- caixa;
- integração;
- permissões do operador;
- atendimento ainda não concluído;
- idempotência.

O resultado deverá classificar condições como:

- válida;
- alerta não bloqueador;
- bloqueio;
- falha técnica;
- exige exceção.

---

# 15. Ação — Negar entrada

## 15.1 Aparência

- botão vermelho;
- rótulo “Negar entrada”;
- descrição curta;
- ícone de negativa;
- posição distinta da ação positiva.

## 15.2 Fluxo

1. operador seleciona negar;
2. modal apresenta pessoa, veículo e contexto;
3. operador escolhe motivo;
4. complemento é exigido conforme motivo;
5. sistema revalida o atendimento;
6. registra tentativa e decisão;
7. não envia comando;
8. apresenta confirmação;
9. oferece iniciar nova validação ou consultar evento.

## 15.3 Motivo

Motivos deverão ser estruturados e parametrizados. Texto livre complementa, não substitui, a categoria.

## 15.4 Resultado

Mensagem recomendada:

**Entrada negada e registrada. Nenhum comando de abertura foi enviado.**

---

# 16. Ação — Salvar sem liberar

## 16.1 Aparência

- botão âmbar;
- rótulo “Salvar sem liberar”;
- descrição “Salvar e continuar depois” ou texto aprovado.

## 16.2 Fluxo

1. validar dados mínimos do atendimento;
2. registrar estado pendente;
3. preservar pessoa, veículo, contribuição não confirmada e observações;
4. não registrar pagamento concluído;
5. não enviar comando;
6. apresentar protocolo ou identificador;
7. permitir retomada autorizada.

## 16.3 Retomada

Ao retomar:

- revalidar todos os estados;
- indicar alterações ocorridas;
- não confiar em autorização anterior;
- manter histórico das tentativas;
- impedir processamento duplicado.

## 16.4 Resultado

Mensagem recomendada:

**Atendimento salvo sem liberação. Nenhum comando de abertura foi enviado.**

---

# 17. Ação — Validar e liberar

## 17.1 Aparência

- botão verde;
- rótulo “Validar e liberar”;
- descrição “Registrar entrada e solicitar abertura”;
- estado de carregamento explícito.

O texto deve evitar afirmar abertura antes da confirmação.

## 17.2 Fluxo transacional

```text
Revalidar condições críticas
        ↓
Reservar identificador idempotente
        ↓
Registrar decisão autorizada
        ↓
Registrar contribuição, quando aplicável
        ↓
Solicitar comando ao adaptador
        ↓
Registrar retorno
        ├── Abertura confirmada
        ├── Comando recusado
        ├── Falha técnica
        └── Confirmação desconhecida
```

A ordem técnica definitiva será detalhada na arquitetura, preservando:

- ausência de pagamento duplicado;
- ausência de evento duplicado;
- rastreabilidade;
- reconciliação;
- distinção dos resultados.

## 17.3 Estados do botão

- disponível;
- desabilitado com motivo;
- revalidação;
- registrando decisão;
- registrando contribuição;
- enviando comando;
- aguardando confirmação;
- concluído;
- falhou.

## 17.4 Resultado confirmado

Mensagem:

**Entrada registrada e abertura confirmada pelo equipamento.**

Exibir:

- pessoa;
- ponto de acesso;
- horário;
- identificador do evento;
- contribuição;
- opção de nova validação.

## 17.5 Autorizada com falha

Mensagem:

**Entrada autorizada, mas a abertura não foi confirmada.**

Exibir:

- estado do comando;
- orientação de contingência;
- ação disponível conforme permissão;
- identificador do evento;
- proibição de novo envio indiscriminado.

## 17.6 Confirmação desconhecida

Mensagem:

**O comando foi enviado, mas o resultado ainda não foi confirmado.**

Não permitir repetir automaticamente sem consulta idempotente.

---

# 18. Liberação manual e contingência

## 18.1 Condições

Contingência somente estará disponível:

- a usuário autorizado;
- quando o cenário estiver previsto;
- no ponto de acesso correto;
- com justificativa;
- com registro de origem;
- por período ou tentativa delimitada.

## 18.2 Modal

Deverá informar:

- falha detectada;
- riscos;
- pessoa e veículo;
- ponto;
- procedimento;
- motivo;
- responsável;
- ação exata.

## 18.3 Resultado

O sistema deverá distinguir:

- contingência registrada;
- abertura manual confirmada pelo operador;
- resultado não confirmado;
- contingência cancelada.

Não simular resposta do equipamento.

## 18.4 Operação sem conexão

O comportamento depende da arquitetura e de `PEN-002`. A UX deverá suportar:

- indicação persistente de modo degradado;
- dados disponíveis e indisponíveis;
- origem e idade do cache;
- ações autorizadas;
- fila de sincronização;
- reconciliação posterior;
- prevenção de duplicidade.

---

# 19. Exceções e tratamentos

## 19.1 Pessoa bloqueada

- destaque crítico;
- motivo visível conforme permissão;
- “Validar e liberar” bloqueado;
- exceção somente se formalmente permitida.

## 19.2 Vínculo expirado

- cadastro pode permanecer ativo;
- vínculo aparece expirado;
- autorização derivada é inválida;
- liberação normal bloqueada.

## 19.3 Documento inválido

- mostrar situação e orientação;
- permitir correção autorizada;
- revalidar antes da decisão.

## 19.4 Face inadequada ou não sincronizada

- distinguir qualidade da foto de falha de sincronização;
- não declarar credencial facial válida;
- permitir outro método autorizado.

## 19.5 Placa divergente

- aplicar seção 11.4;
- exigir conferência;
- manter evidência original.

## 19.6 Caixa fechado

- aplicar seção 12.6;
- não criar movimento concluído indevido.

## 19.7 Equipamento indisponível

- informar antes da decisão;
- desabilitar fluxo automático quando necessário;
- oferecer contingência autorizada;
- registrar falha.

## 19.8 Queda durante processamento

Ao retornar:

- consultar estado pelo identificador idempotente;
- não repetir decisão ou pagamento sem confirmação;
- apresentar resultado conhecido;
- orientar reconciliação se permanecer incerto.

## 19.9 Sessão expirada

- impedir ação;
- preservar identificador seguro do atendimento;
- exigir nova autenticação;
- revalidar permissão;
- não liberar automaticamente após login.

## 19.10 Atualização concorrente

Se cadastro, vínculo ou autorização mudar durante a jornada:

- bloquear decisão antiga;
- indicar alteração;
- recarregar estados;
- preservar observação do operador;
- registrar tentativa.

---

# 20. Estados visuais

## 20.1 Carregamento

- app shell disponível;
- skeleton nas seções;
- texto do processo em decisões;
- layout estável.

## 20.2 Vazio

- nenhuma pessoa identificada;
- sem veículo;
- contribuição não aplicável;
- sem observação.

Cada caso possui mensagem própria.

## 20.3 Erro parcial

Uma seção com falha não deverá ser substituída por estado válido. A decisão final será bloqueada se a informação for crítica.

## 20.4 Dados desatualizados

Exibir:

- estado;
- horário;
- fonte;
- ação de atualizar;
- impacto na decisão.

## 20.5 Somente leitura

Atendimento concluído deverá abrir em modo de consulta, com decisão e trilha, sem repetir ações.

---

# 21. Responsividade

## 21.1 Desktop

Preservar a composição da referência:

- sidebar;
- seções em largura principal;
- pessoa com foto, dados e status;
- veículo com imagem, dados e LPR;
- contribuição em colunas;
- ações finais lado a lado.

## 21.2 Tablet

- sidebar recolhível;
- pessoa em duas colunas;
- status abaixo ou ao lado;
- veículo e LPR empilhados quando necessário;
- contribuição em duas colunas;
- ações finais adaptáveis.

## 21.3 Celular

Ordem:

1. alertas críticos;
2. pessoa e status;
3. veículo e LPR;
4. contribuição;
5. observações;
6. decisões.

Regras:

- uma coluna;
- imagens responsivas;
- dados em pares rótulo/valor;
- ações empilhadas;
- ação negativa separada;
- barra fixa somente sem encobrir conteúdo;
- modais em tela quase completa;
- nenhuma dependência de hover.

## 21.4 Operação recomendada

A jornada deve responder em celular, mas a operação principal de portaria poderá exigir terminal com viewport mínimo, a ser definido na matriz de compatibilidade.

---

# 22. Acessibilidade

A tela deverá:

- possuir título principal;
- utilizar cabeçalhos nas quatro seções;
- anunciar alertas críticos;
- manter foco visível;
- permitir toda decisão por teclado;
- fornecer rótulos persistentes;
- agrupar radios da contribuição;
- associar erros aos campos;
- não depender de cor;
- descrever imagens operacionais de forma contextual;
- permitir ampliar foto e documento;
- devolver foco ao fechar modal;
- anunciar mudança de etapa do processamento;
- evitar regiões vivas excessivas;
- respeitar redução de movimento;
- funcionar com zoom de 200%.

## 22.1 Ordem de foco

1. navegação de salto;
2. cabeçalho;
3. busca ou identificação;
4. pessoa;
5. veículo;
6. contribuição;
7. observações;
8. negar;
9. salvar;
10. validar e liberar.

A ordem poderá mudar no responsivo para acompanhar a ordem visual.

---

# 23. Conteúdo e microcopy

## 23.1 Títulos

- Validação de Entrada;
- Identificação da Pessoa;
- Veículo;
- Contribuição / Taxa de Acesso;
- Observações;
- Status do Cadastro;
- Validade do Acesso;
- Leitura da Placa (LPR);
- Resumo do Pagamento.

## 23.2 Estados recomendados

| Estado | Rótulo |
|---|---|
| Cadastro válido | Cadastro ativo |
| Vínculo válido | Vínculo ativo |
| Face pronta | Facial sincronizada |
| Documento aprovado | Documento validado |
| Autorização válida | Acesso autorizado |
| Placa compatível | Placa reconhecida |
| Comando confirmado | Abertura confirmada |
| Comando sem retorno | Confirmação pendente |

“Acesso liberado” deverá ser reservado ao estado cuja definição operacional esteja aprovada.

## 23.3 Mensagens de bloqueio

| Situação | Mensagem |
|---|---|
| Vínculo expirado | O vínculo expirou e não autoriza esta entrada. |
| Placa divergente | A placa capturada diverge da placa cadastrada. Confira os dados. |
| Caixa fechado | Abra um caixa autorizado antes de registrar o recebimento. |
| Sem permissão | Você não possui permissão para executar esta ação. |
| Equipamento indisponível | O equipamento está indisponível. Use apenas a contingência autorizada. |
| Sessão expirada | Sua sessão expirou. Entre novamente e revalide o atendimento. |

---

# 24. Segurança e privacidade

- autorização aplicada no servidor;
- segregação por implantação;
- documentos e fotos protegidos;
- dados sensíveis mascarados por perfil;
- nenhuma credencial biométrica exposta;
- nenhuma URL permanente de arquivo;
- logs sem conteúdo sensível desnecessário;
- observações sem HTML;
- ações críticas protegidas contra repetição;
- mudança de ponto de acesso revalida o atendimento;
- decisão registra operador, origem e contexto;
- acesso excepcional registra justificativa;
- consulta de documento e exportação auditadas quando aplicável;
- estado Livewire validado no servidor.

---

# 25. Auditoria

## 25.1 Atendimento

Registrar:

- identificador;
- início;
- operador;
- terminal;
- implantação;
- ponto;
- método de identificação;
- pessoa;
- autorização;
- veículo;
- leitura LPR;
- contribuição;
- observação;
- decisão;
- comandos;
- retornos;
- contingência;
- encerramento.

## 25.2 Alterações

Correções durante a jornada deverão registrar valores anterior e posterior.

## 25.3 Evidência técnica

Falha externa deverá conter:

- adaptador;
- equipamento;
- operação;
- horário;
- identificador de correlação;
- resultado sanitizado;
- retentativa;
- estado final.

---

# 26. Desempenho e observabilidade

## 26.1 Metas

Metas numéricas permanecem pendentes. A UX exige:

- retorno rápido na identificação;
- carregamento progressivo;
- resposta visível imediata ao clique;
- nenhuma duplicação por ansiedade do operador;
- atualização independente de seções quando seguro.

## 26.2 Monitoramento

Medir:

- tempo de identificação;
- tempo até dados completos;
- tempo de decisão;
- tempo de comando;
- tempo de confirmação;
- falhas por equipamento;
- divergências LPR;
- retomadas;
- contingências;
- ações duplicadas evitadas.

## 26.3 Timeout

Timeout não equivale a falha física. A interface deverá usar “resultado não confirmado” até reconciliação.

---

# 27. Diretrizes para Blade e Livewire

## 27.1 Componentização sugerida

```text
EntryValidation
├── PersonIdentification
├── PersonValidationSummary
├── VehicleLprComparison
├── ContributionForm
├── AccessNotes
├── AccessDecisionBar
├── DenialDialog
├── ContingencyDialog
└── DecisionResult
```

## 27.2 Estado

O componente coordenador deverá possuir identificador do atendimento e controlar transições válidas. Componentes filhos não poderão concluir isoladamente a liberação.

## 27.3 Regras no servidor

Livewire deverá:

- validar a cada ação;
- aplicar Policies ou Gates;
- usar transações quando aplicável;
- emitir comando por serviço desacoplado;
- usar idempotência;
- preservar resultado;
- não confiar em campos ocultos;
- tratar sessão e concorrência.

## 27.4 JavaScript

Permitido apenas para:

- captura autorizada;
- foco;
- modal;
- recursos do navegador;
- feedback visual pontual.

Regras de autorização e decisão não residirão no navegador.

---

# 28. Contrato funcional de dados

## 28.1 Contexto

- atendimento;
- implantação;
- terminal;
- ponto;
- direção;
- operador;
- caixa;
- horário;
- conectividade.

## 28.2 Pessoa

- identificador interno;
- dados autorizados para exibição;
- foto protegida;
- cadastro;
- vínculos;
- autorização;
- credenciais;
- alertas;
- vigência.

## 28.3 Veículo

- veículo;
- placa cadastrada normalizada;
- leitura original;
- imagem;
- confiança;
- câmera;
- vínculo;
- situação;
- divergência.

## 28.4 Contribuição

- classificação;
- regra;
- valor;
- desconto autorizado;
- total;
- forma;
- pagador;
- caixa;
- estado do movimento.

## 28.5 Decisão

- tipo;
- motivos;
- justificativa;
- exceção;
- evento;
- comando;
- confirmação;
- estado final.

---

# 29. Cenários de teste

## 29.1 Fluxos principais

- morador válido sem veículo;
- morador válido com LPR compatível;
- visitante aprovado;
- prestador dentro da vigência;
- contribuição em dinheiro com caixa aberto;
- isento;
- negar com motivo;
- salvar e retomar;
- validar com confirmação.

## 29.2 Regras e bloqueios

- cadastro bloqueado;
- vínculo expirado;
- autorização futura;
- documento pendente;
- face não sincronizada;
- horário não permitido;
- área não autorizada;
- placa divergente;
- baixa confiança;
- caixa fechado;
- operador sem permissão.

## 29.3 Integrações

- equipamento disponível;
- indisponível antes da decisão;
- falha ao enviar;
- timeout;
- confirmação tardia;
- resposta duplicada;
- reenvio do operador;
- queda de conexão;
- retomada e reconciliação.

## 29.4 Financeiro operacional

- valor padrão;
- valor inválido;
- forma indisponível;
- cancelamento antes do comando;
- falha depois do registro;
- repetição da ação;
- diferença entre caixa do operador e terminal.

## 29.5 Responsividade e acessibilidade

- desktop de referência;
- tablet;
- celular;
- teclado;
- leitor de tela;
- zoom;
- redução de movimento;
- modal;
- mensagens;
- foco após resultado.

## 29.6 Segurança

- acesso cruzado;
- documento sem permissão;
- alteração de veículo sem permissão;
- contingência sem permissão;
- requisição Livewire adulterada;
- sessão expirada;
- repetição de identificador;
- acesso direto a arquivo.

---

# 30. Critérios de aceite

## 30.1 Funcionais

**CA-UXV-001:** a tela concentra pessoa, veículo, contribuição, observações e decisão.  
**CA-UXV-002:** cadastro, vínculo, autorização, credencial e evento são apresentados separadamente.  
**CA-UXV-003:** bloqueios e divergências impedem liberação normal.  
**CA-UXV-004:** negativa exige motivo e registra tentativa.  
**CA-UXV-005:** salvar sem liberar não envia comando nem conclui pagamento.  
**CA-UXV-006:** validar e liberar revalida condições críticas.  
**CA-UXV-007:** contribuição é vinculada ao atendimento e ao caixa.  
**CA-UXV-008:** placa original, confiança e divergência são preservadas.  
**CA-UXV-009:** toda decisão gera evento auditável.  

## 30.2 Integração e contingência

**CA-UXV-010:** autorização, comando e confirmação são estados distintos.  
**CA-UXV-011:** timeout não é apresentado como sucesso ou falha física confirmada.  
**CA-UXV-012:** reenvio não duplica evento, pagamento ou liberação.  
**CA-UXV-013:** falha de equipamento apresenta orientação de contingência.  
**CA-UXV-014:** contingência exige permissão e justificativa.  
**CA-UXV-015:** reconciliação atualiza o estado sem apagar o histórico.  

## 30.3 Visuais

**CA-UXV-016:** as quatro seções e as três decisões preservam a composição da referência.  
**CA-UXV-017:** verde, âmbar e vermelho mantêm seus significados sem depender apenas de cor.  
**CA-UXV-018:** alertas críticos possuem destaque imediato.  
**CA-UXV-019:** imagens e dados mantêm proporção, hierarquia e legibilidade.  
**CA-UXV-020:** dados ilustrativos não são incorporados como valores fixos.  

## 30.4 Responsivos e acessíveis

**CA-UXV-021:** a jornada funciona nos viewports homologados.  
**CA-UXV-022:** a ordem móvel preserva prioridade operacional.  
**CA-UXV-023:** toda ação funciona por teclado.  
**CA-UXV-024:** foco e anúncios dinâmicos são gerenciados.  
**CA-UXV-025:** contraste e áreas interativas atendem ao Design System.  

## 30.5 Segurança e desempenho

**CA-UXV-026:** permissões são aplicadas no servidor.  
**CA-UXV-027:** arquivos e dados sensíveis permanecem protegidos.  
**CA-UXV-028:** dados são segregados por implantação.  
**CA-UXV-029:** metas de resposta serão atendidas após definição formal.  
**CA-UXV-030:** falhas técnicas são observáveis e correlacionáveis.  

---

# 31. Pendências abertas

| PEN-UXV | Pendência | Impacto | Encaminhamento |
|---|---|---|---|
| PEN-UXV-001 | Confirmar fabricante, protocolo e resposta da controladora | Estado do comando e homologação | `PEN-001` do Product Book e ADR |
| PEN-UXV-002 | Definir contingência sem internet ou equipamento | Ações em modo degradado | `PEN-002` e arquitetura |
| PEN-UXV-003 | Definir regras, valores e formas da contribuição | Campos, bloqueios e caixa | `PEN-003` e regras de negócio |
| PEN-UXV-004 | Definir política de biometria e documentos | Exibição e captura | `PEN-005` e LGPD |
| PEN-UXV-005 | Confirmar pontos de acesso e terminais | Cabeçalho, comando e testes | `PEN-020` |
| PEN-UXV-006 | Aprovar métodos de identificação por ponto | Estado inicial | Operação e equipamentos |
| PEN-UXV-007 | Definir motivos de negativa | Modal e relatórios | Regras de negócio |
| PEN-UXV-008 | Definir motivos e níveis de exceção | Contingência e auditoria | Segurança e operação |
| PEN-UXV-009 | Definir limiar de confiança LPR | Automação e bloqueio | Equipamento e regras |
| PEN-UXV-010 | Definir se alteração de veículo será permitida na validação | Integridade cadastral | Produto e permissões |
| PEN-UXV-011 | Definir limite final de observações | Componente e retenção | Produto |
| PEN-UXV-012 | Definir política de descontos | Resumo financeiro | Regras de contribuição |
| PEN-UXV-013 | Definir metas de tempo de resposta e confirmação | Homologação | RNF e infraestrutura |
| PEN-UXV-014 | Definir comportamento de confirmação tardia | Reconciliação | Arquitetura |
| PEN-UXV-015 | Definir prazo e expiração do atendimento pendente | Retomada | Regra de negócio |
| PEN-UXV-016 | Definir completude da captura de saída | Histórico apresentado | Operação |
| PEN-UXV-017 | Aprovar protótipos por viewport | Aceite visual definitivo | Prototipação |
| PEN-UXV-018 | Validar terminologia “taxa” ou “contribuição” | Linguagem e jurídico | Product Owner |
| PEN-UXV-019 | Definir se a abertura física pode ser confirmada automaticamente | Mensagem final | Protocolo do equipamento |
| PEN-UXV-020 | Definir política de leitura e mascaramento de dados por perfil | Privacidade | Segurança e LGPD |

---

# 32. Decisões consolidadas

Ficam consolidados:

- jornada única com quatro seções e três decisões;
- pessoa, vínculo, autorização, credencial e evento apresentados separadamente;
- veículo e LPR comparados sem sobrescrever evidência;
- contribuição separada da autorização;
- negativa sempre registrada;
- salvar sem liberar não envia comando;
- validar e liberar revalida condições;
- autorização, envio e confirmação são estados distintos;
- falha externa não é apresentada como sucesso;
- contingência depende de permissão e justificativa;
- idempotência obrigatória;
- dados ilustrativos da referência não serão fixos;
- layout responsivo preserva a ordem operacional;
- Blade e Livewire formam a base da implementação futura;
- regras, permissões e decisões permanecem no servidor.

## 32.1 Registro de aprovação

| Papel | Nome | Decisão | Data | Observações |
|---|---|---|---|---|
| Product Owner | Vinicius Velasco de Azevedo | Aprovado | 28/07/2026 | UX/UI da Validação de Entrada aprovada como referência para prototipação, testes e implementação futura |
| Soluções do Vale | Representante designado | Ciente | 28/07/2026 | Aprovação registrada no repositório oficial |

---

# 33. Próximo documento

Após a aprovação desta especificação, deverá ser produzido:

**`docs/006_UX_UI_PRE_CADASTRO.md`**

O próximo documento deverá detalhar:

- acesso público e convite;
- dados pessoais;
- endereço;
- documento;
- selfie;
- veículo;
- confirmação;
- protocolo;
- análise pela portaria;
- aprovação, rejeição e correção;
- privacidade e responsividade móvel.

---

## Situação do documento

Esta especificação consolida o comportamento funcional, visual, responsivo e operacional da Validação de Entrada e encontra-se **aprovada**. As pendências de integração, contingência, contribuição, biometria e prototipação permanecem rastreadas e deverão ser resolvidas antes da implementação definitiva dos elementos afetados, sem invalidar esta aprovação documental.
