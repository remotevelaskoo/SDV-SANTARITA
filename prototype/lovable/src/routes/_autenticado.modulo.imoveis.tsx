import { createFileRoute } from "@tanstack/react-router";
import { 
  Building2, 
  Search, 
  Plus, 
  Filter, 
  ChevronRight,
  User,
  Users,
  Home,
  MapPin,
  ArrowLeft,
  Phone,
  CalendarDays,
  History,
  ShieldAlert
} from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";

import { Painel, PainelCabecalho, BadgeStatus } from "@/components/ds";
import { AppShell } from "@/components/shell/app-shell";
import { cn } from "@/lib/utils";

export const Route = createFileRoute("/_autenticado/modulo/imoveis")({
  head: () => ({
    meta: [
      { title: "Gestão de Imóveis — SDV Access Santa Rita" },
      {
        name: "description",
        content: "Consulta e gestão de unidades, blocos e ocupantes do condomínio.",
      },
    ],
  }),
  component: GestaoImoveis,
});

const IMOVEIS_MOCK = [
  { id: "1", unidade: "101", bloco: "A", tipo: "Apartamento", ocupantes: 3, situacao: "Ocupado" },
  { id: "2", unidade: "102", bloco: "A", tipo: "Apartamento", ocupantes: 1, situacao: "Ocupado" },
  { id: "3", unidade: "103", bloco: "A", tipo: "Apartamento", ocupantes: 0, situacao: "Vazio" },
  { id: "4", unidade: "104", bloco: "A", tipo: "Apartamento", ocupantes: 4, situacao: "Inadimplente" },
  { id: "5", unidade: "201", bloco: "B", tipo: "Cobertura", ocupantes: 2, situacao: "Ocupado" },
  { id: "6", unidade: "202", bloco: "B", tipo: "Apartamento", ocupantes: 5, situacao: "Ocupado" },
];

function GestaoImoveis() {
  const [busca, setBusca] = useState("");
  const [imovelSelecionado, setImovelSelecionado] = useState<typeof IMOVEIS_MOCK[0] | null>(null);
  const [editando, setEditando] = useState(false);

  const handleSalvarEdicao = () => {
    toast.success("Dados do imóvel atualizados", {
      description: `${imovelSelecionado?.bloco} — ${imovelSelecionado?.unidade} foi atualizado com sucesso.`
    });
    setEditando(false);
  };

  if (editando && imovelSelecionado) {
    return (
      <AppShell 
        titulo={`Editar Imóvel: ${imovelSelecionado.bloco} — ${imovelSelecionado.unidade}`}
        descricao="Atualização de parâmetros e ocupação da unidade"
      >
        <div className="mx-auto max-w-2xl space-y-6">
          <button 
            onClick={() => setEditando(false)}
            className="flex items-center gap-2 text-sm font-medium text-muted-foreground hover:text-foreground transition-colors"
          >
            <ArrowLeft className="h-4 w-4" />
            Cancelar e voltar
          </button>

          <Painel>
            <PainelCabecalho titulo="Configurações da Unidade" descricao="Dados estruturais e situação administrativa" />
            <div className="p-6 space-y-4">
              <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-2">
                  <Label htmlFor="edit-bloco">Bloco</Label>
                  <Input id="edit-bloco" defaultValue={imovelSelecionado.bloco} />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="edit-unidade">Unidade</Label>
                  <Input id="edit-unidade" defaultValue={imovelSelecionado.unidade} />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="edit-tipo">Tipo de Unidade</Label>
                  <Input id="edit-tipo" defaultValue={imovelSelecionado.tipo} />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="edit-vagas">Vagas Vinculadas</Label>
                  <Input id="edit-vagas" defaultValue="02" />
                </div>
              </div>

              <div className="flex gap-3 pt-6">
                <Button onClick={handleSalvarEdicao} className="flex-1 bg-brand-blue-600 hover:bg-brand-blue-700">
                  SALVAR ALTERAÇÕES
                </Button>
                <Button variant="outline" onClick={() => setEditando(false)} className="flex-1">
                  VOLTAR
                </Button>
              </div>
            </div>
          </Painel>
        </div>
      </AppShell>
    );
  }

  if (imovelSelecionado) {
    return (
      <AppShell 
        titulo={`${imovelSelecionado.bloco} — ${imovelSelecionado.unidade}`}
        descricao="Detalhes da unidade e gestão de ocupantes"
      >
        <div className="space-y-6">
          <button 
            onClick={() => setImovelSelecionado(null)}
            className="flex items-center gap-2 text-sm font-medium text-muted-foreground hover:text-foreground transition-colors"
          >
            <ArrowLeft className="h-4 w-4" />
            Voltar para lista
          </button>

          <div className="grid gap-6 lg:grid-cols-3">
            <div className="lg:col-span-2 space-y-6">
              <Painel>
                <PainelCabecalho 
                  titulo="Moradores Vinculados" 
                  descricao="Pessoas com acesso autorizado a esta unidade"
                />
                <div className="divide-y divide-border">
                  {[...Array(imovelSelecionado.ocupantes)].map((_, i) => (
                    <div key={i} className="flex items-center justify-between p-4 hover:bg-neutral-50 transition-colors">
                      <div className="flex items-center gap-4">
                        <div className="h-10 w-10 rounded-full bg-secondary flex items-center justify-center">
                          <User className="h-5 w-5 text-muted-foreground" />
                        </div>
                        <div>
                          <h4 className="text-sm font-bold text-foreground">
                            {i === 0 ? "RESPONSÁVEL DA UNIDADE" : `DEPENDENTE ${i}`}
                          </h4>
                          <div className="mt-0.5 flex items-center gap-3 text-xs text-muted-foreground">
                            <span className="flex items-center gap-1"><Phone className="h-3 w-3" /> (11) 9****-**{i}2</span>
                            <span className="flex items-center gap-1"><CalendarDays className="h-3 w-3" /> Desde Jan/2024</span>
                          </div>
                        </div>
                      </div>
                      <BadgeStatus tom="sucesso">ATIVO</BadgeStatus>
                    </div>
                  ))}
                  {imovelSelecionado.ocupantes === 0 && (
                    <div className="p-8 text-center text-muted-foreground text-sm italic">
                      Nenhum morador vinculado a esta unidade.
                    </div>
                  )}
                </div>
              </Painel>

              <Painel>
                <PainelCabecalho 
                  titulo="Histórico Recente" 
                  descricao="Últimas movimentações desta unidade"
                />
                <div className="divide-y divide-border">
                  {[1, 2, 3].map((i) => (
                    <div key={i} className="flex items-center justify-between p-4 text-sm">
                      <div className="flex items-center gap-3">
                        <History className="h-4 w-4 text-muted-foreground" />
                        <span>Entrada registrada via Portaria Principal</span>
                      </div>
                      <span className="text-xs text-muted-foreground">Há {i * 2} horas</span>
                    </div>
                  ))}
                </div>
              </Painel>
            </div>

            <div className="space-y-6">
              <Painel className="bg-brand-blue-600 text-white border-none">
                <div className="p-6 space-y-4">
                  <h3 className="text-lg font-bold">Resumo da Unidade</h3>
                  <div className="space-y-3">
                    <div className="flex justify-between text-sm">
                      <span className="opacity-80">Tipo:</span>
                      <span className="font-bold">{imovelSelecionado.tipo}</span>
                    </div>
                    <div className="flex justify-between text-sm">
                      <span className="opacity-80">Situação:</span>
                      <span className="font-bold uppercase">{imovelSelecionado.situacao}</span>
                    </div>
                    <div className="flex justify-between text-sm">
                      <span className="opacity-80">Vagas de Garagem:</span>
                      <span className="font-bold">02</span>
                    </div>
                  </div>
                  {imovelSelecionado.situacao === "Inadimplente" && (
                    <div className="mt-4 flex items-center gap-2 rounded-md bg-white/20 p-3 text-xs font-bold">
                      <ShieldAlert className="h-4 w-4" />
                      RESTRIÇÃO FINANCEIRA ATIVA
                    </div>
                  )}
                </div>
              </Painel>

              <div className="grid grid-cols-1 gap-3">
                <Button 
                  onClick={() => setEditando(true)}
                  className="w-full flex items-center justify-center gap-2 rounded-lg border border-border bg-card p-3 text-sm font-bold hover:bg-neutral-50 text-foreground"
                >
                  EDITAR DADOS
                </Button>
                <Button variant="ghost" className="w-full flex items-center justify-center gap-2 rounded-lg border border-border bg-card p-3 text-sm font-bold hover:bg-neutral-50 text-warning-foreground">
                  SUSPENDER ACESSOS
                </Button>
              </div>
            </div>
          </div>
        </div>
      </AppShell>
    );
  }

  return (
    <AppShell 
      titulo="Imóveis" 
      descricao="Consulta de unidades e ocupantes por bloco"
    >
      <div className="space-y-6">
        {/* Filtros e Busca */}
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="relative flex-1 max-w-md">
            <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <input
              type="text"
              placeholder="Buscar por unidade ou morador..."
              className="h-10 w-full rounded-md border border-border bg-card pl-10 pr-4 text-sm focus:border-brand-blue-500 focus:outline-none focus:ring-2 focus:ring-brand-blue-500/10"
              value={busca}
              onChange={(e) => setBusca(e.target.value)}
            />
          </div>
          <div className="flex items-center gap-2">
            <button className="flex items-center gap-2 rounded-md border border-border bg-card px-3 py-2 text-sm font-medium hover:bg-neutral-50">
              <Filter className="h-4 w-4" />
              FILTRAR
            </button>
            <button className="flex items-center gap-2 rounded-md bg-brand-blue-600 px-3 py-2 text-sm font-bold text-white hover:bg-brand-blue-700">
              <Plus className="h-4 w-4" />
              NOVO IMÓVEL
            </button>
          </div>
        </div>

        {/* Grade de Blocos / Unidades */}
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {IMOVEIS_MOCK.filter(i => i.unidade.includes(busca) || i.bloco.toLowerCase().includes(busca.toLowerCase())).map((imovel) => (
            <Painel 
              key={imovel.id} 
              className="group hover:border-brand-blue-200 transition-colors cursor-pointer"
              onClick={() => setImovelSelecionado(imovel)}
            >
              <div className="p-4">
                <div className="flex items-start justify-between">
                  <div className="flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-brand-blue-50 text-brand-blue-600">
                      <Home className="h-5 w-5" />
                    </div>
                    <div>
                      <h3 className="text-sm font-bold text-foreground">
                        {imovel.bloco} — {imovel.unidade}
                      </h3>
                      <p className="text-xs text-muted-foreground">{imovel.tipo}</p>
                    </div>
                  </div>
                  <BadgeStatus 
                    tom={
                      imovel.situacao === "Ocupado" ? "sucesso" : 
                      imovel.situacao === "Vazio" ? "neutro" : "negativo"
                    }
                  >
                    {imovel.situacao.toUpperCase()}
                  </BadgeStatus>
                </div>

                <div className="mt-4 grid grid-cols-2 gap-4 border-t border-border pt-4">
                  <div className="space-y-1">
                    <p className="text-[10px] font-bold text-muted-foreground uppercase">Ocupantes</p>
                    <div className="flex items-center gap-1.5 text-xs font-semibold">
                      <Users className="h-3.5 w-3.5 text-brand-blue-500" />
                      {imovel.ocupantes} pessoas
                    </div>
                  </div>
                  <div className="space-y-1 text-right">
                    <p className="text-[10px] font-bold text-muted-foreground uppercase">Localização</p>
                    <div className="flex items-center justify-end gap-1.5 text-xs font-semibold">
                      <MapPin className="h-3.5 w-3.5 text-brand-blue-500" />
                      Bloco {imovel.bloco}
                    </div>
                  </div>
                </div>

                <div className="mt-4 flex items-center justify-between rounded-md bg-neutral-50 p-2 group-hover:bg-brand-blue-50 transition-colors">
                  <div className="flex -space-x-2">
                    {[...Array(Math.min(imovel.ocupantes, 3))].map((_, i) => (
                      <div key={i} className="h-6 w-6 rounded-full border-2 border-white bg-neutral-200 flex items-center justify-center text-[10px] font-bold">
                        <User className="h-3 w-3" />
                      </div>
                    ))}
                    {imovel.ocupantes > 3 && (
                      <div className="h-6 w-6 rounded-full border-2 border-white bg-neutral-300 flex items-center justify-center text-[8px] font-bold">
                        +{imovel.ocupantes - 3}
                      </div>
                    )}
                  </div>
                  <ChevronRight className="h-4 w-4 text-muted-foreground" />
                </div>
              </div>
            </Painel>
          ))}
        </div>
      </div>
    </AppShell>
  );
}
