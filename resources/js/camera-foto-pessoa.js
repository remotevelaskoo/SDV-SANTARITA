import {
    chavePreferencia,
    lerPreferencias,
    MENSAGENS,
    resolverDispositivo,
    restricoesRosto,
    salvarPreferencia,
} from './camera-preferencias';

/**
 * Foto atual da pessoa no cadastro rápido (AJ-010).
 *
 * Usa a câmera "rosto" escolhida para o posto (ou a frontal), somente vídeo.
 * Ao fotografar: fecha a câmera, mostra a prévia e envia ao Livewire só a
 * imagem confirmada. "Refazer foto" descarta a anterior no servidor e abre a
 * câmera de novo. Sem câmera ao vivo (conexão não segura), usa a câmera
 * nativa do celular. Nada fica no localStorage além da escolha da câmera.
 */
export function fotoPessoaCamera({ implantacaoId, postoId, propriedade = 'fotoPessoa', metodoRefazer = 'refazerFotoPessoa' } = {}) {
    return {
        chave: chavePreferencia(implantacaoId, postoId),
        cameras: [],
        cameraId: '',
        ativa: false,
        abrindo: false,
        enviando: false,
        previa: null,
        aviso: '',
        stream: null,

        async init() {
            await this.carregar();
        },

        destroy() {
            this.encerrar();
        },

        podeUsarCameraAoVivo() {
            return window.isSecureContext && !!navigator.mediaDevices?.getUserMedia;
        },

        async carregar() {
            if (!navigator.mediaDevices?.enumerateDevices) {
                return;
            }
            const dispositivos = await navigator.mediaDevices.enumerateDevices();
            this.cameras = dispositivos.filter((d) => d.kind === 'videoinput');
            const preferida = resolverDispositivo(dispositivos, lerPreferencias(localStorage, this.chave).rosto);
            this.cameraId = preferida.deviceId || '';
        },

        escolher(deviceId) {
            const dispositivo = this.cameras.find((c) => c.deviceId === deviceId);
            this.cameraId = dispositivo ? deviceId : '';
            if (dispositivo) {
                salvarPreferencia(localStorage, this.chave, 'rosto', dispositivo);
            }
            if (this.ativa) {
                this.abrir();
            }
        },

        async abrir() {
            this.aviso = '';
            this.fechar();
            if (!this.podeUsarCameraAoVivo()) {
                this.$refs.nativa.click();
                return;
            }
            this.abrindo = true;
            try {
                this.stream = await navigator.mediaDevices.getUserMedia(restricoesRosto(this.cameraId || null));
                this.$refs.video.srcObject = this.stream;
                this.ativa = true;
                await this.carregar();
            } catch {
                this.aviso = MENSAGENS.erro_acesso;
            } finally {
                this.abrindo = false;
            }
        },

        fechar() {
            this.stream?.getTracks().forEach((t) => t.stop());
            this.stream = null;
            this.ativa = false;
        },

        fotografar() {
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
                this.fechar();
                this.enviar(new File([blob], 'pessoa.jpg', { type: 'image/jpeg' }));
            }, 'image/jpeg', 0.92);
        },

        recebidaDaCameraNativa(evento) {
            const arquivo = evento.target.files?.[0];
            if (arquivo) {
                this.enviar(arquivo);
            }
            evento.target.value = '';
        },

        enviar(arquivo) {
            this.descartarPrevia();
            this.previa = URL.createObjectURL(arquivo);
            this.enviando = true;
            this.$wire.upload(propriedade, arquivo, () => {
                this.enviando = false;
            }, () => {
                this.enviando = false;
                this.descartarPrevia();
                this.aviso = 'Não foi possível enviar a foto. Tente novamente.';
            });
        },

        async refazer() {
            this.descartarPrevia();
            await this.$wire.call(metodoRefazer);
            this.abrir();
        },

        descartarPrevia() {
            if (this.previa) {
                URL.revokeObjectURL(this.previa);
            }
            this.previa = null;
        },

        /** Cancelar, salvar ou novo atendimento: câmera fechada e prévia descartada. */
        encerrar() {
            this.fechar();
            this.descartarPrevia();
        },
    };
}
