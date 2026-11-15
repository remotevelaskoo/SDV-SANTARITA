import test from "node:test";
import assert from "node:assert/strict";
import { createHandler } from "../api/gestao.js";
import { gestaoConfiguration } from "../lib/gestao.js";

const node = "n".repeat(40);
function contexto() {
    const metricas = new Map();
    const gestores = new Map();
    const usuarios = new Map();
    const ctx = {
        tenant: "12345678-1234-4234-8234-123456789abc",
        node,
        name: "Santa Rita",
        store: {
            async saveMetrics(dia, dados) {
                metricas.set(dia, { dados, updated_at: "2026-11-15T12:00:00Z" });
            },
            async metrics(dia) {
                return metricas.get(dia) ?? null;
            },
            async days() {
                return [...metricas.keys()].sort().reverse();
            },
            async gestor(email) {
                return gestores.get(email) ?? null;
            },
            async saveGestor(row) {
                gestores.set(row.email, row);
            },
            async syncAllowed(emails) {
                for (const row of gestores.values()) row.ativo = emails.includes(row.email);
            },
        },
        auth: {
            async signIn(email, senha) {
                const u = usuarios.get(email);
                return u && u.senha === senha
                    ? { access_token: `token-de-acesso-${email}`, refresh_token: "r", expires_at: 1, user: { email, user_metadata: { nome: u.nome } } }
                    : null;
            },
            async refresh() {
                return null;
            },
            async emailOf(token) {
                return token.startsWith("token-de-acesso-") ? token.slice(16) : null;
            },
            async upsertUser(userId, email, senha, nome) {
                usuarios.set(email, { senha, nome });
                return userId ?? "11111111-1111-4111-8111-111111111111";
            },
        },
    };
    return { ctx, metricas, gestores };
}
async function chamar(handler, { method = "POST", body, auth, query = {} }) {
    const res = {
        statusCode: 0,
        headers: {},
        setHeader(k, v) {
            this.headers[k] = v;
        },
        status(c) {
            this.statusCode = c;
            return this;
        },
        json(d) {
            this.body = d;
            return this;
        },
    };
    await handler({ method, body, query, headers: auth ? { authorization: auth } : {} }, res);
    return res;
}

test("sem GESTAO_ENABLED o painel fica desligado", () => {
    assert.throws(() => gestaoConfiguration({}), /desativado/);
});

test("nó publica métricas e cria o acesso; gestor entra e lê só com senha", async () => {
    const { ctx } = contexto();
    const h = createHandler(() => ctx);
    const dados = { dia: "2026-11-15", arrecadacao: { total: 75 } };

    assert.equal((await chamar(h, { body: { action: "metricas", dia: "2026-11-15", dados }, auth: `Bearer ${node}` })).statusCode, 200);
    // Sem a credencial do nó, ninguém publica números.
    assert.equal((await chamar(h, { body: { action: "metricas", dia: "2026-11-15", dados }, auth: "Bearer outro" })).statusCode, 400);

    assert.equal((await chamar(h, { body: { action: "gestor", email: "Gestor@Teste.com", nome: "Gestor", senha: "curta" }, auth: `Bearer ${node}` })).statusCode, 422);
    assert.equal((await chamar(h, { body: { action: "gestor", email: "Gestor@Teste.com", nome: "Gestor", senha: "senha-forte-123" }, auth: `Bearer ${node}` })).statusCode, 200);

    assert.equal((await chamar(h, { body: { action: "login", email: "gestor@teste.com", senha: "errada-123456" } })).statusCode, 401);
    const entrar = await chamar(h, { body: { action: "login", email: "gestor@teste.com", senha: "senha-forte-123" } });
    assert.equal(entrar.statusCode, 200);

    assert.equal((await chamar(h, { method: "GET" })).statusCode, 401);
    const leitura = await chamar(h, { method: "GET", auth: `Bearer ${entrar.body.token}` });
    assert.equal(leitura.statusCode, 200);
    assert.equal(leitura.body.dia, "2026-11-15");
    assert.equal(leitura.body.dados.arrecadacao.total, 75);
});

test("quem perde o Dashboard no sistema local perde o painel remoto", async () => {
    const { ctx } = contexto();
    const h = createHandler(() => ctx);
    await chamar(h, { body: { action: "gestor", email: "gestor@teste.com", senha: "senha-forte-123" }, auth: `Bearer ${node}` });
    const token = (await chamar(h, { body: { action: "login", email: "gestor@teste.com", senha: "senha-forte-123" } })).body.token;

    assert.equal((await chamar(h, { body: { action: "gestores", emails: [] }, auth: `Bearer ${node}` })).statusCode, 200);

    assert.equal((await chamar(h, { method: "GET", auth: `Bearer ${token}` })).statusCode, 403);
    assert.equal((await chamar(h, { body: { action: "login", email: "gestor@teste.com", senha: "senha-forte-123" } })).statusCode, 401);
});

test("e-mail sem acesso liberado não entra, mesmo com conta no Auth", async () => {
    const { ctx } = contexto();
    const h = createHandler(() => ctx);
    await ctx.auth.upsertUser(null, "outro@teste.com", "senha-forte-123", "Outro");
    assert.equal((await chamar(h, { body: { action: "login", email: "outro@teste.com", senha: "senha-forte-123" } })).statusCode, 401);
});
