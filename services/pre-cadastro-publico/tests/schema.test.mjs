import test from "node:test";
import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import { PGlite } from "@electric-sql/pglite";
test("Postgres: isolamento, RLS, bucket privado, quota atômica e ACK monotônico", async () => {
    const db = new PGlite();
    try {
        await db.exec(`create role anon; create role authenticated; create role service_role bypassrls;
      create schema storage; create table storage.buckets(id text primary key,name text,public boolean,file_size_limit bigint,allowed_mime_types text[]);`);
        await db.exec(
            await readFile(
                new URL("../supabase/bootstrap.sql", import.meta.url),
                "utf8",
            ),
        );
        const tables = await db.query(
            `select relname,relrowsecurity from pg_class where relname in ('sdv_portal_solicitacoes','sdv_portal_rate_limits')`,
        );
        assert.equal(tables.rows.length, 2);
        assert.ok(tables.rows.every((row) => row.relrowsecurity));
        assert.equal(
            (await db.query("select public from storage.buckets")).rows[0]
                .public,
            false,
        );
        await db.exec("set role anon");
        await assert.rejects(
            db.query("select * from public.sdv_portal_solicitacoes"),
            /permission denied/,
        );
        await assert.rejects(
            db.query("select public.sdv_portal_rate('teste')"),
            /permission denied/,
        );
        await db.exec("reset role; set role authenticated");
        await assert.rejects(
            db.query("select * from public.sdv_portal_solicitacoes"),
            /permission denied/,
        );
        await db.exec("reset role; set role service_role");
        assert.equal(
            (await db.query("select public.sdv_portal_rate('teste') as hits"))
                .rows[0].hits,
            1,
        );
        assert.equal(
            (await db.query("select public.sdv_portal_rate('teste') as hits"))
                .rows[0].hits,
            2,
        );
        const id = "12345678-1234-4234-8234-123456789abc";
        const tenant = "87654321-4321-4321-8321-123456789abc";
        await db.query(
            `insert into public.sdv_portal_solicitacoes(id,implantacao_id,upload_hash,receipt_hash,document_type,state) values($1,$2,'hash','hash','cnh','enviado')`,
            [id, tenant],
        );
        const ack = async (t, v, s) =>
            (
                await db.query(
                    "select public.sdv_portal_ack($1,$2,$3,$4) as ok",
                    [id, t, v, s],
                )
            ).rows[0].ok;
        assert.equal(await ack(id, 1, "aguardando"), false);
        assert.equal(await ack(tenant, 2, "aprovado"), true);
        assert.equal(await ack(tenant, 2, "aprovado"), true);
        assert.equal(await ack(tenant, 1, "aguardando"), false);
        assert.equal(await ack(tenant, 2, "rejeitado"), false);
        assert.equal(await ack(tenant, 3, "inexistente"), false);
        assert.equal(
            (
                await db.query(
                    "select review_status from public.sdv_portal_solicitacoes",
                )
            ).rows[0].review_status,
            "aprovado",
        );
    } finally {
        await db.close();
    }
});
test("Postgres: migração da fila com espera, conferência e catálogo privado", async () => {
    const db = new PGlite();
    try {
        await db.exec(`create role anon; create role authenticated; create role service_role bypassrls;
      create schema storage; create table storage.buckets(id text primary key,name text,public boolean,file_size_limit bigint,allowed_mime_types text[]);`);
        await db.exec(
            await readFile(
                new URL("../supabase/bootstrap.sql", import.meta.url),
                "utf8",
            ),
        );
        const tenant = "87654321-4321-4321-8321-123456789abc";
        const id = "12345678-1234-4234-8234-123456789abc";
        // Solicitação já existente antes da migração continua válida.
        await db.query(
            `insert into public.sdv_portal_solicitacoes(id,implantacao_id,upload_hash,receipt_hash,document_type,state,submitted_at) values($1,$2,'h','h','cnh','enviado',now())`,
            [id, tenant],
        );
        await db.exec(
            await readFile(
                new URL(
                    "../supabase/migrations/20261111200000_fila_conferencia_catalogo_local.sql",
                    import.meta.url,
                ),
                "utf8",
            ),
        );
        await db.exec("set role anon");
        await assert.rejects(
            db.query("select * from public.sdv_portal_catalogos"),
            /permission denied/,
        );
        await assert.rejects(
            db.query("select * from public.sdv_portal_falha($1,$2,'teste',5)", [
                id,
                tenant,
            ]),
            /permission denied/,
        );
        await db.exec("reset role; set role service_role");
        const fail = async (code = "dados_invalidos") =>
            (
                await db.query(
                    "select * from public.sdv_portal_falha($1,$2,$3,3)",
                    [id, tenant, code],
                )
            ).rows[0];
        assert.equal(await fail("Nome Pessoal"), undefined);
        assert.deepEqual(await fail(), {
            state: "enviado",
            import_attempts: 1,
        });
        const wait = (
            await db.query(
                "select next_attempt_at > now() as later, last_failure from public.sdv_portal_solicitacoes",
            )
        ).rows[0];
        assert.deepEqual(wait, {
            later: true,
            last_failure: "dados_invalidos",
        });
        assert.deepEqual(await fail(), {
            state: "enviado",
            import_attempts: 2,
        });
        assert.deepEqual(await fail(), {
            state: "conferencia",
            import_attempts: 3,
        });
        assert.equal(await fail(), undefined);
        const reopen = async (t) =>
            (
                await db.query(
                    "select public.sdv_portal_reabrir($1,$2) as ok",
                    [id, t],
                )
            ).rows[0].ok;
        assert.equal(await reopen(id), false);
        assert.equal(await reopen(tenant), true);
        assert.equal(await reopen(tenant), false);
        assert.deepEqual(
            (
                await db.query(
                    "select state,import_attempts,next_attempt_at from public.sdv_portal_solicitacoes",
                )
            ).rows[0],
            { state: "enviado", import_attempts: 0, next_attempt_at: null },
        );
        await assert.rejects(
            db.query(
                `update public.sdv_portal_solicitacoes set state='invalido'`,
            ),
            /check constraint/,
        );
        await db.query(
            `insert into public.sdv_portal_catalogos(implantacao_id,itens,hash) values($1,'[{"label":"A","responsible":null}]','x')`,
            [tenant],
        );
        await assert.rejects(
            db.query(
                `insert into public.sdv_portal_catalogos(implantacao_id,itens,hash) values(gen_random_uuid(),'{}','x')`,
            ),
            /check constraint/,
        );
    } finally {
        await db.close();
    }
});
