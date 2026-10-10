import {
    chavePreferencia,
    lerPreferencias,
    MENSAGENS,
    resolverDispositivo,
    restricoesVideo,
    salvarPreferencia,
} from './camera-preferencias';

/**
 * Componente Alpine das câmeras do posto (SDV-OPE-018 §5-6).
 *
 * - `configuracao`: autoriza e associa as câmeras de documentos e placas
 *   deste computador ao posto;
 * - `captura`: abre a câmera de documentos, mostra a moldura e envia ao
 *   Livewire só a imagem confirmada (frente ou verso), nunca vídeo contínuo.
 */
export function camerasDoPosto({ implantacaoId, postoId, modo = 'captura' }) {
    return {
        modo,
        chave: chavePreferencia(implantacaoId, postoId),
        cameras: [],
        escolhas: { documentos: '', placas: '' },
        aviso: '',
        ativa: false,
        stream: null,
        capturas: { frente: null, verso: null },

        async init() {
            await this.carregar();
            if (this.modo === 'captura' && this.escolhas.documentos) {
                await this.abrir();
            }
        },

        destroy() {
            this.fechar();
        },

        async carregar() {
            if (!navigator.mediaDevices?.enumerateDevices) {
                this.aviso = MENSAGENS.sem_camera;
                return;
            }
            const dispositivos = await navigator.mediaDevices.enumerateDevices();
            this.cameras = dispositivos.filter((d) => d.kind === 'videoinput');
            const preferencias = lerPreferencias(localStorage, this.chave);
            const resolvida = resolverDispositivo(dispositivos, preferencias.documentos);
            this.escolhas.documentos = resolvida.deviceId || '';
            this.escolhas.placas = resolverDispositivo(dispositivos, preferencias.placas).deviceId || '';
            this.aviso = resolvida.motivo ? MENSAGENS[resolvida.motivo] : '';
        },

        /** Pede permissão de câmera (somente vídeo) para liberar a lista com nomes. */
        async autorizar() {
            try {
                const teste = await navigator.mediaDevices.getUserMedia(restricoesVideo(null));
                teste.getTracks().forEach((t) => t.stop());
                await this.carregar();
            } catch {
                this.aviso = MENSAGENS.erro_acesso;
            }
        },

        escolher(finalidade, deviceId) {
            const dispositivo = this.cameras.find((c) => c.deviceId === deviceId);
            if (!dispositivo) {
                return;
            }
            salvarPreferencia(localStorage, this.chave, finalidade, dispositivo);
            this.escolhas[finalidade] = deviceId;
            this.aviso = '';
            if (finalidade === 'documentos' && this.modo === 'captura') {
                this.abrir();
            }
        },

        async abrir() {
            this.fechar();
            try {
                this.stream = await navigator.mediaDevices.getUserMedia(restricoesVideo(this.escolhas.documentos));
                this.$refs.video.srcObject = this.stream;
                this.ativa = true;
                this.aviso = '';
            } catch {
                this.ativa = false;
                this.escolhas.documentos = '';
                this.aviso = MENSAGENS.camera_ausente;
            }
        },

        fechar() {
            this.stream?.getTracks().forEach((t) => t.stop());
            this.stream = null;
            this.ativa = false;
        },

        /** Captura o quadro atual e envia como arquivo ao Livewire (lado: frente|verso). */
        capturar(lado) {
            const video = this.$refs.video;
            if (!this.ativa || !video.videoWidth) {
                this.aviso = MENSAGENS.erro_acesso;
                return;
            }
            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);
            canvas.toBlob((blob) => {
                const arquivo = new File([blob], `${lado}.jpg`, { type: 'image/jpeg' });
                this.capturas[lado] = URL.createObjectURL(blob);
                this.$wire.upload(lado === 'verso' ? 'capturaVerso' : 'capturaFrente', arquivo);
            }, 'image/jpeg', 0.92);
        },

        refazer(lado) {
            if (this.capturas[lado]) {
                URL.revokeObjectURL(this.capturas[lado]);
            }
            this.capturas[lado] = null;
            this.$wire.descartarCaptura(lado);
        },
    };
}
