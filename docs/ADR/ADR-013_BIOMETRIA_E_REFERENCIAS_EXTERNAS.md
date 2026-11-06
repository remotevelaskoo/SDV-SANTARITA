# ADR-013 — BIOMETRIA E REFERÊNCIAS EXTERNAS

**Identificador:** ADR-013
**Versão:** 1.1.0
**Status:** Proposto (revisão AJ-010) — ativação operacional ainda não autorizada
**Prioridade:** P2 — Condicional
**Produto:** SDV Access — Implantação Santa Rita
**Responsável pelo produto:** Vinicius Velasco de Azevedo
**Data:** 30/07/2026

---

## Controle de versões

| Versão | Data | Responsável | Alteração |
|---|---|---|---|
| 1.0.0 | 30/07/2026 | Product Owner | Registro e aprovação do adiamento da biometria |
| 1.0.1 | 12/08/2026 | Product Owner | Esclarecimento entre conferência humana da selfie e sincronização com controladora |
| 1.1.0 | 06/11/2026 | Soluções do Vale | Revisão proposta para o AJ-010: foto da pessoa capturada no cadastro rápido e envio ao terminal facial implementado atrás de bandeira, capacidade e homologação; condições de ativação registradas (§21) |

# 1. Contexto

O produto prevê reconhecimento facial futuro. Selfies podem ser coletadas em fluxos aprovados, mas imagem, template biométrico, credencial e autorização são entidades distintas.

# 2. Problema

Não há fabricante confirmado, política jurídica, formato, retenção, segurança, taxa de erro ou procedimento de exclusão homologado.

# 3. Decisão

**Adiar a ativação de biometria e credenciais faciais.**

Enquanto adiado:

- selfie não gera template;
- selfie não sincroniza equipamento;
- face não concede acesso;
- nenhum template é persistido;
- nenhum fornecedor recebe imagem;
- validação de entrada usa caminhos não biométricos aprovados.

A exibição protegida da selfie submetida a operador autorizado, exclusivamente para conferência humana do pré-cadastro ou da identidade, não ativa biometria e não altera o status deste ADR. Permanecem proibidos o envio da imagem, a geração de template e a sincronização com qualquer controladora enquanto esta decisão estiver adiada.

# 4. Condições de retomada

- finalidade;
- base legal;
- transparência ao titular;
- política de retenção;
- direitos e atendimento;
- fabricante e contrato;
- formato do template;
- criptografia;
- exclusão;
- acurácia;
- vieses;
- contingência;
- segurança e homologação.

# 5. Princípios futuros

- minimização;
- finalidade específica;
- consentimento quando aplicável;
- alternativa não biométrica;
- template separado da foto;
- credencial com vigência;
- autorização independente;
- revogação;
- auditoria;
- menor privilégio.

# 6. Referência externa

Se o fabricante mantiver o template:

- SDV guarda referência externa;
- UUID interno permanece principal;
- implantação e adaptador no escopo;
- segredo não é referência;
- estado de sincronização explícito;
- remoção deve ser confirmada;
- histórico preservado sem reter template desnecessário.

# 7. Template local

Somente será considerado se:

- necessário;
- formato documentado;
- criptografia forte;
- chaves separadas;
- acesso restrito;
- retenção;
- backup e descarte;
- risco aprovado.

# 8. Ciclo de vida futuro

```text
coleta autorizada
 → validação
 → geração
 → credencial pendente
 → sincronização
 → ativa
 → suspensa/revogada
 → remoção confirmada
```

# 9. Segurança

- nenhum template em log;
- nenhum template no frontend;
- acesso segregado;
- comunicação protegida;
- antirreplay;
- rotação de segredo;
- proteção contra export;
- resposta a vazamento;
- teste de exclusão.

# 10. Acurácia e decisão

- limiares configurados;
- taxa de falso aceite e rejeição medida;
- qualidade de captura;
- detecção de apresentação quando suportada;
- falha não vira sucesso;
- revisão humana;
- evento preserva resultado;
- biometria não substitui autorização.

# 11. Direitos e privacidade

- informação clara;
- canal de atendimento;
- correção;
- revogação;
- alternativa;
- retenção;
- descarte;
- registro de acesso;
- avaliação de impacto quando aplicável.

# 12. Testes futuros

- cadastro;
- duplicidade;
- falso aceite/rejeição;
- bloqueio;
- expiração;
- equipamento offline;
- remoção;
- vazamento;
- isolamento;
- contingência;
- alternativa não biométrica.

# 13. Consequências

Positivas:

- evita tratamento sensível sem base;
- reduz risco;
- preserva alternativa.

Negativas:

- reconhecimento facial indisponível;
- acesso depende de métodos alternativos;
- integração futura exigirá homologação.

# 14. Riscos

| Risco | Mitigação |
|---|---|
| selfie virar credencial | separação explícita |
| template não removido | confirmação e reconciliação |
| falso aceite | limiar e autorização |
| fornecedor reter dados | contrato e auditoria |
| vazamento irreversível | minimização e criptografia |

# 15. Critérios de aceite

**CA-ADR-013-001:** biometria não está ativada.
**CA-ADR-013-002:** selfie não cria credencial.
**CA-ADR-013-003:** template não é armazenado.
**CA-ADR-013-004:** autorização permanece separada.
**CA-ADR-013-005:** alternativa não biométrica permanece.
**CA-ADR-013-006:** retomada exige base e finalidade.
**CA-ADR-013-007:** fabricante é homologado.
**CA-ADR-013-008:** exclusão é comprovável.
**CA-ADR-013-009:** referência externa não substitui UUID.
**CA-ADR-013-010:** ativação exige nova versão deste ADR.

# 16. Rastreabilidade

- `RN-045`, `RN-065`, `RN-066`, `RN-075`;
- ADR-006, ADR-007 e ADR-009;
- `PEN-RNG-005`, `PEN-ARQ-008`.

# 17. Pendências

| PEN-ADR-013 | Pendência |
|---|---|
| PEN-ADR-013-001 | Base legal e finalidade |
| PEN-ADR-013-002 | Fabricante e contrato |
| PEN-ADR-013-003 | Formato e localização do template |
| PEN-ADR-013-004 | Retenção e descarte |
| PEN-ADR-013-005 | Acurácia e limiares |
| PEN-ADR-013-006 | Alternativa operacional |
| PEN-ADR-013-007 | Inventário, API, capacidades, contrato e homologação do equipamento BRAVAS considerado pela implantação |

# 18. Aprovação

| Papel | Nome | Decisão | Data |
|---|---|---|---|
| Product Owner | Vinicius Velasco de Azevedo | Aprovado o adiamento | 30/07/2026 |
| Responsável técnico | Soluções do Vale Tecnologia | Recomendado | 30/07/2026 |

# 19. Condição de retomada

Voltará a **Proposto** após política de privacidade, inventário e prova técnica.

# 20. Decisão resultante

Biometria permanece desativada; selfies não se tornam credenciais.

# 21. Revisão AJ-010 — foto da pessoa e envio ao terminal facial

A solicitação do Product Owner no AJ-010 (`docs/021`) retoma este ADR para um caso de uso específico: o porteiro fotografa a pessoa no cadastro rápido e envia essa foto ao terminal facial da implantação. Esta revisão é **proposta**; as seções 3 a 20 continuam valendo para tudo o que não estiver dito aqui.

## 21.1 O que passa a ser permitido

- capturar uma foto atual da pessoa no cadastro rápido, validada no servidor (formato, tamanho, dimensões, brilho) e guardada no armazenamento privado já usado para a foto da pessoa, com versão (checksum), autoria e instante;
- manter a foto da pessoa separada da frente, do verso e do recorte do retrato do documento; as imagens do documento continuam temporárias e não são guardadas;
- registrar na outbox o pedido de envio dessa foto ao terminal escolhido, com idempotência por credencial, terminal e versão da foto;
- o worker reler a foto pela referência privada, conferir pessoa, implantação, versão e checksum, reduzir a imagem em memória (JPEG, lado máximo 640 px) e enviá-la pelo adaptador.

## 21.2 O que continua proibido

- o SDV gerar, receber ou guardar template biométrico; o template, se existir, é gerado e mantido pelo terminal (§6);
- a foto ou o envio concederem acesso: autorização, validade, expiração e revogação seguem os fluxos próprios, e enviar foto nunca aciona relé;
- imagem, base64, CPF, URL privada ou senha do terminal em log, auditoria, payload da outbox ou frontend; o payload leva apenas identificadores e a versão da foto;
- apresentar “face sincronizada” quando o terminal só confirmou o usuário: a tela mostra cadastro, foto e terminal em linhas separadas;
- tratar timeout como sucesso.

## 21.3 Condições para ativação operacional

O envio fica **desligado por padrão** e só sai do SDV quando todas as condições abaixo forem verdadeiras ao mesmo tempo:

1. bandeira `SDV_INTEGRACAO_FOTO_FACIAL=true` (`config('integracoes.foto_facial_habilitada')`);
2. capacidade `credencial_facial` marcada como homologada no perfil do modelo e firmware (`config/integracoes.php`, `perfis_homologados`), seguindo o procedimento do `docs/015` §8;
3. homologação de bancada específica do envio de foto (roteiro em `docs/021` §10), com o resultado registrado no `docs/016`;
4. decisões de dados formalizadas pelo Product Owner: finalidade e base legal (PEN-ADR-013-001), texto de transparência ao titular, retenção e descarte da foto e da face no terminal (PEN-ADR-013-004) e alternativa não biométrica (PEN-ADR-013-006);
5. remoção da face junto com o usuário confirmada em bancada, para que revogação e expiração apaguem a face do terminal.

Enquanto qualquer condição faltar, a tela mostra **“Foto salva; envio facial pendente”** e o cadastro, a foto e o envio do usuário ao terminal continuam funcionando.

## 21.4 Protocolo previsto

Operação `enviar_foto_facial` no adaptador Hikvision (`app/Integracoes/Adaptadores/Hikvision`), usando `PUT /ISAPI/Intelligent/FDLib/FDSetUp?format=json` em multipart (parte JSON `FaceDataRecord` com `faceLibType=blackFD`, `FDID=1`, `FPID` = número do usuário no terminal, e parte de imagem JPEG). O caminho consta da documentação oficial ISAPI; os nomes das partes, o limite de tamanho (provisório: 200 KB) e a remoção da face junto com o usuário **ainda precisam ser confirmados** no guia do firmware V3.18.0 e na bancada. O contrato está coberto pelo simulador e por testes com `Http::fake`; não há envio real homologado.

## 21.5 Critérios de aceite adicionais

**CA-ADR-013-011:** a foto da pessoa é distinta das imagens do documento.
**CA-ADR-013-012:** o SDV não guarda template.
**CA-ADR-013-013:** o envio exige bandeira, capacidade homologada e cadastro do usuário já confirmado no terminal.
**CA-ADR-013-014:** foto nova invalida o estado sincronizado da versão anterior.
**CA-ADR-013-015:** revogação confirmada marca a face como removida.
**CA-ADR-013-016:** foto e envio não concedem acesso nem acionam relé.

## Situação do ADR

**Proposto (revisão AJ-010).** O adiamento de 30/07/2026 continua valendo para qualquer uso fora do §21. O §21 está implementado e desligado; a ativação depende das condições do §21.3 e de aprovação do Product Owner nesta tabela.
