import { createFileRoute } from "@tanstack/react-router";
import { FileBarChart2, Download, Filter, Calendar } from "lucide-react";
import { useState } from "react";

import { Painel, PainelCabecalho, BadgeStatus, Alerta } from "@/components/ds";
import { AppShell } from "@/components/shell/app-shell";
import { Button } from "@/components/ui/button";

export const Route = createFileRoute("/_autenticado/modulo/relatorios")({
  head: () => ({
    meta: [
      { title: "Relatórios Operacionais — SDV Access Santa Rita" },
      {
        name: "description",
        content: "Relatórios de acesso, movimentações e auditoria do condomínio Santa Rita.",
      },
    ],
  }),
  component: RelatoriosModulo,
});

const RELATORIOS_DISPONIVEIS = [
  {
    id: "fluxo-acesso",
    titulo: "Fluxo de Acessos",
    descricao: "Consolidado de entradas e saídas por período e tipo de vínculo.",
    categoria: "Operacional",
  },
  {
    id: "visitantes-ativos",
    titulo: "Visitantes em Pátio",
    descricao: "Lista de pessoas que realizaram entrada mas ainda não registraram saída.",
    categoria: "Segurança",
  },
  {
    id: "auditoria-portaria",
    titulo: "Auditoria de Portaria",
    descricao: "Logs de ações dos operadores e horários de abertura de portões.",
    categoria: "Gestão",
  },
  {
    id: "ocorrencias",
    titulo: "Ocorrências e Bloqueios",
    descricao: "Registros de tentativas de acesso negadas e alertas gerados.",
    categoria: "Segurança",
  },
];

function RelatoriosModulo() {
  const [gerando, setGerando] = useState<string | null>(null);

  const handleExportar = (id: string) => {
    setGerando(id);
    // Simulação de geração de PDF
    setTimeout(() => {
      setGerando(null);
      alert("Relatório gerado com sucesso! O download do PDF começará em instantes.");
    }, 1500);
  };

  return (
    <AppShell 
      titulo="Relatórios" 
      descricao="Extração de dados e inteligência operacional"
    >
      <div className="space-y-6">
        <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
          <div className="flex items-center gap-2 overflow-x-auto pb-1 sm:pb-0">
            <Button variant="outline" size="sm" className="h-8 gap-1">
              <Filter className="h-3.5 w-3.5" />
              Filtrar
            </Button>
            <Button variant="outline" size="sm" className="h-8 gap-1">
              <Calendar className="h-3.5 w-3.5" />
              Últimos 30 dias
            </Button>
          </div>
        </div>

        <div className="grid gap-4 md:grid-cols-2">
          {RELATORIOS_DISPONIVEIS.map((rel) => (
            <Painel key={rel.id} className="flex flex-col">
              <div className="flex-1 p-5">
                <div className="mb-3 flex items-start justify-between">
                  <div className="grid h-10 w-10 place-items-center rounded-lg bg-brand-blue-50 text-brand-blue-600">
                    <FileBarChart2 className="h-5 w-5" />
                  </div>
                  <BadgeStatus tom="neutro">{rel.categoria}</BadgeStatus>
                </div>
                <h3 className="text-base font-bold text-foreground">{rel.titulo}</h3>
                <p className="mt-1 text-sm text-muted-foreground leading-relaxed">
                  {rel.descricao}
                </p>
              </div>
              <div className="border-t border-border bg-neutral-50/50 p-4">
                <Button 
                  onClick={() => handleExportar(rel.id)}
                  disabled={gerando === rel.id}
                  className="w-full gap-2 bg-brand-blue-600 hover:bg-brand-blue-700"
                >
                  {gerando === rel.id ? (
                    <>Gerando...</>
                  ) : (
                    <>
                      <Download className="h-4 w-4" />
                      EXPORTAR EM PDF
                    </>
                  )}
                </Button>
              </div>
            </Painel>
          ))}
        </div>

        <Alerta 
          severidade="info"
          titulo="Relatórios Programados"
          descricao="Você pode agendar o envio automático destes relatórios para seu e-mail semanalmente através das configurações de perfil."
        />
      </div>
    </AppShell>
  );
}
