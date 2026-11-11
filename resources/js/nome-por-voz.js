/**
 * Nome completo por voz no cadastro rápido (AJ-010, docs/021 §12).
 *
 * Só começa com o clique no microfone, ouve em pt-BR e usa apenas o
 * resultado final não vazio. O nome atual fica intacto em falha, cancelamento
 * ou silêncio. Se o operador digitar enquanto ouve, a transcrição pendente é
 * descartada. Uma sessão por vez; resultados de uma sessão encerrada são
 * ignorados. Nada de áudio ou transcrição vai para log ou localStorage.
 *
 * O reconhecimento usa a Web Speech API do navegador. No Chrome/Edge o áudio
 * é processado pelo serviço do fabricante do navegador e exige internet.
 */
export const MENSAGENS_VOZ = {
    ouvindo: 'Ouvindo… fale o nome completo.',
    naoEntendeu: 'Não consegui entender. Toque no microfone e tente novamente ou digite.',
    permissao: 'O navegador não liberou o microfone. Digite o nome ou libere o microfone nas configurações do navegador.',
    semMicrofone: 'Nenhum microfone encontrado neste computador. Digite o nome.',
    servico: 'O reconhecimento de voz está indisponível agora (sem internet ou serviço fora do ar). Digite o nome.',
    falha: 'O reconhecimento de voz parou. Toque no microfone e tente novamente ou digite.',
    semSuporte: 'Preenchimento por voz indisponível neste navegador. Digite o nome.',
};

/** Mensagens do CPF por voz: mesmas regras, com orientação para os dígitos. */
export const MENSAGENS_VOZ_CPF = {
    ouvindo: 'Ouvindo… fale os 11 números do CPF.',
    naoEntendeu: 'Não entendi os 11 números do CPF. Toque no microfone e fale um número de cada vez, ou digite.',
    permissao: 'O navegador não liberou o microfone. Digite o CPF ou libere o microfone nas configurações do navegador.',
    semMicrofone: 'Nenhum microfone encontrado neste computador. Digite o CPF.',
    servico: 'O reconhecimento de voz está indisponível agora (sem internet ou serviço fora do ar). Digite o CPF.',
    falha: 'O reconhecimento de voz parou. Toque no microfone e tente novamente ou digite.',
    semSuporte: 'Preenchimento por voz indisponível neste navegador. Digite o CPF.',
};

const DIGITOS_FALADOS = {
    zero: '0', um: '1', uma: '1', dois: '2', duas: '2', tres: '3', quatro: '4',
    cinco: '5', seis: '6', meia: '6', sete: '7', oito: '8', nove: '9',
};

/**
 * CPF a partir da fala: aceita algarismos ("529 982 247 25") e números
 * falados um a um ("cinco dois nove…", "meia" = 6). Ignora "ponto", "traço"
 * e afins. Devolve 000.000.000-00, ou null quando não saem 11 dígitos.
 */
export function cpfFalado(texto) {
    const palavras = String(texto ?? '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .split(/[^a-z0-9]+/)
        .filter(Boolean);

    let digitos = '';
    for (const palavra of palavras) {
        if (/^\d+$/.test(palavra)) {
            digitos += palavra;
        } else if (DIGITOS_FALADOS[palavra] !== undefined) {
            digitos += DIGITOS_FALADOS[palavra];
        }
    }

    if (digitos.length !== 11) {
        return null;
    }

    return `${digitos.slice(0, 3)}.${digitos.slice(3, 6)}.${digitos.slice(6, 9)}-${digitos.slice(9)}`;
}

const ERROS = {
    'no-speech': 'naoEntendeu',
    'not-allowed': 'permissao',
    'service-not-allowed': 'permissao',
    'audio-capture': 'semMicrofone',
    network: 'servico',
    'language-not-supported': 'servico',
};

export function reconhecedorDoNavegador(janela = globalThis.window) {
    return janela?.SpeechRecognition ?? janela?.webkitSpeechRecognition ?? null;
}

/** Texto final, não vazio, da primeira alternativa; null quando não há. */
export function transcricaoFinal(evento) {
    const resultados = Array.from(evento?.results ?? []);
    const finais = resultados.filter((r) => r?.isFinal);
    if (finais.length === 0) {
        return null;
    }
    const texto = finais
        .map((r) => r[0]?.transcript ?? '')
        .join(' ')
        .replace(/\s+/g, ' ')
        .trim();

    return texto === '' ? null : texto;
}

/**
 * `tipo: 'cpf'` reaproveita o mesmo fluxo para o CPF: a fala vira os 11
 * dígitos formatados antes de chegar ao Livewire; fala sem 11 dígitos não
 * altera o campo.
 */
export function nomePorVoz({ metodo = 'preencherNomePorVoz', Reconhecedor, tipo = 'nome' } = {}) {
    const MSG = tipo === 'cpf' ? MENSAGENS_VOZ_CPF : MENSAGENS_VOZ;
    const converter = tipo === 'cpf' ? cpfFalado : (texto) => texto;

    return {
        suportado: false,
        textoSemSuporte: MSG.semSuporte,
        ouvindo: false,
        mensagem: '',
        sessao: 0,
        reconhecimento: null,
        recebeuFinal: false,

        init() {
            this.Classe = Reconhecedor ?? reconhecedorDoNavegador();
            this.suportado = typeof this.Classe === 'function';
            this.aoSair = () => this.cancelar();
            globalThis.window?.addEventListener?.('pagehide', this.aoSair);
        },

        destroy() {
            this.cancelar();
            globalThis.window?.removeEventListener?.('pagehide', this.aoSair);
        },

        alternar() {
            if (this.ouvindo) {
                this.cancelar();
            } else {
                this.iniciar();
            }
        },

        iniciar() {
            if (!this.suportado || this.ouvindo) {
                return; // uma sessão por vez
            }
            const sessao = ++this.sessao;
            const rec = new this.Classe();
            rec.lang = 'pt-BR';
            rec.interimResults = false;
            rec.continuous = false;
            rec.maxAlternatives = 1;

            rec.onresult = (evento) => {
                if (sessao !== this.sessao) {
                    return; // resultado atrasado de uma sessão já encerrada
                }
                const texto = transcricaoFinal(evento);
                if (texto === null) {
                    return;
                }
                this.recebeuFinal = true;
                this.encerrarSessao();
                const valor = converter(texto);
                if (valor === null) {
                    this.mensagem = MSG.naoEntendeu;
                    return;
                }
                this.mensagem = '';
                this.$wire[metodo](valor);
            };
            rec.onerror = (evento) => {
                if (sessao !== this.sessao) {
                    return;
                }
                const chave = ERROS[evento?.error];
                if (evento?.error === 'aborted') {
                    return; // cancelamento pedido pelo operador ou pela tela
                }
                this.mensagem = MSG[chave ?? 'falha'];
                this.encerrarSessao();
            };
            rec.onend = () => {
                if (sessao !== this.sessao) {
                    return;
                }
                if (this.ouvindo && !this.recebeuFinal && this.mensagem === MSG.ouvindo) {
                    this.mensagem = MSG.naoEntendeu;
                }
                this.ouvindo = false;
                this.reconhecimento = null;
            };

            this.reconhecimento = rec;
            this.recebeuFinal = false;
            this.ouvindo = true;
            this.mensagem = MSG.ouvindo;
            try {
                rec.start();
            } catch {
                this.mensagem = MSG.falha;
                this.encerrarSessao();
            }
        },

        /** Cancelamento: o nome atual fica como está. */
        cancelar() {
            if (!this.ouvindo && this.reconhecimento === null) {
                return;
            }
            this.encerrarSessao();
            this.mensagem = '';
        },

        /** "Cadastrar manualmente" leva o foco direto ao Nome completo (docs/021 §13). */
        focarNome() {
            const focar = () => this.$el?.querySelector?.('#quick-name')?.focus();
            if (typeof this.$nextTick === 'function') {
                this.$nextTick(focar);
            } else {
                focar();
            }
        },

        /** Digitação durante a escuta vence a transcrição pendente. */
        digitou() {
            if (this.ouvindo) {
                this.cancelar();
            }
        },

        encerrarSessao() {
            this.sessao++; // invalida callbacks da sessão atual
            const rec = this.reconhecimento;
            this.reconhecimento = null;
            this.ouvindo = false;
            if (rec) {
                rec.onresult = rec.onerror = rec.onend = null;
                try {
                    rec.abort();
                } catch {
                    // já encerrado
                }
            }
        },
    };
}
