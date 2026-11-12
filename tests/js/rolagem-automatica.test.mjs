import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
    destinoDaRolagem,
    escolherAlvo,
    jaVisivel,
    marcadosNovos,
    nivelMaisAlto,
} from '../../resources/js/rolagem-automatica.js';

/** Elemento falso: só o que a decisão usa (matches, closest, contains). */
function el(nome, { seletores = [], dentroDe = null, ignorado = false } = {}) {
    const elemento = {
        nome,
        pai: dentroDe,
        matches: (seletor) => seletores.some((s) => seletor.includes(s)),
        closest: (seletor) => (ignorado && seletor.includes('dialog') ? {} : null),
        contains(outro) {
            for (let atual = outro; atual; atual = atual.pai) {
                if (atual === elemento) return true;
            }
            return false;
        },
    };
    return elemento;
}

test('erro de preenchimento novo vem antes de tudo', () => {
    const erro = el('erro');
    const etapa = el('etapa-foto', { seletores: ['[data-rolar]'] });
    assert.deepEqual(escolherAlvo({ errosNovos: [erro], marcados: [etapa] }), { el: erro, tipo: 'erro' });
});

test('etapa marcada que mudou (conferência → foto) é o alvo', () => {
    const foto = el('form-foto');
    const antes = new Set(['cadastro-rapido-conferencia']);
    const marcados = marcadosNovos(antes, [['cadastro-rapido-foto', foto]]);
    assert.deepEqual(escolherAlvo({ marcados }), { el: foto, tipo: 'marcado' });
    assert.deepEqual(marcadosNovos(new Set(['cadastro-rapido-foto']), [['cadastro-rapido-foto', foto]]), [], 'mesma etapa não rola de novo');
});

test('bloco novo vence o aviso; aviso sozinho também é alvo', () => {
    const aviso = el('toast', { seletores: ['.ui-toast'] });
    const formulario = el('form', { seletores: ['form'] });
    assert.equal(escolherAlvo({ adicionados: [aviso, formulario] }).el, formulario);
    assert.deepEqual(escolherAlvo({ adicionados: [aviso] }), { el: aviso, tipo: 'aviso' });
});

test('só conta o elemento de nível mais alto do que entrou', () => {
    const secao = el('secao', { seletores: ['section'] });
    const campo = el('campo', { dentroDe: secao });
    assert.deepEqual(nivelMaisAlto([campo, secao]), [secao]);
    assert.equal(escolherAlvo({ adicionados: [campo, secao] }).el, secao);
});

test('dentro de diálogo ou de área sem rolagem é ignorado; sem alvo, nada rola', () => {
    const noDialogo = el('form-modal', { seletores: ['form'], ignorado: true });
    assert.equal(escolherAlvo({ adicionados: [noDialogo] }), null);
    assert.equal(escolherAlvo({ adicionados: [el('span')] }), null);
});

test('alvo já à vista não rola; abaixo da dobra ou sob o cabeçalho rola', () => {
    assert.equal(jaVisivel(200, 800, 64), true);
    assert.equal(jaVisivel(700, 800, 64), false, 'perto do rodapé');
    assert.equal(jaVisivel(-300, 800, 64), false, 'acima da tela');
    assert.equal(jaVisivel(40, 800, 64), false, 'escondido sob o cabeçalho fixo');
});

test('destino deixa o alvo logo abaixo do cabeçalho fixo', () => {
    assert.equal(destinoDaRolagem(900, 1000, 64), 1824);
    assert.equal(destinoDaRolagem(-500, 100, 64), 0, 'nunca negativo');
});
