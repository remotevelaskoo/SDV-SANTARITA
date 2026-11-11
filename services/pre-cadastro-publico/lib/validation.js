import { createHash, timingSafeEqual } from "node:crypto";
export class PortalError extends Error {
    constructor(status, message) {
        super(message);
        this.status = status;
    }
}
export const hash = (value) => createHash("sha256").update(value).digest("hex");
export const uuid = (value) =>
    typeof value === "string" &&
    /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(
        value,
    );
export const secret = (value) =>
    typeof value === "string" && /^[a-zA-Z0-9_-]{32,128}$/.test(value);
export function sameSecret(provided, expected) {
    return (
        typeof provided === "string" &&
        typeof expected === "string" &&
        expected.length >= 32 &&
        timingSafeEqual(
            Buffer.from(hash(provided)),
            Buffer.from(hash(expected)),
        )
    );
}
export function check(condition, message, status = 422) {
    if (!condition) throw new PortalError(status, message);
}
function text(value, max, field, required = false) {
    check(
        value == null || typeof value === "string",
        `${field}: valor inválido.`,
    );
    const result = (value ?? "").trim().replace(/\s+/g, " ");
    check(
        result.length <= max && !/[\x00-\x1f\x7f]/.test(result),
        `${field}: valor inválido.`,
    );
    check(!required || result.length >= 3, `Preencha ${field}.`);
    return result || null;
}
export function normalize(input, destinations = []) {
    check(
        input && typeof input === "object" && !Array.isArray(input),
        "Dados inválidos.",
    );
    const name = text(input.name, 120, "nome completo", true);
    // Mesma política do formulário Laravel: CPF tem 11 dígitos. Não autentica identidade.
    const document = text(input.cpf, 14, "CPF", true)?.replace(/\D/g, "");
    check(/^\d{11}$/.test(document), "Informe o CPF com 11 dígitos.");
    const access_type = input.accessType;
    check(
        ["turista", "visitante", "prestador"].includes(access_type),
        "Escolha o tipo de acesso.",
    );
    check(
        ["cnh", "rg_cin"].includes(input.documentType),
        "Escolha CNH ou RG/CIN.",
    );
    const phone = text(input.phone, 20, "telefone");
    check(
        !phone ||
            (/^[\d\s()+.\-]+$/.test(phone) &&
                /^\d{10,13}$/.test(phone.replace(/\D/g, ""))),
        "Informe telefone válido com DDD.",
    );
    const email = text(input.email, 120, "e-mail");
    check(
        !email || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email),
        "Informe um e-mail válido.",
    );
    const birth_date = text(input.birthDate, 10, "nascimento");
    check(
        !birth_date ||
            (/^\d{4}-\d{2}-\d{2}$/.test(birth_date) &&
                !Number.isNaN(Date.parse(birth_date)) &&
                new Date(birth_date).toISOString().slice(0, 10) ===
                    birth_date &&
                birth_date < new Date().toISOString().slice(0, 10)),
        "Informe nascimento válido.",
    );
    const destination_property =
        access_type === "turista"
            ? null
            : text(input.destinationProperty, 160, "imóvel", true);
    const destination = destinations.find(
        (d) => d.label === destination_property,
    );
    check(!destination_property || destination, "Selecione um imóvel válido.");
    const responsible_name =
        access_type === "visitante"
            ? text(
                  destination?.responsible,
                  120,
                  "responsável pelo visitante",
                  true,
              )
            : null;
    check(
        typeof input.hasVehicle === "boolean",
        "Informe se está com veículo.",
    );
    const vehicle_plate = input.hasVehicle
        ? text(input.plate, 8, "placa", true)?.replace(/-/g, "").toUpperCase()
        : null;
    check(
        !input.hasVehicle || /^[A-Z]{3}\d[A-Z0-9]\d{2}$/.test(vehicle_plate),
        "Informe uma placa válida.",
    );
    check(
        input.privacyAccepted === true,
        "Confirme a ciência do aviso de privacidade.",
    );
    return {
        name,
        document,
        document_type: input.documentType,
        birth_date,
        phone,
        email,
        access_type,
        address_informed: text(input.address, 500, "endereço"),
        destination_property,
        destination_label:
            access_type === "turista"
                ? "Praia do Santa Rita"
                : destination_property,
        responsible_name,
        vehicle_plate,
        vehicle_model: input.hasVehicle
            ? text(input.vehicleModel, 100, "modelo")
            : null,
        vehicle_color: input.hasVehicle
            ? text(input.vehicleColor, 40, "cor")
            : null,
    };
}
export function categories(documentType) {
    return documentType === "rg_cin"
        ? ["documento", "documento_verso", "selfie"]
        : ["documento", "selfie"];
}
