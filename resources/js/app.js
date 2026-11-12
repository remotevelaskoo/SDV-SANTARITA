import { camerasDoPosto } from './camera-documento';
import { fotoPessoaCamera } from './camera-foto-pessoa';
import { selfieCamera } from './camera-selfie';
import { nomePorVoz } from './nome-por-voz';
import { iniciarNavegacao } from './navegacao';
import { iniciarRolagemAutomatica } from './rolagem-automatica';

// Componentes Alpine usados pelas telas Livewire (o Alpine vem com o Livewire).
document.addEventListener('alpine:init', () => {
    window.Alpine.data('camerasDoPosto', camerasDoPosto);
    window.Alpine.data('selfieCamera', selfieCamera);
    window.Alpine.data('fotoPessoaCamera', fotoPessoaCamera);
    window.Alpine.data('nomePorVoz', nomePorVoz);
});

// Barra lateral compacta/expandida e gaveta do celular (AJ-009).
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => iniciarNavegacao());
} else {
    iniciarNavegacao();
}
document.addEventListener('livewire:navigated', () => iniciarNavegacao());

// Depois de cada ação, a tela vai até o que mudou (etapa, formulário, erro, aviso).
document.addEventListener('livewire:init', () => iniciarRolagemAutomatica(window.Livewire));
if (window.Livewire) {
    iniciarRolagemAutomatica(window.Livewire);
}
