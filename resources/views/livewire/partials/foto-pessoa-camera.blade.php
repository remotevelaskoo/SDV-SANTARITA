{{-- Câmera do rosto (fotoPessoaCamera). Fica dentro do x-data do componente. A foto é guardada só no Concluir. --}}
<p class="equipamento-management-aviso" x-show="aviso" x-text="aviso"></p>

<label class="ui-field" x-show="cameras.length > 1">
    <span class="ui-field__label">Câmera do rosto</span>
    <span class="ui-select-control">
        <select class="ui-field__control ui-field__select" x-model="cameraId" x-on:change="escolher($event.target.value)">
            <option value="">Câmera frontal do dispositivo</option>
            <template x-for="camera in cameras" :key="camera.deviceId">
                <option :value="camera.deviceId" x-text="camera.label || 'Câmera sem nome'"></option>
            </template>
        </select>
    </span>
</label>

<div class="foto-pessoa__visor" x-show="ativa">
    <video x-ref="video" autoplay playsinline muted></video>
    <span class="foto-pessoa__moldura" aria-hidden="true"></span>
</div>
<figure class="foto-pessoa__previa" x-show="previa && !ativa">
    <img :src="previa" alt="Prévia da foto da pessoa">
    <figcaption x-text="enviando ? 'Enviando foto…' : 'Prévia da foto'"></figcaption>
</figure>

<input type="file" accept="image/*" capture="user" class="sr-only" x-ref="nativa" x-on:change="recebidaDaCameraNativa($event)" tabindex="-1" aria-hidden="true">

<div class="equipamento-management-acoes">
    <button type="button" class="ui-button ui-button--primary" x-show="!ativa && !previa" x-on:click="abrir()" :disabled="abrindo"><span class="ui-button__content">{{ $rotuloAbrir ?? 'Abrir câmera' }}</span></button>
    <button type="button" class="ui-button ui-button--primary" x-show="ativa" x-on:click="fotografar()"><span class="ui-button__content">Fotografar</span></button>
    <button type="button" class="ui-button ui-button--ghost ui-button--sm" x-show="ativa" x-on:click="fechar()"><span class="ui-button__content">Fechar câmera</span></button>
    <button type="button" class="ui-button ui-button--ghost ui-button--sm" x-show="previa" x-on:click="refazer()" :disabled="enviando"><span class="ui-button__content">Refazer foto</span></button>
</div>
@error($erro)<small class="ui-field__message ui-field__message--error" role="alert">{{ $message }}</small>@enderror
