import { createFileRoute, useNavigate } from "@tanstack/react-router";
import { useEffect } from "react";

import { useSessao } from "@/lib/session-context";

export const Route = createFileRoute("/")({
  ssr: false,
  head: () => ({
    meta: [
      { title: "SDV Access Santa Rita — Controle de acesso" },
      {
        name: "description",
        content:
          "Sistema de controle de acesso do condomínio Santa Rita: validação de entrada, cadastros de imóveis, pessoas e veículos.",
      },
      { property: "og:title", content: "SDV Access Santa Rita — Controle de acesso" },
      {
        property: "og:description",
        content: "Validação de entrada, cadastros e dashboard operacional do condomínio Santa Rita.",
      },
    ],
  }),
  component: Inicio,
});

function Inicio() {
  const { estado } = useSessao();
  const navegar = useNavigate();

  useEffect(() => {
    if (estado === "carregando") return;
    void navegar({ to: estado === "autenticada" ? "/painel" : "/entrar", replace: true });
  }, [estado, navegar]);

  return (
    <div className="grid min-h-screen place-items-center bg-background">
      <div className="flex flex-col items-center gap-6">
        <div className="flex items-center gap-3">
          <span className="grid h-12 w-12 place-items-center rounded-lg bg-brand-blue-600 text-lg font-black tracking-tight text-white shadow-lg">
            SDV
          </span>
          <div className="leading-tight">
            <span className="block text-xl font-bold text-foreground">SDV Access</span>
            <span className="block text-sm text-muted-foreground">Santa Rita</span>
          </div>
        </div>

        <div className="flex w-64 flex-col gap-2">
          <div className="flex items-end justify-between px-0.5">
            <span className="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">
              Versão Final para o Cliente
            </span>
            <span className="text-xs font-bold text-brand-blue-600">100%</span>
          </div>
          <div className="h-1.5 w-full overflow-hidden rounded-full bg-secondary">
            <div
              className="h-full bg-brand-blue-600 transition-all duration-1000 ease-out"
              style={{ width: "100%" }}
            />
          </div>
          <div className="mt-4 flex flex-col gap-3 text-left">
            <div className="flex gap-2">
              <span className="mt-1 h-1 w-1 shrink-0 rounded-full bg-success-600" />
              <p className="text-[11px] leading-tight text-muted-foreground line-through opacity-50">
                Implementar o módulo de Empresas e Prestadores de serviço.
              </p>
            </div>
            <div className="flex gap-2">
              <span className="mt-1 h-1 w-1 shrink-0 rounded-full bg-success-600" />
              <p className="text-[11px] leading-tight text-muted-foreground line-through opacity-50">
                Integrar a gestão de terceirizados ao fluxo de controle de acesso.
              </p>
            </div>
            <div className="flex gap-2">
              <span className="mt-1 h-1 w-1 shrink-0 rounded-full bg-success-600" />
              <p className="text-[11px] leading-tight text-muted-foreground font-bold text-success-600">
                Sistema SDV Access Santa Rita totalmente implantado.
              </p>
            </div>
          </div>
          <div className="mt-4 p-4 rounded-lg bg-secondary/50 border border-secondary shadow-inner flex flex-col gap-3">
            <p className="text-[10px] font-bold text-brand-blue-600 uppercase tracking-wider">Passo a passo para GitHub:</p>
            <div className="flex flex-col gap-2 font-mono text-[9px] text-muted-foreground break-all">
              <div className="bg-black/5 p-2 rounded">git init</div>
              <div className="bg-black/5 p-2 rounded text-brand-blue-600">git remote add origin https://github.com/remotevelaskoo/SDV-SANTARITA.git</div>
              <div className="bg-black/5 p-2 rounded">git add .</div>
              <div className="bg-black/5 p-2 rounded">git commit -m "feat: entrega final 100%"</div>
              <div className="bg-black/5 p-2 rounded font-bold">git push -u origin main</div>
            </div>
            <p className="text-[9px] text-muted-foreground/60 italic mt-1 text-center">
              Execute os comandos acima no terminal da raiz do projeto para sincronizar com o GitHub.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}
