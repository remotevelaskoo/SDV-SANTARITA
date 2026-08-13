# ADR-011 — PYTHON/FASTAPI PARA OCR OU IA

**Identificador:** ADR-011
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
| 1.0.0 | 30/07/2026 | Product Owner | Registro e aprovação do adiamento de Python/FastAPI |
| 1.0.1 | 12/08/2026 | Product Owner | Registro da importação assistida como caso futuro, sem ativar Python/FastAPI |

# 1. Contexto

OCR poderá auxiliar documentos e placas. IA poderá surgir em capacidades futuras. A diretriz aprovada permite Python/FastAPI somente quando necessário.

# 2. Problema

Ainda não há caso aprovado, volume, modelo, política de dados ou ganho mensurado que justifique um serviço adicional.

# 3. Decisão

**Adiar a introdução de Python/FastAPI.**

Enquanto adiado:

- conferência manual é o caminho obrigatório;
- Laravel continua responsável pelo fluxo;
- OCR não valida documento;
- selfie não cria biometria;
- nenhuma imagem é enviada a serviço de IA;
- nenhum serviço Python entra no deploy.

# 4. Condições de retomada

- caso de uso aprovado;
- precisão mínima;
- volume;
- latência;
- custo;
- política de privacidade;
- retenção;
- segurança;
- dataset autorizado;
- caminho manual;
- responsável pelo modelo;
- monitoramento.

# 5. Alternativas futuras

| Alternativa | Avaliação necessária |
|---|---|
| biblioteca no Laravel | capacidade e manutenção |
| API externa | privacidade, custo e lock-in |
| serviço Python interno | operação e modelos |
| processamento no equipamento | contrato e evidência |

# 6. Contrato futuro

Se aprovado:

- API autenticada;
- rede restrita;
- correlação;
- arquivo por URL temporária;
- timeout;
- idempotência;
- versão do modelo;
- confiança;
- resultado estruturado;
- descarte temporário.

# 7. Limites funcionais

- resultado é sugestão;
- humano confirma quando exigido;
- limiar é configurado;
- valor original preservado;
- correção humana auditada;
- falha usa fluxo manual;
- IA não concede autorização.

# 8. Dados

- minimização;
- finalidade;
- proibição de treino não autorizado;
- ausência de retenção pelo fornecedor;
- criptografia;
- acesso mínimo;
- arquivos validados;
- descarte;
- registro de processamento.

# 9. Segurança

- segredo no ADR-009;
- sem endpoint público irrestrito;
- limite de tamanho;
- tipo validado;
- proteção contra arquivo malicioso;
- isolamento de processo;
- dependências verificadas;
- logs sem imagem ou documento.

# 10. Operação

- container separado;
- health check;
- fila própria;
- recursos limitados;
- observabilidade;
- versionamento;
- rollback do modelo;
- fallback manual.

# 11. Validação

- conjunto de teste autorizado;
- métricas por categoria;
- falsos positivos/negativos;
- desempenho;
- vieses aplicáveis;
- segurança;
- custo;
- homologação humana.

# 12. Consequências

Positivas:

- evita complexidade e risco prematuros;
- mantém entrega manual possível;
- preserva privacidade.

Negativas:

- trabalho manual maior;
- automação de OCR adiada;
- prazo futuro para prova técnica.

# 13. Critérios de aceite

**CA-ADR-011-001:** Python não integra o MVP atual.
**CA-ADR-011-002:** conferência manual permanece.
**CA-ADR-011-003:** OCR não valida automaticamente.
**CA-ADR-011-004:** IA não concede acesso.
**CA-ADR-011-005:** imagens não são enviadas sem política.
**CA-ADR-011-006:** retomada exige caso aprovado.
**CA-ADR-011-007:** resultado futuro inclui confiança e versão.
**CA-ADR-011-008:** fallback manual é obrigatório.
**CA-ADR-011-009:** treino com dados depende de autorização.
**CA-ADR-011-010:** ativação exige nova versão deste ADR.

# 14. Rastreabilidade

- `RN-074`, `RN-075`, `RN-086`, `RN-087`;
- `PEN-019` do Product Book;
- ADR-006 e ADR-009.

# 15. Pendências

| PEN-ADR-011 | Pendência |
|---|---|
| PEN-ADR-011-001 | Caso de uso e escopo |
| PEN-ADR-011-002 | Política de dados |
| PEN-ADR-011-003 | Metas de precisão |
| PEN-ADR-011-004 | Tecnologia/modelo |
| PEN-ADR-011-005 | Infraestrutura e custo |
| PEN-ADR-011-006 | Fontes, volume e campos da importação assistida |

# 16. Aprovação

| Papel | Nome | Decisão | Data |
|---|---|---|---|
| Product Owner | Vinicius Velasco de Azevedo | Aprovado o adiamento | 30/07/2026 |
| Responsável técnico | Soluções do Vale Tecnologia | Recomendado | 30/07/2026 |

# 17. Condição de retomada

Voltará a **Proposto** após aprovação do caso, política de dados e prova técnica.

O caso de uso de importação assistida de documentos, imagens, planilhas e dados legados está aprovado como necessidade futura do produto. Essa aprovação funcional não ativa Python/FastAPI nem autoriza envio de arquivos a terceiros. Antes da implementação deverão ser definidos escopo, fontes, volume, política de dados, métricas de qualidade e prova técnica.

Quando retomado, o resultado permanecerá em área de preparação, com confiança, versão, fonte, candidatos a duplicidade e revisão humana obrigatória. A gravação canônica continuará sob responsabilidade do Laravel e das regras de domínio existentes.

# 18. Decisão resultante

Python/FastAPI permanece fora do MVP atual. O fluxo manual é obrigatório.

## Situação do ADR

**Adiado com aprovação formal.**
