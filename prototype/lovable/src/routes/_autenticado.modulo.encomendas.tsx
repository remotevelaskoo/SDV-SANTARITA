import { createFileRoute } from "@tanstack/react-router";
import { Package, Plus, Search, MapPin, User, Clock, CheckCircle2 } from "lucide-react";
import { useState } from "react";

import { Painel, PainelCabecalho, BadgeStatus, EstadoVazio } from "@/components/ds";
import { AppShell } from "@/components/shell/app-shell";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";

export const Route = createFileRoute("/_autenticado/modulo/encomendas")({
  head: () => ({
    meta: [
      { title: "Gestão de Encomendas — SDV Access Santa Rita" },
      {
        name: "description",
        content: "Recebimento e entrega de encomendas na portaria do condomínio Santa Rita.",
      },
    ],
  }),
  component: EncomendasModulo,
});

const ENCOMENDAS_MOCK = [
  {
    id: "enc-1",
    destinatario: "MARCOS SILVEIRA",
    local: "Bloco A — Apto 102",
    transportadora: "Mercado Livre",
    chegada: "10:15",
    status: "Pendente",
  },
  {
    id: "enc-2",
    destinatario: "JULIANA PAIVA",
    local: "Bloco B — Apto 304",
    transportadora: "Amazon",
    chegada: "09:30",
    status: "Entregue",
  },
  {
    id: "enc-3",
    destinatario: "ROBERTO ALVES",
    local: "Bloco C — Apto 221",
    transportadora: "Correios",
    chegada: "14:20",
    status: "Pendente",
  },
];

function EncomendasModulo() {
  const [busca, setBusca] = useState("");
  const [lista, setLista] = useState(ENCOMENDAS_MOCK);

  const handleEntregar = (id: string) => {
    setLista(lista.map(item => item.id === id ? { ...item, status: "Entregue" } : item));
  };

  const filtradas = lista.filter(item => 
    item.destinatario.toLowerCase().includes(busca.toLowerCase()) || 
    item.local.toLowerCase().includes(busca.toLowerCase())
  );

  return (
    <AppShell 
      titulo="Gestão de Encomendas" 
      descricao="Recebimento e baixa de pacotes na portaria"
    >
      <div className="space-y-6">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="relative max-w-md flex-1">
            <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input 
              placeholder="Buscar destinatário ou unidade..." 
              className="pl-9"
              value={busca}
              onChange={(e) => setBusca(e.target.value)}
            />
          </div>
          <Button className="gap-2 bg-brand-blue-600 hover:bg-brand-blue-700">
            <Plus className="h-4 w-4" />
            NOVO RECEBIMENTO
          </Button>
        </div>

        <Painel>
          <PainelCabecalho 
            titulo="Pacotes aguardando retirada" 
            descricao={`${filtradas.filter(e => e.status === 'Pendente').length} encomendas pendentes no sistema`}
          />
          
          {filtradas.length > 0 ? (
            <div className="divide-y divide-border">
              {filtradas.map((enc) => (
                <div key={enc.id} className="flex flex-col items-start justify-between gap-4 p-4 transition-colors hover:bg-neutral-50 sm:flex-row sm:items-center">
                  <div className="flex items-center gap-4">
                    <div className={cn(
                      "flex h-12 w-12 shrink-0 items-center justify-center rounded-lg",
                      enc.status === "Pendente" ? "bg-warning-surface text-warning-foreground" : "bg-success-surface text-success-foreground"
                    )}>
                      <Package className="h-6 w-6" />
                    </div>
                    <div className="min-w-0">
                      <h4 className="font-bold text-foreground">{enc.destinatario}</h4>
                      <div className="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                        <span className="flex items-center gap-1">
                          <MapPin className="h-3 w-3" />
                          {enc.local}
                        </span>
                        <span className="flex items-center gap-1">
                          <User className="h-3 w-3" />
                          {enc.transportadora}
                        </span>
                        <span className="flex items-center gap-1">
                          <Clock className="h-3 w-3" />
                          Chegada: {enc.chegada}
                        </span>
                      </div>
                    </div>
                  </div>
                  
                  <div className="flex w-full shrink-0 items-center gap-3 sm:w-auto">
                    {enc.status === "Pendente" ? (
                      <>
                        <BadgeStatus tom="atencao">PENDENTE</BadgeStatus>
                        <Button 
                          size="sm" 
                          onClick={() => handleEntregar(enc.id)}
                          className="flex-1 gap-1.5 sm:flex-none"
                        >
                          <CheckCircle2 className="h-4 w-4" />
                          DAR BAIXA
                        </Button>
                      </>
                    ) : (
                      <BadgeStatus tom="sucesso" icone={CheckCircle2}>ENTREGUE</BadgeStatus>
                    )}
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <EstadoVazio 
              icone={Package}
              titulo="Nenhuma encomenda encontrada"
              descricao="Não há pacotes que correspondam aos critérios de busca no momento."
            />
          )}
        </Painel>
      </div>
    </AppShell>
  );
}

function cn(...inputs: any[]) {
  return inputs.filter(Boolean).join(" ");
}
