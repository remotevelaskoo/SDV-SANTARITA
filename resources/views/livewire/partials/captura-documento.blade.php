@php($cameras = $this->contextoCameras())
<section class="captura-documento" aria-label="Fotografar documento"
    x-data="camerasDoPosto({ implantacaoId: @js($cameras['implantacao']), postoId: @js($cameras['posto']), modo: 'captura' })"
    x-on:cadastro-rapido-encerrado.window="encerrar()">
    <header>
        <strong>Fotografar documento</strong>
        <small>A imagem é usada só para ler os dados e não é guardada. Não é biometria. A leitura começa sozinha depois da captura.</small>
    </header>

    <p class="equipamento-management-aviso" x-show="aviso" x-text="aviso"></p>

    <div class="captura-documento__camera">
        <label class="ui-field">
            <span class="ui-field__label">Tipo do documento</span>
            <span class="ui-select-control">
                <select class="ui-field__control ui-field__select" wire:model.live="capturaTipoDocumento">
                    <option value="cnh">CNH</option>
                    <option value="rg_cin">RG ou CIN (frente e verso)</option>
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
        <button type="button" class="ui-button ui-button--primary ui-button--sm" x-on:click="capturar('frente')" :disabled="!ativa || enviando"><span class="ui-button__content" x-text="capturas.frente ? 'Capturar frente de novo' : 'Capturar frente'"></span></button>
        <button type="button" @class(['ui-button ui-button--sm', 'ui-button--primary' => $this->versoObrigatorio(), 'ui-button--secondary' => ! $this->versoObrigatorio()]) x-on:click="capturar('verso')" :disabled="!ativa || enviando"><span class="ui-button__content">{{ $this->versoObrigatorio() ? 'Capturar verso (obrigatório)' : 'Capturar verso (opcional)' }}</span></button>
        <button type="button" class="ui-button ui-button--ghost ui-button--sm" x-show="capturas.frente" x-on:click="refazer('frente')"><span class="ui-button__content">Refazer frente</span></button>
        <button type="button" class="ui-button ui-button--ghost ui-button--sm" x-show="capturas.verso" x-on:click="refazer('verso')"><span class="ui-button__content">Refazer verso</span></button>
    </div>

    @if ($this->versoObrigatorio() && $capturaFrente !== null && $capturaVerso === null)
        <x-ui.alert variant="info" title="Agora o verso">No RG/CIN o CPF e o nascimento ficam no verso. Capture o verso para a leitura começar.</x-ui.alert>
    @endif

    <div class="captura-documento__miniaturas">
        <template x-if="capturas.frente"><figure><img :src="capturas.frente" alt="Frente capturada"><figcaption>Frente</figcaption></figure></template>
        <template x-if="capturas.verso"><figure><img :src="capturas.verso" alt="Verso capturado"><figcaption>Verso</figcaption></figure></template>
    </div>

    <p class="captura-documento__lendo" wire:loading wire:target="capturaFrente,capturaVerso,analisarDocumento" role="status">Lendo o documento…</p>

    <details class="captura-documento__contingencia">
        <summary>Enviar arquivo (contingência)</summary>
        <div class="equipamento-management-grid">
            <label class="ui-field"><span class="ui-field__label">Frente</span><input type="file" accept="image/jpeg,image/png" wire:model="capturaFrente" x-on:change="$wire.set('capturaOrigem', 'arquivo', false)"></label>
            <label class="ui-field"><span class="ui-field__label">Verso{{ $this->versoObrigatorio() ? '' : ' (opcional)' }}</span><input type="file" accept="image/jpeg,image/png" wire:model="capturaVerso"></label>
        </div>
    </details>

    @error('capturaFrente')<small class="ui-field__message ui-field__message--error">{{ $message }}</small>@enderror
    @error('capturaVerso')<small class="ui-field__message ui-field__message--error">{{ $message }}</small>@enderror

    @if ($analiseDocumento)
        @if ($analiseDocumento['status'] === 'repetir')
            <x-ui.alert variant="warning" title="Capture novamente">
                @foreach ($analiseDocumento['problemas'] as $lado => $problemas)
                    <strong>{{ ucfirst($lado) }}:</strong> {{ implode(' ', $problemas) }}<br>
                @endforeach
            </x-ui.alert>
        @elseif ($analiseDocumento['status'] === 'nao_reconhecido')
            <x-ui.alert variant="warning" title="Documento não identificado">{{ $analiseDocumento['mensagem'] }}</x-ui.alert>
        @endif
    @endif

    <div class="equipamento-management-acoes">
        <x-ui.button variant="ghost" size="sm" wire:click="digitarManualmente">Digitar os dados manualmente</x-ui.button>
    </div>
</section>
