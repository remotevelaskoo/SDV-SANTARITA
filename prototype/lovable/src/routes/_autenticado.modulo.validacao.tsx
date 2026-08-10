import { createFileRoute, useNavigate } from "@tanstack/react-router";
import { 
  ShieldCheck, 
  Search, 
  User, 
  Building2, 
  Car, 
  AlertCircle,
  QrCode,
  ScanLine,
  ArrowRight,
  ChevronLeft,
  CheckCircle2,
  Clock,
  MapPin,
  Calendar,
  XCircle,
  DoorOpen,
  Plus,
  UserCheck
} from "lucide-react";
import { useState } from "react";
import { useServerFn } from "@tanstack/react-start";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";

import { Painel, PainelCabecalho, BadgeStatus, Alerta } from "@/components/ds";
import { AppShell } from "@/components/shell/app-shell";
import { cn } from "@/lib/utils";
import { registrarAcessoLocal } from "@/data/dashboard";
import { useSessao } from "@/lib/session-context";
import { validarBiometriaFacial } from "@/lib/biometria.functions";

export const Route = createFileRoute("/_autenticado/modulo/validacao")({
  head: () => ({
    meta: [
      { title: "Validação de Entrada — SDV Access Santa Rita" },
      {
        name: "description",
        content: "Fluxo de validação e liberação de acesso para moradores, visitantes e prestadores.",
      },
    ],
  }),
  component: ValidacaoEntrada,
});

function ValidacaoEntrada() {
  const [busca, setBusca] = useState("");
  const [tipoFluxo, setTipoFluxo] = useState<"entrada" | "saida">("entrada");
  const [etapa, setEtapa] = useState<"busca" | "detalhes">("busca");
  const [selecionado, setSelecionado] = useState<any>(null);
  const [analisandoBio, setAnalisandoBio] = useState(false);
  
  const { sessao } = useSessao();
  const navegar = useNavigate();
  const fnValidarBio = useServerFn(validarBiometriaFacial);

  const handleConfirmarAcesso = () => {
    if (!selecionado) return;
    
    registrarAcessoLocal({
      horario: new Date().toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" }),
      nome: selecionado.nome,
      documento: selecionado.documento,
      vinculo: selecionado.tipo,
      imovel: selecionado.local,
      pontoAcesso: sessao?.pontoAcesso || "Portaria Principal",
      tipo: tipoFluxo,
      resultado: "liberado"
    });

    void navegar({ to: "/painel" });
  };

  const simularBiometria = async () => {
    if (!selecionado) return;
    setAnalisandoBio(true);
    
    try {
      const response = await fnValidarBio({
        data: {
          imagemBase64: "dummy-data",
          pessoaId: "pessoa-123", // Mock ID
          tipo: "visitante"
        }
      });

      if (response.sucesso) {
        toast.success("Biometria Facial Confirmada", {
          description: `Score de confiança: ${(response.score * 100).toFixed(1)}%`
        });
      } else {
        toast.error("Falha na Biometria Facial", {
          description: "Divergência detectada entre a foto do cadastro e o sensor."
        });
      }
    } catch (error) {
      toast.error("Erro no processamento biométrico");
    } finally {
      setAnalisandoBio(false);
    }
  };

  if (etapa === "detalhes" && selecionado) {
    return (
      <AppShell 
        titulo="Confirmar acesso" 
        descricao={tipoFluxo === "entrada" ? "Verifique as informações antes da liberação" : "Confirme a saída do veículo/pedestre"}
      >
        <div className="mx-auto max-w-2xl space-y-6">
          <button 
            onClick={() => setEtapa("busca")}
            className="flex items-center gap-2 text-sm font-medium text-muted-foreground hover:text-foreground transition-colors"
          >
            <ChevronLeft className="h-4 w-4" />
            Voltar para busca
          </button>

          <Painel className="overflow-hidden">
            <div className={cn(
              "px-6 py-8 text-white transition-colors",
              tipoFluxo === "entrada" ? "bg-brand-blue-600" : "bg-orange-600"
            )}>
              <div className="flex items-center gap-6">
                <div className="h-24 w-24 rounded-full border-4 border-white/20 bg-white/10 p-1">
                  <div className={cn(
                    "flex h-full w-full items-center justify-center rounded-full bg-white",
                    tipoFluxo === "entrada" ? "text-brand-blue-600" : "text-orange-600"
                  )}>
                    <User className="h-12 w-12" />
                  </div>
                </div>
                <div>
                  <h2 className="text-2xl font-bold">{selecionado.nome}</h2>
                  <div className="mt-2 flex flex-wrap gap-2">
                    <BadgeStatus className="bg-white/20 text-white border-none">{selecionado.tipo}</BadgeStatus>
                    <BadgeStatus className="bg-white/20 text-white border-none">DOCUMENTO: {selecionado.documento}</BadgeStatus>
                  </div>
                </div>
              </div>
            </div>

            <div className="p-6 space-y-6">
              <div className="grid gap-6 sm:grid-cols-2">
                <div className="space-y-1">
                  <p className="text-xs font-bold text-muted-foreground uppercase tracking-wider">Destino</p>
                  <div className="flex items-center gap-2 text-foreground">
                    <Building2 className="h-4 w-4 text-brand-blue-500" />
                    <span className="font-semibold">{selecionado.local}</span>
                  </div>
                </div>
                <div className="space-y-1">
                  <p className="text-xs font-bold text-muted-foreground uppercase tracking-wider">{tipoFluxo === "entrada" ? "Ponto de Entrada" : "Ponto de Saída"}</p>
                  <div className="flex items-center gap-2 text-foreground">
                    <MapPin className="h-4 w-4 text-brand-blue-500" />
                    <span className="font-semibold">{sessao?.pontoAcesso}</span>
                  </div>
                </div>
                <div className="space-y-1">
                  <p className="text-xs font-bold text-muted-foreground uppercase tracking-wider">Data e Hora</p>
                  <div className="flex items-center gap-2 text-foreground">
                    <Clock className="h-4 w-4 text-brand-blue-500" />
                    <span className="font-semibold">{new Date().toLocaleDateString("pt-BR")} — {new Date().toLocaleTimeString("pt-BR", { hour: '2-digit', minute: '2-digit' })}</span>
                  </div>
                </div>
                <div className="space-y-1">
                  <p className="text-xs font-bold text-muted-foreground uppercase tracking-wider">Status do Cadastro</p>
                  <div className="flex items-center gap-2 text-success-foreground font-semibold">
                    <CheckCircle2 className="h-4 w-4" />
                    Ativo e Regular
                  </div>
                </div>
              </div>

              <Alerta 
                severidade="info"
                titulo={tipoFluxo === "entrada" ? "Liberação de Acesso" : "Registro de Saída"}
                descricao={tipoFluxo === "entrada" 
                  ? "Esta ação abrirá o portão eletrônico e registrará a entrada permanentemente."
                  : "Esta ação registrará a baixa no sistema e liberará a vaga ocupada."
                }
              />

              <div className="flex flex-col gap-3">
                <div className="flex flex-col gap-3 sm:flex-row">
                  <button 
                    onClick={handleConfirmarAcesso}
                    className={cn(
                      "flex flex-1 items-center justify-center gap-2 rounded-lg px-6 py-4 text-base font-bold text-white transition-all shadow-nivel-2 active:scale-[0.98]",
                      tipoFluxo === "entrada" ? "bg-brand-blue-600 hover:bg-brand-blue-700" : "bg-orange-600 hover:bg-orange-700"
                    )}
                  >
                    <CheckCircle2 className="h-5 w-5" />
                    {tipoFluxo === "entrada" ? "CONFIRMAR E LIBERAR" : "CONFIRMAR SAÍDA"}
                  </button>
                  <button 
                    onClick={() => setEtapa("busca")}
                    className="flex items-center justify-center gap-2 rounded-lg border border-border bg-card px-6 py-4 text-base font-bold text-muted-foreground transition-all hover:bg-neutral-50"
                  >
                    <XCircle className="h-5 w-5" />
                    CANCELAR
                  </button>
                </div>
                
                {tipoFluxo === "entrada" && (
                  <Button 
                    variant="outline"
                    className="w-full gap-2 border-brand-blue-200 text-brand-blue-700 hover:bg-brand-blue-50 py-6"
                    onClick={simularBiometria}
                    disabled={analisandoBio}
                  >
                    {analisandoBio ? (
                      <span className="flex items-center gap-2">
                        <span className="h-4 w-4 animate-spin rounded-full border-2 border-brand-blue-600/30 border-t-brand-blue-600" />
                        PROCESSANDO BIOMETRIA...
                      </span>
                    ) : (
                      <>
                        <UserCheck className="h-5 w-5" />
                        VALIDAR BIOMETRIA FACIAL
                      </>
                    )}
                  </Button>
                )}
              </div>
            </div>
          </Painel>
        </div>
      </AppShell>
    );
  }

  return (
    <AppShell 
      titulo={tipoFluxo === "entrada" ? "Validação de entrada" : "Controle de saída"}
      descricao={tipoFluxo === "entrada" ? "Identificação e liberação de acesso em tempo real" : "Baixa de veículos e registro de saída de visitantes"}
    >
      <div className="mx-auto max-w-4xl space-y-6">
        {/* Seletor de Fluxo */}
        <div className="flex justify-center">
          <div className="inline-flex rounded-lg border border-border bg-card p-1 shadow-sm">
            <button
              onClick={() => { setTipoFluxo("entrada"); setBusca(""); }}
              className={cn(
                "flex items-center gap-2 rounded-md px-4 py-2 text-sm font-bold transition-all",
                tipoFluxo === "entrada" ? "bg-brand-blue-600 text-white" : "text-muted-foreground hover:bg-neutral-50"
              )}
            >
              <ShieldCheck className="h-4 w-4" />
              ENTRADA
            </button>
            <button
              onClick={() => { setTipoFluxo("saida"); setBusca(""); }}
              className={cn(
                "flex items-center gap-2 rounded-md px-4 py-2 text-sm font-bold transition-all",
                tipoFluxo === "saida" ? "bg-orange-600 text-white" : "text-muted-foreground hover:bg-neutral-50"
              )}
            >
              <DoorOpen className="h-4 w-4" />
              SAÍDA
            </button>
          </div>
        </div>

        {/* Barra de Busca Principal */}
        <section aria-label="Busca de identificação">
          <div className="relative">
            <Search className="absolute top-1/2 left-4 h-5 w-5 -translate-y-1/2 text-muted-foreground" />
            <input
              type="text"
              placeholder={tipoFluxo === "entrada" ? "CPF, placa, nome ou código de pré-cadastro..." : "Digite a placa ou nome para dar baixa na saída..."}
              className={cn(
                "h-14 w-full rounded-xl border-2 border-border bg-card px-12 text-lg font-medium transition-all shadow-nivel-1",
                "focus:border-brand-blue-500 focus:ring-4 focus:ring-brand-blue-500/10 focus:outline-none"
              )}
              value={busca}
              onChange={(e) => setBusca(e.target.value)}
              autoFocus
            />
            <div className="absolute top-1/2 right-4 flex -translate-y-1/2 items-center gap-2">
              <button 
                type="button"
                className={cn(
                  "flex items-center gap-2 rounded-md px-3 py-1.5 text-xs font-bold transition-colors",
                  tipoFluxo === "entrada" ? "bg-secondary text-foreground hover:bg-neutral-200" : "bg-orange-50 text-orange-700 hover:bg-orange-100"
                )}
              >
                {tipoFluxo === "entrada" ? (
                  <>
                    <ScanLine className="h-4 w-4" />
                    LER QR CODE
                  </>
                ) : (
                  <>
                    <Car className="h-4 w-4" />
                    OCR PLACA
                  </>
                )}
              </button>
            </div>
          </div>
          <p className="mt-3 text-center text-xs text-muted-foreground">
            Dica: Digite pelo menos 3 caracteres para iniciar a busca automática.
          </p>
        </section>

        {/* Atalhos de Ação Rápida */}
        <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
          <AtalhoRapido icone={User} rotulo="Morador" cor="bg-blue-50 text-blue-600" />
          <AtalhoRapido icone={Building2} rotulo="Visitante" cor="bg-orange-50 text-orange-600" />
          <AtalhoRapido icone={Car} rotulo="Veículo" cor="bg-emerald-50 text-emerald-600" />
          <AtalhoRapido icone={QrCode} rotulo="Convite" cor="bg-purple-50 text-purple-600" />
        </div>

        {/* Alerta de Exemplo: Bloqueio ou Aviso */}
        {busca.toLowerCase().includes("bloqueado") && (
          <Alerta 
            severidade="danger"
            titulo="Acesso Bloqueado — Inadimplência ou Restrição Administrativa"
            descricao="O morador/imóvel possui restrições ativas. Encaminhe para a administração."
          />
        )}

        {/* Resultados Sugeridos (Mock) */}
        {busca.length >= 3 && (
          <Painel>
            <PainelCabecalho 
              titulo="Resultados encontrados" 
              descricao={tipoFluxo === "entrada" ? `Buscando por "${busca}"` : `Veículos/Pessoas no pátio com "${busca}"`}
            />
            <div className="divide-y divide-border">
              {tipoFluxo === "entrada" ? (
                <>
                  <ResultadoItem 
                    nome="RICARDO VELASKO"
                    tipo="Morador"
                    local="Bloco A, Apto 102"
                    documento="044.***.***-91"
                    status="Liberado"
                    onClick={() => {
                      setSelecionado({
                        nome: "RICARDO VELASKO",
                        tipo: "Morador",
                        local: "Bloco A, Apto 102",
                        documento: "044.***.***-91"
                      });
                      setEtapa("detalhes");
                    }}
                  />
                  <ResultadoItem 
                    nome="ELIANE SANTOS (Limpeza)"
                    tipo="Prestador"
                    local="Bloco C, Apto 504"
                    documento="122.***.***-05"
                    status="Aguardando"
                    onClick={() => {
                      setSelecionado({
                        nome: "ELIANE SANTOS (Limpeza)",
                        tipo: "Prestador",
                        local: "Bloco C, Apto 504",
                        documento: "122.***.***-05"
                      });
                      setEtapa("detalhes");
                    }}
                  />
                </>
              ) : (
                <>
                  <ResultadoItem 
                    nome="CARLOS MENDES"
                    tipo="Visitante"
                    local="Bloco B, Apto 304"
                    documento="Veículo: RQK8H21"
                    status="Aguardando"
                    onClick={() => {
                      setSelecionado({
                        nome: "CARLOS MENDES",
                        tipo: "Visitante",
                        local: "Bloco B, Apto 304",
                        documento: "PLACA: RQK8H21"
                      });
                      setEtapa("detalhes");
                    }}
                  />
                  <ResultadoItem 
                    nome="LOGGI EXPRESS"
                    tipo="Prestador"
                    local="Pátio de Carga"
                    documento="Veículo: GFT4A09"
                    status="Liberado"
                    onClick={() => {
                      setSelecionado({
                        nome: "LOGGI EXPRESS",
                        tipo: "Prestador",
                        local: "Pátio de Carga",
                        documento: "PLACA: GFT4A09"
                      });
                      setEtapa("detalhes");
                    }}
                  />
                </>
              )}
            </div>
          </Painel>
        )}


        {/* Tutorial / Estado Inicial */}
        {busca.length < 3 && (
          <div className="space-y-6">
            <div className="rounded-xl border-2 border-dashed border-border bg-neutral-50/50 p-10 text-center">
              <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-white shadow-sm">
                <ShieldCheck className="h-8 w-8 text-brand-blue-500" />
              </div>
              <h3 className="text-base font-bold text-foreground">Pronto para validar</h3>
              <p className="mx-auto mt-1 max-w-xs text-sm text-muted-foreground">
                Utilize o campo de busca acima ou os atalhos para identificar quem deseja acessar o condomínio.
              </p>
            </div>

            {/* Card de Pré-cadastro */}
            <Painel className="border-brand-blue-200 bg-brand-blue-50/30">
              <div className="flex flex-col items-center justify-between gap-4 p-6 sm:flex-row">
                <div className="flex items-center gap-4 text-center sm:text-left">
                  <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-brand-blue-600 text-white">
                    <QrCode className="h-6 w-6" />
                  </div>
                  <div>
                    <h4 className="font-bold text-brand-blue-900">Novo Pré-cadastro</h4>
                    <p className="text-sm text-brand-blue-700">Gere um convite com QR Code para facilitar a entrada.</p>
                  </div>
                </div>
                <button className="flex items-center gap-2 rounded-lg bg-brand-blue-600 px-4 py-2 text-sm font-bold text-white transition-colors hover:bg-brand-blue-700">
                  <Plus className="h-4 w-4" />
                  CRIAR CONVITE
                </button>
              </div>
            </Painel>
          </div>
        )}
      </div>
    </AppShell>
  );
}

function AtalhoRapido({ icone: Icone, rotulo, cor }: { icone: any, rotulo: string, cor: string }) {
  return (
    <button 
      type="button"
      className="flex flex-col items-center gap-2 rounded-xl border border-border bg-card p-4 transition-all hover:border-brand-blue-200 hover:shadow-nivel-1 group"
    >
      <div className={cn("flex h-12 w-12 items-center justify-center rounded-lg transition-transform group-hover:scale-110", cor)}>
        <Icone className="h-6 w-6" />
      </div>
      <span className="text-xs font-bold text-foreground">{rotulo.toUpperCase()}</span>
    </button>
  );
}

function ResultadoItem({ 
  nome, 
  tipo, 
  local, 
  documento, 
  status,
  onClick
}: { 
  nome: string, 
  tipo: string, 
  local: string, 
  documento: string, 
  status: "Liberado" | "Aguardando" | "Bloqueado",
  onClick?: () => void
}) {
  return (
    <div 
      className={cn(
        "group flex cursor-pointer items-center justify-between p-4 hover:bg-neutral-50 transition-colors",
        onClick && "active:bg-neutral-100"
      )}
      onClick={onClick}
    >
      <div className="flex items-center gap-4">
        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-secondary text-muted-foreground">
          <User className="h-5 w-5" />
        </div>
        <div>
          <div className="flex items-center gap-2">
            <h4 className="text-sm font-bold text-foreground group-hover:text-brand-blue-600 transition-colors">{nome}</h4>
            <BadgeStatus tom={status === "Liberado" ? "sucesso" : status === "Aguardando" ? "atencao" : "negativo"}>
              {status.toUpperCase()}
            </BadgeStatus>
          </div>
          <div className="mt-1 flex gap-3 text-xs text-muted-foreground">
            <span className="flex items-center gap-1">
              <Building2 className="h-3 w-3" />
              {local}
            </span>
            <span>DOC: {documento}</span>
          </div>
        </div>
      </div>
      <ArrowRight className="h-5 w-5 text-muted-foreground transition-transform group-hover:translate-x-1 group-hover:text-brand-blue-600" />
    </div>
  );
}
