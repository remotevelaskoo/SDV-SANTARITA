import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
    chavePreferencia,
    lerPreferencias,
    resolverDispositivo,
    restricoesVideo,
    salvarPreferencia,
} from '../../resources/js/camera-preferencias.js';

/** localStorage em memória, um por "navegador". */
function navegador() {
    const dados = new Map();
    return {
        getItem: (k) => (dados.has(k) ? dados.get(k) : null),
        setItem: (k, v) => dados.set(k, String(v)),
    };
}

const camera = (deviceId, label) => ({ kind: 'videoinput', deviceId, label });
const microfone = { kind: 'audioinput', deviceId: 'mic-1', label: 'Microfone' };

test('três navegadores/postos guardam câmeras independentes', () => {
    const postos = [['principal', 'cam-a'], ['veiculos', 'cam-b'], ['pedestres', 'cam-c']];
    const navegadores = postos.map(([posto, deviceId]) => {
        const armazenamento = navegador();
        salvarPreferencia(armazenamento, chavePreferencia('imp-1', posto), 'documentos', camera(deviceId, `Câmera ${deviceId}`));
        return { armazenamento, posto, deviceId };
    });

    for (const { armazenamento, posto, deviceId } of navegadores) {
        assert.equal(lerPreferencias(armazenamento, chavePreferencia('imp-1', posto)).documentos.deviceId, deviceId);
    }
    assert.deepEqual(lerPreferencias(navegadores[0].armazenamento, chavePreferencia('imp-1', 'veiculos')), {});
});

test('câmera preferida é recuperada por posto e a troca de posto troca a câmera', () => {
    const armazenamento = navegador();
    salvarPreferencia(armazenamento, chavePreferencia('imp-1', 'veiculos'), 'documentos', camera('cam-doc-v', 'Doc veículos'));
    salvarPreferencia(armazenamento, chavePreferencia('imp-1', 'veiculos'), 'placas', camera('cam-placa-v', 'Placa veículos'));
    salvarPreferencia(armazenamento, chavePreferencia('imp-1', 'pedestres'), 'documentos', camera('cam-doc-p', 'Doc pedestres'));
    const disponiveis = [camera('cam-doc-v', 'Doc veículos'), camera('cam-placa-v', 'Placa veículos'), camera('cam-doc-p', 'Doc pedestres')];

    const veiculos = lerPreferencias(armazenamento, chavePreferencia('imp-1', 'veiculos'));
    const pedestres = lerPreferencias(armazenamento, chavePreferencia('imp-1', 'pedestres'));

    assert.equal(resolverDispositivo(disponiveis, veiculos.documentos).deviceId, 'cam-doc-v');
    assert.equal(resolverDispositivo(disponiveis, veiculos.placas).deviceId, 'cam-placa-v');
    assert.equal(resolverDispositivo(disponiveis, pedestres.documentos).deviceId, 'cam-doc-p');
});

test('dispositivo removido ou substituído pede nova seleção', () => {
    const preferencia = { deviceId: 'cam-antiga', label: 'Câmera antiga' };

    assert.deepEqual(resolverDispositivo([camera('cam-nova', 'Câmera nova')], preferencia), { deviceId: null, motivo: 'camera_ausente' });
    assert.deepEqual(resolverDispositivo([microfone], preferencia), { deviceId: null, motivo: 'sem_camera' });
    assert.deepEqual(resolverDispositivo([camera('', '')], preferencia), { deviceId: null, motivo: 'sem_permissao' });
    assert.deepEqual(resolverDispositivo([camera('cam-nova', 'Câmera nova')], undefined), { deviceId: null, motivo: 'sem_preferencia' });
});

test('o microfone nunca é solicitado', () => {
    assert.equal(restricoesVideo('cam-1').audio, false);
    assert.equal(restricoesVideo(null).audio, false);
    assert.deepEqual(restricoesVideo('cam-1').video.deviceId, { exact: 'cam-1' });
});

test('finalidade inválida é recusada e preferência corrompida não quebra', () => {
    const armazenamento = navegador();
    assert.throws(() => salvarPreferencia(armazenamento, 'k', 'microfone', camera('x', 'x')));
    armazenamento.setItem('k', '{inválido');
    assert.deepEqual(lerPreferencias(armazenamento, 'k'), {});
});
