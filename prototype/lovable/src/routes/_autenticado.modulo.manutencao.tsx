import { createFileRoute } from "@tanstack/react-router";
import { 
  Wrench, 
  Calendar, 
  Clock, 
  AlertCircle, 
  CheckCircle2, 
  Plus,
  Filter,
  Search,
  MoreVertical
} from "lucide-react";
import { useState } from "react";

import { Painel, PainelCabecalho, BadgeStatus, Alerta } from "@/components/ds";
import { AppShell } from "@/components/shell/app-shell";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { toast } from "sonner";

export const Route = createFileRoute("/_autenticado/modulo/manutencao")({
  head: () => ({
    meta: [
      { title: "Gestão de Manutenção — SDV Access Santa Rita" },
      {
        name: "description",
        content: "Agendamentos de manutenção e serviços em áreas comuns do condomínio Santa Rita.",
      },
    ],
  }),
  component: ManutencaoModulo,
});

const MANUTENCOES_MOCK = [
  {
    id: "1",
    titulo: "Manutenção Elevador Bloco A",
    area: "Bloco A",
    tipo: "Preventiva",
    status: "Agendado",
    data: "2026-08-10",
    hora: "09:00",
    responsavel: "Atlas Elevadores"
  },
  {
    id: "2",
    titulo: "Limpeza da Piscina",
    area: "Lazer",
    tipo: "Rotina",
    status: "Em andamento",
    data: "2026-08-07",
    hora: "08:00",
    responsavel: "Acqua Limpa"
  },
  {
    id: "3",
    titulo: "Troca de Lâmpadas Hall",
    area: "Geral",
    tipo: "Corretiva",
    status: "Concluído",
    data: "2026-08-06",
    hora: "14:30",
    responsavel: "Equipe Interna"
  }
];

function ManutencaoModulo() {
  const [filtro, setFiltro] = useState("");

  const handleNovoAgendamento = () => {
    toast.info("Módulo de agendamento em desenvolvimento", {
      description: "A interface de criação de ordens de serviço será liberada na próxima atualização."
    });
  };

  return (
    <AppShell 
      titulo="Manutenção" 
      descricao="Gestão de serviços e áreas comuns"
    >
      <div className="space-y-6">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="relative flex-1 max-w-sm">
            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input 
              placeholder="Buscar manutenção..." 
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
            <Button onClick={handleNovoAgendamento} className="gap-2 bg-brand-blue-600 hover:bg-brand-blue-700">
              <Plus className="h-4 w-4" />
              NOVO AGENDAMENTO
            </Button>
          </div>
        </div>

        <div className="grid gap-4">
          {MANUTENCOES_MOCK.map((m) => (
            <Painel key={m.id} className="overflow-hidden">
              <div className="flex flex-col sm:flex-row sm:items-center">
                <div className="flex flex-1 items-center gap-4 p-4 sm:p-5">
                  <div className={cn(
                    "grid h-12 w-12 shrink-0 place-items-center rounded-full",
                    m.status === "Agendado" ? "bg-info-surface text-info-foreground" :
                    m.status === "Em andamento" ? "bg-warning-surface text-warning-foreground" :
                    "bg-success-surface text-success-foreground"
                  )}>
                    <Wrench className="h-6 w-6" />
                  </div>
                  <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2">
                      <h3 className="text-base font-bold text-foreground truncate">{m.titulo}</h3>
                      <BadgeStatus tom={
                        m.status === "Agendado" ? "informativo" :
                        m.status === "Em andamento" ? "atencao" :
                        "sucesso"
                      }>
                        {m.status}
                      </BadgeStatus>
                    </div>
                    <div className="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted-foreground">
                      <span className="flex items-center gap-1.5">
                        <Calendar className="h-3.5 w-3.5" />
                        {m.data.split("-").reverse().join("/")}
                      </span>
                      <span className="flex items-center gap-1.5">
                        <Clock className="h-3.5 w-3.5" />
                        {m.hora}
                      </span>
                      <span className="flex items-center gap-1.5">
                        <Wrench className="h-3.5 w-3.5" />
                        {m.tipo}
                      </span>
                    </div>
                  </div>
                </div>
                <div className="flex items-center justify-between border-t border-border bg-neutral-50/50 p-4 sm:w-64 sm:border-l sm:border-t-0 sm:justify-end sm:gap-4">
                  <div className="text-right sm:block">
                    <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Responsável</p>
                    <p className="text-sm font-medium text-foreground truncate">{m.responsavel}</p>
                  </div>
                  <Button variant="ghost" size="icon" className="h-8 w-8 text-muted-foreground">
                    <MoreVertical className="h-4 w-4" />
                  </Button>
                </div>
              </div>
            </Painel>
          ))}
        </div>

        <Alerta 
          severidade="info"
          titulo="Manutenções Preventivas"
          descricao="O sistema gera alertas automáticos para manutenções periódicas configuradas no Módulo de Administração."
        />
      </div>
    </AppShell>
  );
}

// Helper para classes condicionais (normalmente importado de @/lib/utils)
function cn(...classes: (string | boolean | undefined)[]) {
  return classes.filter(Boolean).join(" ");
}
