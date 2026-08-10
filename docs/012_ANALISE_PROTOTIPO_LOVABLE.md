# Análise do protótipo Lovable

## Origem e preservação

- Projeto Lovable: `Santarita System Hub` (`45f7c944-13fc-45d4-9e80-cc867049c1ed`).
- Repositório de sincronização: `lucaspastorelli/santarita-joyful-system`.
- Commit exportado: `958ffed80c10f393cdc13fe8a836b8323b0830c7`.
- Cópia bruta no repositório principal: branch local `codex/lovable-export`, diretório `prototype/lovable/`.
- O aplicativo do Lovable no GitHub foi limitado somente ao repositório de sincronização.

O código exportado é mantido como referência visual e funcional. Ele não será incorporado diretamente à aplicação principal porque sua arquitetura diverge das decisões aprovadas para o produto.

## Stack encontrada

- React 19 e TypeScript 5.8.
- TanStack Start, TanStack Router e TanStack Query.
- Tailwind CSS 4.
- Radix UI e componentes derivados do shadcn/ui.
- Recharts para gráficos.
- Bun como gerenciador de pacotes no projeto original.

O sistema oficial permanece em Laravel, Blade, Livewire e Tailwind, conforme a ADR-001. O porte deve reproduzir comportamento e aparência sem carregar React/TanStack para o MVP.

## Estrutura do protótipo

- `src/components/shell`: layout principal, cabeçalho e navegação lateral.
- `src/components/dashboard`: cartões, gráfico e lista de acessos.
- `src/components/ds`: componentes semânticos próprios do Design System.
- `src/components/ui`: 45 componentes genéricos de interface.
- `src/data`: indicadores, alertas, acessos, perfis e permissões simulados.
- `src/routes`: login, Dashboard e 12 módulos operacionais/administrativos.
- `src/lib`: sessão local, biometria simulada, auditoria e captura de erros.
- `src/styles.css`: tokens de marca, tokens semânticos, modo escuro e utilitários.

A exportação contém 118 arquivos e aproximadamente 542 KB sem dependências instaladas.

## Estado funcional

O protótipo é uma demonstração de frontend. As métricas e listas vêm de constantes em `src/data`, e a sessão e parte do histórico são persistidas em `localStorage`. Não há banco de dados, autenticação real, API de domínio nem integração com os equipamentos do condomínio.

Foi encontrado um conflito potencial entre a rota genérica `/modulo/$slug` e rotas específicas como `/modulo/pessoas`, `/modulo/prestadores` e `/modulo/encomendas`. Esse desenho não será copiado para o Laravel; cada módulo receberá uma rota explícita quando for portado.

## Primeiro recorte portado

Nesta etapa foram portados para Laravel/Livewire:

- shell responsivo com sidebar, cabeçalho operacional e busca global visual;
- grupos de navegação Operação, Cadastros e Gestão;
- identificação do operador, situação do caixa, relógio e notificações visuais;
- saudação e dois alertas operacionais;
- oito indicadores com valores, comparação, período e atualização;
- gráfico de entradas e saídas com períodos Hoje, 7 dias e 30 dias;
- acessos recentes em tabela no desktop e cartões no mobile;
- monitoramento de quatro câmeras com estado liga/desliga em Livewire.

Os dados permanecem demonstrativos e centralizados no componente Livewire. Eles serão substituídos por casos de uso e consultas reais somente quando o backend correspondente for desenvolvido.

## Fora deste recorte

Login, permissões reais, validação de entrada, pré-cadastro, imóveis, pessoas, prestadores, encomendas, administração, relatórios, logs, manutenção, caixa, biometria e persistência continuam fora do porte atual.
