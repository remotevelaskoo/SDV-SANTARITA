// Painel de gestão (PO 15/11/2026): só leitura dos totais enviados pelo servidor da portaria.
const $ = (id) => document.getElementById(id);
const CHAVE = "sdv-gestao-sessao";
const real = new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" });
const hora = (iso) =>
    iso ? new Date(iso).toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" }) : "—";
const dataLonga = (dia) =>
    new Date(`${dia}T12:00:00`).toLocaleDateString("pt-BR", { weekday: "long", day: "2-digit", month: "long" });
const FORMAS = { dinheiro: "Dinheiro", pix: "Pix", cartao: "Cartão", outro: "Outro" };

let sessao = ler();
let atualizacao = null;

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
    const resposta = await fetch(`/api/gestao${query}`, {
        method: metodo,
        headers: {
            "Content-Type": "application/json",
            ...(sessao && metodo === "GET" ? { Authorization: `Bearer ${sessao.token}` } : {}),
        },
        body: corpo ? JSON.stringify(corpo) : undefined,
    });
    const dados = await resposta.json().catch(() => ({}));
    return { status: resposta.status, dados };
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
    desenhar(r.dados);
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

function desenhar(r) {
    $("entrar").hidden = true;
    $("painel").hidden = false;
    $("sair").hidden = false;
    $("condominio").textContent = r.condominio ?? "Santa Rita";

    const select = $("dia");
    const atual = r.dia;
    select.replaceChildren(
        ...(r.dias ?? []).map((d) => {
            const opcao = document.createElement("option");
            opcao.value = d;
            opcao.textContent = new Date(`${d}T12:00:00`).toLocaleDateString("pt-BR");
            opcao.selected = d === atual;
            return opcao;
        }),
    );

    const hoje = new Date().toLocaleDateString("sv-SE");
    $("titulo-dia").textContent = atual ? (atual === hoje ? "Hoje" : dataLonga(atual)) : "Sem dados ainda";
    const d = r.dados;
    if (!d) {
        $("aviso").hidden = false;
        $("aviso").textContent = "O servidor da portaria ainda não enviou números para este dia.";
        $("atualizado").textContent = "";
        $("numeros").replaceChildren();
        $("formas").replaceChildren();
        $("caixas").replaceChildren();
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

    const a = d.arrecadacao ?? {};
    $("total").textContent = real.format(a.total ?? 0);
    $("composicao").textContent = `Contribuições ${real.format(a.contribuicoes ?? 0)} · lançamentos ${real.format(
        a.lancamentos_manuais ?? 0,
    )} · saídas e estornos ${real.format(a.saidas_e_estornos ?? 0)}`;

    const ac = d.acessos ?? {};
    const c = d.contribuicoes ?? {};
    const cad = d.cadastros ?? {};
    $("numeros").replaceChildren(
        cartao("Entradas liberadas", ac.entradas ?? 0),
        cartao("Pessoas atendidas", ac.pessoas ?? 0),
        cartao("Saídas", ac.saidas ?? 0),
        cartao("Negados", ac.negados ?? 0),
        cartao("Cadastros novos", cad.pessoas_novas ?? 0),
        cartao("Pré-cadastros recebidos", cad.pre_cadastros_recebidos ?? 0),
        cartao("Contribuíram", c.pagas ?? 0),
        cartao("Não contribuíram", c.nao_contribui ?? 0),
        cartao("Isentos", c.isentas ?? 0),
    );

    const formas = Object.entries(a.por_forma ?? {});
    $("formas").replaceChildren(
        ...(formas.length
            ? formas.map(([f, v]) => cartao(FORMAS[f] ?? f, real.format(v)))
            : [cartao("Sem recebimentos", real.format(0))]),
    );

    const linhas = (d.caixas ?? []).map((cx) => {
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
$("sair").addEventListener("click", () => mostrarEntrada());

if (sessao) carregar();
else mostrarEntrada();
