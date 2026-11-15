import { respond, failure } from "../lib/cloud.js";
import { check, sameSecret } from "../lib/validation.js";
import { gestaoConfiguration } from "../lib/gestao.js";
const DIA = /^\d{4}-\d{2}-\d{2}$/;
const EMAIL = /^[^\s@]{1,64}@[^\s@]{1,190}\.[^\s@]{2,}$/;
const bearer = (req) =>
    (req.headers.authorization ?? "").replace(/^Bearer\s+/i, "");
const email = (value) =>
    typeof value === "string" ? value.trim().toLowerCase() : "";
// Painel de gestão (PO 15/11/2026): o servidor do condomínio empurra os totais
// (Bearer do nó) e o gestor lê com e-mail e senha (Supabase Auth).
export function createHandler(makeContext = gestaoConfiguration) {
    return async function handler(req, res) {
        try {
            const ctx = makeContext();
            const body = req.body ?? {};
            if (sameSecret(req.headers.authorization, `Bearer ${ctx.node}`)) {
                return await fromNode(ctx, req, res, body);
            }
            if (req.method === "POST") {
                return await login(ctx, res, body);
            }
            check(req.method === "GET", "Método não permitido.", 405);
            const token = bearer(req);
            check(token.length > 20 && token.length < 4096, "Entre novamente.", 401);
            const quem = await ctx.auth.emailOf(token);
            check(quem, "Entre novamente.", 401);
            check((await ctx.store.gestor(quem))?.ativo, "Acesso desativado.", 403);
            const dias = await ctx.store.days();
            const dia = DIA.test(req.query?.dia ?? "") ? req.query.dia : dias[0];
            const item = dia ? await ctx.store.metrics(dia) : null;
            return respond(res, 200, {
                condominio: ctx.name,
                dia: dia ?? null,
                dias,
                dados: item?.dados ?? null,
                atualizado_em: item?.updated_at ?? null,
            });
        } catch (error) {
            return failure(res, error);
        }
    };
}
async function fromNode(ctx, req, res, body) {
    check(req.method === "POST", "Método não permitido.", 405);
    if (body.action === "metricas") {
        check(
            DIA.test(body.dia ?? "") &&
                body.dados &&
                typeof body.dados === "object" &&
                !Array.isArray(body.dados) &&
                JSON.stringify(body.dados).length <= 600000,
            "Métricas inválidas.",
        );
        await ctx.store.saveMetrics(body.dia, body.dados);
        return respond(res, 200, { stored: body.dia });
    }
    if (body.action === "gestores") {
        check(
            Array.isArray(body.emails) &&
                body.emails.length <= 200 &&
                body.emails.every(
                    (e) => typeof e === "string" && EMAIL.test(e) && e === e.toLowerCase(),
                ),
            "Lista de gestores inválida.",
        );
        await ctx.store.syncAllowed(body.emails);
        return respond(res, 200, { allowed: body.emails.length });
    }
    if (body.action === "gestor") {
        const quem = email(body.email);
        check(EMAIL.test(quem), "E-mail inválido.");
        check(
            typeof body.senha === "string" &&
                body.senha.length >= 10 &&
                body.senha.length <= 72,
            "Senha deve ter de 10 a 72 caracteres.",
        );
        const nome =
            typeof body.nome === "string" ? body.nome.trim().slice(0, 120) : null;
        const atual = await ctx.store.gestor(quem);
        const userId = await ctx.auth.upsertUser(
            atual?.user_id ?? null,
            quem,
            body.senha,
            nome,
        );
        await ctx.store.saveGestor({ email: quem, nome, user_id: userId, ativo: true });
        return respond(res, 200, { ready: true });
    }
    check(false, "Ação desconhecida.", 400);
}
async function login(ctx, res, body) {
    let session = null;
    if (body.action === "login") {
        const quem = email(body.email);
        check(
            EMAIL.test(quem) && typeof body.senha === "string" && body.senha.length <= 72,
            "E-mail ou senha incorretos.",
            401,
        );
        check((await ctx.store.gestor(quem))?.ativo, "E-mail ou senha incorretos.", 401);
        session = await ctx.auth.signIn(quem, body.senha);
        check(session, "E-mail ou senha incorretos.", 401);
    } else {
        check(body.action === "renovar", "Ação desconhecida.", 400);
        check(
            typeof body.refresh === "string" && body.refresh.length <= 512,
            "Sessão expirada.",
            401,
        );
        session = await ctx.auth.refresh(body.refresh);
        check(session, "Sessão expirada.", 401);
    }
    const quem = email(session.user?.email);
    check((await ctx.store.gestor(quem))?.ativo, "Acesso desativado.", 403);
    return respond(res, 200, {
        token: session.access_token,
        refresh: session.refresh_token,
        expira: session.expires_at,
        nome: session.user?.user_metadata?.nome ?? quem,
    });
}
export default createHandler();
