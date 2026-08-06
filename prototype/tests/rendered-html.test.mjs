import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";

async function render() {
  const workerUrl = new URL("../dist/server/index.js", import.meta.url);
  workerUrl.searchParams.set("test", `${process.pid}-${Date.now()}`);
  const { default: worker } = await import(workerUrl.href);

  return worker.fetch(
    new Request("http://localhost/", {
      headers: { accept: "text/html" },
    }),
    {
      ASSETS: {
        fetch: async () => new Response("Not found", { status: 404 }),
      },
    },
    {
      waitUntil() {},
      passThroughOnException() {},
    },
  );
}

test("server-renders the SDV Access login experience", async () => {
  const response = await render();
  assert.equal(response.status, 200);
  assert.match(response.headers.get("content-type") ?? "", /^text\/html\b/i);

  const html = await response.text();
  assert.match(html, /<html lang="pt-BR">/i);
  assert.match(html, /<title>SDV Access — Protótipo navegável<\/title>/i);
  assert.match(html, /Entrar no SDV Access/);
  assert.match(html, /Santa Rita · Portaria Principal/);
  assert.match(html, /Simular primeira instalação/);
  assert.doesNotMatch(html, /codex-preview|Your site is taking shape/i);
});

test("keeps the interactive role flows and removes starter dependencies", async () => {
  const [page, layout, packageJson] = await Promise.all([
    readFile(new URL("../app/page.tsx", import.meta.url), "utf8"),
    readFile(new URL("../app/layout.tsx", import.meta.url), "utf8"),
    readFile(new URL("../package.json", import.meta.url), "utf8"),
  ]);

  assert.match(page, /Central técnica da plataforma/);
  assert.match(page, /Administração do condomínio/);
  assert.match(page, /Operação da portaria/);
  assert.match(page, /Ativar terminal/);
  assert.match(page, /Iniciar turno/);
  assert.match(page, /Validar e liberar/);
  assert.match(page, /Perfis e permissões/);
  assert.match(layout, /SDV Access — Protótipo navegável/);
  assert.doesNotMatch(packageJson, /react-loading-skeleton/);
  assert.doesNotMatch(page, /_sites-preview|SkeletonPreview/);
});
