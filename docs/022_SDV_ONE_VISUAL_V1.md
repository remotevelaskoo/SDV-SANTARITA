# SDV One — atualização visual v1 (Modo Portaria)

**Produto:** SDV Access — Implantação Santa Rita  
**Proposta de marca:** SDV One · Soluções do Vale  
**Data:** 08/11/2026  
**Estado:** Implementada apenas em branch de revisão, sem merge na `main`.

## Backup e reversão

- **Snapshot integral do código anterior:** `backup/sdv-access-antes-sdv-one-20261108`
- **Commit de origem da main:** `3cb3b18c2b54a67e6eab7e4580591572067f470d`
- **Proposta visual:** `design/sdv-one-interface-v1`
- A alteração não substitui arquivos da `main`, não altera tabelas e não executa migrações de produção.
- A branch de backup contém o histórico e os arquivos de código, **não** constitui backup de dados de uma eventual instalação em produção. O banco real deve ser copiado separadamente, caso exista.

Revisar as duas versões lado a lado antes de aprovar o PR. Para visualizar o código antigo, abrir a branch de backup no GitHub; não executar `reset --hard` em instalações em uso.

## Escopo da implementação

- Logo escolhida para SDV One, com símbolo vetorial derivado da imagem fornecida, sem alterar dados ou permissões;
- menu com gradiente azul-marinho, item ativo destacado e suporte à navegação compacta / gaveta já existente no AJ-009;
- cabeçalho com nova hierarquia visual, preservando perfil, posto, status e controles existentes;
- hero Modo Portaria com referência arquitetônica decorativa em CSS, sem depender de câmera ou conexão externa;
- caixa com destaque visual, mantendo saldo/abertura/responsável e botão da rota original;
- atalhos reorganizados em grade, mantendo as rotas existentes: o **Cadastro rápido** leva à Validação de entrada, onde seu fluxo já existe (não inventa uma rota);
- três indicadores **reais**: entradas e saídas do dia e pré-cadastros aguardando; todos respeitam o escopo de implantação;
- avisos com acesso à fila de análise apenas quando o usuário tem `pre-registro.analisar`;
- atendimentos recentes e componentes de conferência de documentos preservados;
- liberação manual dos relés preservada, inclusive sua exigência de permissão.

## Limites e próximos ajustes

- A busca global continua desabilitada como antes; o redesign **não** inventa uma funcionalidade de busca.
- Na continuação **v2 (PR #78)**, o status de caixa e as notificações do cabeçalho foram substituídos por dados reais filtrados por permissão e implantação. O status de caixa próprio aparece apenas ao usuário autorizado; pré-cadastros são contabilizados apenas a quem pode analisá-los.
- A liberação física permanece desativada por padrão, dependendo da homologação do hardware.
- O projeto ainda usa o nome técnico interno SDV Access onde necessário; esta fase altera principalmente a identidade visível.
- O novo layout foi inspirado na imagem de referência aprovada, mas substitui a fotografia arquitetônica por decoração leve em CSS para evitar dependências externas.
- As demais telas continuam funcionais e utilizam a nova navegação/identidade; revisão visual detalhada de cada módulo deve ocorrer em etapas posteriores.

## Critérios de aceite

1. Sem regressão de permissões, rotas, upload, auditoria ou operações de caixa.
2. Menu expandido/compacto e gaveta no celular preservados.
3. Todos os atalhos permitidos seguem para seus destinos existentes.
4. Os números apresentados ao operador têm origem em consulta real e não em exemplos fixos.
5. Executar `php artisan test`, `pnpm run test:js`, `pnpm run build`, `vendor/bin/pint --test`.
6. Testar responsividade e aprovar visualmente no iPad antes de incorporar à `main`.

**Nota:** o teste de criação de banco nesta branch executa apenas `migrate:fresh` em SQLite descartável. O erro preexistente do `PostoSeeder` quando `--seed` é executado está tratado separadamente no PR #72.

## Continuação v2 — dados reais no cabeçalho

- Novo `App\\Support\\OperationalHeaderState`: consulta situação do caixa por operador ou permissão consolidada e fila de pré-cadastros por implantação.
- O sininho só mostra indicador visual quando existe pendência real visível ao perfil; os eventos antes escritos diretamente na Blade foram removidos.
- Posto e implantação do menu de usuário refletem o contexto da sessão.
- O campo de busca continua sem funcionalidade e é identificado como **em desenvolvimento**; não é possível confundi-lo com busca pronta.
- `tests/Feature/SdvOneHeaderStateTest.php` cobre os dados do cabeçalho, permissões e isolamento de implantação.
- PR de revisão: https://github.com/remotevelaskoo/SDV-SANTARITA/pull/78 (depende do PR #77).

## Continuação v3 — Validação de Entrada e Pré-cadastros

Branch `design/sdv-one-validacao-precadastros-v3`, derivada da v2.

- A tela de Validação recebeu introdução operacional, etapas orientativas, contexto de atendimento e cards modernizados, sem alterar formulários, dados ou `wire:click`.
- O indicador legado **"Integração conectada"**, que era fixo e potencialmente enganoso, foi substituído por **"Sem acionamento nesta tela"**. A página continua em modo de demonstração e não aciona hardware.
- A fila de pré-cadastros recebeu painéis de status, pesquisa e abas com `aria-pressed` conforme o filtro ativo. Os filtros de tipo/período continuam desabilitados e identificados como indisponíveis.
- A análise individual recebeu hierarquia visual para protocolo, conferência de documentos protegidos, pendências e decisões. As regras de visibilidade e permissões foram mantidas.
- A aparência responsiva usa apenas CSS acrescentado ao arquivo existente: desktop, iPad e celular.
- As rotas, classes Livewire, banco, APIs e contratos de integração não foram modificados.
- Testes de regressão: `tests/Feature/SdvOneOperationalPagesTest.php`.
- **Obrigatório antes de integrar:** revisão visual real no Windows, testes de clique e comparação com o fluxo original. Os testes automatizados não equivalem à homologação de usabilidade nem do hardware.
