/**
 * Câmeras locais do posto (SDV-OPE-018 §6).
 *
 * O servidor não controla webcams USB: a associação do dispositivo físico às
 * finalidades `documentos` e `placas` fica neste navegador, por implantação e
 * posto. Funções puras, testadas em tests/js.
 */

export const FINALIDADES = ['documentos', 'placas'];

export function chavePreferencia(implantacaoId, postoId) {
    return `sdv.cameras.${implantacaoId || 'sem-implantacao'}.${postoId || 'sem-posto'}`;
}

export function lerPreferencias(armazenamento, chave) {
    try {
        const valor = JSON.parse(armazenamento.getItem(chave) || '{}');
        return valor && typeof valor === 'object' ? valor : {};
    } catch {
        return {};
    }
}

export function salvarPreferencia(armazenamento, chave, finalidade, dispositivo) {
    if (!FINALIDADES.includes(finalidade)) {
        throw new Error(`Finalidade de câmera inválida: ${finalidade}`);
    }
    const atuais = lerPreferencias(armazenamento, chave);
    atuais[finalidade] = { deviceId: dispositivo.deviceId, label: dispositivo.label || '' };
    armazenamento.setItem(chave, JSON.stringify(atuais));
    return atuais;
}

/**
 * Encontra a câmera preferida entre as disponíveis. Sem preferência, sem
 * permissão (rótulos vazios) ou com o dispositivo removido/substituído, pede
 * nova seleção em vez de falhar em silêncio.
 */
export function resolverDispositivo(dispositivos, preferencia) {
    const cameras = dispositivos.filter((d) => d.kind === 'videoinput');
    if (cameras.length === 0) {
        return { deviceId: null, motivo: 'sem_camera' };
    }
    if (!preferencia || !preferencia.deviceId) {
        return { deviceId: null, motivo: 'sem_preferencia' };
    }
    const mesma = cameras.find((d) => d.deviceId === preferencia.deviceId);
    if (mesma) {
        return { deviceId: mesma.deviceId, motivo: null };
    }
    if (cameras.every((d) => !d.deviceId && !d.label)) {
        return { deviceId: null, motivo: 'sem_permissao' };
    }
    return { deviceId: null, motivo: 'camera_ausente' };
}

/** Somente vídeo: o microfone nunca é solicitado. */
export function restricoesVideo(deviceId) {
    return {
        audio: false,
        video: deviceId
            ? { deviceId: { exact: deviceId }, width: { ideal: 1920 }, height: { ideal: 1080 } }
            : { width: { ideal: 1920 }, height: { ideal: 1080 } },
    };
}

export const MENSAGENS = {
    sem_camera: 'Nenhuma câmera encontrada neste computador. Conecte a câmera ou envie um arquivo.',
    sem_preferencia: 'Escolha a câmera deste posto.',
    sem_permissao: 'O navegador ainda não autorizou as câmeras. Clique em "Autorizar câmeras".',
    camera_ausente: 'A câmera escolhida para este posto não está disponível (removida ou substituída). Escolha outra.',
    erro_acesso: 'Não foi possível abrir a câmera. Verifique a autorização do navegador ou escolha outra câmera.',
};
