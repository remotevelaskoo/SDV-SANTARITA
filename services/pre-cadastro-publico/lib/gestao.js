import { createClient } from "@supabase/supabase-js";
import { check, uuid } from "./validation.js";
import { result } from "./cloud.js";
// Painel de gestão (PO 15/11/2026). Independente do PORTAL_ENABLED: o pré-cadastro
// pode estar fechado e a gestão continuar vendo os números.
export function gestaoConfiguration(env = process.env) {
    check(env.GESTAO_ENABLED === "true", "Painel de gestão desativado.", 503);
    check(
        /^https:\/\/[^/]+\.supabase\.co$/.test(env.SUPABASE_URL ?? "") &&
            env.SUPABASE_SECRET_KEY &&
            uuid(env.PORTAL_IMPLANTACAO_ID) &&
            typeof env.PORTAL_NODE_SECRET === "string" &&
            env.PORTAL_NODE_SECRET.length >= 32,
        "Painel aguardando configuração.",
        503,
    );
    const tenant = env.PORTAL_IMPLANTACAO_ID;
    const make = () =>
        createClient(env.SUPABASE_URL, env.SUPABASE_SECRET_KEY, {
            auth: { persistSession: false, autoRefreshToken: false },
        });
    const db = make();
    const now = () => new Date().toISOString();
    return {
        tenant,
        node: env.PORTAL_NODE_SECRET,
        name: env.PORTAL_NAME || "Santa Rita",
        store: {
            async saveMetrics(dia, dados) {
                await result(
                    db
                        .from("sdv_gestao_metricas")
                        .upsert(
                            { implantacao_id: tenant, dia, dados, updated_at: now() },
                            { onConflict: "implantacao_id,dia" },
                        ),
                );
            },
            async metrics(dia) {
                const row = await result(
                    db
                        .from("sdv_gestao_metricas")
                        .select("dados,updated_at")
                        .eq("implantacao_id", tenant)
                        .eq("dia", dia)
                        .maybeSingle(),
                );
                return row ?? null;
            },
            async days() {
                const rows = await result(
                    db
                        .from("sdv_gestao_metricas")
                        .select("dia")
                        .eq("implantacao_id", tenant)
                        .order("dia", { ascending: false })
                        .limit(400),
                );
                return rows.map((r) => r.dia);
            },
            async metricsRange(de, ate) {
                return await result(
                    db
                        .from("sdv_gestao_metricas")
                        .select("dia,dados,updated_at")
                        .eq("implantacao_id", tenant)
                        .gte("dia", de)
                        .lte("dia", ate)
                        .order("dia", { ascending: true })
                        .limit(93),
                );
            },
            async gestor(email) {
                const row = await result(
                    db
                        .from("sdv_gestao_gestores")
                        .select("email,nome,user_id,ativo")
                        .eq("implantacao_id", tenant)
                        .eq("email", email)
                        .maybeSingle(),
                );
                return row ?? null;
            },
            async saveGestor(row) {
                await result(
                    db
                        .from("sdv_gestao_gestores")
                        .upsert(
                            { implantacao_id: tenant, ...row, updated_at: now() },
                            { onConflict: "implantacao_id,email" },
                        ),
                );
            },
            // Quem perdeu o Dashboard no sistema local perde o painel remoto.
            async syncAllowed(emails) {
                const rows = await result(
                    db
                        .from("sdv_gestao_gestores")
                        .select("email,ativo")
                        .eq("implantacao_id", tenant),
                );
                for (const row of rows) {
                    const ativo = emails.includes(row.email);
                    if (ativo !== row.ativo) {
                        await result(
                            db
                                .from("sdv_gestao_gestores")
                                .update({ ativo, updated_at: now() })
                                .eq("implantacao_id", tenant)
                                .eq("email", row.email),
                        );
                    }
                }
            },
        },
        auth: {
            async signIn(email, password) {
                const { data, error } = await make().auth.signInWithPassword({
                    email,
                    password,
                });
                return error ? null : data.session;
            },
            async refresh(refreshToken) {
                const { data, error } = await make().auth.refreshSession({
                    refresh_token: refreshToken,
                });
                return error ? null : data.session;
            },
            async emailOf(accessToken) {
                const { data, error } = await db.auth.getUser(accessToken);
                return error ? null : (data.user?.email ?? "").toLowerCase();
            },
            async upsertUser(userId, email, password, nome) {
                if (userId) {
                    const { error } = await db.auth.admin.updateUserById(userId, {
                        password,
                    });
                    if (!error) return userId;
                }
                const { data, error } = await db.auth.admin.createUser({
                    email,
                    password,
                    email_confirm: true,
                    user_metadata: { nome },
                    app_metadata: { sdv_gestao: tenant },
                });
                check(
                    !error,
                    "Não foi possível criar o acesso. O e-mail pode já ter outra conta.",
                    409,
                );
                return data.user.id;
            },
        },
    };
}
