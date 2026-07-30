# ADR-009 — GESTÃO E ROTAÇÃO DE SEGREDOS

**Identificador:** ADR-009
**Versão:** 1.0.0
**Status:** Aprovado
**Prioridade:** P0 — Bloqueador
**Produto:** SDV Access — Implantação Santa Rita
**Responsável pelo produto:** Vinicius Velasco de Azevedo
**Data:** 30/07/2026

---

## Controle de versões

| Versão | Data | Responsável | Alteração |
|---|---|---|---|
| 1.0.0 | 30/07/2026 | Product Owner | Criação e aprovação da gestão e rotação de segredos |

# 1. Contexto

A plataforma usará credenciais para PostgreSQL, S3, serviço compatível com Redis, e-mail, equipamentos, APIs, criptografia e observabilidade.

# 2. Problema

Segredos não podem aparecer no código, imagem, banco operacional, frontend, logs, exports ou documentação. Também precisam ser rotacionados sem interromper indevidamente a operação.

# 3. Decisão

Adotar:

- cofre de segredos ou mecanismo gerenciado equivalente em produção;
- injeção em tempo de execução;
- referências de segredo na configuração;
- segredos distintos por ambiente;
- menor privilégio;
- rotação documentada;
- auditoria de administração;
- detecção de vazamento;
- revogação e resposta a incidentes;
- fornecedor definido na infraestrutura.

# 4. Alternativas rejeitadas

| Alternativa | Motivo |
|---|---|
| segredo no `.env` versionado | exposição no Git |
| segredo em tabela em texto claro | risco e acesso amplo |
| segredo na imagem Docker | imutabilidade torna vazamento persistente |
| um segredo para todos os clientes | raio de impacto |
| exibir valor após cadastro | exposição desnecessária |

# 5. Classificação

Segredos incluem:

- senhas de banco;
- tokens e chaves de API;
- credenciais S3;
- credenciais Redis;
- certificados privados;
- chaves de assinatura;
- credenciais de equipamentos;
- chaves de criptografia;
- tokens de webhook.

Senha de usuário será hash de autenticação, não segredo recuperável.

# 6. Armazenamento

- valor no cofre;
- configuração guarda referência;
- criptografia em repouso;
- TLS em trânsito;
- controle de acesso por workload;
- backup e recuperação do cofre;
- metadados sem conteúdo;
- proibição de fallback em texto claro.

# 7. Ambientes

- desenvolvimento usa segredos locais não versionados;
- testes usam valores descartáveis;
- homologação não reutiliza produção;
- produção usa identidade própria;
- nenhum segredo cruza ambiente;
- rotação em um ambiente não altera outro.

# 8. Escopo

Sempre que possível:

- por serviço;
- por implantação;
- por adaptador;
- por equipamento;
- por finalidade;
- com permissões mínimas;
- com validade.

Credencial compartilhada exige justificativa.

# 9. Entrega ao workload

- identidade da aplicação;
- secret mount, Docker secret ou API segura;
- memória apenas pelo tempo necessário;
- arquivos temporários com permissão restrita;
- nenhuma saída em comando ou diagnóstico;
- falha segura se ausente;
- recarga ou restart controlado.

# 10. Interface administrativa

Ao cadastrar:

- campo mascarado;
- valor enviado por canal seguro;
- nunca retornar depois;
- mostrar apenas estado, referência e última rotação;
- substituição em vez de edição;
- teste explícito;
- auditoria sem valor.

# 11. Logs e erros

- redaction central;
- headers sensíveis removidos;
- query strings sanitizadas;
- payload externo filtrado;
- stack trace revisado;
- erro não repete segredo;
- testes automatizados de vazamento.

# 12. Rotação

Fluxo:

1. criar nova credencial;
2. autorizar novo valor;
3. distribuir referência;
4. testar;
5. promover;
6. observar;
7. revogar anterior;
8. auditar;
9. confirmar ausência de uso.

Quando suportado, haverá janela de sobreposição curta.

# 13. Rotação emergencial

- bloquear ou revogar;
- identificar escopo;
- gerar novo;
- atualizar workload;
- invalidar sessões/tokens relacionados;
- revisar logs;
- registrar incidente;
- comunicar responsáveis;
- validar recuperação.

# 14. Chaves de criptografia

- separadas dos dados;
- acesso mínimo;
- rotação planejada;
- versionamento da chave;
- material antigo preservado enquanto necessário para descriptografia;
- exclusão de chave tratada como operação destrutiva;
- recuperação testada.

# 15. Equipamentos

- credencial por implantação/equipamento quando possível;
- SDK não recebe segredo além do necessário;
- frontend nunca recebe;
- callback possui segredo próprio;
- rotação considera disponibilidade física;
- equipamento perdido é revogado.

# 16. CI/CD

- pipeline usa identidade temporária quando possível;
- segredo não entra em artifact;
- logs mascarados;
- ambientes protegidos;
- acesso a produção aprovado;
- scanner de segredos;
- commits bloqueados quando detectados.

# 17. Break-glass

Acesso emergencial:

- conta separada;
- MFA quando disponível;
- justificativa;
- prazo curto;
- alerta imediato;
- gravação de ações;
- revisão posterior;
- revogação automática.

# 18. Observabilidade

Monitorar:

- leitura de segredo;
- falha de acesso;
- segredo vencendo;
- rotação atrasada;
- uso do valor anterior;
- acesso fora do padrão;
- break-glass;
- vazamento detectado.

# 19. Testes

- aplicação inicia com referência;
- falha sem segredo;
- frontend não recebe valor;
- logs não contêm valor;
- rotação sem perda indevida;
- revogação funciona;
- ambiente isolado;
- menor privilégio;
- recuperação do cofre;
- equipamento revogado.

# 20. Consequências

Positivas:

- menor exposição;
- rotação rastreável;
- escopo limitado;
- imagens sem segredo.

Negativas:

- dependência do cofre;
- operação de rotação;
- integração com workloads;
- recuperação exige planejamento.

# 21. Riscos

| Risco | Mitigação |
|---|---|
| cofre indisponível | cache em memória controlado e continuidade definida |
| segredo em log | redaction e testes |
| chave excluída | proteção e aprovação |
| rotação quebrar integração | sobreposição e teste |
| acesso humano amplo | RBAC e auditoria |

# 22. Critérios de aceite

**CA-ADR-009-001:** nenhum segredo é versionado.
**CA-ADR-009-002:** imagens não contêm segredos.
**CA-ADR-009-003:** produção usa cofre ou equivalente.
**CA-ADR-009-004:** configurações guardam referências.
**CA-ADR-009-005:** ambientes não compartilham valores.
**CA-ADR-009-006:** frontend nunca recebe segredo persistido.
**CA-ADR-009-007:** logs são sanitizados.
**CA-ADR-009-008:** rotação é testável e auditada.
**CA-ADR-009-009:** credenciais antigas são revogadas.
**CA-ADR-009-010:** chaves de criptografia possuem recuperação.
**CA-ADR-009-011:** break-glass é excepcional.
**CA-ADR-009-012:** CI/CD não incorpora valores.
**CA-ADR-009-013:** menor privilégio é aplicado.
**CA-ADR-009-014:** fornecedor permanece desacoplado.

# 23. Rastreabilidade

- `RN-066`, `RN-090` a `RN-093`, `RN-100`;
- ADR-005, ADR-006 e ADR-007.

# 24. Pendências

| PEN-ADR-009 | Pendência |
|---|---|
| PEN-ADR-009-001 | Produto de cofre |
| PEN-ADR-009-002 | Política de rotação por categoria |
| PEN-ADR-009-003 | Estratégia de identidade dos workloads |
| PEN-ADR-009-004 | MFA e break-glass |
| PEN-ADR-009-005 | Scanner de segredos |

# 25. Aprovação

| Papel | Nome | Decisão | Data |
|---|---|---|---|
| Product Owner | Vinicius Velasco de Azevedo | Aprovado | 30/07/2026 |
| Responsável técnico | Soluções do Vale Tecnologia | Recomendado | 30/07/2026 |

## Situação do ADR

**Aprovado.** Segredos serão externos, referenciados, restritos e rotacionáveis.
