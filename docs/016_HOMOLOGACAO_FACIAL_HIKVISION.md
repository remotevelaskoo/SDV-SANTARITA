# SDV Access — Homologação em bancada da facial Hikvision

**Documento:** SDV-INT-016
**Versão:** 1.2.0
**Status:** Bancada concluída para conexão, inventário, capacidades, captura e porta 1; operação real desligada
**Data:** 27/09/2026
**Decisões de origem:** [ADR-016](ADR/ADR-016_INTEGRACAO_DIRETA_TERMINAL_FACIAL_E_PONTO_DE_ACESSO.md) (§21), ADR-004, ADR-005, ADR-007, ADR-009, ADR-010 e ADR-013
**Base:** [SDV-INT-015](015_FUNDACAO_INTEGRACAO_TERMINAIS_FACIAIS.md)

Nenhum IP, senha, número de série completo, endereço MAC, impressão do certificado, imagem
capturada ou dado pessoal aparece neste documento. Os valores reais ficam no cadastro do
equipamento (senha cifrada) e no `.env` local, fora do Git.

## Controle de versões

| Versão | Data | Alteração |
|---|---|---|
| 1.0.0 | 27/09/2026 | Primeira integração real: endpoints, matriz e teste do relé |
| 1.1.0 | 27/09/2026 | Reorganização com objetivo, ambiente, formatos sanitizados, testes e evidências |
| 1.2.0 | 09/10/2026 | Teste individual de relés por facial (§14), porta do relé por equipamento e endereço mascarado nas telas |

## 1. Objetivo

Comprovar, em bancada e sem cancela ou catraca, que o SDV Access consegue, pelo backend e
atrás da porta de integração: cadastrar o terminal, testar a conexão, consultar informações e
capacidades reais, capturar e exibir uma imagem estática, acionar o relé da porta 1 e auditar
todas as operações.

## 2. Equipamento, firmware e ambiente

| Item | Valor |
|---|---|
| Modelo | Hikvision DS-K1T673DX-BR |
| Firmware | família V3.18.0; build ensaiada **V3.18.0 build 250115** |
| Tipo informado | `ACS` |
| Relé | porta 1, no módulo seguro de controle de porta ligado por RS-485 |
| Rede | rede local da bancada, IP reservado, sem exposição à internet |
| Transporte | HTTPS com certificado autoassinado do terminal, chave pública fixada no SDV |
| Carga no relé | nenhuma; cancela e catraca desconectadas |
| Aplicação | SDV em container local (PHP 8.5, Laravel 13, Livewire 4, SQLite) |

Arquitetura exercitada:

```text
Navegador → Backend SDV (Livewire /equipamentos) → PortaEquipamentoAcesso
  → HikvisionIsapiAdaptador → ClienteIsapi (Digest, timeout, chave fixada) → Facial
```

## 3. Autenticação

- HTTP **Digest** (`qop=auth`, `realm` informado pelo próprio terminal), feita somente pelo
  backend. Sem credencial todos os caminhos abaixo respondem `401`; um caminho inexistente
  responde `404`, o que permitiu confirmar a existência dos caminhos antes de autenticar.
- Usuário técnico e senha são digitados pelo administrador na tela; a senha é cifrada e nunca
  volta ao navegador, a logs ou à auditoria.
- TLS: o administrador lê o certificado na tela, confere a impressão com o painel do terminal e
  confia nele. O SDV fixa o SHA-256 da chave pública e exige essa chave nas chamadas do
  adaptador. Na bancada, uma chave diferente fez o curl abortar antes de enviar dados (erro 90).

## 4. Endpoints ISAPI confirmados

| Uso | Método e caminho | Resultado no terminal |
|---|---|---|
| Conexão e inventário | `GET /ISAPI/System/deviceInfo` | `200 application/xml` |
| Capacidades de acesso | `GET /ISAPI/AccessControl/capabilities` | `200 application/xml` |
| Canais de vídeo | `GET /ISAPI/Streaming/channels` | `200 application/xml`, canais 101 e 102 |
| Imagem estática | `GET /ISAPI/Streaming/channels/101/picture` | `200 image/jpeg` |
| Capacidades do comando de porta | `GET /ISAPI/AccessControl/RemoteControl/door/capabilities` | `200 application/xml` |
| Estado da porta | `GET /ISAPI/AccessControl/AcsWorkStatus?format=json` | `200 application/json` |
| Comando da porta 1 | `PUT /ISAPI/AccessControl/RemoteControl/door/1` | `200 application/xml`, `statusCode=1` |

Não oferecidos nesta build: `GET /ISAPI/Streaming/channels/1/picture` e
`GET /ISAPI/AccessControl/Door/capabilities` (`404`).

## 5. Formatos de requisição e resposta (sanitizados)

Valores reais de série, nome de canal e identificadores foram substituídos.

`GET /ISAPI/System/deviceInfo`:

```xml
<DeviceInfo version="2.0" xmlns="http://www.isapi.org/ver20/XMLSchema">
  <deviceName>…</deviceName>
  <model>DS-K1T673DX-BR</model>
  <serialNumber>[SÉRIE OMITIDA]</serialNumber>
  <firmwareVersion>V3.18.0</firmwareVersion>
  <firmwareReleasedDate>build 250115</firmwareReleasedDate>
  <deviceType>ACS</deviceType>
</DeviceInfo>
```

O SDV guarda `firmware = "firmwareVersion firmwareReleasedDate"`, que é a chave da matriz.

`GET /ISAPI/AccessControl/capabilities` (trecho usado):

```xml
<AccessControl version="2.0" xmlns="http://www.isapi.org/ver20/XMLSchema">
  <isSupportRemoteControlDoor>true</isSupportRemoteControlDoor>
  <isSupportUserInfo>true</isSupportUserInfo>
  <isSupportUserInfoDetailDelete>true</isSupportUserInfoDetailDelete>
  <isSupportFDLib>true</isSupportFDLib>
  <isSupportAcsEvent>true</isSupportAcsEvent>
  <isSupportAcsWorkStatus>true</isSupportAcsWorkStatus>
</AccessControl>
```

`GET /ISAPI/Streaming/channels` (trecho): o primeiro `StreamingChannel` com `enabled=true` e
`Video/enabled=true` é o canal da captura (`101` nesta build).

`GET /ISAPI/AccessControl/RemoteControl/door/capabilities`:

```xml
<RemoteControlDoor version="2.0" xmlns="http://www.isapi.org/ver20/XMLSchema">
  <doorNo min="1" max="2"></doorNo>
  <cmd opt="open,close,alwaysOpen,alwaysClose"></cmd>
</RemoteControlDoor>
```

`PUT /ISAPI/AccessControl/RemoteControl/door/1`, requisição enviada:

```xml
<RemoteControlDoor version="2.0" xmlns="http://www.isapi.org/ver20/XMLSchema"><cmd>open</cmd></RemoteControlDoor>
```

Resposta recebida:

```xml
<ResponseStatus version="2.0" xmlns="http://www.isapi.org/ver20/XMLSchema">
  <statusCode>1</statusCode>
  <subStatusCode>ok</subStatusCode>
</ResponseStatus>
```

`GET /ISAPI/AccessControl/AcsWorkStatus?format=json` (campos usados como evidência):
`doorLockStatus`, `doorStatus`, `magneticStatus`, `doorOnlineStatus`.

## 6. Resultado da captura

- canal descoberto automaticamente: 101;
- imagens `image/jpeg` de 33 a 40 KB, 432×768, assinatura JPEG validada;
- limite de tamanho (3 MB), tipo e assinatura validados antes de guardar;
- guardada só a captura mais recente, cifrada em cache por 5 minutos, servida por
  `GET /equipamentos/{id}/imagem` (autenticada, isolada por implantação, `no-store`);
- exibida na tela com data e hora; nenhuma imagem foi versionada, logada ou auditada;
- a captura não é usada como credencial biométrica.

## 7. Resultado do comando da porta 1

| Etapa | Evidência |
|---|---|
| Estado antes | `doorLockStatus [0,0]`, `doorStatus [4,4]`, `magneticStatus [0,0]` |
| Solicitação | operador com `equipamentos.liberar-acesso`, motivo informado e confirmação; auditoria `liberacao_remota_solicitada`, resultado `solicitado`, com operador, IP, equipamento, ponto `BANCADA-01` e modo `teste_bancada` |
| Envio | uma tentativa, sem retentativa automática, processada em cerca de 1 s |
| Resposta | `statusCode=1`, `subStatusCode=ok` → resultado **aceito** |
| Auditoria do resultado | `integracao_abertura_remota`, resultado `aceito`, motivo `comando_aceito` |
| Observação física | clique do relé ouvido na bancada pelo responsável |
| Estado depois | igual ao anterior: o pulso terminou antes da leitura e não há sensor de porta |

**Comando aceito não comprova passagem física.** Sem sensor ligado ao ponto, o SDV registra
apenas que o terminal aceitou o comando. A abertura operacional continua desligada
(`SDV_INTEGRACAO_ABERTURA_REMOTA=false`) até a validação do contato seco na cancela ou catraca.

## 8. Matriz de capacidades

Estados possíveis por capacidade e firmware: `nao_implementada`, `nao_suportada`, `detectada`,
`em_homologacao` (executa só com o terminal em homologação), `homologada` e `bloqueada`. O
terminal que declara `false` vence o perfil. Firmware fora da matriz não executa nada.

| Capacidade | V3.18.0 build 250115 | Evidência |
|---|---|---|
| Testar conexão | homologada | `deviceInfo` |
| Consultar informações (inventário) | homologada | firmware e série preenchidos e auditados |
| Consultar capacidades | homologada | `AccessControl/capabilities` |
| Capturar imagem | homologada | §6 |
| Abertura remota (porta 1) | homologada como comando aceito | §7 |
| Coletar e receber eventos | detectada, não implementada | `isSupportAcsEvent=true` |
| Gerenciar pessoas | detectada, não implementada | `isSupportUserInfo=true` |
| Sincronizar, consultar e revogar credencial | detectadas, não implementadas | `isSupportUserInfo`, `isSupportUserInfoDetailDelete` |
| Credencial facial | bloqueada | ADR-013 |
| Consultar resultado do comando, idempotência nativa | não implementadas | sem prova validada |

Configuração: `config/integracoes.php`, chave `hikvision.perfis_homologados`.

## 9. Testes executados

Automatizados (`php artisan test`: 281 testes, 1543 asserções, todos passando; Pint sem
pendências):

| Arquivo | Cobertura |
|---|---|
| `tests/Feature/Integracoes/Contrato/HikvisionContratoTest.php` | contrato da porta; firmware conhecido e desconhecido; matriz por capacidade; terminal que nega capacidade; canal descoberto; imagem válida, não imagem e acima do limite; rede, timeout de conexão e de resposta, certificado inválido e alterado (pin), credencial inválida, resposta incompatível; abertura aceita, recusada, desconhecida, não alcançada |
| `tests/Feature/Integracoes/HomologacaoBancadaTest.php` | senha cifrada e substituição; cofre exigido; logs e ocorrências sem segredo; teste manual sem retentativa; captura temporária e auditada; captura indisponível e sem capacidade; permissão e equipamento inativo; liberação aceita, recusada, desconhecida e tardia sem reenvio; comando duplicado; permissão, motivo, homologação, capacidade e habilitação exigidos; bancada nunca ativada |
| `tests/Feature/Integracoes/TelaEquipamentosTest.php` | rota e permissões; senha nunca no HTML nem na auditoria; conexão e credencial inválida na tela; imagem pelo backend; botão sem permissão explícita; motivo, confirmação e clique duplo; isolamento entre implantações |
| `tests/Feature/Integracoes/CadastroEquipamentosTest.php`, `OutboxEProcessamentoTest.php`, `ArquiteturaIntegracaoTest.php` | cadastro, outbox, idempotência, fabricante isolado no adaptador |

Manuais na bancada: teste de conexão (certificado não confiável, depois sucesso), leitura e
confiança do certificado, consulta de capacidades, captura e exibição da imagem, e um único
acionamento do relé autorizado pelo responsável.

## 10. Evidências registradas

- auditoria no SDV (`/auditoria` e histórico do equipamento): `equipamento_cadastrado`,
  `equipamento_credencial_definida`, `equipamento_certificado_confiado`,
  `equipamento_inventario_detectado`, `integracao_testar_conexao`,
  `integracao_consultar_capacidades`, `equipamento_imagem_capturada`,
  `liberacao_remota_solicitada` e `integracao_abertura_remota`;
- ocorrências técnicas por operação (`integracao_ocorrencias`), com latência e código;
- as respostas sanitizadas das seções 5 e 7.

## 11. Limitações

- aceito não é passagem física: falta sensor no ponto;
- abertura operacional desligada até validar o contato seco na central;
- eventos, gerenciamento de pessoas, sincronização facial e revogação não implementados;
- a senha cifrada no banco vale só fora de produção e **não substitui o cofre** do ADR-009;
- a fixação da chave usa `CURLOPT_PINNEDPUBLICKEY` pela opção `curl` do Guzzle, depreciada no
  Guzzle 7.12 e rejeitada no 8.0: substituir antes dessa atualização (handler próprio ou
  certificado confiável com o IP no SAN, mantendo a fixação);
- somente imagem estática, sem streaming e sem histórico de imagens;
- a bancada usa SQLite; a suíte de integrações já foi validada também no PostgreSQL na fundação.

## 12. Pendências

| Pendência | Depende de |
|---|---|
| Sensor e prova de passagem física | escolha do ponto e do sensor |
| Contato seco e pulso na cancela/catraca real | diagrama elétrico do ponto (PEN-ADR-016-005) |
| Eventos do terminal | implementação e homologação (PEN-ADR-016-003) |
| Pessoas, sincronização e revogação | implementação, ADR-013 e política de privacidade |
| Operação offline | ADR-008 |
| Cofre de segredos de produção | PEN-ADR-009-001 |
| Substituir a opção depreciada do Guzzle | antes do Guzzle 8 |

## 13. Como repetir a homologação pela interface

1. No `.env` local: `SDV_HOMOLOGACAO_FACIAL_HOST=<ip da facial>` (sugestão do formulário) e,
   somente durante o teste do relé, `SDV_INTEGRACAO_TESTE_RELE=true`. Depois rode
   `php artisan config:clear`.
2. Entre como administrador e abra **Gestão › Equipamentos › Cadastrar equipamento**. Confira os
   valores, digite usuário e senha técnica e salve. O ponto "Bancada de testes" (tipo `bancada`)
   é criado e o terminal fica "Em homologação".
3. **Ler certificado**: confira a impressão SHA-256 com a do painel do terminal e clique em
   **Confiar neste certificado**.
4. **Testar conexão**: deve mostrar modelo e firmware; série e firmware vazios são preenchidos.
5. **Consultar capacidades**: confira a matriz da §8.
6. **Atualizar imagem**: a imagem aparece com data e hora e expira em 5 minutos.
7. **Testar relé** (exige `equipamentos.liberar-acesso` e a flag): siga o roteiro da §14.
8. Confira o **Histórico auditável** do equipamento e **Logs e auditoria**.
9. Volte `SDV_INTEGRACAO_TESTE_RELE=false` e rode `php artisan config:clear`.

## 14. Teste individual de relés

Cada facial aciona somente o seu relé. A tela **Gestão › Equipamentos** tem a seção
**Teste individual de relés**, com todas as faciais da implantação: nome, ponto, direção,
porta do relé, online/offline, situação da homologação, última comunicação e o botão
**Testar relé**. O botão fica desabilitado, com o motivo, enquanto faltar qualquer requisito.

### 14.1 Regras

- **Requisitos:** permissão `equipamentos.liberar-acesso`, `SDV_INTEGRACAO_TESTE_RELE=true`,
  terminal "Em homologação" e vinculado a um ponto, online, certificado confiado, abertura remota
  homologada para o firmware atual e confirmada no terminal.
- **Equipamento resolvido no backend:** a tela envia só o id da facial. O SDV relê o
  equipamento na implantação atual e monta endereço, porta do relé, certificado, credencial e
  adaptador a partir do cadastro dele. Nada disso vem do navegador.
- **Porta do relé por equipamento:** `equipamentos.porta_rele`, padrão 1. Trocar a porta ou o
  firmware desfaz a homologação do relé.
- **Proteções:** uma chave idempotente por confirmação (clique duplo e reenvio devolvem a mesma
  operação); a mesma chave para outra facial é recusada; uma tentativa só, sem reenvio
  automático; equipamento de outra implantação não é encontrado.
- **Resultado:** solicitado, enviado, aceito pelo terminal, recusado, desconhecido ou falha.
  Nenhuma entrada ou saída de pessoa é registrada; "aceito" não comprova o acionamento físico.
- **Auditoria:** `liberacao_remota_solicitada` (operador, implantação, equipamento, ponto, porta,
  motivo, data e hora, correlation ID, chave idempotente, IP do operador) e
  `integracao_abertura_remota` (resultado, código, latência, correlation ID, chave). Nunca a
  senha técnica.
- **Endereço mascarado:** listas, detalhe e confirmação mostram o IP como `192.•••.•••.8`; o valor
  completo fica só no backend e no formulário de cadastro.

### 14.2 Roteiro de homologação manual (repetir para cada facial)

1. Ligue `SDV_INTEGRACAO_TESTE_RELE=true` no `.env` local e rode `php artisan config:clear`.
2. Em **Gestão › Equipamentos › Teste individual de relés**, localize a facial. Se o botão
   estiver desabilitado, resolva o motivo exibido (por exemplo: testar a conexão, confiar no
   certificado ou consultar as capacidades).
3. Clique em **Testar relé** e confira na confirmação: nome, fabricante e modelo, número de série,
   ponto, direção, endereço mascarado, porta do relé e o aviso de teste em bancada. Confirme que é
   o terminal à sua frente.
4. Informe o motivo, marque a confirmação e clique em **Confirmar teste do relé** uma vez.
5. Observe o relé e anote se houve clique/acionamento. O SDV deve mostrar "Aceito pelo terminal".
6. Clique em **Testar de novo** e repita uma vez para confirmar a estabilidade.
7. Com dois testes aceitos e o clique observado, clique em **Registrar relé homologado**, marque
   "Observei o clique/acionamento" e descreva o que foi observado. A facial passa a mostrar
   "Relé homologado".
8. Passe para a próxima facial e repita do passo 2.
9. Ao terminar, volte `SDV_INTEGRACAO_TESTE_RELE=false` e rode `php artisan config:clear`.

O mesmo roteiro vale com o terminal já vinculado ao ponto real (ainda "Em homologação"): é assim
que o contato seco na central da cancela é homologado (ADR-016 §11, passo 9). Mudar o terminal de
ponto desfaz a homologação do relé.

A abertura operacional (`SDV_INTEGRACAO_ABERTURA_REMOTA`) continua desligada: este roteiro é só
homologação em bancada. A liberação de pessoas pela portaria é outro fluxo, com permissão e
configuração próprias: ver [SDV-OPE-017](017_LIBERACAO_MANUAL_OPERACIONAL.md) e o ADR-017.
