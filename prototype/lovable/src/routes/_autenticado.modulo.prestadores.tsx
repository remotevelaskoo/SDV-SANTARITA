import { createFileRoute } from "@tanstack/react-router";
import { 
  UsersRound, 
  Search, 
  Filter, 
  Plus, 
  MoreVertical, 
  Building2, 
  Calendar,
  ShieldCheck
} from "lucide-react";
import { useState } from "react";

import { Painel, BadgeStatus, Alerta } from "@/components/ds";
import { AppShell } from "@/components/shell/app-shell";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { toast } from "sonner";
import { cn } from "@/lib/utils";

export const Route = createFileRoute("/_autenticado/modulo/prestadores")({
  head: () => ({
    meta: [
      { title: "Empresas e Prestadores — SDV Access Santa Rita" },
      {
        name: "description",
        content: "Gestão de empresas prestadoras de serviço e funcionários terceirizados.",
      },
    ],
  }),
  component: PrestadoresModulo,
});

const PRESTADORES_MOCK = [
  {
    id: "1",
    nome: "Limpa Mais Serviços LTDA",
    tipo: "Empresa",
    categoria: "Limpeza e Conservação",
    status: "Ativo",
    documento: "12.345.678/0001-90",
    contato: "(11) 98888-7777",
    vence_em: "2027-01-15",
    funcionarios_ativos: 8
  },
  {
    id: "2",
    nome: "João Silva Eletricista",
    tipo: "Autônomo",
    categoria: "Manutenção Elétrica",
    status: "Ativo",
    documento: "123.456.789-00",
    contato: "(11) 97777-6666",
    vence_em: "2026-12-20",
    funcionarios_ativos: 1
  },
  {
    id: "3",
    nome: "Segurança Total Vigilância",
    tipo: "Empresa",
    categoria: "Segurança Patrimonial",
    status: "Bloqueado",
    documento: "98.765.432/0001-10",
    contato: "(11) 96666-5555",
    vence_em: "2026-06-30",
    funcionarios_ativos: 12
  }
];

function PrestadoresModulo() {
  const [filtro, setFiltro] = useState("");

  const handleNovoPrestador = () => {
    toast.info("Módulo de cadastro em desenvolvimento", {
      description: "A interface para novos prestadores será liberada na próxima fase."
    });
  };

  return (
    <AppShell 
      titulo="Empresas e Prestadores" 
      descricao="Gestão de acesso de serviços e terceirizados"
    >
      <div className="space-y-6">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="relative flex-1 max-w-sm">
            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input 
              placeholder="Buscar empresa ou prestador..." 
              className="pl-9"
              value={filtro}
              onChange={(e) => setFiltro(e.target.value)}
            />
          </div>
          <div className="flex items-center gap-2">
            <Button variant="outline" className="gap-2">
              <Filter className="h-4 w-4" />
              FILTRAR
            </Button>
            <Button onClick={handleNovoPrestador} className="gap-2 bg-brand-blue-600 hover:bg-brand-blue-700 text-white">
              <Plus className="h-4 w-4" />
              NOVA EMPRESA
            </Button>
          </div>
        </div>

        <div className="grid gap-4">
          {PRESTADORES_MOCK.map((p) => (
            <Painel key={p.id} className="overflow-hidden">
              <div className="flex flex-col sm:flex-row sm:items-center">
                <div className="flex flex-1 items-center gap-4 p-4 sm:p-5">
                  <div className={cn(
                    "grid h-12 w-12 shrink-0 place-items-center rounded-lg bg-neutral-100 text-neutral-600",
                    p.status === "Bloqueado" && "bg-error-surface text-error-foreground"
                  )}>
                    <UsersRound className="h-6 w-6" />
                  </div>
                  <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2">
                      <h3 className="text-base font-bold text-foreground truncate">{p.nome}</h3>
                      <BadgeStatus tom={p.status === "Ativo" ? "sucesso" : "negativo"}>
                        {p.status}
                      </BadgeStatus>
                    </div>
                    <div className="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted-foreground">
                      <span className="flex items-center gap-1.5">
                        <Building2 className="h-3.5 w-3.5" />
                        {p.categoria}
                      </span>
                      <span className="flex items-center gap-1.5">
                        <ShieldCheck className="h-3.5 w-3.5" />
                        {p.documento}
                      </span>
                      <span className="flex items-center gap-1.5">
                        <Calendar className="h-3.5 w-3.5" />
                        Vence: {p.vence_em.split("-").reverse().join("/")}
                      </span>
                    </div>
                  </div>
                </div>
                <div className="flex items-center justify-between border-t border-border bg-neutral-50/50 p-4 sm:w-72 sm:border-l sm:border-t-0 sm:justify-end sm:gap-6">
                  <div className="text-right sm:block">
                    <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Funcionários</p>
                    <p className="text-sm font-bold text-foreground">{p.funcionarios_ativos} ativos</p>
                  </div>
                  <div className="flex items-center gap-2">
                    <Button variant="outline" size="sm" className="h-8">DETALHES</Button>
                    <Button variant="ghost" size="icon" className="h-8 w-8 text-muted-foreground">
                      <MoreVertical className="h-4 w-4" />
                    </Button>
                  </div>
                </div>
              </div>
            </Painel>
          ))}
        </div>

        {PRESTADORES_MOCK.some(p => p.status === "Bloqueado") && (
          <Alerta 
            severidade="danger"
            titulo="Restrições de Acesso"
            descricao="Existem empresas com acesso bloqueado. Verifique os motivos na auditoria antes de liberar a entrada de funcionários."
          />
        )}
      </div>
    </AppShell>
  );
}
