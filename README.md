# SDV Santa Rita

Repositório oficial do projeto **SDV Access – Santa Rita**, desenvolvido pela **Soluções do Vale**.

## Objetivo

Centralizar a documentação funcional, técnica e operacional do produto, incluindo visão do produto, regras de negócio, UX/UI, arquitetura, banco de dados, APIs, testes, implantação e manuais.

## Protótipo navegável

A primeira versão navegável do frontend está disponível em:

- [Abrir demonstração do SDV Access](https://sdv-access-santa-rita-demo.lucaspastorelli.chatgpt.site)
- [Código-fonte do protótipo](./prototype/)
- [Especificação funcional e instruções de execução](./docs/002_PROTOTIPO_FRONTEND.md)

O protótipo foi criado para validar a experiência do usuário, o fluxo de autenticação e a separação de responsabilidades entre os perfis. Ele utiliza dados simulados e ainda não possui backend, banco de dados, autenticação real ou integração com equipamentos.

> A tecnologia usada no protótipo é apenas um meio de validação visual. A implementação do MVP seguirá as decisões técnicas registradas nas diretrizes oficiais do projeto.

## Perfis representados

- **Operacional:** valida entradas, consulta pessoas e veículos e registra decisões durante o turno.
- **Administrador do cliente:** administra usuários, permissões e configurações do condomínio.
- **Administrador da plataforma:** executa manutenção, atualizações, suporte e gestão técnica global.

## Estrutura atual

```text
.
├── prototype/                         # protótipo navegável do frontend
├── docs/
│   ├── 000_DIRETRIZES_DO_PROJETO.md
│   ├── 001_VOLUME_01_PRODUCT_BOOK_PARTE_01.md
│   ├── 001_VOLUME_01_PRODUCT_BOOK_PARTE_02.md
│   ├── 001_VOLUME_01_PRODUCT_BOOK_PARTE_03.md
│   ├── 002_PROTOTIPO_FRONTEND.md
│   └── references/                    # referências visuais aprovadas
└── README.md
```

## Estrutura documental prevista

```text
docs/
├── Product Book
├── Brand Book
├── Design System
├── UX/UI
├── Regras de negócio
├── Banco de dados
├── APIs
├── Arquitetura
├── Guia do desenvolvedor
├── Implantação
├── Plano de testes
├── manuals/
└── adr/
```

## Princípio central

O modelo de negócio do SDV Access é centrado no **imóvel**, ao qual são vinculados moradores, inquilinos, visitantes, prestadores e veículos.

## Status

Produto em definição e documentação. Protótipo navegável v0.1 disponível para validação.
