/**
 * Gráfico de entradas x saídas — 004_UX_UI_DASHBOARD §10.
 */
import { useState } from "react";
import {
  Area,
  AreaChart,
  CartesianGrid,
  Legend,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";

import { PainelCabecalho } from "@/components/ds";
import { SERIES_ENTRADAS_SAIDAS, type AgrupamentoGrafico } from "@/data/dashboard";
import { cn } from "@/lib/utils";

const OPCOES: { valor: AgrupamentoGrafico; rotulo: string }[] = [
  { valor: "hoje", rotulo: "Hoje" },
  { valor: "7dias", rotulo: "7 dias" },
  { valor: "30dias", rotulo: "30 dias" },
];

export function GraficoEntradasSaidas() {
  const [agrupamento, setAgrupamento] = useState<AgrupamentoGrafico>("hoje");
  const dados = SERIES_ENTRADAS_SAIDAS[agrupamento];

  return (
    <>
      <PainelCabecalho
        titulo="Entradas e saídas"
        descricao="Volume de movimentações validadas no período"
        acoes={
          <div
            role="group"
            aria-label="Período do gráfico"
            className="flex rounded-md border border-border p-0.5"
          >
            {OPCOES.map((opcao) => (
              <button
                key={opcao.valor}
                type="button"
                onClick={() => setAgrupamento(opcao.valor)}
                aria-pressed={agrupamento === opcao.valor}
                className={cn(
                  "rounded-[0.3rem] px-2.5 py-1 text-xs font-semibold whitespace-nowrap transition-colors",
                  agrupamento === opcao.valor
                    ? "bg-brand-blue-600 text-white"
                    : "text-muted-foreground hover:bg-secondary",
                )}
              >
                {opcao.rotulo}
              </button>
            ))}
          </div>
        }
      />
      <div className="h-72 px-2 py-4 sm:px-4">
        <ResponsiveContainer width="100%" height="100%">
          <AreaChart data={dados} margin={{ top: 4, right: 8, bottom: 0, left: -12 }}>
            <defs>
              <linearGradient id="grad-entradas" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stopColor="var(--chart-entradas)" stopOpacity={0.35} />
                <stop offset="100%" stopColor="var(--chart-entradas)" stopOpacity={0.02} />
              </linearGradient>
              <linearGradient id="grad-saidas" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stopColor="var(--chart-saidas)" stopOpacity={0.3} />
                <stop offset="100%" stopColor="var(--chart-saidas)" stopOpacity={0.02} />
              </linearGradient>
            </defs>
            <CartesianGrid stroke="var(--border)" vertical={false} />
            <XAxis
              dataKey="rotulo"
              stroke="var(--muted-foreground)"
              tickLine={false}
              axisLine={false}
              fontSize={12}
            />
            <YAxis
              stroke="var(--muted-foreground)"
              tickLine={false}
              axisLine={false}
              fontSize={12}
              width={44}
            />
            <Tooltip
              contentStyle={{
                background: "var(--card)",
                border: "1px solid var(--border)",
                borderRadius: "0.5rem",
                fontSize: "0.8125rem",
                color: "var(--foreground)",
              }}
            />
            <Legend
              verticalAlign="top"
              height={28}
              iconType="circle"
              wrapperStyle={{ fontSize: "0.75rem" }}
            />
            <Area
              type="monotone"
              dataKey="entradas"
              name="Entradas"
              stroke="var(--chart-entradas)"
              strokeWidth={2}
              fill="url(#grad-entradas)"
            />
            <Area
              type="monotone"
              dataKey="saidas"
              name="Saídas"
              stroke="var(--chart-saidas)"
              strokeWidth={2}
              fill="url(#grad-saidas)"
            />
          </AreaChart>
        </ResponsiveContainer>
      </div>
    </>
  );
}
