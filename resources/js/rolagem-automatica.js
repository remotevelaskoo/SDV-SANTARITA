/**
 * Rolagem automática até o que mudou depois de uma ação (regra do PO de
 * 12/11/2026: "a tela não pode ficar perdida").
 *
 * Depois de cada atualização do Livewire disparada por clique, tecla ou envio:
 * 1. erro de preenchimento novo → rola até o primeiro e põe o cursor no campo;
 * 2. bloco marcado com `data-rolar` que apareceu ou mudou de valor (ex.: a
 *    etapa "foto" do cadastro rápido) → rola até ele e foca o primeiro campo;
 * 3. bloco novo (formulário, seção, cartão) → rola até ele e foca o primeiro campo;
 * 4. só um aviso novo (sucesso/erro) fora da tela → NÃO rola: o próprio aviso
 *    aparece flutuando na parte de baixo da tela, onde o operador está, e
 *    volta ao seu lugar depois de alguns segundos (pedido do PO: o aviso
 *    aparece na seção em que estou, sem me levar para cima).
 *
 * Nada rola se o alvo já estiver à vista, se a atualização não veio de uma
 * ação do usuário (atualização periódica) ou dentro de `data-sem-rolagem`.
 */

export const SELETOR_ERRO = '.field-error, .ui-field__message--error, [aria-invalid="true"]';
export const SELETOR_AVISO = '.ui-toast, .ui-alert, .alert, [role="alert"], [role="status"]';
export const SELETOR_BLOCO = 'form, fieldset, section, article, .ui-card, [data-rolar]';
export const SELETOR_CAMPO = 'input:not([type="hidden"]):not([disabled]):not([readonly]), select:not([disabled]), textarea:not([disabled]):not([readonly])';

/** Janela, em ms, entre a ação do usuário e a atualização que ainda conta como resposta a ela. */
export const JANELA_ACAO_MS = 15000;

/** Elemento dentro de diálogo aberto ou de área que pediu para não rolar. */
export function ignorado(el) {
    return Boolean(el.closest?.('dialog, [data-sem-rolagem]'));
}

/** Chaves `data-rolar` que não existiam antes (na ordem do documento). */
export function marcadosNovos(chavesAntes, marcadosAgora) {
    return marcadosAgora.filter(([chave]) => !chavesAntes.has(chave)).map(([, el]) => el);
}

/** Entre os elementos adicionados, fica só o de nível mais alto de cada grupo. */
export function nivelMaisAlto(elementos) {
    return elementos.filter((el) => !elementos.some((outro) => outro !== el && outro.contains?.(el)));
}

/**
 * Aviso: o próprio aviso, ou um bloco que só embrulha avisos (ex.: a seção
 * "Resultado do atendimento" com o aviso e o protocolo), sem campos para
 * preencher. Um formulário novo com um alerta dentro continua sendo bloco.
 */
export function ehAviso(el) {
    if (el.matches?.(SELETOR_AVISO)) {
        return true;
    }

    return Boolean(el.querySelector?.(SELETOR_AVISO)) && !el.querySelector?.(SELETOR_CAMPO);
}

/**
 * Decide o alvo da rolagem.
 *
 * @returns {{ el: object, tipo: 'erro'|'marcado'|'bloco'|'aviso' } | null}
 */
export function escolherAlvo({ errosNovos = [], marcados = [], adicionados = [] }) {
    const valido = (el) => el && !ignorado(el);

    const erro = errosNovos.find(valido);
    if (erro) {
        return { el: erro, tipo: 'erro' };
    }

    const marcado = marcados.find(valido);
    if (marcado) {
        return { el: marcado, tipo: 'marcado' };
    }

    // Aviso novo = a ação terminou (salvou, confirmou): o operador fica onde
    // está e o aviso vem até ele, mesmo que outros blocos tenham mudado.
    const topo = nivelMaisAlto(adicionados.filter(valido));
    const aviso = topo.find(ehAviso);
    if (aviso) {
        return { el: aviso, tipo: 'aviso' };
    }

    const bloco = topo.find((el) => el.matches?.(SELETOR_BLOCO));
    if (bloco) {
        return { el: bloco, tipo: 'bloco' };
    }

    return null;
}

/** Já está à vista: o topo aparece entre o cabeçalho fixo e 3/4 da tela. */
export function jaVisivel(topo, alturaTela, alturaCabecalho) {
    return topo >= alturaCabecalho && topo <= alturaTela * 0.75;
}

/** Tempo, em ms, que o aviso fica flutuando antes de voltar ao lugar. */
export const AVISO_FLUTUANTE_MS = 8000;

/**
 * O que fazer com o alvo: aviso nunca leva a tela embora (flutua se estiver
 * fora da vista); os demais rolam se não estiverem à vista.
 *
 * @returns {'nada'|'rolar'|'flutuar'}
 */
export function acaoParaAlvo(tipo, visivelNaTela) {
    if (visivelNaTela) {
        return 'nada';
    }

    return tipo === 'aviso' ? 'flutuar' : 'rolar';
}

/** Posição final da janela para o alvo ficar logo abaixo do cabeçalho fixo. */
export function destinoDaRolagem(topoNaTela, rolagemAtual, alturaCabecalho, folga = 12) {
    return Math.max(0, Math.round(rolagemAtual + topoNaTela - alturaCabecalho - folga));
}

function visivel(el) {
    return el.offsetParent !== null || el.getClientRects().length > 0;
}

function primeiroCampo(el) {
    if (el.matches(SELETOR_CAMPO) && visivel(el)) {
        return el;
    }

    return [...el.querySelectorAll(SELETOR_CAMPO)].find(visivel) ?? null;
}

/** Campo ligado a uma mensagem de erro (mesmo grupo de campo). */
function campoDoErro(erro) {
    if (erro.matches(SELETOR_CAMPO)) {
        return erro;
    }
    const grupo = erro.closest('.ui-field, .form-field, label, fieldset') ?? erro.parentElement;

    return grupo ? primeiroCampo(grupo) : null;
}

function alturaCabecalho() {
    const cabecalho = document.querySelector('.operational-header');
    if (!cabecalho) {
        return 0;
    }
    const estilo = getComputedStyle(cabecalho);

    return estilo.position === 'sticky' || estilo.position === 'fixed' ? cabecalho.getBoundingClientRect().height : 0;
}

/**
 * Mostra o próprio aviso (com os botões funcionando) flutuando embaixo da
 * tela. Some do flutuante depois de alguns segundos, ao fechar com Esc ou
 * ao clicar fora; com o ponteiro em cima, espera.
 */
function flutuar(el) {
    el.classList.add('aviso-flutuante');
    let prazo;
    const voltar = () => {
        clearTimeout(prazo);
        el.classList.remove('aviso-flutuante');
        el.removeEventListener('pointerenter', pausar);
        el.removeEventListener('pointerleave', agendar);
        document.removeEventListener('keydown', aoTeclar, true);
        document.removeEventListener('pointerdown', aoClicarFora, true);
    };
    const agendar = () => { clearTimeout(prazo); prazo = setTimeout(voltar, AVISO_FLUTUANTE_MS); };
    const pausar = () => clearTimeout(prazo);
    const aoTeclar = (evento) => { if (evento.key === 'Escape') voltar(); };
    const aoClicarFora = (evento) => { if (!el.contains(evento.target)) voltar(); };
    el.addEventListener('pointerenter', pausar);
    el.addEventListener('pointerleave', agendar);
    document.addEventListener('keydown', aoTeclar, true);
    document.addEventListener('pointerdown', aoClicarFora, true);
    agendar();
}

function rolarAte(el) {
    const cabecalho = alturaCabecalho();
    const { top } = el.getBoundingClientRect();
    const suave = !window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
    window.scrollTo({ top: destinoDaRolagem(top, window.scrollY, cabecalho), behavior: suave ? 'smooth' : 'auto' });
}

function focar(alvo) {
    const ativo = document.activeElement;
    // Quem já está digitando num campo dentro do alvo não perde o cursor.
    if (ativo && ativo !== document.body && alvo.el.contains(ativo) && ativo.matches(SELETOR_CAMPO)) {
        return;
    }
    const campo = alvo.tipo === 'erro' ? campoDoErro(alvo.el) : primeiroCampo(alvo.el);
    campo?.focus({ preventScroll: true });
}

/** Liga a regra a todas as telas Livewire. */
export function iniciarRolagemAutomatica(livewire = window.Livewire) {
    if (!livewire || window.__sdvRolagemAutomatica) {
        return;
    }
    window.__sdvRolagemAutomatica = true;

    let ultimaAcao = 0;
    let adicionados = [];
    let agendado = false;
    let chaves = new Set();
    let erros = new Set();

    const marcadosAgora = () => [...document.querySelectorAll('[data-rolar]')].map((el) => [el.dataset.rolar, el]);
    const errosAgora = () => [...document.querySelectorAll(SELETOR_ERRO)];
    const fotografar = () => {
        chaves = new Set(marcadosAgora().map(([chave]) => chave));
        erros = new Set(errosAgora());
    };

    const registrarAcao = () => { ultimaAcao = Date.now(); };
    ['pointerdown', 'keydown', 'submit'].forEach((evento) => document.addEventListener(evento, registrarAcao, true));

    const processar = () => {
        agendado = false;
        const novosAdicionados = adicionados.filter((el) => el.isConnected);
        adicionados = [];
        const atuais = marcadosAgora();
        const errosAtuais = errosAgora();
        const alvo = escolherAlvo({
            errosNovos: errosAtuais.filter((el) => !erros.has(el)),
            marcados: marcadosNovos(chaves, atuais),
            adicionados: novosAdicionados,
        });
        chaves = new Set(atuais.map(([chave]) => chave));
        erros = new Set(errosAtuais);

        if (!alvo || Date.now() - ultimaAcao > JANELA_ACAO_MS) {
            return;
        }
        // Uma ação, uma rolagem: atualizações periódicas seguintes não mexem na tela.
        ultimaAcao = 0;
        // No erro, o campo inteiro (com o rótulo) fica à vista, não só a mensagem.
        const destino = alvo.tipo === 'erro' ? (alvo.el.closest('.ui-field, .form-field, label') ?? alvo.el) : alvo.el;
        const acao = acaoParaAlvo(alvo.tipo, jaVisivel(destino.getBoundingClientRect().top, window.innerHeight, alturaCabecalho()));
        if (acao === 'rolar') {
            rolarAte(destino);
        } else if (acao === 'flutuar') {
            flutuar(destino);
        }
        if (alvo.tipo !== 'aviso') {
            focar(alvo);
        }
    };

    livewire.hook('morph.added', ({ el }) => {
        if (el.nodeType === 1) {
            adicionados.push(el);
        }
    });
    livewire.hook('morphed', () => {
        if (!agendado) {
            agendado = true;
            // Depois do Alpine terminar de montar o que entrou (x-show, x-init).
            requestAnimationFrame(() => requestAnimationFrame(processar));
        }
    });

    fotografar();
    document.addEventListener('livewire:navigated', fotografar);
}
