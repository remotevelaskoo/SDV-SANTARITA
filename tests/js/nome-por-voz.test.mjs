import { test } from 'node:test';
import assert from 'node:assert/strict';
import { MENSAGENS_VOZ, MENSAGENS_VOZ_CPF, cpfFalado, nomePorVoz, transcricaoFinal } from '../../resources/js/nome-por-voz.js';

/** Reconhecimento simulado: sem áudio, sem rede. Guarda as instâncias criadas. */
function simulador() {
    const instancias = [];
    class Falso {
        constructor() {
            this.iniciado = false;
            this.abortado = false;
            instancias.push(this);
        }
        start() { this.iniciado = true; }
        abort() { this.abortado = true; }
        resultado(texto, isFinal = true) {
            const r = [{ transcript: texto }];
            r.isFinal = isFinal;
            this.onresult?.({ results: [r] });
        }
        erro(codigo) { this.onerror?.({ error: codigo }); }
        fim() { this.onend?.(); }
    }
    return { Falso, instancias };
}

function componente(Reconhecedor) {
    const chamadas = [];
    const c = nomePorVoz({ Reconhecedor });
    c.$wire = { preencherNomePorVoz: (t) => chamadas.push(t) };
    c.init();
    return { c, chamadas };
}

test('só começa no clique e ouve em pt-BR, sem resultados parciais', () => {
    const { Falso, instancias } = simulador();
    const { c } = componente(Falso);
    assert.equal(instancias.length, 0);
    c.alternar();
    assert.equal(instancias.length, 1);
    assert.equal(instancias[0].lang, 'pt-BR');
    assert.equal(instancias[0].interimResults, false);
    assert.equal(instancias[0].iniciado, true);
    assert.equal(c.mensagem, MENSAGENS_VOZ.ouvindo);
});

test('resultado final preenche o nome pelo Livewire e encerra a escuta', () => {
    const { Falso, instancias } = simulador();
    const { c, chamadas } = componente(Falso);
    c.iniciar();
    instancias[0].resultado('  Vinícius   Velasco ');
    assert.deepEqual(chamadas, ['Vinícius Velasco']);
    assert.equal(c.ouvindo, false);
    assert.equal(instancias[0].abortado, true);
});

test('resultado parcial ou vazio não substitui o nome', () => {
    const { Falso, instancias } = simulador();
    const { c, chamadas } = componente(Falso);
    c.iniciar();
    instancias[0].resultado('Vinícius', false);
    instancias[0].resultado('   ');
    assert.deepEqual(chamadas, []);
    instancias[0].fim();
    assert.equal(c.mensagem, MENSAGENS_VOZ.naoEntendeu);
});

test('digitar durante a escuta descarta a transcrição pendente', () => {
    const { Falso, instancias } = simulador();
    const { c, chamadas } = componente(Falso);
    c.iniciar();
    const rec = instancias[0];
    const onresult = rec.onresult;
    c.digitou();
    assert.equal(c.ouvindo, false);
    onresult({ results: [Object.assign([{ transcript: 'Atrasado' }], { isFinal: true })] });
    assert.deepEqual(chamadas, []);
});

test('cancelar preserva o nome e permite nova tentativa', () => {
    const { Falso, instancias } = simulador();
    const { c, chamadas } = componente(Falso);
    c.iniciar();
    c.alternar(); // segundo clique = parar
    assert.equal(c.ouvindo, false);
    assert.equal(c.mensagem, '');
    c.iniciar();
    instancias[1].resultado('Maria Souza');
    assert.deepEqual(chamadas, ['Maria Souza']);
});

test('uma sessão por vez e callback antigo ignorado', () => {
    const { Falso, instancias } = simulador();
    const { c, chamadas } = componente(Falso);
    c.iniciar();
    c.iniciar();
    assert.equal(instancias.length, 1);
    const antigo = instancias[0].onresult;
    c.destroy(); // mudou de etapa ou saiu da tela
    c.iniciar();
    antigo({ results: [Object.assign([{ transcript: 'Atendimento anterior' }], { isFinal: true })] });
    assert.deepEqual(chamadas, []);
    instancias[1].resultado('Nome Atual');
    assert.deepEqual(chamadas, ['Nome Atual']);
});

test('erros viram mensagens simples e não chamam o Livewire', () => {
    for (const [codigo, chave] of [['no-speech', 'naoEntendeu'], ['not-allowed', 'permissao'], ['service-not-allowed', 'permissao'], ['audio-capture', 'semMicrofone'], ['network', 'servico'], ['algo-novo', 'falha']]) {
        const { Falso, instancias } = simulador();
        const { c, chamadas } = componente(Falso);
        c.iniciar();
        instancias[0].erro(codigo);
        assert.equal(c.mensagem, MENSAGENS_VOZ[chave], codigo);
        assert.equal(c.ouvindo, false);
        assert.equal(instancias.length, 1, 'não tenta de novo sozinho');
        assert.deepEqual(chamadas, []);
    }
});

test('navegador sem suporte não mostra botão que falha', () => {
    const c = nomePorVoz({ Reconhecedor: null });
    c.$wire = {};
    c.init();
    assert.equal(c.suportado, false);
    c.iniciar();
    assert.equal(c.ouvindo, false);
    assert.equal(c.textoSemSuporte, MENSAGENS_VOZ.semSuporte);
});

test('transcricaoFinal junta só os finais', () => {
    const final = Object.assign([{ transcript: 'Ana' }], { isFinal: true });
    const parcial = Object.assign([{ transcript: 'xx' }], { isFinal: false });
    assert.equal(transcricaoFinal({ results: [parcial] }), null);
    assert.equal(transcricaoFinal({ results: [final, parcial] }), 'Ana');
});

test('cpfFalado aceita algarismos, números falados um a um e ignora pontuação falada', () => {
    assert.equal(cpfFalado('529 982 247 25'), '529.982.247-25');
    assert.equal(cpfFalado('529.982.247-25'), '529.982.247-25');
    assert.equal(cpfFalado('cinco dois nove ponto nove oito dois ponto dois quatro sete traço dois cinco'), '529.982.247-25');
    assert.equal(cpfFalado('um um um quatro quatro quatro sete sete sete três meia'), '111.444.777-36');
    assert.equal(cpfFalado('12345'), null);
    assert.equal(cpfFalado('Maria da Silva'), null);
    assert.equal(cpfFalado(''), null);
});

test('modo CPF envia o CPF formatado e não altera o campo quando não entende', () => {
    const { Falso, instancias } = simulador();
    const chamadas = [];
    const c = nomePorVoz({ Reconhecedor: Falso, tipo: 'cpf', metodo: 'preencherCpfPorVoz' });
    c.$wire = { preencherCpfPorVoz: (t) => chamadas.push(t) };
    c.init();

    c.alternar();
    assert.equal(c.mensagem, MENSAGENS_VOZ_CPF.ouvindo);
    instancias[0].resultado('529 982 247 25');
    assert.deepEqual(chamadas, ['529.982.247-25']);
    assert.equal(c.ouvindo, false);

    c.alternar();
    instancias[1].resultado('cinco dois nove');
    assert.deepEqual(chamadas, ['529.982.247-25'], 'fala incompleta não chega ao servidor');
    assert.equal(c.mensagem, MENSAGENS_VOZ_CPF.naoEntendeu);
    assert.equal(c.textoSemSuporte, MENSAGENS_VOZ_CPF.semSuporte);
});
