/**
 * Sessão simulada (front-end apenas).
 * Não é autenticação segura: valida credenciais contra os usuários de exemplo
 * e persiste a sessão no navegador. Substituir por Lovable Cloud quando aprovado.
 */

import { PERFIS, USUARIOS_EXEMPLO, type PerfilId, type Permissao } from "@/data/perfis";

export const CHAVE_SESSAO = "sdv-access.sessao";

export interface Sessao {
  identificacao: string;
  nome: string;
  perfil: PerfilId;
  condominio: string;
  pontoAcesso: string;
  iniciadaEm: string;
}

export function lerSessao(): Sessao | null {
  if (typeof window === "undefined") return null;
  try {
    const bruto = window.localStorage.getItem(CHAVE_SESSAO);
    if (!bruto) return null;
    const dado = JSON.parse(bruto) as Partial<Sessao>;
    if (!dado.identificacao || !dado.perfil || !(dado.perfil in PERFIS)) return null;
    return dado as Sessao;
  } catch {
    return null;
  }
}

export function gravarSessao(sessao: Sessao) {
  if (typeof window === "undefined") return;
  window.localStorage.setItem(CHAVE_SESSAO, JSON.stringify(sessao));
}

export function limparSessao() {
  if (typeof window === "undefined") return;
  window.localStorage.removeItem(CHAVE_SESSAO);
}

export type ResultadoAutenticacao =
  | { ok: true; sessao: Sessao }
  | { ok: false; mensagem: string };

export function autenticar(identificacao: string, senha: string): ResultadoAutenticacao {
  const chave = identificacao.trim().toLowerCase();
  const usuario = USUARIOS_EXEMPLO.find((item) => item.identificacao === chave);

  // Mensagem única: não revelar se a identificação existe (DS §20).
  if (!usuario || usuario.senha !== senha) {
    return { ok: false, mensagem: "Identificação ou senha inválida." };
  }

  return {
    ok: true,
    sessao: {
      identificacao: usuario.identificacao,
      nome: usuario.nome,
      perfil: usuario.perfil,
      condominio: usuario.condominio,
      pontoAcesso: usuario.pontoAcesso,
      iniciadaEm: new Date().toISOString(),
    },
  };
}

export function permissoesDoPerfil(perfil: PerfilId): Permissao[] {
  return PERFIS[perfil].permissoes;
}

export function temPermissao(perfil: PerfilId, permissao: Permissao): boolean {
  return PERFIS[perfil].permissoes.includes(permissao);
}
