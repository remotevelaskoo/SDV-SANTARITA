# SDV ACCESS — UX/UI DE RELATÓRIOS
## Consultas, filtros, totais e exportações autorizadas

**Documento:** SDV-UXR-014
**Versão:** 1.0.0
**Status:** Implementado para homologação
**Produto:** SDV Access — Implantação Santa Rita
**Responsável pelo produto:** Vinicius Velasco de Azevedo
**Data:** Agosto de 2026

---

## Controle de versões

| Versão | Data | Responsável | Alteração |
|---|---|---|---|
| 1.0.0 | 12/08/2026 | Soluções do Vale | Especificação executável da P16 — Relatórios |

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

PDF, relatórios agendados, arquivos persistidos, indicadores avançados e exportações assíncronas permanecem fora desta entrega.

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

# 9. Pendências

| Identificador | Pendência | Dependência |
|---|---|---|
| PEN-UXR-001 | Auditoria persistente de cada exportação | P22 |
| PEN-UXR-002 | Definir padrão de PDF e impressão | Produto e Design System |
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
