# Protótipo frontend — SDV Access

Protótipo navegável criado para validar visualmente os primeiros fluxos do SDV Access Santa Rita.

## Demonstração

[Abrir o protótipo publicado](https://sdv-access-santa-rita-demo.lucaspastorelli.chatgpt.site)

## Escopo implementado

- ativação inicial do terminal;
- login individual;
- escolha de perfil;
- abertura de turno e caixa do operador;
- validação de pessoa, veículo e acesso;
- ações de liberar, negar ou manter pendente;
- visão do administrador do cliente;
- gestão demonstrativa de permissões;
- console do administrador da plataforma;
- revogação e reativação demonstrativa de terminal;
- adaptação para telas menores.

Todos os dados e resultados são simulados no navegador. O protótipo não grava informações reais.

## Executar localmente

Pré-requisito: Node.js 22.13 ou superior.

```bash
npm install
npm run dev
```

Abra o endereço local informado no terminal.

## Verificar

```bash
npm run build
npm test
```

## Arquivos principais

- `app/page.tsx`: fluxos, estados e conteúdo das telas;
- `app/globals.css`: identidade visual e responsividade;
- `tests/rendered-html.test.mjs`: verificações do resultado compilado;
- `public/og.png`: imagem de apresentação do protótipo.

## Importante

Esta aplicação é um protótipo de UX e não define a tecnologia do produto final. O MVP seguirá a arquitetura aprovada em `docs/000_DIRETRIZES_DO_PROJETO.md`.
