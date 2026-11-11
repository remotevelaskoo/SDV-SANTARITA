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
