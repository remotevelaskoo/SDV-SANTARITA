# SDV Santa Rita

Repositório oficial do projeto **SDV Access – Santa Rita**, desenvolvido pela **Soluções do Vale**.

## Objetivo

Centralizar a documentação funcional, técnica e operacional do produto, incluindo visão do produto, regras de negócio, UX/UI, arquitetura, banco de dados, APIs, testes, implantação e manuais.

## Estrutura prevista

```text
docs/
├── 001_Product_Book.md
├── 002_Brand_Book.md
├── 003_Design_System.md
├── 004_UX_UI.md
├── 005_Business_Rules.md
├── 006_Database.md
├── 007_API.md
├── 008_Architecture.md
├── 009_Developer_Guide.md
├── 010_Deployment.md
├── 011_Test_Plan.md
├── manuals/
└── adr/
```

## Princípio central

O modelo de negócio do SDV Access é centrado no **imóvel**, ao qual são vinculados moradores, inquilinos, visitantes, prestadores e veículos.

## Status

21 de 28 partes do plano concluídas (75%); P17, P24 e P26 em andamento. A integração com o terminal facial Hikvision DS-K1T673DX-BR foi homologada em bancada (conexão, inventário, capacidades, imagem estática e comando do relé), com a operação real ainda desligada. Câmeras da portaria, cancela/catraca e leitura de placas ainda dependem de equipamento — ver o [Plano de divisão e acompanhamento do desenvolvimento](docs/013_PLANO_DE_DIVISAO_DO_DESENVOLVIMENTO.md).

A P28 formaliza a operação híbrida local e nuvem ([ADR-018](docs/ADR/ADR-018_OPERACAO_HIBRIDA_LOCAL_E_NUVEM.md)), necessária para que a portaria continue funcionando durante uma queda da internet.

O ajuste AJ-005, iniciado em outubro de 2026, adiciona captura estruturada de RG/CIN e CNH e uma prova interna de OCR assistido no pré-cadastro. Nome, CPF, nascimento e número do documento são apresentados como sugestões com confiança e confirmação humana. A foto impressa pode ser localizada e recortada apenas para conferência visual; selfie, reconhecimento facial e integração com equipamentos permanecem capacidades separadas.

Em 28 de outubro de 2026, a documentação foi reconciliada com a implementação original do João Lucas e com as sete fatias posteriores do AJ-006. Permanece aprovada a jornada de seis etapas: a frente do documento é obrigatória, o verso é solicitado quando aplicável, a selfie é uma evidência separada e o OCR é somente assistivo. As melhorias posteriores de orientação, qualidade e diagnóstico do OCR são cumulativas e não substituem a captura nem a conferência humana. O desvio de configuração que ocultou a etapa documental por padrão está registrado como correção necessária antes da homologação.

## Coordenação da equipe

O desenvolvimento foi dividido em partes para que Lucas e Vinicius possam trabalhar em paralelo. O quadro com situação, responsável, dependências e orientação de trabalho está em:

- [Plano de divisão e acompanhamento do desenvolvimento](docs/013_PLANO_DE_DIVISAO_DO_DESENVOLVIMENTO.md)
- [UX/UI de Relatórios — P16](docs/014_UX_UI_RELATORIOS.md)
- [AJ-006 — Ajustes operacionais da validação, do pré-cadastro e do envio aos terminais](docs/020_AJUSTES_OPERACIONAIS_VALIDACAO_E_PRE_CADASTRO.md)

## Base técnica do desenvolvimento

Esta primeira etapa utiliza:

- PHP `8.4`;
- Laravel `13`;
- Livewire `4`;
- Tailwind CSS `4`;
- Vite `8`;
- PostgreSQL será a fonte transacional nas etapas de backend;
- SQLite é usado somente para facilitar a execução local inicial.

## Ambiente local

Pré-requisitos:

- PHP 8.4 e Composer;
- Node.js 24 ou compatível;
- pnpm 11 ou npm compatível.

Instalação:

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
pnpm install
pnpm run build
```

Execução:

```bash
php artisan serve
```

O Dashboard estará disponível em `http://127.0.0.1:8000/dashboard`.

Verificações:

```bash
php artisan test
vendor/bin/pint --test
pnpm run build
```

Os indicadores do dashboard consultam dados reais do banco (pessoas, acessos, vínculos, veículos, caixa). Módulos que dependem de equipamento físico ainda não homologado (câmeras, portões, leitura de placas) informam claramente que não estão integrados, em vez de simular uma conexão.

## Integração com terminais faciais

Decisão: [ADR-016](docs/ADR/ADR-016_INTEGRACAO_DIRETA_TERMINAL_FACIAL_E_PONTO_DE_ACESSO.md). Detalhes em [SDV-INT-015](docs/015_FUNDACAO_INTEGRACAO_TERMINAIS_FACIAIS.md) (fundação) e [SDV-INT-016](docs/016_HOMOLOGACAO_FACIAL_HIKVISION.md) (homologação em bancada e passo a passo pela tela **Gestão › Equipamentos**).

Variáveis do `.env` (nunca coloque a senha do terminal no `.env` nem no Git):

| Variável | Padrão | Uso |
|---|---|---|
| `SDV_INTEGRACAO_SIMULADOR_PERMITIDO` | `true` fora de produção | permite o adaptador `simulador` |
| `SDV_INTEGRACAO_SEGREDO_CIFRADO` | `true` fora de produção | aceita a senha técnica digitada na tela, cifrada com `APP_KEY`; produção exige cofre (ADR-009) |
| `SDV_HOMOLOGACAO_FACIAL_HOST` | vazio | IP sugerido no formulário; cada equipamento guarda o seu |
| `SDV_INTEGRACAO_TESTE_RELE` | `false` | libera "Liberar acesso" somente na bancada, durante o teste do relé |
| `SDV_INTEGRACAO_ABERTURA_REMOTA` | `false` | abertura automática decidida pelo SDV; permanece desligada até validar o contato seco |
| `SDV_INTEGRACAO_LIBERACAO_OPERACIONAL` | `false` | liberação manual no Modo Portaria ([SDV-OPE-017](docs/017_LIBERACAO_MANUAL_OPERACIONAL.md), ADR-017); desligada até homologação e aprovação formal |
| `SDV_FUSO_OPERACIONAL` | `America/Sao_Paulo` | fuso da portaria; precisa ser igual a `APP_TIMEZONE` para enviar visitas de turista ([AJ-006](docs/020_AJUSTES_OPERACIONAIS_VALIDACAO_E_PRE_CADASTRO.md)) |
| `SDV_TURISTA_VALIDADE_HORAS` | `24` | validade da credencial do turista, com teto fixo de 24 horas |
| `SDV_REVOGACAO_REPETIR_MINUTOS` | `15` | intervalo para pedir de novo a revogação que ficou sem comunicação com o terminal |

O vencimento das visitas de turista roda pelo agendador (`php artisan schedule:run` no cron, a cada minuto), com o comando `acessos:processar-vencimentos`.

Depois de alterar o `.env`, rode `php artisan config:clear`. Firmwares e capacidades homologados ficam em `config/integracoes.php` (`hikvision.perfis_homologados`).
