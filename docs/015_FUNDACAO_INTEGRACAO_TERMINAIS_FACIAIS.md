# SDV Access — Fundação da integração com terminais faciais

**Documento:** SDV-INT-015
**Versão:** 1.0.1
**Status:** Implementado como fundação; integração real homologada em bancada em [SDV-INT-016](016_HOMOLOGACAO_FACIAL_HIKVISION.md)
**Data:** 27/09/2026
**Decisões de origem:** ADR-004, ADR-005, ADR-007, ADR-008, ADR-009, ADR-013 e ADR-016

## Atualização 1.0.1 (27/09/2026)

Este documento descreve a fundação como foi entregue e não foi reescrito. A etapa seguinte
([SDV-INT-016](016_HOMOLOGACAO_FACIAL_HIKVISION.md), ADR-016 §21) mudou os pontos abaixo:

- o adaptador Hikvision passou a falar com o terminal real (firmware V3.18.0 build 250115):
  conexão, inventário, capacidades, captura estática e comando da porta 1;
- as capacidades passaram a ter matriz de homologação por firmware (§3 do SDV-INT-016);
- a senha técnica também pode ser digitada na tela e gravada cifrada, **somente fora de
  produção** (decisão 4 abaixo continua valendo para produção);
- a administração ganhou a tela **Gestão › Equipamentos** (a pendência "Sem telas" do §6 foi
  resolvida para equipamentos; pontos de acesso seguem sem tela própria);
- novo status de equipamento `em_homologacao` e novo tipo de ponto `bancada`, que nunca é ativado;
- "teste do relé" separado da abertura remota operacional, que continua desligada.

## 1. O que esta entrega é

A base de software para ligar o SDV Access aos terminais Hikvision DS-K1T673DX-BR, sem conectar cancela ou catraca real e sem ativar biometria:

- cadastro de pontos de acesso (cancela ou catraca) e de equipamentos por implantação;
- vínculo exclusivo e com histórico entre terminal e ponto;
- inventário: fabricante, modelo, série, IP, porta, firmware, direção, situação e saúde;
- referência protegida à credencial técnica, sem guardar o valor;
- porta de integração independente do fabricante;
- adaptador Hikvision isolado e honesto, sem endpoints ISAPI;
- simulador contratual com todos os cenários do ADR-007 §14;
- outbox transacional, idempotência, lease, retentativa com backoff e reconciliação;
- estados explícitos de sincronização;
- inbox de eventos com deduplicação e instantes separados;
- logs e ocorrências sanitizados;
- auditoria das operações;
- testes de contrato, de regra e de arquitetura.

## 2. O que esta entrega não é

- Não fala com o terminal real. O adaptador Hikvision declara todas as capacidades como **ausentes** até o firmware ser inventariado e a documentação ISAPI compatível estar disponível (PEN-ADR-016-001 e PEN-ADR-016-002).
- Não aciona relé, cancela nem catraca. A abertura remota fica **desligada** por configuração (`SDV_INTEGRACAO_ABERTURA_REMOTA=false`) até a homologação do contato seco em bancada.
- Não trata biometria. Credencial do tipo `face` é recusada antes da outbox e de novo no processador, enquanto o ADR-013 estiver adiado.
- Não oferece operação offline automática (ADR-008 adiado).
- Não tem telas. A especificação em `docs/008` §21-25 está aprovada, mas as telas ficam para uma entrega própria, depois da validação desta base.

## 3. Estrutura

```text
app/Integracoes/
├── Dominio/            contrato estável, sem Laravel e sem fabricante
│   ├── Contratos/PortaEquipamentoAcesso.php
│   ├── Dados/          ContextoEquipamento, SegredoTecnico, ResultadoOperacao, ...
│   ├── Enums/          Capacidade, ResultadoEquipamento, EstadoSincronizacao, ...
│   └── Excecoes/
├── Aplicacao/          casos de uso: cadastro, outbox, processador, reconciliação
├── Infra/              registro de adaptadores, resolvedor de segredo, sanitizador
└── Adaptadores/
    ├── Hikvision/      único lugar onde o fabricante pode aparecer
    └── Simulador/
```

Tabelas novas (todas com `implantacao_id`, UUIDv7 e sem exclusão física no fluxo):
`pontos_acesso`, `equipamentos`, `equipamento_ponto_vinculos`, `equipamento_credenciais`, `equipamento_capacidades`, `referencias_externas`, `operacoes_integracao`, `integracao_ocorrencias`, `credencial_sincronizacoes` e `equipamento_eventos_recebidos`.

## 4. Fluxo de uma operação

```text
caso de uso (transação)
  → valida regra (elegibilidade já decidida pelo SDV, capacidade, política)
  → grava outbox com chave idempotente e hash do conteúdo
  → audita
commit
  → job com somente identificadores (fila integrations ou access-critical)
  → processador reserva a operação com lease
  → revalida equipamento, validade, capacidade, ADR-013 e abertura habilitada
  → chama a porta fora de transação
  → grava resultado, efeitos, ocorrência sanitizada e auditoria
```

Resultados: `confirmado`, `aceito`, `recusado`, `falha_tecnica`, `indisponivel`, `confirmacao_desconhecida`, `expirado`, `capacidade_ausente`. Só `confirmado` conta como sucesso.

| Situação | Comportamento |
|---|---|
| Mesma chave e mesmo conteúdo | devolve a operação existente |
| Mesma chave e conteúdo diferente | recusa (`PayloadDivergente`) |
| Terminal inalcançável | sincronização é reagendada com backoff e jitter; esgotadas as tentativas, intervenção |
| Timeout | `confirmacao_desconhecida`, sem reenvio; a reconciliação consulta o terminal, ou manda para intervenção se não houver como provar |
| Abertura com timeout ou worker interrompido | nunca é reenviada; consulta de resultado quando suportada, senão intervenção |
| Comando de abertura vencido | `expirado`, sem envio |
| Capacidade não verificada | `capacidade_ausente`, sem chamar o terminal |

## 5. Decisões tomadas nesta entrega

1. **Base na `main`.** A branch `codex/fundacao-mvp` tinha um esqueleto Laravel já superado pela `main`; dela foi aproveitado somente o ADR dos terminais.
2. **ADR renumerado para ADR-016.** O número ADR-015 já identifica a consulta de CEP na `main`.
3. **Vínculo 1:1 terminal ↔ ponto.** O ADR-016 exige que cada terminal atenda um só ponto; por decisão conservadora, esta base também limita um terminal vigente por ponto. Se um ponto precisar de um terminal de entrada e outro de saída, a regra deve ser revista.
4. **Segredo por referência.** Só é aceito o formato `env:NOME_EM_MAIUSCULAS`; `vault:` fica reservado até a escolha do cofre de produção (ADR-009 §3). Valores parecidos com senha são recusados no cadastro.
5. **IP privado obrigatório.** Endereço público ou nome de host são recusados (ADR-016 §8).
6. **Ativação exige** ponto vigente, referência de credencial (exceto simulador), capacidades consultadas e firmware inventariado.
7. **Troca de firmware** invalida as capacidades verificadas.
8. **`credencial_sincronizacoes.credencial_id` sem chave estrangeira.** A tabela `credenciais` ainda não existe; a FK deve ser criada junto com o módulo de credenciais.
9. **Eventos recebidos em tabela própria** (`equipamento_eventos_recebidos`), separada do futuro `eventos_acesso` do domínio: evento do terminal não é decisão de acesso.
10. **Contexto de implantação em jobs.** Foi adicionado `ImplantacaoContext::executarNo()`, porque jobs e comandos não têm sessão.
11. **Permissão.** A permissão existente `integracoes.gerenciar` é a candidata a proteger as telas desta área. Os serviços ainda não verificam permissão, como os demais serviços do projeto, que deixam essa checagem para o componente Livewire.

## 6. Contradições e pendências encontradas

| Item | Situação |
|---|---|
| `ADR-013` (PEN-ADR-013-007) e `docs/013` (AJ-003) ainda citam a BRAVAS | Contradiz o ADR-016; atualizar em revisão documental própria |
| Docker, Compose e guia só existem em `codex/fundacao-mvp` | Portar para a `main` em PR separado |
| Testes e ambiente local usam SQLite | 3 testes existentes falham no PostgreSQL (`AuditLogTest` compara coluna `json` com texto; 2 testes de duplicidade capturam violação de unicidade dentro da transação). Os testes novos passam nos dois bancos |
| Sem `credenciais` e `autorizacoes` | Sincronização recebe a credencial já decidida elegível; a FK e a origem da elegibilidade virão com esses módulos |
| Sem telas de pontos e equipamentos | Entrega seguinte (P17, fatias pendentes) |
| Comandos agendados | `integracoes:despachar` e `integracoes:reconciliar` existem, mas não foram agendados; agendar quando houver worker em produção |

## 7. Como validar localmente

```bash
composer install
pnpm install && pnpm run build
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate

php artisan test --filter=Integracoes
php artisan test
vendor/bin/pint --test
```

Contra PostgreSQL:

```bash
DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_DATABASE=sdv_test DB_USERNAME=... DB_PASSWORD=... php artisan test --filter=Integracoes
```

Exercício manual com o simulador (`php artisan tinker`):

```php
$cad = app(App\Integracoes\Aplicacao\CadastroEquipamentos::class);
$ponto = $cad->registrarPontoAcesso(['codigo' => 'CANCELA-01', 'nome' => 'Cancela principal', 'tipo' => 'cancela', 'direcao_suportada' => 'bidirecional']);
$terminal = $cad->registrarEquipamento([
    'nome' => 'Terminal portaria', 'tipo' => 'terminal_facial', 'fabricante' => 'Hikvision',
    'modelo' => 'DS-K1T673DX-BR', 'numero_serie' => 'BANCADA-01', 'endereco_rede' => '192.168.1.64',
    'protocolo' => 'isapi', 'firmware_versao' => 'a-inventariar', 'adaptador' => 'simulador', 'direcao' => 'entrada',
]);
$cad->vincularAoPonto($terminal, $ponto);
app(App\Integracoes\Aplicacao\DiagnosticoEquipamento::class)->consultarCapacidades($terminal);
```

Com `QUEUE_CONNECTION=database`, rode `php artisan queue:work --queue=access-critical,integrations` para processar.

## 8. Próximos passos para a integração real

1. Inventariar em bancada serial, firmware e capacidades do terminal.
2. Obter a documentação ISAPI oficial daquela versão de firmware.
3. Implementar as chamadas somente em `app/Integracoes/Adaptadores/Hikvision`, com cliente HTTP, autenticação e TLS conforme a documentação.
4. Registrar o firmware em `integracoes.hikvision.perfis_homologados` com as capacidades comprovadas.
5. Fazer o adaptador passar em `ContratoPortaEquipamentoTestCase` e testar em rede isolada, sem carga no relé.
6. Homologar o contato seco e a duração do pulso antes de habilitar `SDV_INTEGRACAO_ABERTURA_REMOTA`.
7. Retomar ADR-008 e ADR-013 antes de qualquer uso com pessoas reais.
