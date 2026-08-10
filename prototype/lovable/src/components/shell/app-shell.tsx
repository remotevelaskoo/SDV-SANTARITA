/**
 * App shell — 003_DESIGN_SYSTEM §11.1
 * Navegação lateral + cabeçalho operacional + área principal.
 */
import { useState } from "react";

import { NavegacaoLateral } from "@/components/shell/navegacao-lateral";
import { CabecalhoOperacional } from "@/components/shell/cabecalho-operacional";
import { Sheet, SheetContent, SheetTitle } from "@/components/ui/sheet";
import { cn } from "@/lib/utils";

export function AppShell({
  titulo,
  descricao,
  children,
}: {
  titulo: string;
  descricao?: string | undefined;
  children: React.ReactNode;
}) {
  const [recolhida, setRecolhida] = useState(false);
  const [menuMobile, setMenuMobile] = useState(false);

  return (
    <div className="flex min-h-screen w-full bg-background">
      <aside
        className={cn(
          "hidden shrink-0 border-r border-shell-border lg:block",
          recolhida ? "w-16" : "w-64",
        )}
      >
        <div className="sticky top-0 h-screen">
          <NavegacaoLateral recolhida={recolhida} onRecolher={() => setRecolhida(true)} />
        </div>
      </aside>

      <Sheet open={menuMobile} onOpenChange={setMenuMobile}>
        <SheetContent side="left" className="w-72 border-shell-border bg-shell p-0">
          <SheetTitle className="sr-only">Navegação do SDV Access</SheetTitle>
          <NavegacaoLateral recolhida={false} onNavegar={() => setMenuMobile(false)} />
        </SheetContent>
      </Sheet>

      <div className="flex min-w-0 flex-1 flex-col">
        <CabecalhoOperacional
          titulo={titulo}
          descricao={descricao}
          onAbrirMenuMobile={() => setMenuMobile(true)}
          onAlternarRecolhida={() => setRecolhida((valor) => !valor)}
        />
        <main className="min-w-0 flex-1 px-4 py-4 sm:px-6 sm:py-6">{children}</main>
      </div>
    </div>
  );
}
