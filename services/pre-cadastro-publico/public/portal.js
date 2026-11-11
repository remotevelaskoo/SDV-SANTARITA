import { CameraSession, photograph } from "/camera.js";
const $ = (selector) => document.querySelector(selector);
const form = $("#cadastro");
const params = new URLSearchParams(location.hash.slice(1));
const invite = params.get("convite") || "";
let id = params.get("id");
let receipt = params.get("recibo");
let token;
let uploadStarted = false;
let sent = false;
const previews = new Map();
const capturedFiles = new Map();
const cameraDialog = $("#camera-dialog");
const cameraVideo = $("#camera-video");
const cameraStatus = $("#camera-status");
const cameraCapture = $("#camera-capture");
const camera = new CameraSession((constraints) =>
    navigator.mediaDevices.getUserMedia(constraints),
);
let cameraCategory = null;
let cameraSequence = 0;
let submitting = false;
const imageFile = (category) =>
    capturedFiles.get(category) || form.elements[category].files[0];
function invalidateAttempt() {
    if (uploadStarted && !sent) {
        id = null;
        receipt = null;
        token = null;
        uploadStarted = false;
    }
}
function preview(category, file) {
    const image = form.elements[category].closest(".file").querySelector("img");
    if (previews.has(category)) URL.revokeObjectURL(previews.get(category));
    if (!file) {
        image.hidden = true;
        return;
    }
    const url = URL.createObjectURL(file);
    previews.set(category, url);
    image.src = url;
    image.hidden = false;
}
function closeCamera() {
    cameraSequence++;
    camera.close();
    cameraVideo.srcObject = null;
    cameraCapture.disabled = true;
    if (cameraDialog.open) cameraDialog.close();
}
async function openCamera(category) {
    if (submitting) return;
    if (
        !navigator.mediaDevices?.getUserMedia ||
        !window.isSecureContext ||
        !cameraDialog.showModal
    ) {
        $("#resultado").textContent =
            "A prévia de câmera não está disponível neste navegador. Use o seletor de imagem para fotografar ou escolher um arquivo.";
        form.elements[category].click();
        return;
    }
    closeCamera();
    cameraCategory = category;
    const sequence = ++cameraSequence;
    $("#camera-title").textContent =
        category === "selfie" ? "Fotografar pessoa" : "Fotografar documento";
    cameraStatus.textContent =
        "Permita o acesso à câmera para visualizar a imagem.";
    cameraVideo.classList.toggle("camera-selfie", category === "selfie");
    cameraDialog.showModal();
    try {
        const stream = await camera.open(
            category === "selfie" ? "user" : "environment",
        );
        if (!stream || sequence !== cameraSequence) return;
        cameraVideo.srcObject = stream;
        await cameraVideo.play();
        if (sequence !== cameraSequence) return;
        cameraStatus.textContent =
            "Confira o enquadramento e toque em Tirar foto.";
        cameraCapture.disabled =
            cameraVideo.videoWidth < 320 || cameraVideo.videoHeight < 320;
    } catch (error) {
        if (sequence !== cameraSequence) return;
        camera.close();
        cameraVideo.srcObject = null;
        const messages = {
            NotAllowedError:
                "Acesso à câmera não permitido. Autorize a câmera nas permissões deste site ou cancele e selecione uma imagem.",
            NotFoundError:
                "Nenhuma câmera foi encontrada. Cancele e selecione uma imagem já salva.",
            NotReadableError:
                "A câmera está ocupada. Feche outros aplicativos que a utilizam e tente novamente.",
        };
        cameraStatus.textContent =
            messages[error.name] ||
            "Não foi possível abrir a câmera. Cancele e tente novamente ou selecione uma imagem.";
    }
}
for (const button of document.querySelectorAll("[data-camera]"))
    button.addEventListener("click", () => openCamera(button.dataset.camera));
$("#camera-cancel").addEventListener("click", closeCamera);
cameraDialog.addEventListener("cancel", (event) => {
    event.preventDefault();
    closeCamera();
});
cameraDialog.addEventListener("close", () => {
    camera.close();
    cameraSequence++;
    cameraVideo.srcObject = null;
});
cameraVideo.addEventListener("loadedmetadata", () => {
    cameraCapture.disabled =
        cameraVideo.videoWidth < 320 || cameraVideo.videoHeight < 320;
});
cameraCapture.addEventListener("click", async () => {
    const sequence = cameraSequence;
    const category = cameraCategory;
    cameraCapture.disabled = true;
    try {
        const file = await photograph(cameraVideo);
        if (sequence !== cameraSequence) return;
        capturedFiles.set(category, file);
        form.elements[category].value = "";
        invalidateAttempt();
        preview(category, file);
        conditionals();
        closeCamera();
        $("#resultado").textContent =
            "Foto capturada. Confira a prévia; você pode abrir a câmera novamente para refazer.";
    } catch (error) {
        if (sequence !== cameraSequence) return;
        cameraStatus.textContent = error.message;
        cameraCapture.disabled = false;
    }
});
window.addEventListener("pagehide", closeCamera);
document.addEventListener("visibilitychange", () => {
    if (document.hidden) closeCamera();
});
const random = () =>
    Array.from(crypto.getRandomValues(new Uint8Array(32)), (b) =>
        b.toString(16).padStart(2, "0"),
    ).join("");
async function api(body) {
    const response = await fetch("/api/portal", {
        method: body ? "POST" : "GET",
        headers: {
            "Content-Type": "application/json",
            "x-portal-invite": invite,
        },
        ...(body ? { body: JSON.stringify(body) } : {}),
    });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || "Serviço indisponível.");
    return result;
}
function conditionals() {
    const type = form.elements.accessType.value;
    $("#destino").hidden = type === "turista";
    form.elements.destinationProperty.required = type !== "turista";
    const back = form.elements.documentType.value === "rg_cin";
    $("#verso").hidden = !back;
    form.elements.documento.required = !capturedFiles.has("documento");
    form.elements.selfie.required = !capturedFiles.has("selfie");
    form.elements.documento_verso.required =
        back && !capturedFiles.has("documento_verso");
    $("#veiculo").hidden = !form.elements.hasVehicle.checked;
    form.elements.plate.required = form.elements.hasVehicle.checked;
}
form.addEventListener("change", (event) => {
    invalidateAttempt();
    if (event.target.name === "documentType") closeCamera();
    if (event.target.type === "file") {
        const field = event.target;
        if (field.files[0]) {
            capturedFiles.delete(field.name);
            if (field.files[0].size > 8 * 1024 * 1024) {
                $("#resultado").textContent = "Cada imagem deve ter até 8 MB.";
                field.value = "";
            }
        }
        preview(field.name, imageFile(field.name));
    }
    conditionals();
});
async function refresh() {
    try {
        const response = await api({ action: "status", id, token: receipt });
        $("#protocol").textContent = response.protocol || "Envio pendente";
        const labels = {
            aguardando: "Aguardando análise da portaria",
            aprovado: "Pré-cadastro aprovado",
            rejeitado: "Pré-cadastro não aprovado. Procure a administração.",
            correcao: "Correção solicitada. Procure a administração.",
        };
        $("#status").textContent =
            response.state === "rascunho"
                ? "Envio não concluído."
                : labels[response.status];
    } catch (error) {
        $("#status").textContent = error.message;
    }
}
function showReceipt() {
    closeCamera();
    capturedFiles.clear();
    form.hidden = true;
    $("#receipt").hidden = false;
    $("#loading").hidden = true;
}
$("#refresh").addEventListener("click", refresh);
$("#copy").addEventListener("click", async () => {
    try {
        await navigator.clipboard.writeText(location.href);
        $("#copy-message").textContent =
            "Link copiado. Guarde-o em local privado.";
    } catch {
        $("#copy-message").textContent =
            "Copie o endereço desta página para guardar seu acompanhamento.";
    }
});
form.addEventListener("submit", async (event) => {
    event.preventDefault();
    if (!form.reportValidity()) return;
    closeCamera();
    submitting = true;
    for (const button of document.querySelectorAll("[data-camera]"))
        button.disabled = true;
    $("#enviar").disabled = true;
    try {
        id ||= crypto.randomUUID();
        token ||= random();
        receipt ||= random();
        $("#resultado").textContent = "Preparando envio protegido…";
        const data = Object.fromEntries(new FormData(form));
        data.hasVehicle = form.elements.hasVehicle.checked;
        data.privacyAccepted = form.elements.privacyAccepted.checked;
        if (uploadStarted) {
            const existing = await api({
                action: "status",
                id,
                token: receipt,
            });
            if (existing.state !== "rascunho") {
                sent = true;
                location.hash = new URLSearchParams({
                    convite: invite,
                    id,
                    recibo: receipt,
                }).toString();
                for (const url of previews.values()) URL.revokeObjectURL(url);
                previews.clear();
                form.reset();
                showReceipt();
                await refresh();
                return;
            }
        }
        const begin = await api({
            action: "begin",
            id,
            token,
            receipt,
            documentType: data.documentType,
        });
        uploadStarted = true;
        for (const [category, url] of Object.entries(begin.uploads)) {
            const file = imageFile(category);
            if (!file) throw new Error("Envie todas as imagens obrigatórias.");
            $("#resultado").textContent =
                "Enviando imagens. Mantenha esta página aberta…";
            const response = await fetch(url, {
                method: "PUT",
                headers: { "Content-Type": file.type, "x-upsert": "false" },
                body: file,
            });
            // O objeto não pode ser sobrescrito por uma nova tentativa.
            if (!response.ok && response.status !== 409) {
                const error = await response.json().catch(() => ({}));
                const duplicate =
                    error.error === "Duplicate" ||
                    error.message === "The resource already exists";
                if (!duplicate)
                    throw new Error(
                        "Falha ao enviar imagem. Tente novamente sem fechar a página.",
                    );
            }
        }
        const response = await api({ action: "submit", id, token, data });
        sent = true;
        location.hash = new URLSearchParams({
            convite: invite,
            id,
            recibo: receipt,
        }).toString();
        for (const url of previews.values()) URL.revokeObjectURL(url);
        previews.clear();
        form.reset();
        showReceipt();
        $("#protocol").textContent = response.protocol;
        await refresh();
    } catch (error) {
        $("#resultado").textContent = error.message;
    } finally {
        submitting = false;
        for (const button of document.querySelectorAll("[data-camera]"))
            button.disabled = false;
        $("#enviar").disabled = false;
    }
});
try {
    const config = await api();
    $("#condominio").textContent = config.name;
    $("#privacidade").textContent = config.privacy;
    for (const destination of config.destinations) {
        const option = document.createElement("option");
        option.value = destination;
        option.textContent = destination;
        form.elements.destinationProperty.append(option);
    }
    $("#loading").hidden = true;
    if (id && receipt) {
        showReceipt();
        await refresh();
    } else {
        form.hidden = false;
        conditionals();
    }
} catch (error) {
    $("#loading").textContent = error.message;
}
