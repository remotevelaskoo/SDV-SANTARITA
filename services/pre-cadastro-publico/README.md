# SDV One — portal público de pré-cadastro

Serviço isolado para Vercel/Node.js, sem React e sem cópia do sistema administrativo. O núcleo continua Laravel/Livewire no condomínio. Especificação, situação real e ativação em [documento 023](../../docs/023_PRE_CADASTRO_PUBLICO_HIBRIDO.md).

## Desenvolvimento e testes

```sh
npm ci
npm test
npm run dev
```

O serviço usa variáveis do ambiente; para desenvolvimento com arquivo local: `node --env-file=.env dev.mjs`. Copie `.env.example`, configure somente dados de homologação e não comite credenciais. Não há banco fictício de contingência: sem configuração o endpoint responde 503.

`tests/portal.test.mjs` usa transporte simulado e imagens sintéticas. `tests/schema.test.mjs` executa o bootstrap no PostgreSQL embarcado PGlite e verifica permissões, RLS, quota e conflitos de versão. Isso não equivale à homologação de uploads ou sincronização em serviços remotos.

## Publicação

Crie um projeto **somente para este diretório**, com `framework: null`, instalação `npm ci` e saída `public`, preservando `api/` como Functions. Não aponte para a raiz Laravel. Use `vercel.json` deste diretório; não publique `.env`, testes ou SQL como arquivos estáticos.

Configure as variáveis de `.env.example` no backend, com segredos protegidos. A chave secreta do Supabase nunca é enviada ao visitante. Não habilite sem o projeto de banco isolado, catálogo real, aviso de privacidade e teste com o nó local.

A URL do convite tem formato `https://DOMINIO/#convite=SEGREDO`. O fragmento não entra na requisição inicial nem nos cabeçalhos de referência. O frontend envia o convite no cabeçalho somente à API do portal. O recibo também usa fragmento e não apresenta nome, CPF ou imagens. Não instalar analytics, pixels ou scripts de terceiros que possam ler esses fragmentos.

## Operação local

Depois de incorporar a branch ao nó local e aplicar sua migração:

```sh
php artisan pre-cadastro:sincronizar-nuvem
```

O scheduler existente executará o comando a cada minuto **somente** com `SDV_PRE_CADASTRO_NUVEM_ENABLED=true`. Configure `schedule:run` no servidor. Use a credencial exclusiva do nó; não copie a chave administrativa do Supabase para o computador da portaria.

A fila de análise já existente recebe a solicitação e seus arquivos no armazenamento privado. O comando não cria pessoa autorizada, credencial facial, cobrança ou liberação física. O resultado de uma decisão local é confirmado na nuvem com versão crescente; falhas ficam pendentes para nova tentativa.
