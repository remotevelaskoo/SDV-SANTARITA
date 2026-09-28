# ADR-016 — INTEGRAÇÃO DIRETA ENTRE TERMINAL FACIAL E PONTO DE ACESSO

**Identificador:** ADR-016  
**Versão:** 1.1.0  
**Status:** Aprovado  
**Prioridade:** P1 — Obrigatório para a integração facial  
**Produto:** SDV Access — Implantação Santa Rita  
**Responsável pelo produto:** Vinicius Velasco de Azevedo  
**Responsável técnico:** Soluções do Vale Tecnologia  
**Data:** 27/09/2026

---

## Controle de versões

| Versão | Data | Responsável | Alteração |
|---|---|---|---|
| 1.0.0 | 27/09/2026 | Product Owner | Aprovação da integração direta entre terminal facial Hikvision e ponto de acesso |
| 1.0.1 | 27/09/2026 | Soluções do Vale | Renumeração de ADR-015 para ADR-016 ao incorporar à `main`, onde ADR-015 já identifica a consulta de CEP (ViaCEP); conteúdo aprovado inalterado |
| 1.1.0 | 27/09/2026 | Soluções do Vale | Registro da evidência técnica de bancada (§21) e da topologia com módulo seguro RS-485; decisão, alternativas e critérios de aceite das seções 1 a 20 inalterados |

# 1. Contexto

A implantação Santa Rita utilizará terminais de reconhecimento facial **Hikvision DS-K1T673DX-BR**. Cada terminal atenderá somente um ponto físico, classificado como cancela ou catraca.

Foi decidido não utilizar a controladora BRAVAS como intermediária nesse recorte. O terminal facial deverá comunicar-se com o SDV Access por rede e acionar diretamente a entrada de comando do respectivo ponto de acesso por meio de seu relé.

O equipamento observado possui alimentação nominal de 12 V. Essa característica não autoriza alimentar motor, solenóide ou carga de potência diretamente pelo terminal.

# 2. Problema

Definir uma topologia simples e segura que:

- elimine uma controladora intermediária quando ela não agregar capacidade necessária;
- associe inequivocamente cada terminal a uma cancela ou catraca;
- preserve o SDV Access como fonte de cadastro, vínculo, autorização e revogação;
- permita reconhecimento e acionamento local após sincronização autorizada;
- mantenha integração desacoplada conforme o ADR-007;
- evite que o terminal alimente diretamente a carga acionada.

# 3. Forças e restrições

- cada terminal acionará somente uma cancela ou catraca;
- a comunicação de software deverá usar o protocolo oficial disponível no equipamento, inicialmente ISAPI, sujeito à confirmação do firmware;
- a conexão elétrica deverá usar contato seco do relé, nos bornes COM/NO ou COM/NC aplicáveis;
- a alimentação de 12 V do terminal será separada do circuito de potência;
- a entrada da central da cancela ou catraca deverá aceitar comando compatível com relé/contato seco;
- o modelo exato da cancela ou catraca não bloqueia o desenvolvimento do adaptador nem os testes de bancada;
- o modelo e o diagrama elétrico do ponto serão obrigatórios antes da ligação definitiva em campo;
- biometria em produção continua condicionada ao ADR-013 e às decisões de privacidade.

# 4. Alternativas consideradas

| Alternativa | Decisão | Justificativa |
|---|---|---|
| SDV → BRAVAS → terminal/ponto | Não adotada neste recorte | Acrescenta componente e integração sem necessidade confirmada para um ponto por terminal |
| SDV → terminal → relé → entrada de comando | Adotada | Simplifica a topologia e preserva a separação entre autorização, credencial e acionamento |
| terminal alimentar diretamente motor ou carga | Rejeitada | Cria risco elétrico e excede a função esperada do relé de comando |
| decisão integral de autorização no terminal | Rejeitada | Violaria as regras de domínio e dificultaria revogação, auditoria e governança |

# 5. Decisão

Adotar a seguinte topologia para a implantação Santa Rita:

```text
SDV Access
  → porta estável de integração
    → adaptador Hikvision/ISAPI
      → terminal Hikvision DS-K1T673DX-BR
        → contato seco do relé
          → entrada de comando da cancela ou catraca
```

Cada terminal:

- pertencerá a uma única implantação;
- será cadastrado como equipamento independente;
- será associado a um único ponto de acesso;
- terá direção operacional declarada, quando aplicável;
- possuirá identidade, endereço de rede, firmware e capacidades inventariados;
- receberá somente as credenciais autorizadas para seu escopo;
- devolverá eventos para conciliação e auditoria.

O SDV Access continuará responsável por determinar elegibilidade, vigência e revogação. O terminal comprovará a identidade conforme sua capacidade e executará o acionamento local; reconhecimento facial não criará autorização.

# 6. Fronteira elétrica

- o relé do terminal será utilizado como **contato seco de comando**;
- não será ligada carga de potência diretamente ao terminal;
- a central da cancela ou catraca permanecerá responsável por motor, direção, temporização física e dispositivos de segurança;
- sensores antiesmagamento, laço indutivo, fim de curso e liberação emergencial não serão substituídos pelo SDV ou pela facial;
- o pulso do relé deverá ser configurado e homologado antes da instalação definitiva;
- qualquer exceção exigirá avaliação elétrica e registro técnico.

# 7. Integração de software

O adaptador Hikvision deverá implementar somente capacidades comprovadas no firmware homologado, incluindo, quando disponíveis:

- teste de conexão e saúde;
- cadastro ou atualização da referência facial;
- revogação e remoção;
- consulta de estado de sincronização;
- recepção ou coleta de eventos;
- abertura remota autorizada;
- consulta de capacidades;
- reconciliação após indisponibilidade.

Serão preservados:

- UUID interno do SDV;
- identificador externo separado;
- estado de sincronização;
- instante do equipamento e instante de recebimento;
- correlação e idempotência;
- erro técnico sanitizado;
- trilha de auditoria.

# 8. Rede e segurança

- terminal em rede local segmentada;
- IP reservado ou fixo controlado;
- nenhuma publicação direta do terminal na internet;
- credencial administrativa armazenada fora do frontend, conforme ADR-009;
- senha inicial substituída antes dos testes;
- TLS utilizado quando suportado e homologado;
- acesso ao equipamento limitado ao serviço de integração e à administração autorizada;
- logs não conterão senha, template biométrico ou imagem facial desnecessária;
- firmware será inventariado e sua atualização será controlada.

# 9. Operação offline e contingência

A capacidade local do terminal não será presumida como estratégia de contingência aprovada.

Antes de permitir operação durante indisponibilidade do SDV ou da rede, deverão ser homologados:

- credenciais mantidas localmente;
- tempo máximo até propagação de revogação;
- comportamento de credencial expirada;
- armazenamento e recuperação de eventos;
- relógio e sincronização;
- reconciliação após retorno;
- procedimento manual de emergência.

Essas definições permanecem condicionadas à retomada do ADR-008.

# 10. Privacidade e biometria

Este ADR aprova a **topologia técnica e a prova de integração**, mas não autoriza tratamento biométrico em produção.

A ativação com pessoas reais depende da retomada e aprovação do ADR-013, incluindo finalidade, base aplicável, transparência, retenção, exclusão, alternativa não biométrica, segurança, responsabilidades e homologação de acurácia.

Até essa aprovação, testes deverão utilizar dados sintéticos ou pessoas especificamente autorizadas para a prova controlada, com exclusão ao final.

# 11. Estratégia de implementação

1. inventariar serial, firmware, endereço de rede e capacidades do terminal;
2. obter documentação oficial do protocolo compatível com o firmware;
3. ativar o terminal em rede isolada de bancada;
4. testar conectividade e autenticação sem carga física;
5. criar simulador contratual conforme ADR-007;
6. implementar adaptador Hikvision atrás da porta de equipamentos;
7. testar cadastro, atualização, revogação, evento e reconciliação;
8. medir o relé e validar seu pulso sem conectar motor ou carga;
9. homologar contato seco na entrada de comando da central;
10. executar piloto controlado;
11. autorizar produção somente após resolver ADR-008 e ADR-013.

# 12. Validação

A prova técnica deverá cobrir:

- autenticação no terminal;
- detecção de firmware e capacidades;
- cadastro, atualização e remoção de uma credencial de teste;
- recusa de pessoa não autorizada;
- revogação e comprovação de remoção;
- emissão e ingestão de evento;
- evento duplicado;
- timeout e retorno tardio;
- terminal reiniciado;
- perda e retorno da rede;
- divergência de relógio;
- acionamento do relé em bancada;
- ausência de tensão/carga indevida no contato de comando;
- vínculo exclusivo entre terminal e ponto de acesso.

# 13. Consequências

## 13.1 Positivas

- menor quantidade de componentes;
- menor custo e menor superfície de integração;
- topologia adequada a um ponto por terminal;
- autonomia local potencial, sujeita à homologação;
- substituição futura preservada pelo adaptador.

## 13.2 Negativas

- dependência operacional do terminal em cada ponto;
- falha do terminal indisponibiliza seu ponto automático;
- sincronização e revogação precisam de reconciliação rigorosa;
- integração ISAPI e firmware exigem homologação real;
- instalação elétrica continua exigindo validação antes do campo.

# 14. Riscos e mitigações

| Risco | Mitigação |
|---|---|
| relé ligado diretamente à carga | usar somente contato seco na entrada da central e validar em bancada |
| credencial revogada permanecer local | estado explícito, retentativa, reconciliação e alerta |
| terminal exposto na internet | rede segmentada, bloqueio de entrada externa e serviço de integração controlado |
| evento offline perdido ou duplicado | coleta, idempotência, timestamps separados e reconciliação |
| firmware divergir da documentação | inventário e testes contratuais por versão |
| biometria ativada antes da governança | bloqueio de produção pelo ADR-013 |
| reconhecimento substituir autorização | SDV permanece fonte de autorização e sincroniza somente credenciais elegíveis |

# 15. Critérios de aceite

**CA-ADR-016-001:** cada terminal está vinculado a somente um ponto de acesso.  
**CA-ADR-016-002:** o ponto é uma cancela ou catraca identificada no SDV.  
**CA-ADR-016-003:** a BRAVAS não integra este recorte da topologia.  
**CA-ADR-016-004:** o relé usa contato seco na entrada de comando.  
**CA-ADR-016-005:** o terminal não alimenta diretamente motor ou carga.  
**CA-ADR-016-006:** o SDV permanece fonte de autorização e revogação.  
**CA-ADR-016-007:** o fabricante permanece isolado pelo adaptador.  
**CA-ADR-016-008:** firmware e capacidades são inventariados antes da implementação definitiva.  
**CA-ADR-016-009:** testes de bancada precedem a ligação ao ponto real.  
**CA-ADR-016-010:** indisponibilidade e reconciliação são homologadas antes do go-live.  
**CA-ADR-016-011:** produção biométrica permanece bloqueada até aprovação do ADR-013.  
**CA-ADR-016-012:** modelo da cancela/catraca não bloqueia o software, mas seu diagrama é validado antes da ligação definitiva.

# 16. Rastreabilidade

- `RF-032` — sincronizar cadastro facial;
- `RN-038`, `RN-040`, `RN-045`, `RN-077` a `RN-080`, `RN-086` a `RN-093`;
- `PEN-001`, `PEN-002`, `PEN-005` e `PEN-017` do Product Book;
- ADR-004, ADR-005, ADR-007, ADR-008, ADR-009, ADR-010 e ADR-013.

# 17. Dependências

- documentação ISAPI correspondente ao firmware instalado;
- credencial administrativa e configuração segura;
- rede local e endereçamento;
- confirmação da capacidade de evento e sincronização;
- entrada de comando por contato seco;
- retomada do ADR-008 para contingência;
- retomada do ADR-013 para biometria em produção.

# 18. Pendências

| Identificador | Pendência | Bloqueia |
|---|---|---|
| PEN-ADR-016-001 | Confirmar firmware efetivamente instalado | Adaptador definitivo |
| PEN-ADR-016-002 | Obter documentação ISAPI compatível e condições de uso | Integração real |
| PEN-ADR-016-003 | Confirmar eventos, callbacks ou polling disponíveis | Auditoria e reconciliação |
| PEN-ADR-016-004 | Homologar comportamento offline e revogação | Go-live |
| PEN-ADR-016-005 | Validar contato seco e duração do pulso em cada ponto | Instalação definitiva |
| PEN-ADR-016-006 | Resolver política de biometria e privacidade | Produção com pessoas reais |
| PEN-ADR-016-007 | Definir alternativa não biométrica e procedimento de emergência | Go-live |

# 19. Aprovação

| Papel | Nome | Decisão | Data | Observações |
|---|---|---|---|---|
| Product Owner | Vinicius Velasco de Azevedo | Aprovado | 27/09/2026 | Integração direta, um terminal por ponto e retirada da BRAVAS deste recorte |
| Responsável técnico | Soluções do Vale Tecnologia | Recomendado | 27/09/2026 | Condicionado à bancada, contato seco, segurança de rede e ADRs 008/013 |

# 20. Decisão resultante

A implantação Santa Rita adotará integração direta do SDV Access com terminais Hikvision DS-K1T673DX-BR. Cada terminal acionará somente uma cancela ou catraca por contato seco, sem controladora BRAVAS intermediária. O modelo do ponto não bloqueia o desenvolvimento, mas a ligação definitiva exige validação de sua entrada de comando. A produção biométrica permanece condicionada aos ADRs 008 e 013.

## Situação do ADR

**Aprovado.** A topologia técnica e a prova de integração estão autorizadas; o uso biométrico em produção ainda não está autorizado.

---

# 21. Evidência técnica de bancada (adendo 1.1.0)

Este adendo registra o resultado da prova de integração autorizada no §20. Ele não altera a
decisão, as alternativas, a fronteira elétrica nem os critérios de aceite acima. O detalhe
técnico, sem IP, série, MAC, certificado ou credencial real, está em
[SDV-INT-016](../016_HOMOLOGACAO_FACIAL_HIKVISION.md).

## 21.1 Equipamento e topologia ensaiados

- modelo homologado em bancada: **Hikvision DS-K1T673DX-BR**;
- família de firmware: **V3.18.0**; build efetivamente ensaiada: **V3.18.0 build 250115**;
- o relé da porta 1 fica no **módulo seguro de controle de porta ligado por RS-485** ao terminal.
  A topologia do §5 passa a ser, na bancada:

```text
SDV Access
  → porta estável de integração
    → adaptador Hikvision/ISAPI (HTTPS, Digest, chave pública fixada)
      → terminal DS-K1T673DX-BR
        → RS-485
          → módulo seguro de controle de porta
            → contato seco do relé (porta 1)
              → entrada de comando da cancela ou catraca (ainda não ligada)
```

- o módulo seguro não altera a fronteira elétrica do §6: o relé continua sendo contato seco de
  comando e nenhuma carga de potência foi ligada.

## 21.2 Comunicação

- HTTPS com autenticação Digest feita somente pelo backend;
- o certificado do terminal é autoassinado. O SDV fixa o hash **SHA-256 da chave pública**
  confiada pelo administrador e dispensa a cadeia de CA apenas nas chamadas desse adaptador;
  a validação TLS do restante da aplicação não muda. Chave diferente aborta a conexão antes do
  envio de dados;
- a credencial técnica fica cifrada fora de produção; em produção continua exigido o cofre do
  ADR-009.

## 21.3 Capacidades homologadas separadamente

Cada capacidade tem estado próprio por firmware; a build ensaiada não é declarada "compatível"
como um todo.

| Capacidade | Estado na V3.18.0 build 250115 |
|---|---|
| Teste de conexão e saúde | homologada |
| Inventário (modelo, série, firmware) | homologada |
| Consulta de capacidades | homologada |
| Captura de imagem estática (canal descoberto no terminal) | homologada |
| Abertura remota da porta 1 | homologada como comando aceito; operação real desligada |
| Coleta e recepção de eventos | detectada no terminal; não implementada |
| Gerenciamento de pessoas, sincronização, consulta e revogação de credencial | detectadas no terminal; não implementadas |
| Credencial facial | bloqueada pelo ADR-013 |

## 21.4 Comando aceito não é passagem física

- o terminal respondeu ao comando de abertura da porta 1 com status de sucesso e o relé foi
  ouvido na bancada;
- o SDV registra esse resultado como **aceito**, nunca como passagem ou acesso realizado;
- a confirmação física exigirá sensor (porta, laço, fim de curso ou evento equivalente do
  ponto) integrado e homologado;
- a abertura operacional permanece **desligada** até a validação do contato seco na entrada
  de comando da cancela ou catraca real (§11, passo 9; PEN-ADR-016-005).

## 21.5 Situação das pendências

| Identificador | Situação após a bancada |
|---|---|
| PEN-ADR-016-001 | Resolvida para a build V3.18.0 build 250115 |
| PEN-ADR-016-002 | Parcial: endpoints de informações, capacidades, captura e porta validados; eventos, pessoas e sincronização pendentes |
| PEN-ADR-016-003 | Pendente: o terminal declara suporte a eventos, sem implementação |
| PEN-ADR-016-004 | Pendente (ADR-008) |
| PEN-ADR-016-005 | Parcial: pulso do relé ouvido em bancada; contato seco na central não validado |
| PEN-ADR-016-006 | Pendente (ADR-013) |
| PEN-ADR-016-007 | Pendente |

A homologação dos endpoints **não autoriza** sincronização facial nem uso com pessoas reais.
ADR-008 e ADR-013 continuam não resolvidos.
