/**
 * Navegação lateral (AJ-009, complemento "recolhimento moderno" — SDV-UXD-004).
 *
 * - Computador: barra expandida (ícones e nomes) ou compacta (faixa de ícones),
 *   com preferência guardada por usuário e implantação neste navegador.
 * - Grupos recolhíveis, independentes da compactação da barra.
 * - Celular: gaveta sobre fundo escurecido, com foco preso enquanto aberta.
 *
 * Só apresentação: os itens já chegam filtrados pelo servidor. O localStorage
 * guarda apenas "expanded" ou "compact" sob uma chave resumida (sem dados
 * pessoais nem permissões); se ele falhar, o menu continua funcionando.
 */

export const EXPANDIDA = 'expanded';
export const COMPACTA = 'compact';

const SELETOR_FOCAVEL = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

/** Acesso ao localStorage que nunca lança (modo privado, bloqueio, cota). */
export function armazenamentoSeguro(win) {
    try {
        return win.localStorage ?? null;
    } catch {
        return null;
    }
}

export function lerEstado(armazenamento, chave) {
    if (!armazenamento || !chave) {
        return EXPANDIDA;
    }

    try {
        return armazenamento.getItem(chave) === COMPACTA ? COMPACTA : EXPANDIDA;
    } catch {
        return EXPANDIDA;
    }
}

/** @returns {boolean} se a preferência foi guardada. */
export function salvarEstado(armazenamento, chave, estado) {
    if (!armazenamento || !chave) {
        return false;
    }

    try {
        armazenamento.setItem(chave, estado === COMPACTA ? COMPACTA : EXPANDIDA);

        return true;
    } catch {
        return false;
    }
}

export function alternarEstado(estado) {
    return estado === COMPACTA ? EXPANDIDA : COMPACTA;
}

/** Textos e atributos do botão da barra em cada estado. */
export function rotuloDoControle(estado) {
    return estado === COMPACTA
        ? { rotulo: 'Expandir menu', expandido: 'false' }
        : { rotulo: 'Recolher menu', expandido: 'true' };
}

/**
 * Próximo elemento ao pressionar Tab dentro de uma área com foco preso.
 * Retorna null quando o navegador pode seguir normalmente.
 */
export function focoCircular(focaveis, atual, voltando) {
    if (focaveis.length === 0) {
        return null;
    }

    const primeiro = focaveis[0];
    const ultimo = focaveis[focaveis.length - 1];
    const indice = focaveis.indexOf(atual);

    if (indice === -1) {
        return voltando ? ultimo : primeiro;
    }

    if (voltando && atual === primeiro) {
        return ultimo;
    }

    if (!voltando && atual === ultimo) {
        return primeiro;
    }

    return null;
}

function focavel(el) {
    if (typeof el.checkVisibility === 'function') {
        return el.checkVisibility({ visibilityProperty: true });
    }

    return el.getClientRects().length > 0 && el.ownerDocument.defaultView.getComputedStyle(el).visibility !== 'hidden';
}

function reduzMovimento(win) {
    return win.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
}

function chaveDaPreferencia(doc) {
    return doc.querySelector('[data-nav-key]')?.getAttribute('data-nav-key') ?? null;
}

function aplicarEstado(doc, estado) {
    doc.documentElement.setAttribute('data-sidebar', estado);

    const { rotulo, expandido } = rotuloDoControle(estado);
    doc.querySelectorAll('[data-sidebar-toggle]').forEach((botao) => {
        botao.setAttribute('aria-label', rotulo);
        botao.setAttribute('aria-expanded', expandido);
        botao.setAttribute('data-nav-label', rotulo);
        const texto = botao.querySelector('.sidebar__toggle-text');
        if (texto) {
            texto.textContent = rotulo;
        }
    });
}

function estadoAtual(doc) {
    return doc.documentElement.getAttribute('data-sidebar') === COMPACTA ? COMPACTA : EXPANDIDA;
}

/* ---------- dica (tooltip) da faixa compacta ---------- */

let alvoDaDica = null;

function mostrarDica(doc, alvo) {
    const dica = doc.querySelector('[data-nav-tooltip]');
    if (!dica || estadoAtual(doc) !== COMPACTA || !alvo.closest('.sidebar')) {
        return;
    }

    alvoDaDica = alvo;

    const caixa = alvo.getBoundingClientRect();
    dica.textContent = alvo.getAttribute('data-nav-label') ?? '';
    // Ao lado da faixa, nunca sobre ela.
    const borda = alvo.closest('.sidebar').getBoundingClientRect().right;
    dica.style.left = `${Math.round(Math.max(caixa.right, borda) + 8)}px`;
    dica.style.top = `${Math.round(caixa.top + caixa.height / 2)}px`;
    dica.hidden = false;
}

function esconderDica(doc) {
    alvoDaDica = null;
    const dica = doc.querySelector('[data-nav-tooltip]');
    if (dica) {
        dica.hidden = true;
    }
}

/* ---------- gaveta do celular ---------- */

let acionadorDaGaveta = null;

function gaveta(doc) {
    return doc.querySelector('[data-drawer]');
}

export function gavetaAberta(doc) {
    const elemento = gaveta(doc);

    return Boolean(elemento && !elemento.hidden && elemento.classList.contains('is-open'));
}

function abrirGaveta(doc, win, acionador) {
    const elemento = gaveta(doc);
    if (!elemento || gavetaAberta(doc)) {
        return;
    }

    acionadorDaGaveta = acionador ?? doc.querySelector('[data-drawer-open]');
    elemento.hidden = false;
    // Força o layout antes da classe para a transição de entrada acontecer.
    void elemento.offsetWidth;
    elemento.classList.add('is-open');
    doc.querySelectorAll('[data-drawer-open]').forEach((botao) => botao.setAttribute('aria-expanded', 'true'));

    const fundo = doc.querySelector('.app-shell');
    if (fundo) {
        fundo.inert = true;
        fundo.setAttribute('aria-hidden', 'true');
    }
    doc.body.classList.add('has-drawer-open');

    const atual = elemento.querySelector('[aria-current="page"]');
    (atual ?? elemento.querySelector('[data-drawer-close].mobile-drawer__close'))?.focus();
}

function fecharGaveta(doc, win, { devolverFoco = true } = {}) {
    const elemento = gaveta(doc);
    if (!elemento || elemento.hidden) {
        return;
    }

    elemento.classList.remove('is-open');
    doc.querySelectorAll('[data-drawer-open]').forEach((botao) => botao.setAttribute('aria-expanded', 'false'));

    const fundo = doc.querySelector('.app-shell');
    if (fundo) {
        fundo.inert = false;
        fundo.removeAttribute('aria-hidden');
    }
    doc.body.classList.remove('has-drawer-open');

    const ocultar = () => {
        if (!elemento.classList.contains('is-open')) {
            elemento.hidden = true;
        }
    };
    reduzMovimento(win) ? ocultar() : win.setTimeout(ocultar, 240);

    if (devolverFoco) {
        (acionadorDaGaveta ?? doc.querySelector('[data-drawer-open]'))?.focus();
    }
    acionadorDaGaveta = null;
}

/* ---------- inicialização ---------- */

/** Reaplica o estado guardado; roda a cada carregamento e navegação Livewire. */
export function sincronizarNavegacao(doc, win) {
    aplicarEstado(doc, lerEstado(armazenamentoSeguro(win), chaveDaPreferencia(doc)));
    esconderDica(doc);
    const elemento = gaveta(doc);
    if (elemento && !elemento.classList.contains('is-open')) {
        elemento.hidden = true;
    }
}

export function iniciarNavegacao(doc = document, win = window) {
    if (win.__sdvNavegacao) {
        sincronizarNavegacao(doc, win);

        return;
    }
    win.__sdvNavegacao = true;

    doc.addEventListener('click', (evento) => {
        const alvo = evento.target instanceof win.Element ? evento.target : null;
        if (!alvo) {
            return;
        }

        const controle = alvo.closest('[data-sidebar-toggle]');
        if (controle) {
            const novo = alternarEstado(estadoAtual(doc));
            aplicarEstado(doc, novo);
            salvarEstado(armazenamentoSeguro(win), chaveDaPreferencia(doc), novo);
            esconderDica(doc);
            if (doc.activeElement === controle && novo === COMPACTA) {
                mostrarDica(doc, controle);
            }

            return;
        }

        const grupo = alvo.closest('[data-nav-group-toggle]');
        if (grupo) {
            const secao = grupo.closest('[data-nav-group]');
            const aberto = !secao.classList.contains('is-open');
            secao.classList.toggle('is-open', aberto);
            grupo.setAttribute('aria-expanded', aberto ? 'true' : 'false');

            return;
        }

        const abrir = alvo.closest('[data-drawer-open]');
        if (abrir) {
            abrirGaveta(doc, win, abrir);

            return;
        }

        if (alvo.closest('[data-drawer-close]')) {
            fecharGaveta(doc, win);

            return;
        }

        // Escolher um destino na gaveta fecha a gaveta (o link segue normalmente).
        if (alvo.closest('[data-drawer] a[href]')) {
            fecharGaveta(doc, win, { devolverFoco: false });
        }
    });

    doc.addEventListener('keydown', (evento) => {
        if (evento.key === 'Escape') {
            esconderDica(doc);
            if (gavetaAberta(doc)) {
                evento.preventDefault();
                fecharGaveta(doc, win);
            }

            return;
        }

        if (evento.key === 'Tab' && gavetaAberta(doc)) {
            const painel = gaveta(doc).querySelector('.mobile-drawer__panel');
            // Itens de grupos recolhidos (visibility: hidden) não recebem foco.
            const focaveis = [...painel.querySelectorAll(SELETOR_FOCAVEL)].filter(focavel);
            const proximo = focoCircular(focaveis, doc.activeElement, evento.shiftKey);
            if (proximo) {
                evento.preventDefault();
                proximo.focus();
            }
        }
    });

    const comDica = (evento) => evento.target instanceof win.Element
        ? evento.target.closest('.sidebar [data-nav-label]')
        : null;

    doc.addEventListener('mouseover', (evento) => {
        const alvo = comDica(evento);
        alvo ? mostrarDica(doc, alvo) : null;
    });
    doc.addEventListener('mouseout', (evento) => {
        if (comDica(evento)) {
            esconderDica(doc);
        }
    });
    doc.addEventListener('focusin', (evento) => {
        const alvo = comDica(evento);
        alvo ? mostrarDica(doc, alvo) : esconderDica(doc);
    });
    doc.addEventListener('focusout', (evento) => {
        if (comDica(evento)) {
            esconderDica(doc);
        }
    });
    // Rolar a faixa (inclusive ao focar um item pelo teclado) reposiciona a dica.
    doc.addEventListener('scroll', () => {
        const alvo = alvoDaDica;
        if (alvo && (alvo === doc.activeElement || alvo.matches(':hover'))) {
            mostrarDica(doc, alvo);
        } else {
            esconderDica(doc);
        }
    }, true);

    // Ao virar computador com a gaveta aberta, fecha sem prender o fundo.
    win.matchMedia?.('(min-width: 64rem)').addEventListener?.('change', (consulta) => {
        if (consulta.matches) {
            fecharGaveta(doc, win, { devolverFoco: false });
        }
    });

    // Volta do cache do navegador (botão Voltar) com a gaveta fechada.
    win.addEventListener('pageshow', () => fecharGaveta(doc, win, { devolverFoco: false }));

    sincronizarNavegacao(doc, win);
}
