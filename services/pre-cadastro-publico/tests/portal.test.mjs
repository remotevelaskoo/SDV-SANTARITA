import test from "node:test";
import assert from "node:assert/strict";
import sharp from "sharp";
import { hash, normalize, sameSecret, localDate } from "../lib/validation.js";
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
    visitDate: localDate(),
};
function context() {
    const rows = new Map();
    const objects = new Map();
    const catalogs = new Map([
        [
            tenant,
            {
                implantacao_id: tenant,
                itens: [
                    {
                        label: "Imóvel real de teste",
                        responsible: "Responsável de teste",
                    },
                    { label: "Imóvel sem responsável", responsible: null },
                ],
            },
        ],
    ]);
    const ctx = {
        tenant,
        invite: "i".repeat(40),
        node: "n".repeat(40),
        rateSecret: "r".repeat(40),
        bucket: "privado",
        privacy: "Aviso de homologação",
        name: "Teste",
    };
    ctx.client = {
        from(table) {
            const store = table === "sdv_portal_catalogos" ? catalogs : rows;
            const key =
                table === "sdv_portal_catalogos" ? "implantacao_id" : "id";
            let action = "select",
                payload,
                filters = [],
                or = null,
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
                or(expression) {
                    or = expression;
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
                            if (!store.has(payload[key]) || !ignore)
                                store.set(payload[key], {
                                    ...(store === rows
                                        ? {
                                              state: "rascunho",
                                              review_status: "aguardando",
                                              local_version: 0,
                                              import_attempts: 0,
                                          }
                                        : {}),
                                    ...payload,
                                });
                            data = null;
                        } else {
                            // Só a expressão usada pela fila: espera vencida ou ausente.
                            const due = (row) => {
                                if (!or) return true;
                                const limit = or.split("lte.")[1];
                                return (
                                    !row.next_attempt_at ||
                                    row.next_attempt_at <= limit
                                );
                            };
                            const selected = [...store.values()].filter(
                                (row) =>
                                    filters.every(([k, v]) => row[k] === v) &&
                                    due(row),
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
            if (name === "sdv_portal_falha") {
                const row = rows.get(input.p_id);
                if (!row || row.state !== "enviado")
                    return Promise.resolve({ data: [], error: null });
                row.import_attempts = (row.import_attempts || 0) + 1;
                row.last_failure = input.p_code;
                row.next_attempt_at = new Date(
                    Date.now() + 60000 * 2 ** (row.import_attempts - 1),
                ).toISOString();
                if (row.import_attempts >= input.p_max)
                    row.state = "conferencia";
                return Promise.resolve({
                    data: [
                        {
                            state: row.state,
                            import_attempts: row.import_attempts,
                        },
                    ],
                    error: null,
                });
            }
            if (name === "sdv_portal_reabrir") {
                const row = rows.get(input.p_id);
                const ok = row?.state === "conferencia";
                if (ok)
                    Object.assign(row, {
                        state: "enviado",
                        import_attempts: 0,
                        next_attempt_at: null,
                    });
                return Promise.resolve({ data: ok, error: null });
            }
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
    return { ctx, rows, objects, catalogs };
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
test("catálogo vem do sistema local; nomes de responsáveis não são públicos", async () => {
    const { ctx, catalogs } = context();
    const handler = portal(() => ctx);
    const config = await call(handler, {
        method: "GET",
        headers: { "x-portal-invite": ctx.invite },
    });
    assert.deepEqual(config.body.destinations, {
        visitante: ["Imóvel real de teste"],
        prestador: ["Imóvel real de teste", "Imóvel sem responsável"],
    });
    assert.ok(!JSON.stringify(config.body).includes("Responsável de teste"));
    const itens = catalogs.get(tenant).itens;
    // Visitante exige imóvel com responsável principal; prestador nunca leva responsável.
    assert.throws(() =>
        normalize(
            {
                ...valid,
                accessType: "visitante",
                destinationProperty: "Imóvel sem responsável",
            },
            itens,
        ),
    );
    const prestador = normalize(
        {
            ...valid,
            accessType: "prestador",
            destinationProperty: "Imóvel real de teste",
        },
        itens,
    );
    assert.equal(prestador.responsible_name, null);
    assert.equal(prestador.destination_label, "Imóvel real de teste");
    catalogs.clear();
    const empty = await call(handler, {
        method: "GET",
        headers: { "x-portal-invite": ctx.invite },
    });
    assert.deepEqual(empty.body.destinations, { visitante: [], prestador: [] });
});
test("data prevista da visita: de hoje até 30 dias, sem datas impossíveis", () => {
    const now = new Date("2026-11-11T15:00:00Z");
    const at = (visitDate) => () => normalize({ ...valid, visitDate }, [], now);
    assert.equal(at("2026-11-11")().visit_date, "2026-11-11");
    assert.equal(at("2026-12-11")().visit_date, "2026-12-11");
    for (const day of ["2026-11-10", "2026-12-12", "2026-02-31", "", "amanhã"])
        assert.throws(at(day), (error) => error.status === 422);
    // 23h30 em Brasília já é dia 12 em UTC: a data civil local continua sendo 11.
    const late = new Date("2026-11-12T02:30:00Z");
    assert.equal(
        normalize({ ...valid, visitDate: "2026-11-11" }, [], late).visit_date,
        "2026-11-11",
    );
});
test("falhas de importação esperam, não bloqueiam a fila e vão para conferência", async () => {
    const { ctx, rows } = context();
    const handler = sync(() => ctx);
    const headers = { authorization: `Bearer ${ctx.node}` };
    const ids = Array.from(
        { length: 11 },
        (_, i) => `87654321-4321-4321-8321-${String(i).padStart(12, "0")}`,
    );
    ids.forEach((rid, i) =>
        rows.set(rid, {
            id: rid,
            implantacao_id: tenant,
            state: "enviado",
            document_type: "cnh",
            review_status: "aguardando",
            local_version: 0,
            import_attempts: 0,
            submitted_at: `2026-11-11T10:${String(i).padStart(2, "0")}:00Z`,
        }),
    );
    const pull = async () =>
        (
            await call(handler, {
                method: "GET",
                headers,
                query: { action: "pull" },
            })
        ).body.records.map((r) => r.id);
    const fail = (rid, code = "dados_invalidos") =>
        call(handler, {
            method: "POST",
            headers,
            body: { action: "falha", id: rid, code },
        });
    assert.deepEqual(await pull(), ids.slice(0, 10));
    // As dez primeiras falham: a 11ª passa a ser entregue no ciclo seguinte.
    for (const rid of ids.slice(0, 10))
        assert.equal((await fail(rid)).statusCode, 200);
    assert.deepEqual(await pull(), [ids[10]]);
    // Código com dados pessoais ou formato livre é recusado.
    assert.equal((await fail(ids[0], "CPF 123")).statusCode, 422);
    for (let attempt = 2; attempt <= 5; attempt++) {
        rows.get(ids[0]).next_attempt_at = null;
        const response = await fail(ids[0]);
        assert.equal(response.body.attempts, attempt);
    }
    assert.equal(rows.get(ids[0]).state, "conferencia");
    assert.equal((await fail(ids[0])).body.state, "conferencia");
    const reopen = await call(handler, {
        method: "POST",
        headers,
        body: { action: "reabrir", id: ids[0] },
    });
    assert.equal(reopen.body.reopened, true);
    assert.equal(rows.get(ids[0]).state, "enviado");
    assert.equal(rows.get(ids[0]).import_attempts, 0);
    assert.ok((await pull()).includes(ids[0]));
    // Recibo público indica conferência sem detalhes da falha.
    rows.get(ids[0]).state = "conferencia";
    rows.get(ids[0]).receipt_hash = hash("c".repeat(40));
    const status = await call(
        portal(() => ctx),
        {
            method: "POST",
            headers: { "x-portal-invite": ctx.invite },
            body: { action: "status", id: ids[0], token: "c".repeat(40) },
        },
    );
    assert.equal(status.body.state, "conferencia");
    assert.equal(status.body.last_failure, undefined);
});
test("catálogo só é aceito do nó autenticado e sem rótulos repetidos", async () => {
    const { ctx, catalogs } = context();
    const handler = sync(() => ctx);
    const send = (itens, authorization = `Bearer ${ctx.node}`) =>
        call(handler, {
            method: "POST",
            headers: { authorization },
            body: { action: "catalogo", itens },
        });
    const itens = [
        { label: "Bloco A — 101", responsible: "Pessoa responsável" },
        { label: "Bloco A — 102", responsible: null },
    ];
    assert.equal((await send(itens, "Bearer errado")).statusCode, 401);
    assert.equal((await send([itens[0], itens[0]])).statusCode, 422);
    assert.equal(
        (await send([{ label: "", responsible: null }])).statusCode,
        422,
    );
    const ok = await send(itens);
    assert.equal(ok.statusCode, 200);
    assert.equal(ok.body.stored, 2);
    assert.deepEqual(catalogs.get(tenant).itens, itens);
});
