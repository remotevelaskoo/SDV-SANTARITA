import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
    COMPACTA,
    EXPANDIDA,
    alternarEstado,
    armazenamentoSeguro,
    focoCircular,
    lerEstado,
    rotuloDoControle,
    salvarEstado,
} from '../../resources/js/navegacao.js';

/** localStorage em memória, um por navegador. */
function navegador() {
    const dados = new Map();
    return {
        dados,
        getItem: (k) => (dados.has(k) ? dados.get(k) : null),
        setItem: (k, v) => dados.set(k, String(v)),
    };
}

const quebrado = {
    getItem() { throw new Error('SecurityError'); },
    setItem() { throw new Error('QuotaExceededError'); },
};

test('preferência fica separada por usuário e implantação (uma chave cada)', () => {
    const armazenamento = navegador();
    assert.equal(salvarEstado(armazenamento, 'sdv.menu.usuarioA-impl1', COMPACTA), true);
    assert.equal(lerEstado(armazenamento, 'sdv.menu.usuarioA-impl1'), COMPACTA);
    assert.equal(lerEstado(armazenamento, 'sdv.menu.usuarioA-impl2'), EXPANDIDA);
    assert.equal(lerEstado(armazenamento, 'sdv.menu.usuarioB-impl1'), EXPANDIDA);
});

test('só "expanded" ou "compact" é gravado; valores estranhos voltam ao padrão', () => {
    const armazenamento = navegador();
    salvarEstado(armazenamento, 'k', 'qualquer-coisa');
    assert.deepEqual([...armazenamento.dados.values()], [EXPANDIDA]);
    armazenamento.setItem('k', '{"perfil":"admin"}');
    assert.equal(lerEstado(armazenamento, 'k'), EXPANDIDA);
});

test('armazenamento indisponível não quebra o menu', () => {
    assert.equal(lerEstado(quebrado, 'k'), EXPANDIDA);
    assert.equal(salvarEstado(quebrado, 'k', COMPACTA), false);
    assert.equal(lerEstado(null, 'k'), EXPANDIDA);
    assert.equal(salvarEstado(null, 'k', COMPACTA), false);
    assert.equal(lerEstado(navegador(), null), EXPANDIDA);

    const janelaSemAcesso = {};
    Object.defineProperty(janelaSemAcesso, 'localStorage', { get() { throw new Error('bloqueado'); } });
    assert.equal(armazenamentoSeguro(janelaSemAcesso), null);
});

test('controle da barra alterna os dois estados com nome acessível', () => {
    assert.equal(alternarEstado(EXPANDIDA), COMPACTA);
    assert.equal(alternarEstado(COMPACTA), EXPANDIDA);
    assert.deepEqual(rotuloDoControle(EXPANDIDA), { rotulo: 'Recolher menu', expandido: 'true' });
    assert.deepEqual(rotuloDoControle(COMPACTA), { rotulo: 'Expandir menu', expandido: 'false' });
});

test('foco circula dentro da gaveta aberta', () => {
    const [fechar, a, b] = ['fechar', 'a', 'b'];
    const lista = [fechar, a, b];
    assert.equal(focoCircular(lista, b, false), fechar);
    assert.equal(focoCircular(lista, fechar, true), b);
    assert.equal(focoCircular(lista, a, false), null);
    assert.equal(focoCircular(lista, 'fora', false), fechar);
    assert.equal(focoCircular(lista, 'fora', true), b);
    assert.equal(focoCircular([], 'x', false), null);
});
