// Ciclo da câmera separado da interface para testar permissão tardia e encerramento.
export class CameraSession {
    constructor(getUserMedia) {
        this.getUserMedia = getUserMedia;
        this.stream = null;
        this.generation = 0;
    }
    async open(facingMode) {
        this.close();
        const generation = this.generation;
        const stream = await this.getUserMedia({
            audio: false,
            video: {
                facingMode: { ideal: facingMode },
                width: { ideal: 1280 },
                height: { ideal: 960 },
            },
        });
        if (generation !== this.generation) {
            stream.getTracks().forEach((track) => track.stop());
            return null;
        }
        this.stream = stream;
        return stream;
    }
    close() {
        this.generation++;
        this.stream?.getTracks().forEach((track) => track.stop());
        this.stream = null;
    }
}

export async function photograph(
    video,
    makeCanvas = () => document.createElement("canvas"),
) {
    const { videoWidth: width, videoHeight: height } = video;
    if (width < 320 || height < 320)
        throw new Error("Aguarde a imagem da câmera antes de fotografar.");
    const scale = Math.min(1, 1600 / width, 1600 / height);
    const canvas = makeCanvas();
    canvas.width = Math.round(width * scale);
    canvas.height = Math.round(height * scale);
    const context = canvas.getContext("2d");
    if (!context) throw new Error("Não foi possível preparar a fotografia.");
    context.drawImage(video, 0, 0, canvas.width, canvas.height);
    const blob = await new Promise((resolve) =>
        canvas.toBlob(resolve, "image/jpeg", 0.9),
    );
    if (!blob)
        throw new Error(
            "Não foi possível capturar a fotografia. Tente novamente.",
        );
    return new File([blob], "fotografia.jpg", { type: "image/jpeg" });
}
