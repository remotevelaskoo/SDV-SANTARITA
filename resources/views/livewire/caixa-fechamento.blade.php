<div class="caixa-fechamento">
    <nav class="caixa-fechamento__acoes" aria-label="Ações do fechamento">
        <x-ui.button variant="ghost" :href="auth()->user()->hasPermission('caixa.proprio.gerenciar') ? route('cash-register') : route('cash-register.closings')">← Voltar</x-ui.button>
        <span class="caixa-fechamento__espaco"></span>
        <x-ui.button variant="secondary" wire:click="exportarCsv">Exportar itens em CSV</x-ui.button>
        <x-ui.button variant="primary" :href="route('cash-register.closing.print', $turno->id)" target="_blank" rel="noopener">Imprimir fechamento</x-ui.button>
    </nav>

    <article class="caixa-fechamento__folha">
        @include('caixa.fechamento-relatorio')
    </article>

    <p class="caixa-fechamento__nota">O fechamento é definitivo e continua válido mesmo que a impressão falhe. O PDF homologado ainda não está disponível; use "Imprimir fechamento".</p>
</div>
