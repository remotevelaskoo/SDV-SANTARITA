import { configuration, result, row, respond, failure } from "../lib/cloud.js";
import { check, sameSecret, categories, hash } from "../lib/validation.js";
// Depois de MAX_ATTEMPTS falhas a solicitação sai da fila e fica para conferência.
export const MAX_ATTEMPTS = 5;
export function createHandler(makeContext = configuration) {
    return async function handler(req, res) {
        try {
            const ctx = makeContext();
            check(
                sameSecret(req.headers.authorization, `Bearer ${ctx.node}`),
                "Nó não autorizado.",
                401,
            );
            if (req.method === "GET" && req.query.action === "pull") {
                const records = await result(
                    ctx.client
                        .from("sdv_portal_solicitacoes")
                        .select(
                            "id,implantacao_id,protocol,dados,files,submitted_at",
                        )
                        .eq("implantacao_id", ctx.tenant)
                        .eq("state", "enviado")
                        // Falhas recentes esperam a vez e não bloqueiam as seguintes.
                        .or(
                            `next_attempt_at.is.null,next_attempt_at.lte.${new Date().toISOString()}`,
                        )
                        .order("submitted_at")
                        .limit(10),
                );
                return respond(res, 200, { records });
            }
            if (req.method === "GET" && req.query.action === "file") {
                const item = await row(ctx, req.query.id);
                check(
                    item.state !== "rascunho" &&
                        categories(item.document_type).includes(
                            req.query.category,
                        ),
                    "Arquivo não encontrado.",
                    404,
                );
                const file = await result(
                    ctx.client.storage
                        .from(ctx.bucket)
                        .download(
                            `${ctx.tenant}/${item.id}/final-${req.query.category}.jpg`,
                        ),
                );
                res.setHeader("Cache-Control", "no-store");
                res.setHeader("Content-Type", "image/jpeg");
                return res
                    .status(200)
                    .send(Buffer.from(await file.arrayBuffer()));
            }
            check(req.method === "POST", "Método não permitido.", 405);
            const body = req.body;
            if (body?.action === "catalogo") {
                const itens = body.itens;
                check(
                    Array.isArray(itens) &&
                        itens.length <= 5000 &&
                        itens.every(
                            (d) =>
                                d &&
                                typeof d.label === "string" &&
                                d.label.trim().length >= 1 &&
                                d.label.length <= 160 &&
                                !/[\x00-\x1f\x7f]/.test(d.label) &&
                                (d.responsible === null ||
                                    (typeof d.responsible === "string" &&
                                        d.responsible.trim().length >= 3 &&
                                        d.responsible.length <= 120)),
                        ) &&
                        new Set(itens.map((d) => d.label)).size ===
                            itens.length,
                    "Catálogo inválido.",
                );
                const clean = itens.map((d) => ({
                    label: d.label.trim(),
                    responsible: d.responsible?.trim() || null,
                }));
                await result(
                    ctx.client.from("sdv_portal_catalogos").upsert(
                        {
                            implantacao_id: ctx.tenant,
                            itens: clean,
                            hash: hash(JSON.stringify(clean)),
                            updated_at: new Date().toISOString(),
                        },
                        { onConflict: "implantacao_id" },
                    ),
                );
                return respond(res, 200, { stored: clean.length });
            }
            if (body?.action === "falha") {
                check(
                    typeof body.code === "string" &&
                        /^[a-z_]{3,40}$/.test(body.code),
                    "Falha inválida.",
                );
                const item = await row(ctx, body.id);
                const changed = await result(
                    ctx.client.rpc("sdv_portal_falha", {
                        p_id: item.id,
                        p_tenant: ctx.tenant,
                        p_code: body.code,
                        p_max: MAX_ATTEMPTS,
                    }),
                );
                const current = Array.isArray(changed) ? changed[0] : changed;
                return respond(res, 200, {
                    state: current?.state ?? item.state,
                    attempts: current?.import_attempts ?? item.import_attempts,
                });
            }
            if (body?.action === "reabrir") {
                const item = await row(ctx, body.id);
                const reopened = await result(
                    ctx.client.rpc("sdv_portal_reabrir", {
                        p_id: item.id,
                        p_tenant: ctx.tenant,
                    }),
                );
                return respond(res, 200, { reopened: reopened === true });
            }
            check(
                body?.action === "ack" &&
                    Number.isSafeInteger(body.version) &&
                    body.version >= 1 &&
                    [
                        "aguardando",
                        "aprovado",
                        "rejeitado",
                        "correcao",
                    ].includes(body.status),
                "Confirmação inválida.",
            );
            const item = await row(ctx, body.id);
            check(item.state !== "rascunho", "Envio ainda incompleto.", 409);
            const accepted = await result(
                ctx.client.rpc("sdv_portal_ack", {
                    p_id: item.id,
                    p_tenant: ctx.tenant,
                    p_version: body.version,
                    p_status: body.status,
                }),
            );
            check(accepted, "Conflito de versão. Conferência necessária.", 409);
            return respond(res, 200, { acknowledged: true });
        } catch (error) {
            return failure(res, error);
        }
    };
}
export default createHandler();
