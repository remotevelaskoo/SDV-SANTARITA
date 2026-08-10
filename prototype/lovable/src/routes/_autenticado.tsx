import { createFileRoute, Outlet, useNavigate } from "@tanstack/react-router";
import { useEffect } from "react";

import { useSessao } from "@/lib/session-context";

export const Route = createFileRoute("/_autenticado")({
  ssr: false,
  component: LayoutAutenticado,
});

function LayoutAutenticado() {
  const { estado } = useSessao();
  const navegar = useNavigate();

  useEffect(() => {
    if (estado === "anonima") {
      void navegar({ to: "/entrar", replace: true });
    }
  }, [estado, navegar]);

  if (estado !== "autenticada") {
    return (
      <div className="grid min-h-screen place-items-center bg-background">
        <div className="flex flex-col items-center gap-4">
          <div className="flex items-center gap-3">
            <span className="grid h-10 w-10 place-items-center rounded-md bg-brand-blue-600 text-base font-black tracking-tight text-white">
              SDV
            </span>
            <div className="leading-tight">
              <span className="block text-lg font-bold text-foreground">SDV Access</span>
              <span className="block text-xs text-muted-foreground">Santa Rita</span>
            </div>
          </div>
          <p className="text-sm text-muted-foreground animate-pulse">Carregando sessão…</p>
        </div>
      </div>
    );
  }

  return <Outlet />;
}
