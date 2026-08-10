@props(['disabled' => false])

<section {{ $attributes->class('ui-access-decision') }} aria-labelledby="access-decision-title">
    <header><div><h3 id="access-decision-title">Decisão do atendimento</h3><p>Confira os dados antes de enviar qualquer comando.</p></div><x-ui.badge variant="warning">Demonstração</x-ui.badge></header>
    <div>
        <x-ui.button variant="danger" :disabled="$disabled"><x-slot:icon><x-icon name="x" /></x-slot:icon><span><strong>Negar entrada</strong><small>Exige motivo e registra a tentativa</small></span></x-ui.button>
        <x-ui.button variant="warning" :disabled="$disabled"><x-slot:icon><x-icon name="clipboard" /></x-slot:icon><span><strong>Salvar sem liberar</strong><small>Não aciona nenhum equipamento</small></span></x-ui.button>
        <x-ui.button variant="success" :disabled="$disabled"><x-slot:icon><x-icon name="check-circle" /></x-slot:icon><span><strong>Validar e liberar</strong><small>Registra, envia e aguarda confirmação</small></span></x-ui.button>
    </div>
    <p><x-icon name="info" /> Autorização aprovada e comando do portão são resultados diferentes.</p>
</section>
