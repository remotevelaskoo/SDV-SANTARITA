# SDV Santa Rita — Diretrizes Oficiais do Projeto

**Documento:** SDV-DIR-000  
**Versão:** 1.0.0  
**Status:** Aprovado  
**Data:** Julho/2026  
**Proprietária:** Soluções do Vale  
**Produto:** SDV Access — Implantação Santa Rita

## 1. Objetivo

Este documento registra as diretrizes obrigatórias para a produção da documentação, definição de produto, UX/UI, arquitetura, banco de dados, APIs, desenvolvimento, testes e implantação do SDV Santa Rita.

## 2. Fonte oficial de referência

As imagens, telas, fluxos, referências visuais e decisões encaminhadas e aprovadas pelo Product Owner durante o levantamento são consideradas **fonte oficial de verdade visual e funcional** do projeto.

Toda documentação e implementação deverá:

- seguir fielmente a estrutura, organização, identidade e comportamento representados nas referências aprovadas;
- preservar os módulos, hierarquias, campos e fluxos previamente definidos;
- evitar alterações de layout, nomenclatura ou comportamento sem registro formal;
- não substituir as referências aprovadas por modelos genéricos de mercado;
- utilizar as imagens como base principal para os documentos de UX/UI e critérios de aceite visual.

## 3. Regra de desenvolvimento

O desenvolvimento seguirá obrigatoriamente esta ordem:

1. Definição do produto;
2. Regras de negócio;
3. UX/UI;
4. Aprovação das telas;
5. Modelagem de banco de dados;
6. Definição das APIs;
7. Arquitetura técnica;
8. Desenvolvimento;
9. Testes e homologação;
10. Implantação.

Nenhuma tela deverá ser implementada antes da aprovação de sua especificação visual e funcional.

## 4. Decisões técnicas aprovadas

- Backend: Laravel;
- Frontend: Blade + Livewire;
- Banco de dados: PostgreSQL;
- Armazenamento: serviço compatível com S3;
- OCR e Inteligência Artificial: Python/FastAPI somente quando necessário;
- Implantação: Docker;
- React não será utilizado no MVP, salvo decisão formal posterior.

## 5. Modelo central do domínio

O cadastro central do sistema será o **Imóvel**, e não a pessoa.

```text
Condomínio
├── Blocos, quando aplicável
└── Imóveis
    ├── Moradores
    ├── Inquilinos
    ├── Outros ocupantes
    ├── Visitantes
    ├── Prestadores vinculados
    └── Veículos
```

Regras essenciais:

- o endereço pertence ao imóvel;
- um imóvel pode possuir múltiplos moradores;
- inquilinos possuem data inicial e final de vínculo;
- o término do vínculo do inquilino revoga automaticamente seu acesso;
- visitantes pertencem a um responsável vinculado ao imóvel;
- prestadores pertencem a empresas ou contratos de prestação de serviço;
- o histórico de ocupação não deverá ser perdido após alterações de moradores ou inquilinos.

## 6. Autonomia documental

A documentação será produzida e publicada progressivamente no repositório sem necessidade de aprovação intermediária para cada capítulo, desde que respeite integralmente:

- as decisões já aprovadas;
- as referências visuais encaminhadas;
- a arquitetura definida;
- as regras de negócio consolidadas;
- o escopo da versão documentada.

Dúvidas não críticas deverão ser tratadas por decisão conservadora, mantendo compatibilidade com as referências aprovadas. Contradições relevantes deverão ser registradas como pendência, sem substituir silenciosamente uma decisão existente.

## 7. Controle de mudanças

Mudanças futuras deverão ser registradas por meio de:

- atualização do documento correspondente;
- histórico de versões;
- requisito impactado;
- ADR, quando envolver decisão arquitetural;
- atualização dos critérios de aceite;
- atualização dos documentos dependentes.

## 8. Documentos previstos

```text
docs/
├── 000_DIRETRIZES_DO_PROJETO.md
├── 001_PRODUCT_BOOK.md
├── 002_BRAND_BOOK.md
├── 003_DESIGN_SYSTEM.md
├── 004_UX_UI_DASHBOARD.md
├── 005_UX_UI_VALIDACAO.md
├── 006_UX_UI_PRE_CADASTRO.md
├── 007_UX_UI_CADASTRO_IMOVEL.md
├── 008_ADMINISTRACAO.md
├── 009_REGRAS_DE_NEGOCIO.md
├── 010_BANCO_DE_DADOS.md
├── 011_APIS.md
├── 012_ARQUITETURA.md
├── 013_MANUAL_DO_DESENVOLVEDOR.md
├── 014_DEPLOY.md
├── 015_PLANO_DE_TESTES.md
├── 016_MANUAL_DA_PORTARIA.md
├── 017_MANUAL_DO_ADMINISTRADOR.md
├── 018_MANUAL_DO_MORADOR.md
└── ADR/
```

## 9. Status

Estas diretrizes são consideradas aprovadas e obrigatórias para todas as etapas seguintes do SDV Santa Rita.
