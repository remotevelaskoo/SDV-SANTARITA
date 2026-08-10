import { createFileRoute, useNavigate } from "@tanstack/react-router";
import { useState, useEffect } from "react";
import { Clock3, Video, Play, Power, AlertTriangle, Bell, Package, ChevronRight } from "lucide-react";
import { toast } from "sonner";

import { CartaoIndicador } from "@/components/dashboard/cartao-indicador";
import { GraficoEntradasSaidas } from "@/components/dashboard/grafico-entradas-saidas";
import { ListaAcessosRecentes } from "@/components/dashboard/lista-acessos-recentes";
import { BadgeStatus, Alerta, EstadoVazio, Painel, PainelCabecalho } from "@/components/ds";
import { AppShell } from "@/components/shell/app-shell";
import { obterHistoricoAcessos, ALERTAS_CRITICOS, INDICADORES } from "@/data/dashboard";
import { useSessao } from "@/lib/session-context";

export const Route = createFileRoute("/_autenticado/painel")({
  head: () => ({
    meta: [
      { title: "Dashboard operacional — SDV Access Santa Rita" },
      {
        name: "description",
        content:
          "Indicadores de acesso, movimentações recentes e alertas críticos do controle de acesso do condomínio Santa Rita.",
      },
      { property: "og:title", content: "Dashboard operacional — SDV Access Santa Rita" },
      {
        property: "og:description",
        content: "Indicadores de acesso, movimentações e alertas do SDV Access Santa Rita.",
      },
    ],
  }),
  component: Painel_,
});

function Painel_() {
  const { sessao, perfil, pode } = useSessao();
  const navegar = useNavigate();
  const indicadores = INDICADORES.filter((indicador) => pode(indicador.permissao));
  const primeiroNome = sessao?.nome.split(" ")[0] ?? "";

  // Simulação de Notificação em Tempo Real (Toast)
  useEffect(() => {
    const timer = setTimeout(() => {
      toast.info("Nova encomenda recebida", {
        description: "Transportadora Loggi acaba de entregar um pacote para Bloco B — Apto 304.",
        icon: <Package className="h-5 w-5 text-brand-blue-600" />,
        duration: 8000,
        action: {
          label: "VER MÓDULO",
          onClick: () => navegar({ to: "/modulo/encomendas" })
        }
      });
    }, 5000);

    return () => clearTimeout(timer);
  }, [navegar]);

  return (
    <AppShell
      titulo="Dashboard operacional"
      descricao={`${perfil?.nome ?? ""} · ${sessao?.pontoAcesso ?? ""}`}
    >
      <div className="space-y-5">
        <div>
          <h2 className="text-lg font-semibold text-foreground">Olá, {primeiroNome}</h2>
          <p className="text-sm text-muted-foreground">
            Situação atual do condomínio {sessao?.condominio}.
          </p>
        </div>

        {ALERTAS_CRITICOS.length > 0 ? (
          <div className="space-y-2">
            {ALERTAS_CRITICOS.map((alerta) => (
              <Alerta
                key={alerta.id}
                severidade={alerta.severidade}
                titulo={alerta.titulo}
                descricao={alerta.descricao}
              />
            ))}
          </div>
        ) : null}

        <section aria-label="Indicadores">
          {indicadores.length > 0 ? (
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
              {indicadores.map((indicador) => (
                <CartaoIndicador
                  key={indicador.id}
                  indicador={indicador}
                  navegavel={Boolean(indicador.destino)}
                />
              ))}
            </div>
          ) : (
            <Painel>
              <EstadoVazio
                icone={Clock3}
                titulo="Sem indicadores disponíveis"
                descricao="Seu perfil não possui permissão para visualizar métricas do dashboard."
              />
            </Painel>
          )}
        </section>

        <div className="grid gap-4 xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
          {pode("dashboard.grafico") ? (
            <Painel>
              <GraficoEntradasSaidas />
            </Painel>
          ) : null}

          {pode("dashboard.acessos_recentes") ? (
            <Painel className="min-w-0 overflow-hidden">
              <PainelCabecalho
                titulo="Acessos recentes"
                descricao="Últimas movimentações validadas"
              />
              <ListaAcessosRecentes
                acessos={obterHistoricoAcessos()}
                documentoCompleto={pode("dashboard.documento.completo")}
              />
            </Painel>
          ) : null}
        </div>

        {pode("dashboard.visualizar") && (
          <Painel>
            <PainelCabecalho 
              titulo="Monitoramento de Câmeras" 
              descricao="Visualização em tempo real dos pontos críticos de acesso"
              acoes={
                <BadgeStatus tom="sucesso" className="animate-pulse">AO VIVO</BadgeStatus>
              }
            />
            <div className="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-4">
              <CameraCard titulo="Portaria Principal" id="cam-01" />
              <CameraCard titulo="Garagem Subsolo" id="cam-02" />
              <CameraCard titulo="Pátio de Carga" id="cam-03" />
              <CameraCard titulo="Área de Lazer" id="cam-04" />
            </div>
          </Painel>
        )}
      </div>
    </AppShell>
  );
}

function CameraCard({ titulo, id }: { titulo: string, id: string }) {
  const [ligada, setLigada] = useState(true);

  return (
    <div className="group relative aspect-video overflow-hidden rounded-lg bg-neutral-900 shadow-inner">
      {ligada ? (
        <div className="relative h-full w-full">
          {/* Overlay Simulado de Câmera */}
          <div className="absolute top-2 left-2 z-10 flex items-center gap-1.5 rounded bg-black/50 px-1.5 py-0.5 text-[10px] font-mono text-white">
            <div className="h-1.5 w-1.5 rounded-full bg-danger animate-pulse" />
            REC {id.toUpperCase()}
          </div>
          
          {/* Fundo Simulado (Placeholder visual) */}
          <div className="flex h-full w-full items-center justify-center bg-brand-navy-900/40">
            <Video className="h-8 w-8 text-white/20" />
            <div className="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent opacity-0 transition-opacity group-hover:opacity-100" />
          </div>

          <div className="absolute bottom-2 left-2 z-10 text-[10px] font-bold text-white uppercase tracking-wider">
            {titulo}
          </div>
        </div>
      ) : (
        <div className="flex h-full w-full flex-col items-center justify-center gap-2 text-neutral-500">
          <AlertTriangle className="h-5 w-5" />
          <span className="text-[10px] font-bold">SEM SINAL</span>
        </div>
      )}
      
      <button 
        onClick={() => setLigada(!ligada)}
        className="absolute top-2 right-2 z-20 rounded bg-white/10 p-1 text-white hover:bg-white/20 transition-colors"
      >
        <Power className="h-3 w-3" />
      </button>
    </div>
  );
}
