import { createFileRoute } from "@tanstack/react-router";
import { Wallet, Search, Plus, Filter, ArrowUpRight, ArrowDownLeft, Calendar, FileText, Download, TrendingUp } from "lucide-react";
import { useState } from "react";

import { Painel, PainelCabecalho, BadgeStatus, Comparacao } from "@/components/ds";
import { AppShell } from "@/components/shell/app-shell";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { SITUACAO_CAIXA } from "@/data/dashboard";
import { cn } from "@/lib/utils";

export const Route = createFileRoute("/_autenticado/modulo/caixa")({
  head: () => ({
    meta: [
      { title: "Módulo Financeiro e Caixa — SDV Access Santa Rita" },
      {
        name: "description",
        content: "Gestão de arrecadação, taxas de estacionamento e controle de fluxo financeiro do caixa.",
      },
    ],
  }),
  component: CaixaModulo,
});

const TRANSACACOES_MOCK = [
  {
    id: "tr-1",
    horario: "15:45",
    descricao: "Taxa Estacionamento (Visitante)",
    pessoa: "CARLOS MENDES",
    unidade: "Bloco B — 304",
    valor: 25.00,
    tipo: "entrada",
    metodo: "PIX"
  },
  {
    id: "tr-2",
    horario: "15:20",
    descricao: "Segunda Via Tag Acesso",
    pessoa: "RICARDO VELASKO",
    unidade: "Bloco A — 102",
    valor: 45.00,
    tipo: "entrada",
    metodo: "Débito"
  },
  {
    id: "tr-3",
    horario: "14:50",
    descricao: "Estorno Taxa Prestador",
    pessoa: "ELIANE SANTOS",
    unidade: "Bloco C — 504",
    valor: -15.00,
    tipo: "saida",
    metodo: "Dinheiro"
  }
];

function CaixaModulo() {
  const [busca, setBusca] = useState("");

  return (
    <AppShell 
      titulo="Módulo de Caixa" 
      descricao="Controle de arrecadação e transações operacionais"
    >
      <div className="space-y-6">
        {/* Header de Status do Caixa */}
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <Painel className="p-4 border-l-4 border-l-success">
            <p className="text-xs font-bold text-muted-foreground uppercase tracking-wider">Saldo em Caixa</p>
            <h3 className="mt-1 text-2xl font-bold text-foreground">R$ 3.487,50</h3>
            <div className="mt-2">
              <Comparacao tendencia="alta" rotulo="vs. ontem" variacao={8.2} />
            </div>
          </Painel>
          
          <Painel className="p-4">
            <p className="text-xs font-bold text-muted-foreground uppercase tracking-wider">Transações Hoje</p>
            <h3 className="mt-1 text-2xl font-bold text-foreground">42</h3>
            <div className="mt-2 flex items-center gap-1 text-xs text-muted-foreground">
              <TrendingUp className="h-3.5 w-3.5 text-success-foreground" />
              <span>+12% volume</span>
            </div>
          </Painel>

          <Painel className="p-4">
            <p className="text-xs font-bold text-muted-foreground uppercase tracking-wider">Ponto de Venda</p>
            <h3 className="mt-1 text-lg font-bold text-foreground">{SITUACAO_CAIXA.identificacao}</h3>
            <div className="mt-2 flex items-center gap-2">
              <BadgeStatus tom="sucesso">ABERTO</BadgeStatus>
              <span className="text-[10px] text-muted-foreground">DESDE {SITUACAO_CAIXA.abertoEm}</span>
            </div>
          </Painel>

          <Painel className="p-4 bg-brand-navy-900 text-white border-none">
            <p className="text-xs font-bold opacity-70 uppercase tracking-wider">Ações Rápidas</p>
            <div className="mt-3 flex gap-2">
              <Button size="sm" className="flex-1 bg-white/10 hover:bg-white/20 text-white text-[10px] font-bold h-8">
                SANGRIA
              </Button>
              <Button size="sm" className="flex-1 bg-brand-blue-600 hover:bg-brand-blue-700 text-white text-[10px] font-bold h-8">
                FECHAR
              </Button>
            </div>
          </Painel>
        </div>

        {/* Listagem de Transações */}
        <div className="space-y-4">
          <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div className="relative max-w-md flex-1">
              <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
              <Input 
                placeholder="Buscar por descrição, pessoa ou unidade..." 
                className="pl-9"
                value={busca}
                onChange={(e) => setBusca(e.target.value)}
              />
            </div>
            <div className="flex items-center gap-2">
              <Button variant="outline" className="gap-2">
                <Calendar className="h-4 w-4" />
                PERÍODO
              </Button>
              <Button className="gap-2 bg-brand-blue-600 hover:bg-brand-blue-700">
                <Plus className="h-4 w-4" />
                NOVA TAXA
              </Button>
            </div>
          </div>

          <Painel>
            <PainelCabecalho 
              titulo="Últimas Transações" 
              descricao="Registros financeiros do turno atual"
              acoes={
                <Button variant="ghost" size="sm" className="h-8 gap-2 text-xs">
                  <Download className="h-3.5 w-3.5" />
                  EXPORTAR
                </Button>
              }
            />
            
            <div className="divide-y divide-border">
              {TRANSACACOES_MOCK.map((tr) => (
                <div key={tr.id} className="flex items-center justify-between p-4 hover:bg-neutral-50 transition-colors">
                  <div className="flex items-center gap-4">
                    <div className={cn(
                      "flex h-10 w-10 items-center justify-center rounded-lg",
                      tr.tipo === 'entrada' ? "bg-success-surface text-success-foreground" : "bg-danger-surface text-danger-foreground"
                    )}>
                      {tr.tipo === 'entrada' ? <ArrowUpRight className="h-5 w-5" /> : <ArrowDownLeft className="h-5 w-5" />}
                    </div>
                    <div>
                      <h4 className="text-sm font-bold text-foreground">{tr.descricao}</h4>
                      <p className="text-xs text-muted-foreground">{tr.pessoa} · {tr.unidade}</p>
                    </div>
                  </div>
                  
                  <div className="text-right">
                    <p className={cn(
                      "text-sm font-bold",
                      tr.tipo === 'entrada' ? "text-success-foreground" : "text-danger-foreground"
                    )}>
                      {tr.tipo === 'entrada' ? '+' : ''} R$ {Math.abs(tr.valor).toFixed(2).replace('.', ',')}
                    </p>
                    <div className="mt-0.5 flex items-center justify-end gap-2 text-[10px] text-muted-foreground">
                      <BadgeStatus tom="neutro" className="px-1 py-0 h-4">{tr.metodo}</BadgeStatus>
                      <span>{tr.horario}</span>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </Painel>
        </div>
      </div>
    </AppShell>
  );
}
