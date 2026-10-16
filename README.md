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

21 de 28 partes do plano concluídas (75%). A P28 formaliza a operação híbrida local e nuvem, necessária para que a portaria continue funcionando durante uma queda da internet. As integrações físicas ainda dependem da definição e homologação de câmeras, portões e leitores — ver o [Plano de divisão e acompanhamento do desenvolvimento](docs/013_PLANO_DE_DIVISAO_DO_DESENVOLVIMENTO.md) para a situação detalhada de cada parte.

O ajuste AJ-005, iniciado em outubro de 2026, adiciona captura estruturada de RG/CIN e CNH e uma prova interna de OCR assistido no pré-cadastro. Nome, CPF, nascimento e número do documento são apresentados como sugestões com confiança e confirmação humana. A foto impressa pode ser localizada e recortada apenas para conferência visual; selfie, reconhecimento facial e integração com equipamentos permanecem capacidades separadas.

## Coordenação da equipe

O desenvolvimento foi dividido em partes para que Lucas e Vinicius possam trabalhar em paralelo. O quadro com situação, responsável, dependências e orientação de trabalho está em:

- [Plano de divisão e acompanhamento do desenvolvimento](docs/013_PLANO_DE_DIVISAO_DO_DESENVOLVIMENTO.md)
- [UX/UI de Relatórios — P16](docs/014_UX_UI_RELATORIOS.md)

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
