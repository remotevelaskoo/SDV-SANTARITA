import { createContext, useCallback, useContext, useEffect, useMemo, useState } from "react";

import type { PerfilId, Permissao } from "@/data/perfis";
import { PERFIS } from "@/data/perfis";
import {
  autenticar,
  gravarSessao,
  lerSessao,
  limparSessao,
  type Sessao,
} from "@/lib/session";

type EstadoSessao = "carregando" | "autenticada" | "anonima";

interface ContextoSessao {
  estado: EstadoSessao;
  sessao: Sessao | null;
  perfil: (typeof PERFIS)[PerfilId] | null;
  entrar: (identificacao: string, senha: string) => { ok: boolean; mensagem?: string };
  sair: () => void;
  pode: (permissao: Permissao) => boolean;
}

const Contexto = createContext<ContextoSessao | null>(null);

export function SessaoProvider({ children }: { children: React.ReactNode }) {
  const [estado, setEstado] = useState<EstadoSessao>("carregando");
  const [sessao, setSessao] = useState<Sessao | null>(null);

  useEffect(() => {
    const existente = lerSessao();
    setSessao(existente);
    setEstado(existente ? "autenticada" : "anonima");
  }, []);

  const entrar = useCallback((identificacao: string, senha: string) => {
    const resultado = autenticar(identificacao, senha);
    if (!resultado.ok) return { ok: false, mensagem: resultado.mensagem };
    gravarSessao(resultado.sessao);
    setSessao(resultado.sessao);
    setEstado("autenticada");
    return { ok: true };
  }, []);

  const sair = useCallback(() => {
    limparSessao();
    setSessao(null);
    setEstado("anonima");
  }, []);

  const pode = useCallback(
    (permissao: Permissao) =>
      sessao ? PERFIS[sessao.perfil].permissoes.includes(permissao) : false,
    [sessao],
  );

  const valor = useMemo<ContextoSessao>(
    () => ({
      estado,
      sessao,
      perfil: sessao ? PERFIS[sessao.perfil] : null,
      entrar,
      sair,
      pode,
    }),
    [estado, sessao, entrar, sair, pode],
  );

  return <Contexto.Provider value={valor}>{children}</Contexto.Provider>;
}

export function useSessao() {
  const contexto = useContext(Contexto);
  if (!contexto) throw new Error("useSessao deve ser usado dentro de SessaoProvider.");
  return contexto;
}
