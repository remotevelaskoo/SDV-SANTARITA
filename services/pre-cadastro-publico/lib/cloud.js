import { createClient } from "@supabase/supabase-js";
import { check, uuid } from "./validation.js";
export function configuration(env = process.env) {
    check(
        env.PORTAL_ENABLED === "true",
        "Pré-cadastro temporariamente indisponível.",
        503,
    );
    check(
        /^https:\/\/[^/]+\.supabase\.co$/.test(env.SUPABASE_URL ?? "") &&
            env.SUPABASE_SECRET_KEY &&
            uuid(env.PORTAL_IMPLANTACAO_ID),
        "Portal aguardando configuração.",
        503,
    );
    check(
        [
            env.PORTAL_INVITE_SECRET,
            env.PORTAL_NODE_SECRET,
            env.PORTAL_RATE_SECRET,
        ].every((s) => typeof s === "string" && s.length >= 32),
        "Portal aguardando configuração.",
        503,
    );
    check(
        new Set([
            env.PORTAL_INVITE_SECRET,
            env.PORTAL_NODE_SECRET,
            env.PORTAL_RATE_SECRET,
        ]).size === 3,
        "Portal aguardando configuração.",
        503,
    );
    check(
        env.PORTAL_PRIVACY_TEXT?.trim(),
        "Portal aguardando aviso de privacidade.",
        503,
    );
    return {
        client: createClient(env.SUPABASE_URL, env.SUPABASE_SECRET_KEY, {
            auth: { persistSession: false, autoRefreshToken: false },
        }),
        tenant: env.PORTAL_IMPLANTACAO_ID,
        bucket: env.PORTAL_BUCKET || "pre-cadastro-privado",
        invite: env.PORTAL_INVITE_SECRET,
        node: env.PORTAL_NODE_SECRET,
        rateSecret: env.PORTAL_RATE_SECRET,
        name: env.PORTAL_NAME || "Santa Rita",
        privacy: env.PORTAL_PRIVACY_TEXT,
    };
}
// Catálogo publicado pelo sistema local (imóveis ativos e responsável principal).
// Sem catálogo sincronizado, visitante e prestador ficam indisponíveis.
export async function catalog(ctx) {
    const item = await result(
        ctx.client
            .from("sdv_portal_catalogos")
            .select("itens")
            .eq("implantacao_id", ctx.tenant)
            .maybeSingle(),
    );
    const itens = Array.isArray(item?.itens) ? item.itens : [];
    return itens.filter(
        (d) =>
            d &&
            typeof d.label === "string" &&
            d.label.trim() &&
            (d.responsible === null || typeof d.responsible === "string"),
    );
}
export async function result(promise) {
    const { data, error } = await promise;
    if (error) throw new Error("Falha no armazenamento protegido.");
    return data;
}
export async function row(ctx, id) {
    check(uuid(id), "Solicitação inválida.", 404);
    const item = await result(
        ctx.client
            .from("sdv_portal_solicitacoes")
            .select("*")
            .eq("implantacao_id", ctx.tenant)
            .eq("id", id)
            .maybeSingle(),
    );
    check(item, "Solicitação não encontrada.", 404);
    return item;
}
export function respond(res, status, body) {
    res.setHeader("Cache-Control", "no-store");
    res.setHeader("X-Content-Type-Options", "nosniff");
    return res.status(status).json(body);
}
export function failure(res, error) {
    return respond(res, error.status || 503, {
        error: error.status
            ? error.message
            : "Serviço temporariamente indisponível. Tente novamente.",
    });
}
