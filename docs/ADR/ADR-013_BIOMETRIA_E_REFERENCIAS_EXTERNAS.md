# ADR-013 — BIOMETRIA E REFERÊNCIAS EXTERNAS

**Identificador:** ADR-013
**Versão:** 1.0.1
**Status:** Adiado
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

## Situação do ADR

**Adiado com aprovação formal.**
