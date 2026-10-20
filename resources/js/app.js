import { camerasDoPosto } from './camera-documento';
import { selfieCamera } from './camera-selfie';

// Componentes Alpine usados pelas telas Livewire (o Alpine vem com o Livewire).
document.addEventListener('alpine:init', () => {
    window.Alpine.data('camerasDoPosto', camerasDoPosto);
    window.Alpine.data('selfieCamera', selfieCamera);
});
