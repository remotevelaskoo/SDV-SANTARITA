# SDV ACCESS — UX/UI DE RELATÓRIOS
## Consultas, filtros, totais e exportações autorizadas

**Documento:** SDV-UXR-014
**Versão:** 1.1.0
**Status:** Implementado parcialmente; fechamento detalhado por turno implementado (AJ-008, §5.7), aguardando revisão
**Produto:** SDV Access — Implantação Santa Rita
**Responsável pelo produto:** Vinicius Velasco de Azevedo
**Data:** Agosto de 2026

---

## Controle de versões

| Versão | Data | Responsável | Alteração |
|---|---|---|---|
| 1.0.0 | 12/08/2026 | Soluções do Vale | Especificação executável da P16 — Relatórios |
| 1.1.0 | 28/10/2026 | Soluções do Vale | Relatório de fechamento por turno, conciliação de contribuições, pessoas pagantes e tickets |

# 1. Objetivo

Este documento define a primeira entrega da P16, sem substituir o histórico de acessos, o caixa, o dashboard ou a auditoria. Relatórios são projeções somente de leitura sobre dados operacionais reais.

# 2. Escopo do MVP

O módulo disponibilizará:

- relatório de acessos;
- relatório de movimentações de caixa;
- filtros por período e atributos próprios de cada relatório;
- totais calculados sobre o mesmo recorte exibido;
- exportação CSV autorizada;
- limite visual de 500 linhas, sem limitar o arquivo exportado;
- segregação automática por implantação.

PDF genérico, relatórios agendados, arquivos persistidos, indicadores avançados e exportações assíncronas permanecem fora desta entrega. A impressão ou geração de PDF do comprovante de fechamento de um turno específico passa a integrar o escopo por ser documento operacional da troca de caixa, e não uma exportação gerencial genérica.

# 3. Perfis e escopo

| Permissão | Escopo |
|---|---|
| `relatorios.proprio.consultar` | somente registros cujo operador seja o usuário autenticado |
| `relatorios.consolidado.consultar` | todos os registros da implantação ativa |

Porteiro/Caixa utilizará a visão própria. Gestor, Auditor e Administrador poderão utilizar a visão consolidada conforme o catálogo aprovado na P20. A autorização deverá ser verificada na rota, no componente e na consulta.

# 4. Relatório de acessos

## 4.1 Fonte

`historico_acessos`, com relacionamentos autorizados de pessoa, imóvel, veículo e operador.

## 4.2 Filtros

- data inicial e final;
- busca por pessoa, protocolo ou placa;
- operador, somente na visão consolidada;
- resultado;
- entrada ou saída;
- ponto de acesso;
- imóvel.

## 4.3 Totais

- registros;
- liberados;
- negados;
- pendentes.

# 5. Relatório de caixa

## 5.1 Fonte

`caixa_movimentacoes`, associada ao turno e ao operador reais.

## 5.2 Filtros

- data inicial e final;
- busca por descrição ou protocolo;
- operador, somente na visão consolidada;
- tipo de movimentação;
- forma de pagamento.

## 5.3 Totais

- quantidade de movimentações;
- entradas;
- saídas e estornos;
- saldo líquido movimentado.

Saldo movimentado não substitui o saldo esperado do turno, pois o saldo inicial pertence ao relatório de fechamento do caixa.

## 5.4 Relatório de fechamento por turno

Cada operador deverá possuir seu próprio turno de caixa. Na troca de operador, o caixa atual é encerrado antes da abertura do próximo, e o fechamento gera um relatório identificado e imutavelmente associado a `caixa_turnos.id`.

O cabeçalho deverá apresentar:

- número/identificador do fechamento;
- implantação, posto e terminal de operação;
- operador responsável;
- abertura e encerramento, com data e hora;
- saldo inicial;
- total de contribuições pagas;
- outras entradas manuais;
- saídas;
- estornos e cancelamentos;
- total esperado;
- valor contado/informado;
- diferença;
- situação `conferido` ou `diferença`;
- observação e justificativa obrigatória quando houver diferença.

O detalhamento de pessoas pagantes deverá conter uma linha por contribuição efetivamente vinculada ao turno:

- data e hora do atendimento;
- nome da pessoa;
- documento mascarado por padrão;
- protocolo do acesso;
- número do ticket, preservando zeros à esquerda;
- valor;
- forma de pagamento;
- operador que registrou;
- ponto de acesso, quando disponível;
- indicação de ticket repetido ou posteriormente corrigido.

Não contribuintes e isentos não entram no total financeiro, mas o relatório deverá apresentar suas quantidades em resumo separado para permitir conferência operacional. A contribuição continua independente da autorização de entrada.

## 5.5 Conciliação obrigatória

Uma contribuição classificada como `pago` deverá, na mesma transação do atendimento:

1. estar vinculada ao caixa aberto do operador autenticado;
2. participar do total de entradas e do saldo esperado desse turno;
3. aparecer no detalhamento do fechamento com pessoa, protocolo e ticket;
4. não gerar contagem dupla caso também exista projeção em `caixa_movimentacoes`.

O cálculo financeiro terá uma fonte canônica claramente definida. A implementação poderá projetar cada contribuição paga como movimentação de entrada ou somar `contribuicoes` separadamente, mas nunca aplicar as duas fórmulas simultaneamente. Testes de reconciliação deverão provar que o total das linhas pagas é igual ao total de contribuições do cabeçalho.

O caixa corrente deverá ser localizado por implantação **e operador autenticado**, nunca apenas pelo último caixa aberto da implantação. A política inicial permitirá no máximo um caixa aberto por operador e posto/terminal operacional; tentativa concorrente deverá ser bloqueada no banco e tratada na interface.

## 5.6 Entrega à gestão

Após confirmar o encerramento, o sistema deverá abrir o resumo do fechamento e oferecer:

- `Imprimir fechamento` em formato limpo, sem menu lateral;
- `Baixar fechamento em PDF`, quando o gerador homologado estiver disponível;
- `Exportar itens em CSV` conforme as permissões existentes;
- consulta posterior pelo histórico de caixas.

O fechamento continuará válido mesmo que a impressão falhe. A gestão, auditoria e administração poderão consultar todos os fechamentos da implantação; Porteiro/Caixa poderá consultar apenas os próprios. A abertura, o fechamento, a visualização consolidada e cada exportação serão auditados.

Correções posteriores de ticket ou outras informações não apagarão o valor original. O relatório consultado deverá sinalizar a correção e permitir alcançar o respectivo histórico de auditoria; qualquer reemissão deverá informar a data de emissão.

## 5.7 Situação da implementação (AJ-008)

Implementado em 29/10/2026 na branch `claude/aj-008-fechamento-caixa`, ainda sem merge na `main`.

**Fórmula canônica.** `contribuicoes` é a única fonte das contribuições pagas; `caixa_movimentacoes` guarda somente lançamentos manuais. Contribuição paga não é projetada como movimentação.

```
esperado = saldo inicial
         + contribuições pagas vinculadas ao turno     (contribuicoes, situação "pago")
         + outras entradas manuais                     (caixa_movimentacoes "entrada")
         − saídas                                      (caixa_movimentacoes "saida")
         − estornos                                    (caixa_movimentacoes "estorno")
diferença = contado − esperado
```

Uma entrada manual que repita o protocolo de uma contribuição paga do mesmo turno é desconsiderada em "outras entradas", e o atendimento continua aceitando uma única contribuição (`historico_acesso_id` único). Assim nenhum pagamento é somado duas vezes.

**Caixa do operador.**
- O caixa corrente é sempre o do operador autenticado na implantação ativa (`CaixasOperador::abertoDo`), e nunca o último aberto da implantação. Isso vale para Caixa, Portaria e Validação de Entrada.
- A abertura registra o posto assumido no turno operacional (`posto_id`); sem posto, usa o terminal padrão.
- Índices únicos parciais no banco garantem no máximo um caixa aberto por operador e um por posto. A tentativa concorrente vira mensagem na tela.
- Por causa do índice por posto, na troca de operador o caixa do posto precisa ser fechado antes de o próximo abrir.

**Contribuição paga.**
- É gravada na mesma transação do atendimento, com bloqueio do caixa do operador.
- Sem caixa aberto, o pagamento é recusado e o atendimento também não é gravado.
- Não contribuinte e isento continuam sendo registrados sem caixa e entram no turno só como quantidade.

**Fechamento.**
- Exige o total contado; diferença exige justificativa de 10 a 500 caracteres.
- Grava no próprio turno o retrato dos totais (contribuições, outras entradas, saídas, estornos, esperado, contado, diferença e quantidades de pagantes, isentos e não contribuintes) e um número `FCX-AAAAMMDD-XXXXX`.
- Depois de fechado, o turno não é editado nem excluído. Movimentação só entra em caixa aberto e não é editada nem excluída. Valor, forma, situação e caixa da contribuição não são reescritos.

**Entrega à gestão.**
- Ao fechar, abre `/caixa/fechamentos/{id}` com o resumo e o detalhamento das pessoas pagantes: horário, pessoa, documento mascarado, protocolo, ticket, valor, forma, operador e ponto. Ticket repetido e ticket corrigido (com data e valor anterior) aparecem sinalizados.
- O resumo oferece `Imprimir fechamento`, que abre `/caixa/fechamentos/{id}/imprimir` em versão limpa, sem menu lateral, com data de emissão e campos de assinatura, e `Exportar itens em CSV`.
- `/caixa/fechamentos` lista os fechamentos: Porteiro/Caixa vê os próprios; quem tem `caixa.consolidado.consultar` (Gestor, Auditor e Administrador) vê todos da implantação.

**Auditoria.**
- Ações registradas: `caixa_aberto`, `caixa_fechado`, `caixa_diferenca_registrada` (com a justificativa), `consultou_fechamentos_consolidados`, `consultou_fechamento_caixa` (fechamento de outro operador), `imprimiu_fechamento_caixa` e `exportou_fechamento_caixa`.

**Fechamentos antigos.** Fechamentos anteriores ao AJ-008 não têm os totais por categoria; o resumo avisa isso em vez de mostrar valores zerados.

**Pendências.**
- PDF homologado (PEN-UXR-002).
- O relatório de caixa da P16 (§5.1) continua lendo só `caixa_movimentacoes`; incluir as contribuições pagas nele é uma decisão separada.
- Confirmar com a operação a regra de um caixa por posto quando dois operadores dividirem o mesmo posto.

Cobertura em `tests/Feature/Aj008/FechamentoCaixaTest.php`.

# 6. Exportação

A exportação inicial será CSV UTF-8, separado por ponto e vírgula, compatível com planilhas utilizadas na operação brasileira.

O arquivo deverá:

- usar exatamente os filtros e o escopo da tela;
- omitir CPF integral, documento, imagem e outros dados sensíveis desnecessários;
- ser gerado somente após nova verificação de permissão;
- não ser persistido publicamente;
- possuir nome com tipo e período do relatório.

A trilha genérica de auditoria da exportação será incorporada pela P22. Até essa entrega, não haverá histórico permanente de arquivos exportados nem geração assíncrona.

# 7. Estados da interface

- carregando;
- pronto;
- vazio;
- sem permissão;
- erro de consulta;
- exportação em andamento;
- falha de exportação.

A ausência de registros não será apresentada como erro.

# 8. Critérios de aceite

**CA-UXR-001:** usuário sem permissão não acessa a rota.

**CA-UXR-002:** visão própria não apresenta dados de outro operador.

**CA-UXR-003:** visão consolidada permanece limitada à implantação ativa.

**CA-UXR-004:** filtros alteram linhas e totais no mesmo recorte.

**CA-UXR-005:** relatório de acessos reconcilia com `historico_acessos`.

**CA-UXR-006:** relatório de caixa reconcilia com `caixa_movimentacoes`.

**CA-UXR-007:** CSV respeita filtros e permissões.

**CA-UXR-008:** exportação não contém documento integral ou imagem.

**CA-UXR-009:** tela funciona em desktop e celular.

**CA-UXR-010:** nenhum relatório altera entidade operacional.

**CA-UXR-011:** cada turno fechado possui relatório próprio com operador, período, saldos, totais, diferença e situação.

**CA-UXR-012:** o total de contribuições do fechamento reconcilia exatamente com as linhas de pessoas pagantes vinculadas ao turno.

**CA-UXR-013:** cada pessoa pagante apresenta protocolo, ticket, valor e forma de pagamento, com documento mascarado.

**CA-UXR-014:** não contribuinte e isento não alteram o saldo, mas aparecem como quantidades no resumo operacional.

**CA-UXR-015:** o caixa aberto usado na Validação de Entrada pertence ao operador autenticado e à implantação ativa.

**CA-UXR-016:** diferença exige justificativa antes do encerramento.

**CA-UXR-017:** fechamento pode ser impresso e consultado novamente sem alterar seus dados operacionais.

**CA-UXR-018:** contribuição paga e movimentação financeira não são somadas duas vezes.

# 9. Pendências

| Identificador | Pendência | Dependência |
|---|---|---|
| PEN-UXR-001 | Auditoria persistente de cada exportação | P22 |
| PEN-UXR-002 | Homologar o padrão visual do PDF; impressão limpa do fechamento foi aprovada como requisito operacional | Produto e Design System |
| PEN-UXR-003 | Relatórios de cadastros, equipamentos e auditoria | Dados reais dos módulos correspondentes |
| PEN-UXR-004 | Exportações pesadas, assíncronas e com expiração | Filas, S3 e política de retenção |
| PEN-UXR-005 | Fórmulas e metas dos indicadores gerenciais | `PEN-RNG-021` e linha de base |

# 10. Rastreabilidade

- `RF-020`;
- `RNF-017`;
- `RN-046` a `RN-049`;
- Product Book Parte 02, seção 12;
- Product Book Parte 03, `MOD-011`, `BL-019`, `HU-031`, `HU-032` e `HOM-011`;
- ADR-001, ADR-002, ADR-004, ADR-005 e ADR-006.
