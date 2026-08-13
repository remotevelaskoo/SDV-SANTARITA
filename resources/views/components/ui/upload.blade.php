@props([
    'id',
    'label' => 'Enviar arquivo',
    'accept' => '.pdf,.jpg,.jpeg,.png',
    'limit' => 'Até 10 MB',
    'source' => 'Arquivo do dispositivo',
])

<div x-data="{ fileName: '', fileSize: '', hasFile: false }" class="ui-upload">
    <input
        id="{{ $id }}"
        type="file"
        accept="{{ $accept }}"
        x-on:change="const file = $event.target.files[0]; hasFile = !!file; fileName = file?.name ?? ''; fileSize = file ? `${(file.size / 1024 / 1024).toFixed(2)} MB` : ''"
        {{ $attributes }}
    >
    <label for="{{ $id }}" class="ui-upload__dropzone">
        <span class="ui-upload__icon"><x-icon name="upload" /></span>
        <strong>{{ $label }}</strong>
        <span>PDF, JPG ou PNG · {{ $limit }}</span>
        <small>{{ $source }}</small>
    </label>
    <div class="ui-upload__file" x-show="hasFile" x-cloak role="status">
        <x-icon name="file" />
        <div><strong x-text="fileName"></strong><small><span x-text="fileSize"></span> · pronto para envio</small></div>
        <button type="button" x-on:click="hasFile = false; fileName = ''; document.getElementById('{{ $id }}').value = ''">Remover</button>
    </div>
</div>
