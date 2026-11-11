import { configuration, result, row, respond, failure } from "../lib/cloud.js";
import { check, sameSecret, categories } from "../lib/validation.js";
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
