import { createFileRoute } from "@tanstack/react-router";
import { 
  ScrollText, 
  Search, 
  Filter, 
  Download, 
  History,
  ShieldCheck,
  User,
  AlertCircle,
  FileCode
} from "lucide-react";
import { useState } from "react";

import { Painel, PainelCabecalho, BadgeStatus, EstadoVazio } from "@/components/ds";
import { AppShell } from "@/components/shell/app-shell";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { cn } from "@/lib/utils";

export const Route = createFileRoute("/_autenticado/modulo/logs")({
  head: () => ({
    meta: [
      { title: "Logs e Auditoria — SDV Access Santa Rita" },
      {
        name: "description",
        content: "Histórico detalhado de ações, eventos e auditoria do sistema.",
      },
    ],
  }),
  component: LogsModulo,
});

const LOGS_MOCK = [
  {
    id: "log-1",
    data: "07/08/2026 14:22",
    usuario: "Marcos Ribeiro (Portaria)",
    acao: "Liberação de Acesso",
    detalhe: "Entrada liberada para RICARDO VELASKO (Morador)",
    tipo: "operacional",
    ip: "192.168.1.45"
  },
  {
    id: "log-2",
    data: "07/08/2026 13:50",
    usuario: "Vinicius Velasco (Admin)",
    acao: "Alteração de Parâmetro",
    detalhe: "OCR Automático habilitado nas configurações globais",
    tipo: "sistema",
    ip: "187.54.12.102"
  },
  {
    id: "log-3",
    data: "07/08/2026 13:15",
    usuario: "Tatiane Souza (Caixa)",
    acao: "Registro Financeiro",
    detalhe: "Taxa de Estacionamento R$ 25,00 (CARLOS MENDES)",
    tipo: "financeiro",
    ip: "192.168.1.48"
  },
  {
    id: "log-4",
    data: "07/08/2026 12:05",
    usuario: "Sistema (Automático)",
    acao: "Backup Concluído",
    detalhe: "Cópia de segurança diária realizada com sucesso",
    tipo: "sistema",
    ip: "localhost"
  },
  {
    id: "log-5",
    data: "07/08/2026 11:30",
    usuario: "Marcos Ribeiro (Portaria)",
    acao: "Bloqueio de Tentativa",
    detalhe: "Acesso negado para MARIA OLIVEIRA (Restrição Financeira)",
    tipo: "seguranca",
    ip: "192.168.1.45"
  }
];

function LogsModulo() {
  const [busca, setBusca] = useState("");
  const [tipoFiltro, setTipoFiltro] = useState<string | null>(null);

  const filtrados = LOGS_MOCK.filter(log => {
    const bateBusca = log.usuario.toLowerCase().includes(busca.toLowerCase()) || 
                     log.detalhe.toLowerCase().includes(busca.toLowerCase()) ||
                     log.acao.toLowerCase().includes(busca.toLowerCase());
    const bateFiltro = tipoFiltro ? log.tipo === tipoFiltro : true;
    return bateBusca && bateFiltro;
  });

  return (
    <AppShell 
      titulo="Logs e Auditoria" 
      descricao="Rastreamento completo de eventos e ações do sistema"
    >
      <div className="space-y-6">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="relative max-w-md flex-1">
            <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input 
              placeholder="Buscar por usuário, ação ou detalhe..." 
              className="pl-9"
              value={busca}
              onChange={(e) => setBusca(e.target.value)}
            />
          </div>
          <div className="flex items-center gap-2">
            <Button variant="outline" className="gap-2">
              <Filter className="h-4 w-4" />
              FILTRAR
            </Button>
            <Button variant="outline" className="gap-2">
              <Download className="h-4 w-4" />
              EXPORTAR CSV
            </Button>
          </div>
        </div>

        <div className="flex items-center gap-2 overflow-x-auto pb-1">
          {["operacional", "sistema", "financeiro", "seguranca"].map(tipo => (
            <button
              key={tipo}
              onClick={() => setTipoFiltro(tipoFiltro === tipo ? null : tipo)}
              className={cn(
                "rounded-full px-4 py-1.5 text-xs font-bold transition-all border",
                tipoFiltro === tipo 
                  ? "bg-brand-navy-900 text-white border-brand-navy-900" 
                  : "bg-card text-muted-foreground border-border hover:bg-neutral-50"
              )}
            >
              {tipo.toUpperCase()}
            </button>
          ))}
        </div>

        <Painel>
          <PainelCabecalho 
            titulo="Eventos do Sistema" 
            descricao={`${filtrados.length} registros cronológicos encontrados`}
          />
          
          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse">
              <thead>
                <tr className="border-b border-border bg-neutral-50/50">
                  <th className="px-5 py-3 text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Data/Hora</th>
                  <th className="px-5 py-3 text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Usuário</th>
                  <th className="px-5 py-3 text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Ação</th>
                  <th className="px-5 py-3 text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Detalhes</th>
                  <th className="px-5 py-3 text-[10px] font-bold text-muted-foreground uppercase tracking-wider text-right">Ações</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {filtrados.map((log) => (
                  <tr key={log.id} className="hover:bg-neutral-50 transition-colors group">
                    <td className="px-5 py-4 whitespace-nowrap text-xs font-mono text-muted-foreground">
                      {log.data}
                    </td>
                    <td className="px-5 py-4">
                      <div className="flex items-center gap-2">
                        <User className="h-3.5 w-3.5 text-muted-foreground" />
                        <span className="text-sm font-medium text-foreground">{log.usuario}</span>
                      </div>
                      <span className="text-[10px] text-muted-foreground block mt-0.5">IP: {log.ip}</span>
                    </td>
                    <td className="px-5 py-4">
                      <div className="flex items-center gap-2">
                        <BadgeStatus tom={
                          log.tipo === 'sistema' ? 'informativo' : 
                          log.tipo === 'seguranca' ? 'negativo' : 
                          log.tipo === 'financeiro' ? 'atencao' : 'neutro'
                        } className="text-[9px]">
                          {log.acao}
                        </BadgeStatus>
                      </div>
                    </td>
                    <td className="px-5 py-4 max-w-xs">
                      <p className="text-sm text-foreground truncate" title={log.detalhe}>
                        {log.detalhe}
                      </p>
                    </td>
                    <td className="px-5 py-4 text-right">
                      <Button variant="ghost" size="icon" className="h-8 w-8 text-muted-foreground hover:text-brand-blue-600">
                        <FileCode className="h-4 w-4" />
                      </Button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {filtrados.length === 0 && (
            <EstadoVazio 
              icone={ScrollText}
              titulo="Nenhum log encontrado"
              descricao="Ajuste os filtros ou a busca para visualizar outros eventos."
            />
          )}
        </Painel>
      </div>
    </AppShell>
  );
}