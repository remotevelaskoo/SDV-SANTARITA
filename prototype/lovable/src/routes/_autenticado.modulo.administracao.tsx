import { createFileRoute } from "@tanstack/react-router";
import { 
  Settings2, 
  ShieldCheck, 
  Users, 
  Database, 
  BellRing, 
  Save,
  Lock,
  Globe
} from "lucide-react";
import { useState } from "react";

import { Painel, PainelCabecalho, Alerta } from "@/components/ds";
import { AppShell } from "@/components/shell/app-shell";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { toast } from "sonner";

export const Route = createFileRoute("/_autenticado/modulo/administracao")({
  head: () => ({
    meta: [
      { title: "Administração do Sistema — SDV Access Santa Rita" },
      {
        name: "description",
        content: "Configurações globais, segurança e parâmetros do sistema SDV Access.",
      },
    ],
  }),
  component: AdministracaoModulo,
});

function AdministracaoModulo() {
  const [salvando, setSalvando] = useState(false);

  const handleSalvar = () => {
    setSalvando(true);
    setTimeout(() => {
      setSalvando(false);
      toast.success("Configurações aplicadas com sucesso", {
        description: "Os parâmetros globais foram atualizados em toda a rede."
      });
    }, 1000);
  };

  return (
    <AppShell 
      titulo="Administração" 
      descricao="Configurações globais e parâmetros de segurança"
    >
      <div className="mx-auto max-w-4xl space-y-6 pb-10">
        <div className="flex items-center justify-between">
          <h2 className="text-lg font-bold text-foreground flex items-center gap-2">
            <Settings2 className="h-5 w-5 text-brand-blue-600" />
            Parâmetros do Condomínio
          </h2>
          <Button onClick={handleSalvar} disabled={salvando} className="bg-brand-blue-600 hover:bg-brand-blue-700">
            <Save className="h-4 w-4 mr-2" />
            {salvando ? "SALVANDO..." : "SALVAR ALTERAÇÕES"}
          </Button>
        </div>

        <div className="grid gap-6 md:grid-cols-2">
          <Painel>
            <PainelCabecalho 
              titulo="Segurança e Acesso" 
              descricao="Regras de validação na portaria"
            />
            <div className="p-5 space-y-4">
              <div className="flex items-center justify-between space-x-2">
                <Label htmlFor="ocr-auto" className="flex flex-col space-y-1 cursor-pointer">
                  <span>OCR de Placa Automático</span>
                  <span className="font-normal text-xs text-muted-foreground">Tentar identificar placa via câmera ao abrir busca.</span>
                </Label>
                <Switch id="ocr-auto" defaultChecked />
              </div>
              <div className="flex items-center justify-between space-x-2">
                <Label htmlFor="bloqueio-financeiro" className="flex flex-col space-y-1 cursor-pointer">
                  <span>Bloqueio por Inadimplência</span>
                  <span className="font-normal text-xs text-muted-foreground">Impedir liberação automática de moradores restritos.</span>
                </Label>
                <Switch id="bloqueio-financeiro" defaultChecked />
              </div>
              <div className="flex items-center justify-between space-x-2">
                <Label htmlFor="qr-expires" className="flex flex-col space-y-1 cursor-pointer">
                  <span>Expiração de Convites</span>
                  <span className="font-normal text-xs text-muted-foreground">Convites QR expiram automaticamente após 24h.</span>
                </Label>
                <Switch id="qr-expires" defaultChecked />
              </div>
            </div>
          </Painel>

          <Painel>
            <PainelCabecalho 
              titulo="Notificações" 
              descricao="Alertas do sistema para moradores e equipe"
            />
            <div className="p-5 space-y-4">
              <div className="flex items-center justify-between space-x-2">
                <Label htmlFor="push-morador" className="flex flex-col space-y-1 cursor-pointer">
                  <span>Notificar Morador na Entrada</span>
                  <span className="font-normal text-xs text-muted-foreground">Enviar push quando um visitante for liberado.</span>
                </Label>
                <Switch id="push-morador" defaultChecked />
              </div>
              <div className="flex items-center justify-between space-x-2">
                <Label htmlFor="email-encomenda" className="flex flex-col space-y-1 cursor-pointer">
                  <span>Aviso de Encomendas</span>
                  <span className="font-normal text-xs text-muted-foreground">E-mail automático ao registrar novo pacote.</span>
                </Label>
                <Switch id="email-encomenda" defaultChecked />
              </div>
              <div className="flex items-center justify-between space-x-2">
                <Label htmlFor="alerta-panico" className="flex flex-col space-y-1 cursor-pointer">
                  <span>Pânico Silencioso</span>
                  <span className="font-normal text-xs text-muted-foreground">Ativar botão de emergência na tela do operador.</span>
                </Label>
                <Switch id="alerta-panico" />
              </div>
            </div>
          </Painel>
        </div>

        <Painel>
          <PainelCabecalho 
            titulo="Identidade do Sistema" 
            descricao="Personalização visual e regional"
          />
          <div className="p-5 grid gap-4 sm:grid-cols-2">
            <div className="space-y-2">
              <Label htmlFor="nome-condominio">Nome do Condomínio</Label>
              <Input id="nome-condominio" defaultValue="Residencial Santa Rita" />
            </div>
            <div className="space-y-2">
              <Label htmlFor="timezone">Fuso Horário</Label>
              <Input id="timezone" defaultValue="America/Sao_Paulo (UTC-3)" />
            </div>
          </div>
        </Painel>

        <Alerta 
          severidade="info"
          titulo="Auditoria de Alterações"
          descricao="Todas as mudanças nesta tela são registradas com IP e usuário para fins de compliance."
        />
      </div>
    </AppShell>
  );
}