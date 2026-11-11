import { createHmac } from "node:crypto";
import sharp from "sharp";
import {
    configuration,
    catalog,
    result,
    row,
    respond,
    failure,
} from "../lib/cloud.js";
import {
    check,
    hash,
    uuid,
    secret,
    sameSecret,
    normalize,
    categories,
    visitWindow,
} from "../lib/validation.js";

export function createHandler(makeContext = configuration) {
    return async function handler(req, res) {
        try {
            const ctx = makeContext();
            check(
                sameSecret(req.headers["x-portal-invite"], ctx.invite),
                "Convite inválido ou encerrado.",
                401,
            );
            if (req.method === "GET") {
                // Somente rótulos: nomes de responsáveis não saem do servidor.
                const destinations = await catalog(ctx);
                return respond(res, 200, {
                    name: ctx.name,
                    destinations: {
                        visitante: destinations
                            .filter((d) => d.responsible)
                            .map((d) => d.label),
                        prestador: destinations.map((d) => d.label),
                    },
                    visitWindow: visitWindow(),
                    privacy: ctx.privacy,
                });
            }
            check(req.method === "POST", "Método não permitido.", 405);
            const body = req.body;
            check(
                body &&
                    typeof body === "object" &&
                    JSON.stringify(body).length < 20000,
                "Requisição inválida.",
                413,
            );
            check(uuid(body.id) && secret(body.token), "Solicitação inválida.");
            // Contagem compartilhada entre instâncias, sem gravar IP puro.
            const address = String(
                req.headers["x-vercel-forwarded-for"] ||
                    req.socket?.remoteAddress ||
                    "unknown",
            )
                .split(",")[0]
                .trim();
            const bucket = new Date().toISOString().slice(0, 13);
            const key = createHmac("sha256", ctx.rateSecret)
                .update(`${ctx.tenant}:${address}:${bucket}`)
                .digest("hex");
            const count = await result(
                ctx.client.rpc("sdv_portal_rate", { p_key: key }),
            );
            check(count <= 120, "Aguarde antes de tentar novamente.", 429);
            if (body.action === "begin") {
                check(
                    secret(body.receipt) &&
                        ["cnh", "rg_cin"].includes(body.documentType),
                    "Solicitação inválida.",
                );
                await result(
                    ctx.client
                        .from("sdv_portal_solicitacoes")
                        .upsert(
                            {
                                id: body.id,
                                implantacao_id: ctx.tenant,
                                upload_hash: hash(body.token),
                                receipt_hash: hash(body.receipt),
                                document_type: body.documentType,
                            },
                            { onConflict: "id", ignoreDuplicates: true },
                        ),
                );
                const item = await row(ctx, body.id);
                check(
                    sameSecret(hash(body.token), item.upload_hash) &&
                        sameSecret(hash(body.receipt), item.receipt_hash),
                    "Solicitação inválida.",
                    403,
                );
                check(
                    item.document_type === body.documentType &&
                        item.state === "rascunho",
                    "Solicitação já enviada ou alterada. Reinicie o formulário.",
                    409,
                );
                const uploads = {};
                for (const category of categories(item.document_type)) {
                    const path = `${ctx.tenant}/${item.id}/entrada-${category}`;
                    const signed = await result(
                        ctx.client.storage
                            .from(ctx.bucket)
                            .createSignedUploadUrl(path),
                    );
                    uploads[category] = signed.signedUrl;
                }
                return respond(res, 200, { id: item.id, uploads });
            }
            const item = await row(ctx, body.id);
            if (body.action === "status") {
                check(
                    sameSecret(hash(body.token), item.receipt_hash),
                    "Protocolo não encontrado.",
                    404,
                );
                return respond(res, 200, {
                    protocol: item.protocol,
                    state: item.state,
                    status: item.review_status,
                    message:
                        item.state === "rascunho"
                            ? "Envio ainda não concluído."
                            : "Recebido. A situação do pré-cadastro não substitui a autorização de entrada.",
                });
            }
            check(
                body.action === "submit" &&
                    sameSecret(hash(body.token), item.upload_hash),
                "Solicitação inválida.",
                403,
            );
            const data = normalize(body.data, await catalog(ctx));
            check(
                data.document_type === item.document_type,
                "Tipo de documento alterado. Reinicie o formulário.",
            );
            const fingerprint = hash(JSON.stringify(data));
            if (item.state !== "rascunho") {
                check(
                    item.data_hash === fingerprint,
                    "Solicitação já enviada com outros dados.",
                    409,
                );
                return respond(res, 200, {
                    protocol: item.protocol,
                    state: item.state,
                });
            }
            const files = [];
            for (const category of categories(item.document_type)) {
                const source = await result(
                    ctx.client.storage
                        .from(ctx.bucket)
                        .download(
                            `${ctx.tenant}/${item.id}/entrada-${category}`,
                        ),
                );
                check(source.size <= 8 * 1024 * 1024, "Imagem acima de 8 MB.");
                const bytes = Buffer.from(await source.arrayBuffer());
                let image;
                try {
                    const options = {
                        limitInputPixels: 40000000,
                        failOn: "warning",
                    };
                    const meta = await sharp(bytes, options).metadata();
                    check(
                        ["jpeg", "png", "webp"].includes(meta.format) &&
                            !meta.pages &&
                            meta.width >= 320 &&
                            meta.height >= 320,
                        "Use uma imagem legível JPG, PNG ou WebP.",
                    );
                    image = await sharp(bytes, options)
                        .rotate()
                        .resize({
                            width: 2400,
                            height: 2400,
                            fit: "inside",
                            withoutEnlargement: true,
                        })
                        .jpeg({ quality: 90 })
                        .toBuffer();
                } catch {
                    check(
                        false,
                        "Não foi possível validar a imagem. Envie outra fotografia.",
                    );
                }
                const path = `${ctx.tenant}/${item.id}/final-${category}.jpg`;
                await result(
                    ctx.client.storage
                        .from(ctx.bucket)
                        .upload(path, image, {
                            contentType: "image/jpeg",
                            upsert: true,
                        }),
                );
                files.push({
                    category,
                    sha256: hash(image),
                    size: image.length,
                });
            }
            const protocol = `PRE-SRA-${item.id.replaceAll("-", "").toUpperCase()}`;
            await result(
                ctx.client
                    .from("sdv_portal_solicitacoes")
                    .update({
                        dados: data,
                        data_hash: fingerprint,
                        files,
                        protocol,
                        state: "enviado",
                        submitted_at: new Date().toISOString(),
                        privacy_version: hash(ctx.privacy),
                    })
                    .eq("implantacao_id", ctx.tenant)
                    .eq("id", item.id)
                    .eq("state", "rascunho"),
            );
            const saved = await row(ctx, item.id);
            check(
                saved.data_hash === fingerprint && saved.state !== "rascunho",
                "Solicitação já enviada com outros dados.",
                409,
            );
            return respond(res, 200, {
                protocol: saved.protocol,
                state: saved.state,
            });
        } catch (error) {
            return failure(res, error);
        }
    };
}
export default createHandler();
