/**
 * DS-CMP-011 — Badge de status
 * DS-CMP-012 — Alerta
 * DS-CMP-014 — Estado vazio
 * DS-CMP-016 — Card
 * DS-CMP-019 — Métrica
 * Fonte: 003_DESIGN_SYSTEM §12.
 */
import { cva, type VariantProps } from "class-variance-authority";
import {
  AlertTriangle,
  ArrowDownRight,
  ArrowUpRight,
  Info,
  Minus,
  ShieldAlert,
  type LucideIcon,
} from "lucide-react";

import { cn } from "@/lib/utils";

/* ------------------------------- DS-CMP-016 ------------------------------- */

export function Painel({
  className,
  ...props
}: React.HTMLAttributes<HTMLDivElement>) {
  return (
    <section
      className={cn(
        "rounded-lg border border-border bg-card text-card-foreground shadow-nivel-1",
        className,
      )}
      {...props}
    />
  );
}

export function PainelCabecalho({
  titulo,
  descricao,
  acoes,
  className,
}: {
  titulo: string;
  descricao?: string | undefined;
  acoes?: React.ReactNode;
  className?: string;
}) {
  return (
    <header
      className={cn(
        "grid grid-cols-[minmax(0,1fr)_auto] items-start gap-3 border-b border-border px-4 py-3 sm:px-5",
        className,
      )}
    >
      <div className="min-w-0">
        <h2 className="truncate text-sm font-semibold text-foreground">{titulo}</h2>
        {descricao ? (
          <p className="mt-0.5 truncate text-xs text-muted-foreground">{descricao}</p>
        ) : null}
      </div>
      {acoes ? <div className="flex shrink-0 items-center gap-2">{acoes}</div> : null}
    </header>
  );
}

/* ------------------------------- DS-CMP-011 ------------------------------- */

const badgeVariants = cva(
  "inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 text-xs font-semibold whitespace-nowrap",
  {
    variants: {
      tom: {
        neutro: "bg-secondary text-secondary-foreground",
        sucesso: "bg-success-surface text-success-foreground",
        atencao: "bg-warning-surface text-warning-foreground",
        negativo: "bg-danger-surface text-danger-foreground",
        informativo: "bg-info-surface text-info-foreground",
      },
    },
    defaultVariants: { tom: "neutro" },
  },
);

export interface BadgeStatusProps
  extends React.HTMLAttributes<HTMLSpanElement>,
    VariantProps<typeof badgeVariants> {
  icone?: LucideIcon;
}

export function BadgeStatus({ tom, icone: Icone, className, children, ...props }: BadgeStatusProps) {
  return (
    <span className={cn(badgeVariants({ tom }), className)} {...props}>
      {Icone ? <Icone className="h-3.5 w-3.5 shrink-0" aria-hidden /> : null}
      {children}
    </span>
  );
}

/* ------------------------------- DS-CMP-012 ------------------------------- */

const ICONES_ALERTA = {
  danger: ShieldAlert,
  warning: AlertTriangle,
  info: Info,
} as const;

const ESTILOS_ALERTA = {
  danger: "border-danger/30 bg-danger-surface text-danger-foreground",
  warning: "border-warning/40 bg-warning-surface text-warning-foreground",
  info: "border-info/30 bg-info-surface text-info-foreground",
} as const;

export function Alerta({
  severidade,
  titulo,
  descricao,
  acao,
}: {
  severidade: keyof typeof ICONES_ALERTA;
  titulo: string;
  descricao?: string | undefined;
  acao?: React.ReactNode;
}) {
  const Icone = ICONES_ALERTA[severidade];
  return (
    <div
      role="status"
      className={cn(
        "grid grid-cols-[auto_minmax(0,1fr)] items-start gap-3 rounded-lg border px-4 py-3 sm:grid-cols-[auto_minmax(0,1fr)_auto]",
        ESTILOS_ALERTA[severidade],
      )}
    >
      <Icone className="mt-0.5 h-4.5 w-4.5 shrink-0" aria-hidden />
      <div className="min-w-0">
        <p className="text-sm font-semibold">{titulo}</p>
        {descricao ? <p className="mt-0.5 text-xs opacity-90">{descricao}</p> : null}
      </div>
      {acao ? <div className="col-span-2 shrink-0 sm:col-span-1">{acao}</div> : null}
    </div>
  );
}

/* ------------------------------- DS-CMP-014 ------------------------------- */

export function EstadoVazio({
  icone: Icone,
  titulo,
  descricao,
  acao,
}: {
  icone: LucideIcon;
  titulo: string;
  descricao?: string | undefined;
  acao?: React.ReactNode;
}) {
  return (
    <div className="flex flex-col items-center justify-center gap-2 px-6 py-12 text-center">
      <div className="grid h-11 w-11 place-items-center rounded-full bg-secondary text-muted-foreground">
        <Icone className="h-5 w-5" aria-hidden />
      </div>
      <p className="text-sm font-semibold text-foreground">{titulo}</p>
      {descricao ? (
        <p className="max-w-sm text-xs text-muted-foreground">{descricao}</p>
      ) : null}
      {acao}
    </div>
  );
}

/* ------------------------------- DS-CMP-019 ------------------------------- */

const ICONES_TENDENCIA = {
  alta: ArrowUpRight,
  baixa: ArrowDownRight,
  estavel: Minus,
} as const;

const CORES_TENDENCIA = {
  alta: "text-success-foreground",
  baixa: "text-danger-foreground",
  estavel: "text-muted-foreground",
} as const;

export function Comparacao({
  tendencia,
  rotulo,
  variacao,
}: {
  tendencia: keyof typeof ICONES_TENDENCIA;
  rotulo: string;
  variacao: number;
}) {
  const Icone = ICONES_TENDENCIA[tendencia];
  return (
    <p className="flex min-w-0 items-center gap-1 text-xs">
      <Icone className={cn("h-3.5 w-3.5 shrink-0", CORES_TENDENCIA[tendencia])} aria-hidden />
      <span className={cn("numerico font-semibold", CORES_TENDENCIA[tendencia])}>
        {tendencia === "estavel" ? "0,0%" : `${variacao.toFixed(1).replace(".", ",")}%`}
      </span>
      <span className="truncate text-muted-foreground">{rotulo}</span>
    </p>
  );
}
