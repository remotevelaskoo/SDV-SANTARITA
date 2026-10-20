@php($cameras = $this->contextoCameras())
<section class="captura-documento" aria-label="Capturar documento"
    x-data="camerasDoPosto({ implantacaoId: @js($cameras['implantacao']), postoId: @js($cameras['posto']), modo: 'captura' })">
    <header>
        <strong>Capturar documento</strong>
        <small>A imagem é usada só para sugerir os dados e não é guardada. Não é biometria.</small>
    </header>

    <p class="equipamento-management-aviso" x-show="aviso" x-text="aviso"></p>

    <div class="captura-documento__camera">
        <label class="ui-field">
            <span class="ui-field__label">Tipo do documento</span>
            <span class="ui-select-control">
                <select class="ui-field__control ui-field__select" wire:model="capturaTipoDocumento">
                    <option value="cnh">CNH</option>
                    <option value="rg_cin">RG ou CIN</option>
                </select>
            </span>
        </label>
        <label class="ui-field">
            <span class="ui-field__label">Câmera de documentos</span>
            <span class="ui-select-control">
                <select class="ui-field__control ui-field__select" x-model="escolhas.documentos" x-on:change="escolher('documentos', $event.target.value)">
                    <option value="">Escolha a câmera</option>
                    <template x-for="camera in cameras" :key="camera.deviceId">
                        <option :value="camera.deviceId" x-text="camera.label || 'Câmera sem nome (autorize o acesso)'"></option>
                    </template>
                </select>
            </span>
        </label>
        <button type="button" class="ui-button ui-button--secondary ui-button--sm" x-on:click="autorizar()"><span class="ui-button__content">Autorizar câmeras</span></button>
    </div>

    <div class="captura-documento__visor" x-show="ativa">
        <video x-ref="video" autoplay playsinline muted></video>
        <span class="captura-documento__moldura" aria-hidden="true"></span>
    </div>

    <div class="equipamento-management-acoes">
        <button type="button" class="ui-button ui-button--primary ui-button--sm" x-on:click="capturar('frente')" :disabled="!ativa"><span class="ui-button__content">Capturar frente</span></button>
        <button type="button" class="ui-button ui-button--secondary ui-button--sm" x-on:click="capturar('verso')" :disabled="!ativa"><span class="ui-button__content">Capturar verso</span></button>
        <button type="button" class="ui-button ui-button--ghost ui-button--sm" x-show="capturas.frente" x-on:click="refazer('frente')"><span class="ui-button__content">Refazer frente</span></button>
        <button type="button" class="ui-button ui-button--ghost ui-button--sm" x-show="capturas.verso" x-on:click="refazer('verso')"><span class="ui-button__content">Refazer verso</span></button>
    </div>

    <div class="captura-documento__miniaturas">
        <template x-if="capturas.frente"><figure><img :src="capturas.frente" alt="Frente capturada"><figcaption>Frente</figcaption></figure></template>
        <template x-if="capturas.verso"><figure><img :src="capturas.verso" alt="Verso capturado"><figcaption>Verso</figcaption></figure></template>
    </div>

    <details class="captura-documento__contingencia">
        <summary>Enviar arquivo (contingência)</summary>
        <div class="equipamento-management-grid">
            <label class="ui-field"><span class="ui-field__label">Frente</span><input type="file" accept="image/jpeg,image/png" wire:model="capturaFrente" x-on:change="$wire.set('capturaOrigem', 'arquivo')"></label>
            <label class="ui-field"><span class="ui-field__label">Verso (opcional)</span><input type="file" accept="image/jpeg,image/png" wire:model="capturaVerso"></label>
        </div>
    </details>

    @error('capturaFrente')<small class="ui-field__message ui-field__message--error">{{ $message }}</small>@enderror
    @error('capturaVerso')<small class="ui-field__message ui-field__message--error">{{ $message }}</small>@enderror

    <div class="equipamento-management-acoes">
        <x-ui.button variant="primary" size="sm" wire:click="analisarDocumento" wire:loading.attr="disabled" wire:target="analisarDocumento,capturaFrente,capturaVerso">Analisar documento</x-ui.button>
        <x-ui.button variant="ghost" size="sm" wire:click="digitarManualmente">Digitar manualmente</x-ui.button>
    </div>

    @if ($analiseDocumento)
        @if ($analiseDocumento['status'] === 'repetir')
            <x-ui.alert variant="warning" title="Capture novamente">
                @foreach ($analiseDocumento['problemas'] as $lado => $problemas)
                    <strong>{{ ucfirst($lado) }}:</strong> {{ implode(' ', $problemas) }}<br>
                @endforeach
            </x-ui.alert>
        @elseif ($analiseDocumento['status'] === 'nao_reconhecido')
            <x-ui.alert variant="warning" title="Documento não identificado">{{ $analiseDocumento['mensagem'] }}</x-ui.alert>
        @else
            <x-ui.alert variant="info" title="Dados sugeridos pela leitura">
                Confira os campos preenchidos. Os marcados com "Requer conferência" precisam da sua confirmação. A leitura não aprova cadastro nem libera acesso.
                @foreach ($analiseDocumento['extras'] as $campo => $extra)
                    @if ($extra['valor'])<br>{{ $campo === 'validade' ? 'Validade' : 'Número do documento' }}: {{ $extra['valor'] }}{{ $extra['conferir'] ? ' (requer conferência)' : '' }}@endif
                @endforeach
            </x-ui.alert>
            @if ($analiseDocumento['duplicidade'])
                <x-ui.alert variant="danger" title="Possível duplicidade">Já existe pessoa com este CPF. Localize o cadastro existente antes de criar outro.</x-ui.alert>
            @endif
        @endif
    @endif
</section>
