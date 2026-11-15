// Painel de gestão (PO 15/11/2026): só leitura dos totais enviados pelo servidor da portaria.
const $ = (id) => document.getElementById(id);
const CHAVE = "sdv-gestao-sessao";
const real = new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" });
const hora = (iso) =>
    iso ? new Date(iso).toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" }) : "—";
const dataLonga = (dia) =>
    new Date(`${dia}T12:00:00`).toLocaleDateString("pt-BR", { weekday: "long", day: "2-digit", month: "long" });
const FORMAS = { dinheiro: "Dinheiro", pix: "Pix", cartao: "Cartão", outro: "Outro" };
const SVG = "http://www.w3.org/2000/svg";

let sessao = ler();
let atualizacao = null;
let resposta = null;
let colaborador = "";

function ler() {
    try {
        return JSON.parse(sessionStorage.getItem(CHAVE) ?? "null");
    } catch {
        return null;
    }
}
function guardar(valor) {
    sessao = valor;
    try {
        if (valor) sessionStorage.setItem(CHAVE, JSON.stringify(valor));
        else sessionStorage.removeItem(CHAVE);
    } catch {
        /* Sem armazenamento: a sessão vale só enquanto a página estiver aberta. */
    }
}

async function api(metodo, corpo, query = "") {
    const r = await fetch(`/api/gestao${query}`, {
        method: metodo,
        headers: {
            "Content-Type": "application/json",
            ...(sessao && metodo === "GET" ? { Authorization: `Bearer ${sessao.token}` } : {}),
        },
        body: corpo ? JSON.stringify(corpo) : undefined,
    });
    const dados = await r.json().catch(() => ({}));
    return { status: r.status, dados };
}

function mostrarEntrada(mensagem) {
    clearInterval(atualizacao);
    guardar(null);
    $("painel").hidden = true;
    $("sair").hidden = true;
    $("entrar").hidden = false;
    $("erro-entrar").hidden = !mensagem;
    $("erro-entrar").textContent = mensagem ?? "";
}

async function carregar(dia) {
    let r = await api("GET", null, dia ? `?dia=${encodeURIComponent(dia)}` : "");
    if (r.status === 401 && sessao?.refresh) {
        const novo = await api("POST", { action: "renovar", refresh: sessao.refresh });
        if (novo.status === 200) {
            guardar(novo.dados);
            r = await api("GET", null, dia ? `?dia=${encodeURIComponent(dia)}` : "");
        }
    }
    if (r.status === 401 || r.status === 403) {
        return mostrarEntrada(r.dados.error ?? "Entre novamente.");
    }
    if (r.status !== 200) {
        $("aviso").hidden = false;
        $("aviso").textContent = r.dados.error ?? "Painel indisponível no momento. Tente de novo em instantes.";
        return;
    }
    resposta = r.dados;
    desenhar();
}

function cartao(rotulo, valor) {
    const div = document.createElement("div");
    div.className = "cartao numero";
    const span = document.createElement("span");
    span.textContent = rotulo;
    const strong = document.createElement("strong");
    strong.textContent = valor;
    div.append(span, strong);
    return div;
}

function opcao(valor, texto, selecionada) {
    const o = document.createElement("option");
    o.value = valor;
    o.textContent = texto;
    o.selected = selecionada;
    return o;
}

// Pizza em SVG puro (sem biblioteca; a CSP do portal só aceita arquivos próprios).
function pizza(alvo, fatias, formatar) {
    const itens = fatias.filter(([, v]) => v > 0);
    const total = itens.reduce((s, [, v]) => s + v, 0);
    const caixa = document.createElement("div");
    caixa.className = "pizza";
    if (!total) {
        const vazio = document.createElement("p");
        vazio.className = "suave";
        vazio.textContent = "Sem valores neste dia.";
        alvo.replaceChildren(vazio);
        return;
    }
    const svg = document.createElementNS(SVG, "svg");
    svg.setAttribute("viewBox", "-1 -1 2 2");
    svg.setAttribute("role", "img");
    svg.setAttribute(
        "aria-label",
        itens.map(([n, v]) => `${n}: ${formatar(v)} (${Math.round((v / total) * 100)}%)`).join("; "),
    );
    let angulo = -Math.PI / 2;
    itens.forEach(([nome, valor], i) => {
        const fracao = valor / total;
        let forma;
        if (fracao >= 0.9999) {
            forma = document.createElementNS(SVG, "circle");
            forma.setAttribute("r", "1");
        } else {
            const fim = angulo + fracao * 2 * Math.PI;
            forma = document.createElementNS(SVG, "path");
            forma.setAttribute(
                "d",
                `M0 0 L${Math.cos(angulo)} ${Math.sin(angulo)} A1 1 0 ${fracao > 0.5 ? 1 : 0} 1 ${Math.cos(fim)} ${Math.sin(fim)} Z`,
            );
            angulo = fim;
        }
        forma.setAttribute("class", `fatia cor-${i % 6}`);
        const titulo = document.createElementNS(SVG, "title");
        titulo.textContent = `${nome}: ${formatar(valor)}`;
        forma.append(titulo);
        svg.append(forma);
    });
    const furo = document.createElementNS(SVG, "circle");
    furo.setAttribute("r", "0.55");
    furo.setAttribute("class", "furo");
    svg.append(furo);

    const legenda = document.createElement("ul");
    legenda.className = "legenda";
    itens.forEach(([nome, valor], i) => {
        const li = document.createElement("li");
        const cor = document.createElement("i");
        cor.className = `cor-${i % 6}`;
        const rotulo = document.createElement("span");
        rotulo.textContent = nome;
        const numero = document.createElement("strong");
        numero.textContent = `${formatar(valor)} · ${Math.round((valor / total) * 100)}%`;
        li.append(cor, rotulo, numero);
        legenda.append(li);
    });
    caixa.append(svg, legenda);
    alvo.replaceChildren(caixa);
}

function somarFormas(lista) {
    const soma = {};
    for (const cx of lista) {
        for (const [f, v] of Object.entries(cx.por_forma ?? {})) soma[f] = (soma[f] ?? 0) + v;
    }
    return soma;
}

function desenhar() {
    const r = resposta;
    $("entrar").hidden = true;
    $("painel").hidden = false;
    $("sair").hidden = false;
    $("condominio").textContent = r.condominio ?? "Santa Rita";

    const atual = r.dia;
    $("dia").replaceChildren(
        ...(r.dias ?? []).map((d) => opcao(d, new Date(`${d}T12:00:00`).toLocaleDateString("pt-BR"), d === atual)),
    );

    const hoje = new Date().toLocaleDateString("sv-SE");
    $("titulo-dia").textContent = atual ? (atual === hoje ? "Hoje" : dataLonga(atual)) : "Sem dados ainda";
    const d = r.dados;
    if (!d) {
        $("aviso").hidden = false;
        $("aviso").textContent = "O servidor da portaria ainda não enviou números para este dia.";
        $("atualizado").textContent = "";
        $("colaborador").replaceChildren(opcao("", "Todos", true));
        for (const id of ["numeros", "caixas", "pizza-formas", "pizza-colaboradores", "pizza-contribuicoes"]) {
            $(id).replaceChildren();
        }
        $("total").textContent = real.format(0);
        $("composicao").textContent = "";
        return;
    }

    // Os números chegam a cada 5 minutos; se o último envio de hoje for antigo, avisa.
    const minutos = r.atualizado_em ? (Date.now() - new Date(r.atualizado_em).getTime()) / 60000 : Infinity;
    $("atualizado").textContent = `Atualizado às ${hora(r.atualizado_em)} · enviado pelo servidor da portaria a cada 5 minutos`;
    $("aviso").hidden = !(atual === hoje && minutos > 15);
    $("aviso").textContent =
        "Sem atualização há mais de 15 minutos. A internet ou o servidor da portaria podem estar fora; os números podem estar desatualizados.";

    const caixas = d.caixas ?? [];
    const nomes = [...new Set(caixas.map((cx) => cx.operador ?? "—"))].sort((a, b) => a.localeCompare(b, "pt-BR"));
    if (colaborador && !nomes.includes(colaborador)) colaborador = "";
    $("colaborador").replaceChildren(
        opcao("", "Todos", !colaborador),
        ...nomes.map((n) => opcao(n, n, n === colaborador)),
    );
    const meus = colaborador ? caixas.filter((cx) => (cx.operador ?? "—") === colaborador) : caixas;

    const a = d.arrecadacao ?? {};
    if (colaborador) {
        const pagas = meus.reduce((s, cx) => s + (cx.contribuicoes_pagas ?? 0), 0);
        $("rotulo-total").textContent = `Arrecadado por ${colaborador}`;
        $("total").textContent = real.format(meus.reduce((s, cx) => s + (cx.arrecadado ?? 0), 0));
        $("composicao").textContent = `${meus.length} caixa(s) · ${pagas} contribuição(ões) paga(s) · saídas ${real.format(
            meus.reduce((s, cx) => s + (cx.saidas ?? 0), 0),
        )}`;
    } else {
        $("rotulo-total").textContent = "Arrecadado no dia";
        $("total").textContent = real.format(a.total ?? 0);
        $("composicao").textContent = `Contribuições ${real.format(a.contribuicoes ?? 0)} · lançamentos ${real.format(
            a.lancamentos_manuais ?? 0,
        )} · saídas e estornos ${real.format(a.saidas_e_estornos ?? 0)}`;
    }

    const formas = colaborador ? somarFormas(meus) : (a.por_forma ?? {});
    pizza(
        $("pizza-formas"),
        Object.entries(formas).map(([f, v]) => [FORMAS[f] ?? f, v]),
        (v) => real.format(v),
    );
    const porPessoa = {};
    for (const cx of caixas) porPessoa[cx.operador ?? "—"] = (porPessoa[cx.operador ?? "—"] ?? 0) + (cx.arrecadado ?? 0);
    // Recebimentos registrados sem caixa (antes da regra de caixa aberto) também contam no total do dia.
    const semCaixa = Math.round(((a.total ?? 0) - Object.values(porPessoa).reduce((s, v) => s + v, 0)) * 100) / 100;
    if (semCaixa > 0) porPessoa["Sem caixa vinculado"] = semCaixa;
    pizza(
        $("pizza-colaboradores"),
        Object.entries(porPessoa).sort((x, y) => y[1] - x[1]),
        (v) => real.format(v),
    );
    const c = d.contribuicoes ?? {};
    pizza(
        $("pizza-contribuicoes"),
        [
            ["Contribuíram", c.pagas ?? 0],
            ["Não contribuíram", c.nao_contribui ?? 0],
            ["Isentos", c.isentas ?? 0],
        ],
        (v) => `${v}`,
    );

    const ac = d.acessos ?? {};
    const cad = d.cadastros ?? {};
    $("numeros").replaceChildren(
        cartao("Entradas liberadas", ac.entradas ?? 0),
        cartao("Pessoas atendidas", ac.pessoas ?? 0),
        cartao("Saídas", ac.saidas ?? 0),
        cartao("Negados", ac.negados ?? 0),
        cartao("Cadastros novos", cad.pessoas_novas ?? 0),
        cartao("Pré-cadastros recebidos", cad.pre_cadastros_recebidos ?? 0),
    );

    const linhas = meus.map((cx) => {
        const tr = document.createElement("tr");
        const celula = (texto, classe) => {
            const td = document.createElement("td");
            td.textContent = texto;
            if (classe) td.className = classe;
            return td;
        };
        const operador = celula(cx.operador ?? "—");
        if (cx.situacao === "aberto") {
            const selo = document.createElement("span");
            selo.className = "selo";
            selo.textContent = "aberto";
            operador.append(selo);
        }
        const dif = cx.diferenca;
        tr.append(
            operador,
            celula(cx.posto ?? "—"),
            celula(`${hora(cx.aberto_em)} – ${cx.fechado_em ? hora(cx.fechado_em) : "agora"}`),
            celula(real.format(cx.arrecadado ?? 0), "num"),
            celula(real.format(cx.esperado ?? 0), "num"),
            celula(cx.informado == null ? "—" : real.format(cx.informado), "num"),
            celula(
                dif == null ? "—" : real.format(dif),
                `num ${dif == null || dif === 0 ? "" : dif > 0 ? "positivo" : "negativo"}`,
            ),
        );
        return tr;
    });
    if (!linhas.length) {
        const tr = document.createElement("tr");
        const td = document.createElement("td");
        td.colSpan = 7;
        td.className = "suave";
        td.textContent = "Nenhum caixa aberto neste dia.";
        tr.append(td);
        linhas.push(tr);
    }
    $("caixas").replaceChildren(...linhas);

    clearInterval(atualizacao);
    if (atual === hoje) atualizacao = setInterval(() => carregar($("dia").value), 60000);
}

$("form-entrar").addEventListener("submit", async (evento) => {
    evento.preventDefault();
    const botao = evento.submitter;
    botao.disabled = true;
    try {
        const r = await api("POST", { action: "login", email: $("email").value, senha: $("senha").value });
        if (r.status !== 200) return mostrarEntrada(r.dados.error ?? "Não foi possível entrar.");
        guardar(r.dados);
        $("senha").value = "";
        await carregar();
    } finally {
        botao.disabled = false;
    }
});
$("dia").addEventListener("change", () => carregar($("dia").value));
$("colaborador").addEventListener("change", () => {
    colaborador = $("colaborador").value;
    if (resposta) desenhar();
});
$("sair").addEventListener("click", () => mostrarEntrada());

if (sessao) carregar();
else mostrarEntrada();
