# SDV Access — Liberação manual operacional no Modo Portaria

**Documento:** SDV-OPE-017
**Versão:** 1.0.0
**Status:** Implementado e desligado por padrão (`SDV_INTEGRACAO_LIBERACAO_OPERACIONAL=false`)
**Data:** 10/10/2026
**Decisão de origem:** [ADR-017](ADR/ADR-017_LIBERACAO_MANUAL_OPERACIONAL_E_TESTE_TECNICO_DO_RELE.md)
**Relacionados:** [SDV-INT-016](016_HOMOLOGACAO_FACIAL_HIKVISION.md) (teste do relé), ADR-016

## 1. O que é

A área **Liberação manual** do Modo Portaria permite que um operador autorizado acione o relé
do terminal de um ponto do seu posto para liberar uma entrada ou saída, sempre com pessoa (ou
ocorrência excepcional), motivo, confirmação, evento de acesso e auditoria.

Não confundir com o **Testar relé** de Gestão › Equipamentos: esse é técnico, de bancada, não
gera evento de acesso e usa outra permissão e outra configuração (ADR-017 §3).

## 2. Permissões

| Permissão | Uso | Concedida por padrão |
|---|---|---|
| `acessos.liberar-manualmente` | liberação manual de pessoa cadastrada | Administrador |
| `acessos.liberar-excepcionalmente` | liberação excepcional sem cadastro | Administrador |
| `integracoes.gerenciar` | cadastro de postos, pontos atendidos e designação de operadores | Administrador |
| `dados-sensiveis.revelar` | ver o documento sem máscara (a revelação é auditada) | conforme perfil |
| `arquivos.sensiveis.visualizar` | abrir a foto/documento protegidos da pessoa | conforme perfil |
| `equipamentos.liberar-acesso` | somente o teste técnico do relé; **não** libera acesso | Administrador |

Para operar, conceda `acessos.liberar-manualmente` ao perfil de portaria em **Gestão › Perfis e
permissões**, depois da aprovação formal.

## 3. Postos e operadores

Em **Gestão › Postos e operadores**:

1. cadastre o posto (ex.: `GUARITA-01`, "Guarita Principal");
2. vincule os pontos de acesso que o posto atende (o terminal de cada ponto passa a ser o
   equipamento permitido);
3. designe os operadores do posto. Para trocar de função, encerre a designação; o histórico
   fica preservado.

Tabelas: `postos`, `posto_pontos_acesso` e `operador_postos` (vínculos com início e fim).

## 4. Requisitos para um equipamento aparecer

Só aparecem no seletor terminais que cumprem tudo (ADR-017 §3.2): ponto do posto do operador,
terminal e ponto ativos, online, certificado confiável, abertura remota homologada para o
firmware atual, relé homologado (SDV-INT-016 §14) e direção compatível. Os demais não aparecem;
se nenhum cumprir, a tela informa.

Hoje a facial de homologação está na bancada (ponto do tipo `bancada`, nunca ativado), portanto
não aparece: é preciso vinculá-la ao ponto real (cancela ou catraca) e ativá-la depois da
validação do contato seco.

## 5. Fluxo operacional

1. Modo Portaria › **Liberação manual**. O posto vem selecionado se o operador tiver um só.
2. **Pessoa cadastrada:** busque por nome ou documento e selecione. A tela mostra nome, iniciais,
   foto e documento protegidos (abertos sob demanda e auditados), documento sem máscara para
   quem pode revelar, perfil, imóvel e vínculo, autorização (vínculo vigente) e validade e
   restrições. Com restrição, a liberação normal é bloqueada.
3. **Liberação excepcional** (permissão própria): informe quem é ou a ocorrência e a
   justificativa (mínimo 20 caracteres).
4. Escolha a direção e o ponto/equipamento. Com um só compatível, ele vem selecionado e visível,
   com o estado online/offline.
5. Informe o motivo e clique em **Revisar liberação**.
6. Confirme: "Você está prestes a acionar Facial Entrada Principal, no ponto Entrada Principal.
   Confirma a liberação?".
7. O resultado aparece com o **protocolo**: solicitada, enviada, aceita pelo terminal, recusada,
   confirmação desconhecida, falha, cancelada ou expirada. "Aceita pelo terminal" **não**
   confirma a passagem física.
8. Em "confirmação desconhecida", não repita às cegas: confira o ponto.

## 6. Registros

**Evento de acesso** (`historico_acessos`): `resultado = liberado` (decisão da portaria),
`resultado_tecnico` (efeito no equipamento), `origem = liberacao_manual`, pessoa, imóvel,
operador, posto, equipamento, ponto, direção, motivo, data e hora, protocolo, correlation ID,
chave idempotente e, quando excepcional, identificação e justificativa.

**Auditoria:** `liberacao_manual_solicitada` ou `liberacao_manual_excepcional_solicitada`
(restrita), `integracao_abertura_remota` (resultado técnico, latência, chave e correlation ID),
`revelou_dado_sensivel` quando o documento é exibido sem máscara e os eventos de postos
(`posto_cadastrado`, `posto_ponto_vinculado`, `operador_designado_ao_posto` etc.).

Nunca são registrados senha técnica, endereço IP, imagem ou template biométrico.

## 7. Configuração

| Variável | Padrão | Efeito |
|---|---|---|
| `SDV_INTEGRACAO_LIBERACAO_OPERACIONAL` | `false` | liga a liberação manual; revalidada no envio do comando |
| `SDV_INTEGRACAO_TESTE_RELE` | `false` | teste técnico de bancada; independente |
| `SDV_INTEGRACAO_ABERTURA_REMOTA` | `false` | abertura automática; independente |

## 8. Testes automatizados

`tests/Feature/Integracoes/LiberacaoManualTest.php`: liberação do equipamento do próprio posto
com evento e auditoria; nome amigável e seleção por direção; equipamento de outro posto e de
outra implantação; usuário sem permissão (inclusive com a permissão de teste do relé); offline,
não operacional, certificado não confiável, firmware ou relé não homologados; direção
incompatível; flag desligada; pessoa sem vínculo e fluxo excepcional; clique duplo e
idempotência; timeout como confirmação desconhecida sem reenvio; confirmação na tela sem
apresentar aceite como passagem; tela de postos; nenhum segredo em log, auditoria ou evento.

## 9. Pendências de homologação

| Pendência | Situação |
|---|---|
| Ponto real (cancela/catraca) ativo com o terminal vinculado | depende da instalação física |
| Contato seco e pulso validados na central do ponto | PEN-ADR-016-005 |
| Sensor para confirmar a passagem | PEN-ADR-017-001 |
| Módulo de autorizações (validade e restrições próprias) | hoje a autorização exibida é o vínculo vigente |
| Aprovação formal para ligar `SDV_INTEGRACAO_LIBERACAO_OPERACIONAL` | PEN-ADR-017-004 |
| Contingência sem rede | ADR-008 |
