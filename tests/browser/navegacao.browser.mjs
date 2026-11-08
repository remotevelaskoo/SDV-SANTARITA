/**
 * Testes de interação da navegação (AJ-009, SDV-UXD-004 — recolhimento moderno).
 *
 * Rodam num navegador real contra a aplicação servida com os seeders demo:
 *
 *   php artisan serve --port=8010   (banco com DatabaseSeeder)
 *   SDV_BASE_URL=http://127.0.0.1:8010 pnpm test:browser
 *
 * Opcional: SDV_EVIDENCIAS=docs/evidencias/AJ-009 salva capturas de tela.
 * Exige Playwright com Chromium instalado (não é dependência do projeto).
 * Sem SDV_BASE_URL os testes são ignorados.
 */
import { after, before, describe, test } from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { mkdirSync } from 'node:fs';

const BASE = process.env.SDV_BASE_URL;
const EVIDENCIAS = process.env.SDV_EVIDENCIAS;
const SENHA = process.env.SDV_SENHA_DEMO ?? 'sdv2026';
const pular = BASE ? false : 'defina SDV_BASE_URL para rodar os testes de navegador';

let chromium;
let navegador;

before(async () => {
    if (pular) return;
    const require = createRequire(import.meta.url);
    try {
        ({ chromium } = require('playwright'));
    } catch {
        ({ chromium } = require(require.resolve('playwright', { paths: [process.env.NODE_PATH ?? '', '/opt/node-tools/node_modules'] })));
    }
    navegador = await chromium.launch();
    if (EVIDENCIAS) mkdirSync(EVIDENCIAS, { recursive: true });
});

after(async () => navegador?.close());

async function entrar(contexto, usuario) {
    const pagina = await contexto.newPage();
    const erros = [];
    pagina.on('pageerror', (erro) => erros.push(erro.message));
    pagina.erros = erros;
    await pagina.goto(`${BASE}/entrar`);
    await pagina.fill('[wire\\:model="identification"]', usuario);
    await pagina.fill('[wire\\:model="password"]', SENHA);
    await pagina.click('button.login-submit');
    await pagina.waitForURL((url) => !url.pathname.startsWith('/entrar'));
    await pagina.waitForLoadState('networkidle');
    return pagina;
}

const esperarTransicao = (pagina) => pagina.waitForTimeout(350);
const caixa = (pagina, seletor) => pagina.locator(seletor).first().boundingBox();
const capturar = async (pagina, nome) => EVIDENCIAS && pagina.screenshot({ path: `${EVIDENCIAS}/${nome}.png` });
const semRolagemHorizontal = (pagina) => pagina.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth);
const visivel = (pagina, seletor) => pagina.locator(seletor).first().isVisible();

describe('computador: barra expandida e compacta', { skip: pular }, () => {
    test('alterna estados, devolve o espaço ao conteúdo e lembra a preferência', async () => {
        const contexto = await navegador.newContext({ viewport: { width: 1366, height: 860 } });
        const pagina = await entrar(contexto, 'administrador');
        const controle = pagina.locator('.sidebar [data-sidebar-toggle]');

        assert.equal((await caixa(pagina, '.sidebar')).width, 256);
        assert.equal((await caixa(pagina, '.workspace')).x, 256);
        assert.equal(await controle.getAttribute('aria-expanded'), 'true');
        assert.equal(await controle.getAttribute('aria-controls'), 'barra-lateral');
        assert.equal((await controle.innerText()).trim(), 'Recolher menu');
        assert.ok((await caixa(pagina, '.sidebar [data-sidebar-toggle]')).height >= 44);
        await capturar(pagina, '01-computador-expandida');

        await controle.click();
        await esperarTransicao(pagina);
        assert.equal(await pagina.getAttribute('html', 'data-sidebar'), 'compact');
        assert.equal((await caixa(pagina, '.sidebar')).width, 72);
        assert.equal((await caixa(pagina, '.workspace')).x, 72, 'conteúdo ocupa o espaço liberado');
        assert.equal((await caixa(pagina, '.workspace')).width, 1366 - 72);
        assert.equal(await controle.getAttribute('aria-expanded'), 'false');
        assert.equal(await controle.getAttribute('aria-label'), 'Expandir menu');
        assert.ok(await semRolagemHorizontal(pagina));

        // Na faixa compacta os nomes saem da tela, mas continuam acessíveis.
        assert.equal(await pagina.locator('.sidebar').getByRole('link', { name: 'Usuários', exact: true }).count(), 1);
        assert.ok((await caixa(pagina, '.sidebar .navigation__item')).width <= 44);

        // Todos os destinos autorizados aparecem como ícones, mesmo de grupos recolhidos.
        const icones = await pagina.locator('.sidebar .navigation__item').evaluateAll((links) => links.filter((l) => l.getBoundingClientRect().height > 0 && getComputedStyle(l.closest('ul')).visibility === 'visible').length);
        assert.equal(icones, await pagina.locator('.sidebar .navigation__item').count());

        // Item ativo: aria-current e indicador lateral, não só cor.
        const ativo = pagina.locator('.sidebar a[aria-current="page"]');
        assert.equal(await ativo.count(), 1);
        assert.notEqual(await ativo.evaluate((a) => getComputedStyle(a, '::before').content), 'none');

        // Dica no hover, sem expandir a barra.
        await pagina.locator('.sidebar').getByRole('link', { name: 'Usuários', exact: true }).hover();
        const dica = pagina.locator('[data-nav-tooltip]');
        await dica.waitFor({ state: 'visible' });
        assert.equal(await dica.innerText(), 'Usuários');
        assert.ok((await caixa(pagina, '[data-nav-tooltip]')).x > 72);
        assert.equal((await caixa(pagina, '.sidebar')).width, 72, 'não expande ao passar o mouse');
        await capturar(pagina, '02-computador-compacta-dica');
        await pagina.mouse.move(800, 500);
        await dica.waitFor({ state: 'hidden' });

        // Dica também pelo teclado; Escape esconde.
        await controle.focus();
        await pagina.keyboard.press('Tab');
        await pagina.keyboard.press('Tab');
        assert.ok(await pagina.evaluate(() => document.activeElement.matches('.sidebar .navigation__item')));
        await dica.waitFor({ state: 'visible' });
        assert.equal(await dica.innerText(), await pagina.evaluate(() => document.activeElement.dataset.navLabel));
        await pagina.keyboard.press('Escape');
        await dica.waitFor({ state: 'hidden' });

        // Preferência sobrevive à recarga e a outra página, sem salto inicial.
        await pagina.goto(`${BASE}/usuarios`);
        assert.equal(await pagina.getAttribute('html', 'data-sidebar'), 'compact');
        assert.equal((await caixa(pagina, '.workspace')).x, 72);
        const guardado = await pagina.evaluate(() => Object.entries(localStorage).filter(([k]) => k.startsWith('sdv.menu.')));
        assert.deepEqual(guardado.map(([, v]) => v), ['compact'], 'só o estado é guardado');

        // Outro usuário no mesmo navegador tem a própria preferência.
        await pagina.evaluate(() => document.querySelector('.sidebar__footer form').submit());
        await pagina.waitForURL(/\/entrar/);
        const outra = await entrar(contexto, 'portaria');
        assert.equal(await outra.getAttribute('html', 'data-sidebar'), 'expanded');

        await outra.locator('.sidebar [data-sidebar-toggle]').click();
        await outra.locator('.sidebar [data-sidebar-toggle]').click();
        assert.equal(await outra.getAttribute('html', 'data-sidebar'), 'expanded');
        assert.deepEqual([...pagina.erros, ...outra.erros], []);
        await contexto.close();
    });

    test('grupos recolhíveis são independentes da compactação', async () => {
        const contexto = await navegador.newContext({ viewport: { width: 1366, height: 860 } });
        const pagina = await entrar(contexto, 'administrador');
        const cadastros = pagina.locator('.sidebar [data-nav-group-toggle]', { hasText: 'Cadastros' });
        const pessoas = pagina.locator('.sidebar').getByRole('link', { name: 'Pessoas', exact: true });

        assert.equal(await cadastros.getAttribute('aria-expanded'), 'false');
        assert.equal(await pessoas.isVisible(), false);
        await cadastros.click();
        await esperarTransicao(pagina);
        assert.equal(await cadastros.getAttribute('aria-expanded'), 'true');
        assert.equal(await pessoas.isVisible(), true);
        await capturar(pagina, '03-computador-grupo-aberto');

        // Pelo teclado (Enter no cabeçalho inteiro).
        await cadastros.focus();
        await pagina.keyboard.press('Enter');
        await esperarTransicao(pagina);
        assert.equal(await cadastros.getAttribute('aria-expanded'), 'false');
        assert.equal(await pessoas.isVisible(), false, 'grupo recolhido não recebe foco');

        // Compactar mostra os ícones; expandir de novo devolve o grupo como estava.
        await pagina.locator('.sidebar [data-sidebar-toggle]').click();
        await esperarTransicao(pagina);
        assert.equal(await pessoas.isVisible(), true);
        await pagina.locator('.sidebar [data-sidebar-toggle]').click();
        await esperarTransicao(pagina);
        assert.equal(await cadastros.getAttribute('aria-expanded'), 'false');
        assert.equal(await pessoas.isVisible(), false);
        await contexto.close();
    });

    test('funciona sem localStorage e respeita movimento reduzido', async () => {
        const contexto = await navegador.newContext({ viewport: { width: 1366, height: 860 }, reducedMotion: 'reduce' });
        await contexto.addInitScript(() => {
            Object.defineProperty(window, 'localStorage', { get() { throw new Error('SecurityError: bloqueado'); } });
        });
        const pagina = await entrar(contexto, 'gestor');
        const duracao = await pagina.locator('.sidebar').evaluate((el) => parseFloat(getComputedStyle(el).transitionDuration));
        assert.ok(duracao < 0.01, `transição desligada (${duracao}s)`);

        await pagina.locator('.sidebar [data-sidebar-toggle]').click();
        await esperarTransicao(pagina);
        assert.equal(await pagina.getAttribute('html', 'data-sidebar'), 'compact');
        assert.equal((await caixa(pagina, '.workspace')).x, 72);
        // Sem armazenamento a preferência não persiste, mas nada quebra.
        await pagina.reload();
        assert.equal(await pagina.getAttribute('html', 'data-sidebar'), 'expanded');
        assert.deepEqual(pagina.erros, []);
        await contexto.close();
    });
});

describe('celular: gaveta', { skip: pular }, () => {
    test('abre, prende o foco e fecha por Escape, fundo, botão e destino', async () => {
        const contexto = await navegador.newContext({ viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true });
        const pagina = await entrar(contexto, 'portaria');
        const acionador = pagina.locator('[data-drawer-open]');
        const gaveta = pagina.locator('#gaveta-navegacao');

        assert.equal(await visivel(pagina, '.sidebar'), false, 'sem faixa permanente de ícones');
        assert.ok((await caixa(pagina, '[data-drawer-open]')).width >= 44);
        assert.equal(await gaveta.isVisible(), false);
        assert.ok(await semRolagemHorizontal(pagina));

        await acionador.click();
        await esperarTransicao(pagina);
        assert.equal(await acionador.getAttribute('aria-expanded'), 'true');
        assert.equal(await gaveta.isVisible(), true);
        assert.ok(await pagina.evaluate(() => document.querySelector('#gaveta-navegacao').contains(document.activeElement)));
        assert.ok(await pagina.evaluate(() => document.querySelector('.app-shell').inert));
        assert.ok(await semRolagemHorizontal(pagina));
        await capturar(pagina, '04-celular-gaveta');

        // Áreas de toque de pelo menos 44 px.
        const pequenos = await pagina.locator('#gaveta-navegacao a, #gaveta-navegacao button').evaluateAll((els) => els
            .filter((el) => el.getBoundingClientRect().height > 0)
            .filter((el) => el.getBoundingClientRect().height < 44)
            .map((el) => el.textContent.trim()));
        assert.deepEqual(pequenos, []);

        // Tab nunca sai da gaveta.
        for (let i = 0; i < 25; i++) {
            await pagina.keyboard.press(i % 7 === 6 ? 'Shift+Tab' : 'Tab');
            assert.ok(await pagina.evaluate(() => document.querySelector('#gaveta-navegacao').contains(document.activeElement)), `foco escapou na tecla ${i}`);
        }

        // Escape fecha e devolve o foco ao acionador.
        await pagina.keyboard.press('Escape');
        await esperarTransicao(pagina);
        assert.equal(await gaveta.isVisible(), false);
        assert.equal(await acionador.getAttribute('aria-expanded'), 'false');
        assert.ok(await pagina.evaluate(() => document.activeElement.matches('[data-drawer-open]')));
        assert.equal(await pagina.evaluate(() => document.querySelector('.app-shell').inert), false);

        // Fundo escurecido fecha.
        await acionador.click();
        await esperarTransicao(pagina);
        await pagina.mouse.click(370, 400);
        await esperarTransicao(pagina);
        assert.equal(await gaveta.isVisible(), false);

        // Botão Fechar.
        await acionador.click();
        await esperarTransicao(pagina);
        await pagina.getByRole('button', { name: 'Fechar menu' }).click();
        await esperarTransicao(pagina);
        assert.equal(await gaveta.isVisible(), false);

        // Grupo dentro da gaveta e escolha de destino.
        await acionador.click();
        await esperarTransicao(pagina);
        await gaveta.locator('[data-nav-group-toggle]', { hasText: 'Cadastros' }).click();
        await esperarTransicao(pagina);
        await capturar(pagina, '05-celular-gaveta-grupo');
        await gaveta.getByRole('link', { name: 'Imóveis', exact: true }).click();
        await pagina.waitForURL(/\/imoveis/);
        await pagina.waitForLoadState('networkidle');
        assert.equal(await pagina.locator('#gaveta-navegacao').isVisible(), false);
        assert.equal(await pagina.locator('#gaveta-navegacao a[aria-current="page"]').getAttribute('data-nav-label'), 'Imóveis');
        assert.deepEqual(pagina.erros, []);
        await contexto.close();
    });
});

describe('permissões preservadas na apresentação', { skip: pular }, () => {
    test('Porteiro/Caixa não vê Dashboard nem administração em nenhum estado', async () => {
        const proibidos = ['Dashboard', 'Usuários', 'Perfis e permissões', 'Configurações', 'Terminais', 'Logs e auditoria'];
        for (const [largura, altura] of [[1366, 860], [390, 844]]) {
            const contexto = await navegador.newContext({ viewport: { width: largura, height: altura } });
            const pagina = await entrar(contexto, 'portaria');
            if (largura > 1000) {
                await pagina.locator('.sidebar [data-sidebar-toggle]').click();
            } else {
                await pagina.locator('[data-drawer-open]').click();
            }
            await esperarTransicao(pagina);
            const rotulos = await pagina.locator('nav[data-navigation] a').evaluateAll((links) => links.map((l) => l.dataset.navLabel));
            for (const nome of proibidos) {
                assert.ok(!rotulos.includes(nome), `${nome} não aparece (${largura}px)`);
            }
            await pagina.goto(`${BASE}/usuarios`);
            assert.ok(!pagina.url().endsWith('/usuarios'), 'acesso direto é recusado');
            await contexto.close();
        }
    });
});
