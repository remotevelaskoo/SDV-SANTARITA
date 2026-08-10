import { Link } from "@tanstack/react-router";
import { ArrowUpRight, RefreshCw } from "lucide-react";

import { Comparacao, Painel } from "@/components/ds";
import type { Indicador } from "@/data/dashboard";
import { cn } from "@/lib/utils";

const formatadorQuantidade = new Intl.NumberFormat("pt-BR");
const formatadorMoeda = new Intl.NumberFormat("pt-BR", {
  style: "currency",
  currency: "BRL",
});

export function CartaoIndicador({
  indicador,
  navegavel,
}: {
  indicador: Indicador;
  navegavel: boolean;
}) {
  const valor =
    indicador.unidade === "moeda"
      ? formatadorMoeda.format(indicador.valor)
      : formatadorQuantidade.format(indicador.valor);

  const conteudo = (
    <>
      <div className="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-2">
        <p className="texto-rotulo min-w-0 text-muted-foreground">{indicador.rotulo}</p>
        {navegavel ? (
          <ArrowUpRight className="h-4 w-4 shrink-0 text-muted-foreground" aria-hidden />
        ) : null}
      </div>
      <p className="texto-metrica mt-2 text-foreground">{valor}</p>
      {indicador.comparacao ? (
        <div className="mt-1.5">
          <Comparacao
            tendencia={indicador.comparacao.tendencia}
            rotulo={indicador.comparacao.rotulo}
            variacao={indicador.comparacao.variacao}
          />
        </div>
      ) : null}
      <div className="mt-3 grid grid-cols-[minmax(0,1fr)_auto] items-center gap-2 border-t border-border pt-2.5">
        <p className="truncate text-xs text-muted-foreground">{indicador.periodo}</p>
        <p className="numerico flex shrink-0 items-center gap-1 text-xs text-muted-foreground">
          <RefreshCw className="h-3 w-3" aria-hidden />
          {indicador.atualizadoEm}
        </p>
      </div>
    </>
  );

  if (navegavel && indicador.destino) {
    return (
      <Link
        to="/modulo/$slug"
        params={{ slug: indicador.destino }}
        className={cn(
          "block rounded-lg border border-border bg-card p-4 shadow-nivel-1 transition-shadow",
          "hover:border-border-strong hover:shadow-nivel-2",
          "focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none",
        )}
      >
        {conteudo}
      </Link>
    );
  }

  return <Painel className="p-4">{conteudo}</Painel>;
}
