import test from "node:test";
import assert from "node:assert/strict";
import sharp from "sharp";
import { hash, normalize, sameSecret } from "../lib/validation.js";
import { configuration } from "../lib/cloud.js";
import { createHandler as portal } from "../api/portal.js";
import { createHandler as sync } from "../api/sync.js";
const tenant = "12345678-1234-4234-8234-123456789abc";
const id = "87654321-4321-4321-8321-123456789abc";
const valid = {
    name: "Pessoa de Teste",
    cpf: "123.456.789-01",
    accessType: "turista",
    documentType: "cnh",
    hasVehicle: false,
    privacyAccepted: true,
};
function context() {
    const rows = new Map();
    const objects = new Map();
    const ctx = {
        tenant,
        invite: "i".repeat(40),
        node: "n".repeat(40),
        rateSecret: "r".repeat(40),
        bucket: "privado",
        privacy: "Aviso de homologação",
        name: "Teste",
        destinations: [
            {
                label: "Imóvel real de teste",
                responsible: "Responsável de teste",
            },
        ],
    };
    ctx.client = {
        from() {
            let action = "select",
                payload,
                filters = [],
                one = false,
                ignore = false;
            const q = {
                select() {
                    return q;
                },
                eq(k, v) {
                    filters.push([k, v]);
                    return q;
                },
                maybeSingle() {
                    one = true;
                    return q;
                },
                order() {
                    return q;
                },
                limit() {
                    return q;
                },
                update(v) {
                    action = "update";
                    payload = v;
                    return q;
                },
                upsert(v, opts) {
                    action = "upsert";
                    payload = v;
                    ignore = opts?.ignoreDuplicates;
                    return q;
                },
                then(resolve, reject) {
                    try {
                        let data;
                        if (action === "upsert") {
                            if (!rows.has(payload.id) || !ignore)
                                rows.set(payload.id, {
                                    state: "rascunho",
                                    review_status: "aguardando",
                                    local_version: 0,
                                    ...payload,
                                });
                            data = null;
                        } else {
                            const selected = [...rows.values()].filter((row) =>
                                filters.every(([k, v]) => row[k] === v),
                            );
                            if (action === "update") {
                                selected.forEach((row) =>
                                    Object.assign(row, payload),
                                );
                                data = null;
                            } else
                                data = one
                                    ? selected[0] || null
                                    : selected.slice(0, 10);
                        }
                        resolve({ data, error: null });
                    } catch (e) {
                        reject(e);
                    }
                },
            };
            return q;
        },
        rpc(name, input) {
            if (name === "sdv_portal_rate")
                return Promise.resolve({ data: 1, error: null });
            const row = rows.get(input.p_id);
            const allowed =
                row &&
                row.implantacao_id === input.p_tenant &&
                (row.local_version < input.p_version ||
                    (row.local_version === input.p_version &&
                        row.review_status === input.p_status));
            if (allowed)
                Object.assign(row, {
                    state: "importado",
                    local_version: input.p_version,
                    review_status: input.p_status,
                });
            return Promise.resolve({ data: !!allowed, error: null });
        },
        storage: {
            from() {
                return {
                    createSignedUploadUrl: async (path) => ({
                        data: {
                            signedUrl: "https://example.supabase.co/" + path,
                        },
                        error: null,
                    }),
                    download: async (path) => ({
                        data: objects.has(path)
                            ? new Blob([objects.get(path)])
                            : null,
                        error: objects.has(path) ? null : new Error("missing"),
                    }),
                    upload: async (path, bytes) => {
                        objects.set(path, bytes);
                        return { data: {}, error: null };
                    },
                };
            },
        },
    };
    return { ctx, rows, objects };
}
async function call(handler, req) {
    const res = {
        headers: {},
        statusCode: 200,
        setHeader(k, v) {
            this.headers[k] = v;
        },
        status(code) {
            this.statusCode = code;
            return this;
        },
        json(data) {
            this.body = data;
            return this;
        },
        send(data) {
            this.body = data;
            return this;
        },
    };
    await handler({ headers: {}, query: {}, ...req }, res);
    return res;
}
test("dados opcionais ficam nulos; sem responsável ou veículo fictício", () => {
    const data = normalize({
        ...valid,
        responsible_name: "forjado",
        vehicleModel: "ignorar",
    });
    assert.equal(data.phone, null);
    assert.equal(data.email, null);
    assert.equal(data.destination_property, null);
    assert.equal(data.responsible_name, null);
    assert.equal(data.vehicle_model, null);
});
test("telefone e dados preenchidos são validados", () => {
    for (const change of [
        { phone: "abc1234567890" },
        { email: "invalido" },
        { birthDate: "2020-02-31" },
        { hasVehicle: true, plate: "errada" },
        { privacyAccepted: false },
        { documentType: "pdf" },
    ])
        assert.throws(() => normalize({ ...valid, ...change }));
    assert.equal(
        normalize({ ...valid, phone: "(12) 99999-1234" }).phone,
        "(12) 99999-1234",
    );
});
test("imóvel obrigatório no formulário público para visitante/prestador, sem catálogo fictício", () => {
    assert.throws(() => normalize({ ...valid, accessType: "visitante" }, []));
    assert.throws(() =>
        normalize(
            { ...valid, accessType: "visitante", destinationProperty: "Outro" },
            [{ label: "Real", responsible: "Responsável real" }],
        ),
    );
    assert.equal(
        normalize(
            {
                ...valid,
                accessType: "visitante",
                destinationProperty: "Real",
                responsibleName: "forjado",
            },
            [{ label: "Real", responsible: "Responsável real" }],
        ).responsible_name,
        "Responsável real",
    );
});
test("segredos ausentes/errados não autenticam", () => {
    assert.equal(sameSecret(undefined, "x".repeat(40)), false);
    assert.equal(sameSecret("x".repeat(40), "x".repeat(40)), true);
    assert.equal(sameSecret("x", "y".repeat(40)), false);
    assert.throws(() => configuration({ PORTAL_ENABLED: "false" }));
});
test("convite é obrigatório e chave administrativa nunca é devolvida", async () => {
    const { ctx } = context();
    assert.equal(
        (
            await call(
                portal(() => ctx),
                { method: "GET" },
            )
        ).statusCode,
        401,
    );
    const response = await call(
        portal(() => ctx),
        { method: "GET", headers: { "x-portal-invite": ctx.invite } },
    );
    assert.equal(response.statusCode, 200);
    assert.equal(response.body.client, undefined);
    assert.equal(response.body.node, undefined);
});
test("envia CNH e selfie reais sintéticas, sem aprovar; reenvio é idempotente", async () => {
    const { ctx, rows, objects } = context();
    const handler = portal(() => ctx);
    const headers = { "x-portal-invite": ctx.invite };
    const token = "t".repeat(40),
        receipt = "c".repeat(40);
    const begin = await call(handler, {
        method: "POST",
        headers,
        body: { action: "begin", id, token, receipt, documentType: "cnh" },
    });
    assert.equal(begin.statusCode, 200);
    assert.equal(Object.keys(begin.body.uploads).length, 2);
    const image = await sharp({
        create: { width: 400, height: 500, channels: 3, background: "#b8c9d9" },
    })
        .jpeg()
        .toBuffer();
    for (const category of ["documento", "selfie"])
        objects.set(`${tenant}/${id}/entrada-${category}`, image);
    const submit = {
        method: "POST",
        headers,
        body: { action: "submit", id, token, data: valid },
    };
    assert.equal((await call(handler, submit)).statusCode, 200);
    assert.equal(rows.get(id).state, "enviado");
    assert.equal(rows.get(id).review_status, "aguardando");
    assert.equal(rows.get(id).files.length, 2);
    assert.equal(rows.get(id).dados.phone, null);
    assert.equal((await call(handler, submit)).statusCode, 200);
    assert.equal(rows.size, 1);
    const different = await call(handler, {
        ...submit,
        body: { ...submit.body, data: { ...valid, name: "Outra pessoa" } },
    });
    assert.equal(different.statusCode, 409);
    const status = await call(handler, {
        method: "POST",
        headers,
        body: { action: "status", id, token: receipt },
    });
    assert.equal(status.statusCode, 200);
    assert.equal(status.body.dados, undefined);
    assert.equal(status.body.files, undefined);
});
test("imagem inválida ou verso ausente não concluem envio", async () => {
    const { ctx, objects } = context();
    const handler = portal(() => ctx);
    const headers = { "x-portal-invite": ctx.invite };
    const token = "t".repeat(40);
    await call(handler, {
        method: "POST",
        headers,
        body: {
            action: "begin",
            id,
            token,
            receipt: "c".repeat(40),
            documentType: "rg_cin",
        },
    });
    objects.set(
        `${tenant}/${id}/entrada-documento`,
        Buffer.from("arquivo falso"),
    );
    assert.equal(
        (
            await call(handler, {
                method: "POST",
                headers,
                body: {
                    action: "submit",
                    id,
                    token,
                    data: { ...valid, documentType: "rg_cin" },
                },
            })
        ).statusCode,
        422,
    );
});
test("nó sem credencial não lê fila/foto; ACK antigo ou conflitante é recusado", async () => {
    const { ctx, rows } = context();
    const handler = sync(() => ctx);
    assert.equal(
        (await call(handler, { method: "GET", query: { action: "pull" } }))
            .statusCode,
        401,
    );
    rows.set(id, {
        id,
        implantacao_id: tenant,
        state: "enviado",
        document_type: "cnh",
        review_status: "aguardando",
        local_version: 0,
    });
    const headers = { authorization: `Bearer ${ctx.node}` };
    const ack = (version, status) =>
        call(handler, {
            method: "POST",
            headers,
            body: { action: "ack", id, version, status },
        });
    assert.equal((await ack(2, "aprovado")).statusCode, 200);
    assert.equal((await ack(1, "aguardando")).statusCode, 409);
    assert.equal((await ack(2, "rejeitado")).statusCode, 409);
    assert.equal((await ack(2, "aprovado")).statusCode, 200);
    assert.equal(
        (
            await call(handler, {
                method: "GET",
                headers,
                query: { action: "pull" },
            })
        ).body.records.length,
        0,
    );
});
test("solicitação de outra implantação é invisível", async () => {
    const { ctx, rows } = context();
    rows.set(id, {
        id,
        implantacao_id: "outra",
        receipt_hash: hash("c".repeat(40)),
    });
    const response = await call(
        portal(() => ctx),
        {
            method: "POST",
            headers: { "x-portal-invite": ctx.invite },
            body: { action: "status", id, token: "c".repeat(40) },
        },
    );
    assert.equal(response.statusCode, 404);
});
