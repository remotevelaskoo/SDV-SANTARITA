# ADR-015 — INTEGRAÇÃO EXTERNA: CONSULTA DE CEP (VIACEP)

**Identificador:** ADR-015
**Versão:** 1.0.0
**Status:** Aprovado
**Prioridade:** P2 — Condicional
**Produto:** SDV Access — Implantação Santa Rita
**Responsável pelo produto:** Vinicius Velasco de Azevedo
**Data:** 11/08/2026

---

## Controle de versões

| Versão | Data | Responsável | Alteração |
|---|---|---|---|
| 1.0.0 | 11/08/2026 | Product Owner | Registro retrospectivo da integração com o ViaCEP, já implementada no Pré-cadastro (P08) e no Cadastro de imóveis (P11) |

# 1. Contexto

O Pré-cadastro de visitante (P08) e o Cadastro de imóveis (P11) coletam CEP, endereço, número, complemento, bairro, cidade e estado. `docs/006_UX_UI_PRE_CADASTRO.md` (seção 10.3) já previa que, quando disponível, a busca por CEP deveria preencher os dados como sugestão, indicar falha e nunca impedir a continuação com preenchimento manual.

Durante o desenvolvimento dessas duas partes, foi integrado o serviço público ViaCEP (`https://viacep.com.br/ws/{cep}/json/`) para realizar essa busca automaticamente, disparada ao sair do campo CEP (`wire:blur`).

# 2. Problema

A integração introduz um serviço externo à aplicação — item que `docs/ADR/000_CATALOGO_DE_ADRS.md` (seção 4) marca como gatilho obrigatório de ADR ("introduzir serviço externo"). A implementação ocorreu antes do registro formal da decisão. Este ADR formaliza retrospectivamente o que já está em produção nas branches mescladas, conforme previsto na seção 9 do catálogo ("decisões emergenciais deverão ser registradas retrospectivamente assim que o serviço estiver estabilizado").

# 3. Forças e restrições

- o preenchimento manual do endereço já é suportado e deve continuar funcionando sem exceção;
- o serviço é gratuito, público, sem autenticação e sem SLA contratado;
- o dado enviado (CEP) não é, isoladamente, um identificador de uma pessoa específica;
- o protótipo não deve tratar o retorno do serviço como dado confirmado (conforme RN aplicável de endereço informado);
- a decisão não pode travar o fluxo de pré-cadastro nem o cadastro de imóveis caso o serviço esteja indisponível.

# 4. Alternativas

| Alternativa | Motivo da não adoção |
|---|---|
| Não oferecer autofill de CEP | Contraria a especificação já aprovada em `006_UX_UI_PRE_CADASTRO.md` (10.3) e piora a experiência de preenchimento |
| Base de CEPs própria, replicada localmente | Custo de manutenção e atualização incompatível com a fase de protótipo; sem ganho relevante frente a um serviço público já consolidado |
| Provedor de CEP pago com SLA | Sem justificativa de custo nesta fase demonstrativa; pode ser reavaliado se o volume ou a criticidade justificarem |
| ViaCEP (adotado) | Gratuito, amplamente usado no ecossistema Brasil, resposta simples em JSON, sem necessidade de credencial |

# 5. Decisão

Adotar o ViaCEP como fonte de sugestão de endereço a partir do CEP informado, com:

- chamada HTTP síncrona com timeout curto (5 segundos);
- disparo apenas quando o CEP possuir 8 dígitos válidos;
- preenchimento de logradouro, bairro, cidade e estado como sugestão, mantendo os campos editáveis;
- indicação explícita de falha (`zipCodeLookupFailed`) sem bloquear a continuação do fluxo;
- nenhuma persistência do payload bruto retornado pelo serviço;
- reutilização do mesmo padrão nos dois pontos que coletam CEP (P08 e P11), evitando implementação divergente.

# 6. Justificativa

O ViaCEP é o serviço mais adotado no mercado brasileiro para esse fim, não exige credencial ou custo, e o padrão de degradação graciosa adotado preserva a regra já registrada em `006_UX_UI_PRE_CADASTRO.md` de que a consulta externa é auxiliar, não autoritativa.

# 7. Consequências positivas

- reduz erro de digitação e tempo de preenchimento do endereço;
- mesmo padrão reutilizado nas duas telas que coletam CEP, reduzindo divergência de comportamento;
- nenhuma dependência de credencial ou segredo a gerenciar (fora do escopo do ADR-009).

# 8. Consequências negativas

- dependência de disponibilidade de um serviço público sem SLA contratado;
- acoplamento a um formato de resposta de terceiro, sujeito a mudança sem aviso;
- chamada de rede síncrona no ciclo de vida do componente Livewire (ainda que com timeout curto).

# 9. Riscos e mitigações

| Risco | Mitigação |
|---|---|
| Serviço indisponível ou lento | Timeout de 5s e captura de exceção; falha não impede preenchimento manual nem envio do formulário |
| CEP não encontrado | Alerta explícito (`zipCodeLookupFailed`) sem apagar dados já digitados |
| Mudança no contrato de resposta do ViaCEP | Uso de acesso defensivo aos campos (`?:` mantendo valor atual) e cobertura de teste com `Http::fake()` |
| Uso indevido como dado confirmado | Endereço permanece editável; UX já trata como sugestão, não como verdade absoluta |

# 10. Segurança e privacidade

- o único dado enviado ao serviço externo é o CEP (8 dígitos numéricos), que isoladamente não identifica uma pessoa;
- nenhum nome, CPF, telefone ou e-mail é enviado ao ViaCEP;
- a chamada ocorre por HTTPS;
- não há armazenamento do payload bruto de resposta, apenas dos campos já normalizados nos campos de formulário existentes;
- a finalidade e retenção do endereço informado no pré-cadastro seguem dependentes de `PEN-RNG-006` (`009_REGRAS_DE_NEGOCIO.md`), que este ADR não resolve.

# 11. Impacto operacional

- nenhuma infraestrutura adicional é necessária (sem fila, sem cache dedicado, sem novo serviço a operar);
- não há métrica ou alerta dedicado no momento; caso o volume de uso cresça, `ADR-010` (Observabilidade) pode ser estendido para monitorar taxa de falha da consulta;
- reversível a qualquer momento: remover a chamada não afeta persistência nem integridade de dados existentes.

# 12. Estratégia de implementação

- `PublicPreRegistration::lookupZipCode()` e `PropertyManagement::lookupZipCode()` seguem o mesmo padrão;
- disparo via `wire:blur` no campo CEP;
- indicador de carregamento (`wire:loading`) e alerta de falha específicos por tela.

# 13. Validação

- testes automatizados com `Http::fake()` cobrindo sucesso e falha de busca, em ambas as telas;
- verificação manual com CEPs reais (01310-100 e 20040-020) confirmando preenchimento correto em ambiente de desenvolvimento.

# 14. Rastreabilidade

- `RN-002`, `RN-060` (`009_REGRAS_DE_NEGOCIO.md`);
- `006_UX_UI_PRE_CADASTRO.md`, seções 10.3 e 10.5;
- `007_UX_UI_CADASTRO_IMOVEL.md`, seção 8.2.

# 15. Dependências

Nenhuma dependência de outro ADR para operar. Não bloqueia nem é bloqueado por decisões pendentes do conjunto `ADR-001` a `ADR-014`.

# 16. Pendências

| PEN-ADR-015 | Pendência |
|---|---|
| PEN-ADR-015-001 | Definir se, em produção, a consulta deverá ter cache local de curta duração para reduzir chamadas repetidas ao mesmo CEP |
| PEN-ADR-015-002 | Avaliar monitoramento de taxa de falha do ViaCEP caso o volume justifique |
| PEN-ADR-015-003 | Reavaliar necessidade de provedor pago com SLA se a criticidade do fluxo aumentar |

Este ADR não resolve `PEN-RNG-006` (finalidade do endereço informado no pré-cadastro), que permanece em aberto em `009_REGRAS_DE_NEGOCIO.md`.

# 17. Aprovação

| Papel | Nome | Decisão | Data |
|---|---|---|---|
| Product Owner | Vinicius Velasco de Azevedo | Aprovado | 11/08/2026 |
| Responsável técnico | Soluções do Vale Tecnologia | Recomendado | 11/08/2026 |

## Situação do ADR

**Aprovado, registrado retrospectivamente.** A integração com o ViaCEP já está em produção no Pré-cadastro (P08) e no Cadastro de imóveis (P11), com degradação graciosa e sem bloqueio do preenchimento manual.
