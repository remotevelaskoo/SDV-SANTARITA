# SDV Access — Homologação em bancada da facial Hikvision

**Documento:** SDV-INT-016
**Versão:** 1.0.0
**Status:** Bancada concluída (conexão, capacidades, imagem e relé); sem cancela ou catraca
**Data:** 27/09/2026
**Decisões de origem:** ADR-004, ADR-005, ADR-007, ADR-009, ADR-010, ADR-013 e ADR-016
**Base:** SDV-INT-015 (fundação da integração)

Este documento registra a primeira integração real do SDV Access com o terminal
DS-K1T673DX-BR e as evidências de bancada. Nenhum IP, série, usuário, senha ou
certificado real aparece aqui: os valores ficam no cadastro do equipamento (senha
cifrada) e no `.env` local, fora do Git.

## 1. Arquitetura

```text
Navegador → Backend SDV (Livewire /equipamentos) → Porta PortaEquipamentoAcesso
  → HikvisionIsapiAdaptador → ClienteIsapi (Digest, timeout, TLS fixado) → Facial
```

- O navegador nunca fala com a facial. A imagem é servida por `GET /equipamentos/{id}/imagem`,
  rota do SDV, autenticada, restrita à implantação e sem cache.
- Nenhuma classe ou payload ISAPI sai de `app/Integracoes/Adaptadores/Hikvision`
  (teste de arquitetura). O nome da classe foi mantido em português
  (`HikvisionIsapiAdaptador`), como o restante da fundação.

## 2. Endpoints ISAPI confirmados no terminal real

Todos com autenticação **HTTP Digest** (`realm` do próprio terminal, `qop=auth`), sobre HTTPS
com a chave pública do certificado fixada. Sem credencial, todos respondem `401`; um caminho
inexistente responde `404`, o que permitiu confirmar a existência dos caminhos antes de autenticar.

| Uso | Método e caminho | Resposta observada (sanitizada) |
|---|---|---|
| Testar conexão e inventário | `GET /ISAPI/System/deviceInfo` | `200 application/xml`, `DeviceInfo` com `model=DS-K1T673DX-BR`, `firmwareVersion=V3.18.0`, `firmwareReleasedDate=build 250115`, `deviceType=ACS` |
| Capacidades de acesso | `GET /ISAPI/AccessControl/capabilities` | `200 application/xml`, `AccessControl` com `isSupportRemoteControlDoor=true`, `isSupportUserInfo=true`, `isSupportFDLib=true`, `isSupportAcsEvent=true`, `isSupportAcsWorkStatus=true` |
| Descoberta do canal de vídeo | `GET /ISAPI/Streaming/channels` | `200 application/xml`, canais `101` e `102` habilitados, entrada de vídeo `1` |
| Imagem estática | `GET /ISAPI/Streaming/channels/101/picture` | `200 image/jpeg`, 33 a 40 KB, 432×768. `channels/1/picture` responde `404` neste firmware |
| Capacidades do comando de porta | `GET /ISAPI/AccessControl/RemoteControl/door/capabilities` | `doorNo` de 1 a 2; `cmd` aceita `open,close,alwaysOpen,alwaysClose` |
| Estado da porta (evidência) | `GET /ISAPI/AccessControl/AcsWorkStatus?format=json` | `200 application/json` com `doorLockStatus`, `doorStatus`, `magneticStatus`, `doorOnlineStatus` |
| Teste do relé | `PUT /ISAPI/AccessControl/RemoteControl/door/1` com `<RemoteControlDoor><cmd>open</cmd></RemoteControlDoor>` | ver §5 |

`GET /ISAPI/AccessControl/Door/capabilities` responde `404` neste firmware.

## 3. Matriz de homologação do firmware V3.18.0 build 250115

Cada capacidade tem estado próprio (`config/integracoes.php`, `hikvision.perfis_homologados`).
A versão do firmware não é "compatível" como um todo.

| Estado | Significado | Executa? |
|---|---|---|
| `nao_implementada` | o adaptador não tem código | não |
| `nao_suportada` | o terminal informa que não oferece | não |
| `detectada` | o terminal oferece, mas nada foi validado | não |
| `em_homologacao` | validada em parte | só com o terminal "Em homologação" |
| `homologada` | validada no terminal real, com evidência | sim |
| `bloqueada` | proibida por decisão | não |

| Capacidade | Estado | Evidência |
|---|---|---|
| Testar conexão | homologada | `deviceInfo` 200 com modelo e firmware |
| Consultar informações (inventário) | homologada | firmware e série preenchidos automaticamente e auditados |
| Consultar capacidades | homologada | `AccessControl/capabilities` 200 |
| Capturar imagem | homologada | JPEG real capturado pelo backend e exibido na tela |
| Abertura remota (porta 1) | homologada | comando aceito pelo terminal e clique do relé ouvido em bancada (§5) |
| Coletar e receber eventos | detectada | `isSupportAcsEvent=true`; não implementado |
| Gerenciar pessoas, sincronizar, consultar e revogar credencial | detectada | `isSupportUserInfo` e `isSupportUserInfoDetailDelete=true`; não implementado |
| Credencial facial | bloqueada | ADR-013 |
| Consultar resultado do comando, idempotência nativa | não implementada | sem prova de execução validada |

O terminal que declara `false` para uma capacidade vence o perfil (`nao_suportada`).
Capacidade fora do perfil nunca executa.

## 4. Segurança

- **Senha técnica:** digitada na tela, cifrada com `APP_KEY` (`equipamento_credenciais.segredo_cifrado`,
  referência `cifrado:BANCO`), nunca devolvida ao HTML, removida do estado do componente antes de
  qualquer validação e trocada por substituição. Permitida fora de produção
  (`SDV_INTEGRACAO_SEGREDO_CIFRADO`); produção continua exigindo cofre (ADR-009 §3). Isto é uma
  extensão explícita do ADR-009 para homologação, não uma troca silenciosa da decisão.
- **TLS:** o certificado do terminal é autoassinado. O administrador lê o certificado na tela,
  confere a impressão SHA-256 e confia nele. O SDV fixa o hash SHA-256 da chave pública
  (`equipamentos.tls_pin_sha256`) e, somente nas chamadas deste adaptador, dispensa a cadeia de CA
  e exige a chave fixada (`CURLOPT_PINNEDPUBLICKEY`). Com pin errado o curl aborta antes de enviar
  dados (erro 90, verificado na bancada). Trocar IP, porta ou esquema apaga o pin.
- **Logs e auditoria:** mensagens escritas pelo adaptador, sem URL, cabeçalho, corpo ou segredo;
  imagem nunca entra em log, auditoria, outbox ou ocorrência.
- **Permissões:** tela e captura exigem `integracoes.gerenciar`; o teste do relé exige também
  `equipamentos.liberar-acesso`, verificada na tela e no caso de uso.

## 5. Teste do relé

Executado em 27/09/2026, com autorização do responsável, sem cancela, catraca ou carga no relé.

| Etapa | Evidência (sanitizada) |
|---|---|
| Estado antes | `AcsWorkStatus`: `doorLockStatus [0,0]`, `doorStatus [4,4]`, `magneticStatus [0,0]` |
| Solicitação | operador autenticado com `equipamentos.liberar-acesso`, motivo informado, confirmação marcada; auditoria `liberacao_remota_solicitada` com resultado `solicitado`, operador, IP, equipamento, ponto `BANCADA-01` e modo `teste_bancada` |
| Envio | `PUT /ISAPI/AccessControl/RemoteControl/door/1`, `cmd=open`, uma tentativa (`max_tentativas=1`), processado em cerca de 1 s |
| Resposta do terminal | `ResponseStatus` com `statusCode=1`, `subStatusCode=ok` → resultado **`aceito`** |
| Auditoria do resultado | `integracao_abertura_remota` com resultado `aceito` e motivo `comando_aceito` |
| Observação física | clique do relé ouvido na bancada pelo responsável |
| Estado depois | `AcsWorkStatus` igual ao anterior: o pulso do relé terminou antes da leitura e não há sensor de porta ligado |

Conclusão: o relé é acionado pelo comando ISAPI. O SDV registra **aceito**, nunca "acesso
realizado": não há sensor nem cancela para comprovar passagem. A capacidade `abertura_remota`
passou a `homologada` para este firmware; a abertura operacional continua desligada por
`SDV_INTEGRACAO_ABERTURA_REMOTA=false` até a homologação do contato seco na central
(ADR-016 §6 e §11, passo 9). Depois do teste, `SDV_INTEGRACAO_TESTE_RELE` voltou a `false`.

## 6. Como repetir a homologação pela interface

1. No `.env` local: `SDV_HOMOLOGACAO_FACIAL_HOST=<ip da facial>` (sugestão do formulário) e,
   somente para o teste do relé, `SDV_INTEGRACAO_TESTE_RELE=true`. Rode `php artisan config:clear`.
2. Entre como administrador e abra **Gestão › Equipamentos › Cadastrar equipamento**. Confira os
   valores, digite usuário e senha técnica e salve. O ponto "Bancada de testes" (tipo `bancada`)
   é criado e o terminal fica "Em homologação".
3. **Ler certificado**: confira a impressão SHA-256 com a exibida no painel web do terminal e
   clique em **Confiar neste certificado**.
4. **Testar conexão**: deve mostrar modelo e firmware; série e firmware vazios são preenchidos.
5. **Consultar capacidades**: confira a matriz da §3.
6. **Atualizar imagem**: a imagem aparece com data e hora e expira em 5 minutos.
7. **Liberar acesso** (só com a permissão e a flag): informe o motivo, confirme que não há carga
   no relé e confirme. O resultado mais forte é "Aceito pelo terminal".
8. Confira o **Histórico auditável** do equipamento e **Logs e auditoria**.

## 7. Limitações e pendências

| Item | Situação |
|---|---|
| Aceito não é abertura física | o terminal confirma o recebimento; não há sensor nem prova de passagem |
| `CURLOPT_PINNEDPUBLICKEY` via opção `curl` do Guzzle | depreciado no Guzzle 7.12 e rejeitado no 8.0; antes de atualizar, trocar por handler próprio ou certificado confiável com SAN do IP |
| Certificado de produção | substituir o autoassinado por certificado emitido para o terminal, mantendo a fixação |
| Senha cifrada no banco | aceita só fora de produção; cofre (PEN-ADR-009-001) continua pendente |
| Eventos, pessoas, sincronização e revogação | detectados no terminal, não implementados |
| Captura | somente imagem estática, em cache cifrado por 5 minutos; sem política de retenção aprovada, não há histórico |
| Cancela ou catraca | não conectadas; ligação definitiva exige diagrama e contato seco (ADR-016 §6) |
