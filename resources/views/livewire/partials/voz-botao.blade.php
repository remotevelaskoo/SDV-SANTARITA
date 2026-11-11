{{-- Botão de preenchimento por voz (mesmo componente do nome, AJ-010 §12). Uso dentro de um x-data="nomePorVoz(...)". --}}
<div class="nome-por-voz__acoes">
    <template x-if="suportado">
        <span class="nome-por-voz__botoes">
            <button type="button" class="ui-button ui-button--secondary ui-button--sm nome-por-voz__microfone" x-on:click="alternar()" :aria-pressed="ouvindo.toString()" :aria-label="ouvindo ? 'Cancelar' : '{{ $rotulo }}'" :title="ouvindo ? 'Cancelar o preenchimento por voz' : '{{ $rotulo }}'">
                <span class="ui-button__content">
                    <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 10a7 7 0 0 0 14 0"/><path d="M12 17v5"/></svg>
                    <span x-text="ouvindo ? 'Cancelar' : '{{ $rotulo }}'"></span>
                </span>
            </button>
        </span>
    </template>
    <small class="nome-por-voz__mensagem" role="status" aria-live="polite" x-text="suportado ? mensagem : textoSemSuporte"></small>
</div>
