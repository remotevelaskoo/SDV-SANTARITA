# SDV Access — Liberação manual operacional no Modo Portaria

**Documento:** SDV-OPE-017
**Versão:** 1.1.0
**Status:** Implementado e desligado por padrão (`SDV_INTEGRACAO_LIBERACAO_OPERACIONAL=false`)
**Data:** 10/10/2026
**Decisão de origem:** [ADR-017](ADR/ADR-017_LIBERACAO_MANUAL_OPERACIONAL_E_TESTE_TECNICO_DO_RELE.md)
**Relacionados:** [SDV-INT-016](016_HOMOLOGACAO_FACIAL_HIKVISION.md) (teste do relé), [SDV-OPE-018](018_POSTOS_OPERACIONAIS_E_CADASTRO_ASSISTIDO_POR_CAMERA.md), ADR-016

## 1. O que é

A área **Liberação manual** do Modo Portaria permite que um operador autorizado acione o relé
do terminal de um ponto do seu posto para liberar uma entrada ou saída, sempre com pessoa (ou
ocorrência excepcional), motivo, confirmação, evento de acesso e auditoria.

Não confundir com o **Testar relé** de Gestão › Equipamentos: esse é técnico, de bancada, não
gera evento de acesso e usa outra permissão e outra configuração (ADR-017 §3).

## 2. Permissões

| Permissão | Uso | Concedida por padrão |
|---|---|---|
| `acessos.liberar-reles-do-posto` | liberação manual pelos relés do posto ativo (substitui `acessos.liberar-manualmente`) | Administrador |
| `acessos.liberar-outros-reles` | seção "Outros pontos de acesso", com confirmação destacada | Administrador |
| `acessos.liberar-excepcionalmente` | liberação excepcional sem cadastro | Administrador |
| `integracoes.gerenciar` | cadastro de postos, pontos atendidos e designação de operadores | Administrador |
| `dados-sensiveis.revelar` | ver o documento sem máscara (a revelação é auditada) | conforme perfil |
| `arquivos.sensiveis.visualizar` | abrir a foto/documento protegidos da pessoa | conforme perfil |
| `equipamentos.liberar-acesso` | somente o teste técnico do relé; **não** libera acesso | Administrador |

Para operar, conceda `acessos.liberar-reles-do-posto` (e, se for o caso, `acessos.liberar-outros-reles`) ao perfil de portaria em **Gestão › Perfis e
permissões**, depois da aprovação formal.

## 3. Postos e operadores

Em **Gestão › Postos e operadores**:

1. cadastre o posto (inicialmente Guarita Principal, Atendimento de Veículos e Atendimento de Pedestres);
2. vincule os pontos e equipamentos preferenciais do posto;
3. designe os operadores. Para trocar de função, encerre a designação; o histórico fica preservado.

Ao iniciar o turno, o operador seleciona um posto autorizado. O posto prioriza seus relés e
câmeras, mas não constitui bloqueio físico. A Guarita Principal pode operar todos os relés
autorizados. Nos demais postos, o operador com permissão ampliada também pode escolher
**Outros pontos de acesso**; nesse caso, a confirmação destaca que o equipamento não pertence
ao posto ativo.

Devem ser diferenciadas as permissões de liberação dos relés do posto e de liberação de outros
relés da implantação. Toda troca de posto e todo acionamento externo ao posto são auditados.

Tabelas: `postos` (com `tipo`: principal, veiculos ou pedestres), `posto_pontos_acesso`,
`operador_postos` (vínculos com início e fim) e `operador_turnos` (posto assumido no turno, com
início, término e motivo: troca de posto, encerramento ou saída do sistema). O Modo Portaria
leva o operador designado a escolher o posto (`/selecionar-posto`) antes de operar, e o posto
ativo fica no cabeçalho. Os postos iniciais são criados pelo `PostoSeeder`.

Ordem dos relés: os do posto primeiro; no posto de Veículos, cancelas antes de catracas; no de
Pedestres, catracas antes de cancelas. A Guarita Principal mostra todos os relés aptos como do
posto. O evento registra `operador_turno_id` e `fora_do_posto`.
As câmeras USB são associadas localmente por navegador e posto, conforme SDV-OPE-018.

## 4. Requisitos para um equipamento aparecer

A seleção principal mostra primeiro os terminais preferenciais do posto que cumpram tudo
(ADR-017 §3.2): terminal e ponto ativos, online, certificado confiável, abertura remota
homologada para o firmware atual, relé homologado (SDV-INT-016 §14) e direção compatível.

Se o operador possuir a permissão ampliada, a seção **Outros pontos de acesso** apresenta os
demais terminais elegíveis da implantação. Os indisponíveis não podem ser acionados e devem
informar o motivo. A autorização é revalidada pelo backend no envio.

Hoje a facial de homologação está na bancada (ponto do tipo `bancada`, nunca ativado), portanto
não aparece. Para levá-la ao ponto real, use **Gestão › Pontos de acesso**:

1. cadastre a cancela ou catraca (começa "em implantação");
2. informe o motivo e **desvincule** a facial da bancada; a homologação do relé é desfeita,
   porque vale só para o ponto em que foi feita;
3. **vincule** a facial ao ponto real; ela continua "Em homologação";
4. em Gestão › Equipamentos, faça o **Testar relé** duas vezes no ponto real, observando a central
   da cancela (contato seco), e registre o relé como homologado (SDV-INT-016 §14);
5. com o motivo, **ative o ponto** e **ative o terminal**;
6. em Postos e operadores, vincule o ponto ao posto e designe os operadores.

Um terminal ativo não pode ser desvinculado: volte-o antes para homologação.

## 5. Fluxo operacional

1. Modo Portaria › **Liberação manual**. O posto vem selecionado se o operador tiver um só.
2. **Pessoa cadastrada:** busque por nome ou documento e selecione. A tela mostra nome, iniciais,
   foto e documento protegidos (abertos sob demanda e auditados), documento sem máscara para
   quem pode revelar, perfil, imóvel e vínculo, autorização (vínculo vigente) e validade e
   restrições. Com restrição, a liberação normal é bloqueada.
3. **Liberação excepcional** (permissão própria): marque o checkbox “Liberação excepcional —
   prosseguir sem autorização regular confirmada”. Para agilizar o atendimento, não há texto
   obrigatório no fluxo padrão. O sistema grava o motivo padronizado
   `liberacao_excepcional_operador`, exige confirmação destacada e sinaliza o evento para
   revisão. Uma observação complementar é opcional, salvo configuração administrativa.
4. Escolha a direção e o ponto/equipamento. Os equipamentos preferenciais do posto aparecem
   primeiro. Com permissão ampliada, é possível abrir **Outros pontos de acesso**; a escolha fora
   do posto exige confirmação destacada. Com um só compatível, ele vem selecionado e visível.
5. Na liberação normal, informe o motivo e clique em **Revisar liberação**. Na excepcional, o
   motivo padronizado é aplicado automaticamente.
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
chave idempotente e, quando excepcional, a marcação, o motivo padronizado, a identificação
disponível e a observação opcional.

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
