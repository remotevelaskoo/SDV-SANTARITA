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
    form.elements.documento_verso.required = back;
    $("#veiculo").hidden = !form.elements.hasVehicle.checked;
    form.elements.plate.required = form.elements.hasVehicle.checked;
}
form.addEventListener("change", (event) => {
    conditionals();
    // Alteração após tentativa de envio usa uma nova solicitação, sem sobrescrever objetos.
    if (uploadStarted && !sent) {
        id = null;
        receipt = null;
        token = null;
        uploadStarted = false;
    }
    if (event.target.type !== "file") return;
    const field = event.target;
    const image = field.parentElement.querySelector("img");
    if (previews.has(field.name)) URL.revokeObjectURL(previews.get(field.name));
    if (!field.files[0]) {
        image.hidden = true;
        return;
    }
    if (field.files[0].size > 8 * 1024 * 1024) {
        $("#resultado").textContent = "Cada imagem deve ter até 8 MB.";
        field.value = "";
        image.hidden = true;
        return;
    }
    const url = URL.createObjectURL(field.files[0]);
    previews.set(field.name, url);
    image.src = url;
    image.hidden = false;
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
            const file = form.elements[category].files[0];
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
