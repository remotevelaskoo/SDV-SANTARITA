import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
    acaoParaAlvo,
    destinoDaRolagem,
    escolherAlvo,
    jaVisivel,
    marcadosNovos,
    nivelMaisAlto,
} from '../../resources/js/rolagem-automatica.js';

/** Elemento falso: só o que a decisão usa (matches, closest, contains). */
function el(nome, { seletores = [], dentroDe = null, ignorado = false, contem = [] } = {}) {
    const elemento = {
        nome,
        pai: dentroDe,
        matches: (seletor) => seletores.some((s) => seletor.includes(s)),
        querySelector: (seletor) => (contem.some((s) => seletor.includes(s)) ? {} : null),
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

test('aviso novo vence bloco que mudou (ação terminou); sem aviso, o bloco é o alvo', () => {
    const aviso = el('toast', { seletores: ['.ui-toast'] });
    const formulario = el('form', { seletores: ['form'] });
    assert.deepEqual(escolherAlvo({ adicionados: [formulario, aviso] }), { el: aviso, tipo: 'aviso' });
    assert.deepEqual(escolherAlvo({ adicionados: [formulario] }), { el: formulario, tipo: 'bloco' });
});

test('seção que só embrulha o aviso conta como aviso; formulário com alerta dentro continua bloco', () => {
    const resultado = el('section-resultado', { seletores: ['section'], contem: ['.ui-toast'] });
    assert.deepEqual(escolherAlvo({ adicionados: [resultado] }), { el: resultado, tipo: 'aviso' });
    const cadastro = el('section-cadastro', { seletores: ['section'], contem: ['.ui-alert', 'input'] });
    assert.deepEqual(escolherAlvo({ adicionados: [cadastro] }), { el: cadastro, tipo: 'bloco' });
});

test('aviso fora da tela flutua e nunca leva a tela para cima; à vista, nada acontece', () => {
    assert.equal(acaoParaAlvo('aviso', false), 'flutuar');
    assert.equal(acaoParaAlvo('aviso', true), 'nada');
    assert.equal(acaoParaAlvo('marcado', false), 'rolar');
    assert.equal(acaoParaAlvo('erro', true), 'nada');
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
