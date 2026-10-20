/**
 * Selfie pela câmera do celular ou do computador no pré-cadastro (etapa 4).
 *
 * Abre a câmera frontal (somente vídeo, nunca microfone) e, ao tirar a foto,
 * envia na hora ao Livewire só essa imagem, como se fosse um arquivo
 * escolhido; "Repetir selfie" permite tirar outra. Sem câmera disponível pelo navegador (por exemplo, fora
 * de conexão segura), recorre à câmera nativa do celular pelo campo de
 * arquivo com `capture="user"`. O envio de arquivo continua disponível.
 */
export function selfieCamera({ propriedade = 'selfieFile' } = {}) {
    return {
        ativa: false,
        abrindo: false,
        foto: null,
        blob: null,
        aviso: '',
        enviando: false,
        stream: null,

        destroy() {
            this.fechar();
            this.descartarFoto();
        },

        podeUsarCameraAoVivo() {
            return window.isSecureContext && !!navigator.mediaDevices?.getUserMedia;
        },

        async abrir() {
            this.aviso = '';
            this.descartarFoto();
            if (!this.podeUsarCameraAoVivo()) {
                // Celular sem acesso direto: abre a câmera nativa.
                this.$refs.nativa.click();
                return;
            }
            this.abrindo = true;
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 960 } },
                    audio: false,
                });
                this.$refs.video.srcObject = this.stream;
                this.ativa = true;
            } catch {
                this.aviso = 'Não foi possível abrir a câmera. Autorize o acesso no navegador ou envie uma foto do dispositivo.';
            } finally {
                this.abrindo = false;
            }
        },

        fechar() {
            this.stream?.getTracks().forEach((t) => t.stop());
            this.stream = null;
            this.ativa = false;
        },

        tirarFoto() {
            const video = this.$refs.video;
            if (!this.ativa || !video.videoWidth) {
                this.aviso = 'A câmera ainda não está pronta. Aguarde um instante e tente de novo.';
                return;
            }
            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);
            canvas.toBlob((blob) => {
                this.blob = blob;
                this.foto = URL.createObjectURL(blob);
                this.fechar();
                this.usarFoto();
            }, 'image/jpeg', 0.92);
        },

        tirarOutra() {
            this.abrir();
        },

        usarFoto() {
            if (!this.blob) {
                return;
            }
            this.enviar(new File([this.blob], 'selfie.jpg', { type: 'image/jpeg' }));
        },

        /** Foto tirada pela câmera nativa do celular (fallback). */
        recebidaDaCameraNativa(evento) {
            const arquivo = evento.target.files?.[0];
            if (arquivo) {
                this.enviar(arquivo);
            }
            evento.target.value = '';
        },

        enviar(arquivo) {
            this.aviso = '';
            this.enviando = true;
            this.$wire.upload(propriedade, arquivo, () => {
                this.enviando = false;
                this.descartarFoto();
            }, () => {
                this.enviando = false;
                this.aviso = 'Não foi possível enviar a selfie. Tente novamente.';
            });
        },

        descartarFoto() {
            if (this.foto) {
                URL.revokeObjectURL(this.foto);
            }
            this.foto = null;
            this.blob = null;
        },
    };
}
