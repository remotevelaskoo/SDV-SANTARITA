import { createFileRoute, useNavigate } from "@tanstack/react-router";
import { KeyRound, Loader2, ShieldCheck } from "lucide-react";
import { useEffect, useState } from "react";

import { Alerta, BadgeStatus } from "@/components/ds";
import { MarcaSdv } from "@/components/shell/navegacao-lateral";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { USUARIOS_EXEMPLO } from "@/data/perfis";
import { useSessao } from "@/lib/session-context";

export const Route = createFileRoute("/entrar")({
  ssr: false,
  head: () => ({
    meta: [
      { title: "Entrar — SDV Access Santa Rita" },
      {
        name: "description",
        content:
          "Acesse o SDV Access Santa Rita para operar validação de entrada, cadastros e o dashboard de controle de acesso.",
      },
      { property: "og:title", content: "Entrar — SDV Access Santa Rita" },
      {
        property: "og:description",
        content: "Acesso ao sistema de controle de acesso do condomínio Santa Rita.",
      },
    ],
  }),
  component: Entrar,
});

function Entrar() {
  const { estado, entrar } = useSessao();
  const navegar = useNavigate();
  const [identificacao, setIdentificacao] = useState("");
  const [senha, setSenha] = useState("");
  const [erro, setErro] = useState<string | null>(null);
  const [enviando, setEnviando] = useState(false);

  useEffect(() => {
    if (estado === "autenticada") {
      void navegar({ to: "/painel", replace: true });
    }
  }, [estado, navegar]);

  function aoEnviar(evento: React.FormEvent<HTMLFormElement>) {
    evento.preventDefault();
    setEnviando(true);
    setErro(null);
    const resultado = entrar(identificacao, senha);
    setEnviando(false);
    if (!resultado.ok) {
      setErro(resultado.mensagem ?? "Não foi possível entrar.");
      return;
    }
    void navegar({ to: "/painel", replace: true });
  }

  return (
    <div className="grid min-h-screen lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
      <section className="hidden flex-col justify-between bg-shell p-10 lg:flex">
        <MarcaSdv />
        <div className="max-w-md">
          <h2 className="text-3xl font-bold text-shell-foreground">
            Controle de acesso centrado no imóvel
          </h2>
          <p className="mt-3 text-sm text-shell-muted">
            Cada pessoa, veículo e autorização é vinculada ao imóvel. Validação rápida na portaria,
            rastreabilidade completa para a administração.
          </p>
          <div className="mt-6 flex flex-wrap gap-2">
            <BadgeStatus tom="informativo" icone={ShieldCheck}>
              Trilha de auditoria
            </BadgeStatus>
            <BadgeStatus tom="informativo" icone={KeyRound}>
              Perfis e permissões
            </BadgeStatus>
          </div>
        </div>
        <p className="text-xs text-shell-muted">SDV Access · Condomínio Santa Rita</p>
      </section>

      <section className="flex items-center justify-center bg-background px-5 py-10">
        <div className="w-full max-w-sm">
          <div className="lg:hidden">
            <span className="inline-block rounded-lg bg-shell px-3 py-2">
              <MarcaSdv />
            </span>
          </div>

          <h1 className="mt-6 text-2xl font-bold text-foreground lg:mt-0">Entrar no sistema</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Informe sua identificação operacional e senha.
          </p>

          {erro ? (
            <div className="mt-4">
              <Alerta severidade="danger" titulo={erro} />
            </div>
          ) : null}

          <form className="mt-5 space-y-4" onSubmit={aoEnviar}>
            <div className="space-y-1.5">
              <Label htmlFor="identificacao">Identificação</Label>
              <Input
                id="identificacao"
                name="identificacao"
                autoComplete="username"
                required
                value={identificacao}
                onChange={(evento) => setIdentificacao(evento.target.value)}
                placeholder="ex.: portaria"
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="senha">Senha</Label>
              <Input
                id="senha"
                name="senha"
                type="password"
                autoComplete="current-password"
                required
                value={senha}
                onChange={(evento) => setSenha(evento.target.value)}
              />
            </div>
            <Button type="submit" className="w-full" disabled={enviando}>
              {enviando ? <Loader2 className="mr-2 h-4 w-4 animate-spin" aria-hidden /> : null}
              Entrar
            </Button>
          </form>

          <div className="mt-6 rounded-lg border border-border bg-card p-4">
            <p className="texto-rotulo text-muted-foreground">Usuários de demonstração</p>
            <ul className="mt-2 space-y-1.5">
              {USUARIOS_EXEMPLO.map((usuario) => (
                <li key={usuario.identificacao}>
                  <button
                    type="button"
                    onClick={() => {
                      setIdentificacao(usuario.identificacao);
                      setSenha(usuario.senha);
                      setErro(null);
                    }}
                    className="grid w-full grid-cols-[minmax(0,1fr)_auto] items-center gap-2 rounded-md px-2 py-1.5 text-left transition-colors hover:bg-secondary"
                  >
                    <span className="min-w-0">
                      <span className="block truncate text-sm font-medium text-foreground">
                        {usuario.nome}
                      </span>
                      <span className="block truncate text-xs text-muted-foreground">
                        {usuario.identificacao} · {usuario.senha}
                      </span>
                    </span>
                    <span className="shrink-0 text-xs font-semibold text-brand-blue-600">
                      usar
                    </span>
                  </button>
                </li>
              ))}
            </ul>
          </div>
        </div>
      </section>
    </div>
  );
}
