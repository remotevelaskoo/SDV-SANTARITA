import { createFileRoute } from "@tanstack/react-router";
import { 
  BadgeCheck, 
  User, 
  Building2, 
  Car, 
  Calendar, 
  Save, 
  QrCode, 
  X, 
  CheckCircle2,
  Clock,
  ChevronLeft
} from "lucide-react";
import { useState } from "react";

import { Painel, PainelCabecalho, Alerta } from "@/components/ds";
import { AppShell } from "@/components/shell/app-shell";
import { cn } from "@/lib/utils";

export const Route = createFileRoute("/_autenticado/modulo/pre-cadastro")({
  head: () => ({
    meta: [
      { title: "Pré-cadastro de Visitantes — SDV Access Santa Rita" },
      {
        name: "description",
        content: "Criação e gestão de pré-autorizações e convites via QR Code.",
      },
    ],
  }),
  component: PreCadastroVisitante,
});

function PreCadastroVisitante() {
  const [etapa, setEtapa] = useState<"formulario" | "sucesso">("formulario");
  const [formData, setFormData] = useState({
    nome: "",
    documento: "",
    tipo: "Visitante",
    imovel: "Bloco A — Apto 102",
    validade: "2026-08-07",
    placa: "",
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setEtapa("sucesso");
  };

  if (etapa === "sucesso") {
    return (
      <AppShell 
        titulo="Convite Gerado" 
        descricao="O pré-cadastro foi registrado com sucesso"
      >
        <div className="mx-auto max-w-xl space-y-6">
          <Painel className="overflow-hidden border-success-foreground/20">
            <div className="bg-success px-6 py-8 text-center text-success-foreground">
              <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-white/20">
                <CheckCircle2 className="h-10 w-10 text-success-foreground" />
              </div>
              <h2 className="text-2xl font-bold">Tudo pronto!</h2>
              <p className="mt-2 opacity-90">O convite para <strong>{formData.nome}</strong> está ativo.</p>
            </div>

            <div className="p-8 flex flex-col items-center gap-6">
              <div className="rounded-xl border-4 border-neutral-100 bg-white p-4 shadow-nivel-1">
                <QrCode className="h-48 w-48 text-brand-blue-900" />
              </div>
              
              <div className="w-full space-y-4 rounded-lg bg-neutral-50 p-6 text-sm">
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <p className="text-xs font-bold text-muted-foreground uppercase">Destino</p>
                    <p className="font-semibold text-foreground">{formData.imovel}</p>
                  </div>
                  <div>
                    <p className="text-xs font-bold text-muted-foreground uppercase">Validade</p>
                    <p className="font-semibold text-foreground">{new Date(formData.validade).toLocaleDateString("pt-BR")}</p>
                  </div>
                </div>
              </div>

              <div className="flex w-full flex-col gap-3">
                <button className="flex w-full items-center justify-center gap-2 rounded-lg bg-brand-blue-600 px-6 py-3 font-bold text-white hover:bg-brand-blue-700">
                  COMPARTILHAR QR CODE
                </button>
                <button 
                  onClick={() => {
                    setEtapa("formulario");
                    setFormData({ ...formData, nome: "", documento: "", placa: "" });
                  }}
                  className="flex w-full items-center justify-center gap-2 rounded-lg border border-border bg-card px-6 py-3 font-bold text-muted-foreground hover:bg-neutral-50"
                >
                  NOVO PRÉ-CADASTRO
                </button>
              </div>
            </div>
          </Painel>
        </div>
      </AppShell>
    );
  }

  return (
    <AppShell 
      titulo="Pré-cadastro" 
      descricao="Autorize a entrada de visitantes antecipadamente"
    >
      <div className="mx-auto max-w-3xl space-y-6">
        <Painel>
          <PainelCabecalho 
            titulo="Dados do Visitante / Prestador" 
            descricao="Informações básicas para identificação na portaria"
          />
          <form onSubmit={handleSubmit} className="p-6 space-y-6">
            <div className="grid gap-6 sm:grid-cols-2">
              <div className="space-y-2">
                <label className="text-sm font-bold text-foreground">NOME COMPLETO</label>
                <input 
                  type="text" 
                  required
                  placeholder="Ex: João da Silva"
                  className="w-full rounded-md border border-border bg-card px-4 py-2 text-sm focus:border-brand-blue-500 focus:outline-none"
                  value={formData.nome}
                  onChange={e => setFormData({ ...formData, nome: e.target.value })}
                />
              </div>
              <div className="space-y-2">
                <label className="text-sm font-bold text-foreground">DOCUMENTO (RG/CPF)</label>
                <input 
                  type="text" 
                  placeholder="000.000.000-00"
                  className="w-full rounded-md border border-border bg-card px-4 py-2 text-sm focus:border-brand-blue-500 focus:outline-none"
                  value={formData.documento}
                  onChange={e => setFormData({ ...formData, documento: e.target.value })}
                />
              </div>
              <div className="space-y-2">
                <label className="text-sm font-bold text-foreground">TIPO DE VÍNCULO</label>
                <select 
                  className="w-full rounded-md border border-border bg-card px-4 py-2 text-sm focus:border-brand-blue-500 focus:outline-none"
                  value={formData.tipo}
                  onChange={e => setFormData({ ...formData, tipo: e.target.value })}
                >
                  <option>Visitante</option>
                  <option>Prestador de Serviço</option>
                  <option>Entregador</option>
                  <option>Familiar</option>
                </select>
              </div>
              <div className="space-y-2">
                <label className="text-sm font-bold text-foreground">IMÓVEL DESTINO</label>
                <div className="relative">
                  <Building2 className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                  <input 
                    type="text" 
                    readOnly
                    className="w-full rounded-md border border-border bg-neutral-50 px-10 py-2 text-sm cursor-not-allowed"
                    value={formData.imovel}
                  />
                </div>
              </div>
              <div className="space-y-2">
                <label className="text-sm font-bold text-foreground">PLACA DO VEÍCULO (OPCIONAL)</label>
                <div className="relative">
                  <Car className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                  <input 
                    type="text" 
                    placeholder="AAA-0000"
                    className="w-full rounded-md border border-border bg-card px-10 py-2 text-sm focus:border-brand-blue-500 focus:outline-none"
                    value={formData.placa}
                    onChange={e => setFormData({ ...formData, placa: e.target.value.toUpperCase() })}
                  />
                </div>
              </div>
              <div className="space-y-2">
                <label className="text-sm font-bold text-foreground">VÁLIDO ATÉ</label>
                <div className="relative">
                  <Calendar className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                  <input 
                    type="date" 
                    required
                    className="w-full rounded-md border border-border bg-card px-10 py-2 text-sm focus:border-brand-blue-500 focus:outline-none"
                    value={formData.validade}
                    onChange={e => setFormData({ ...formData, validade: e.target.value })}
                  />
                </div>
              </div>
            </div>

            <Alerta 
              severidade="info"
              titulo="Aviso de Segurança"
              descricao="O QR Code gerado será enviado ao visitante e terá validade apenas até a data selecionada."
            />

            <div className="flex justify-end gap-3 pt-4">
              <button 
                type="button"
                className="rounded-lg border border-border bg-card px-6 py-2.5 text-sm font-bold text-muted-foreground hover:bg-neutral-50"
              >
                CANCELAR
              </button>
              <button 
                type="submit"
                className="flex items-center gap-2 rounded-lg bg-brand-blue-600 px-6 py-2.5 text-sm font-bold text-white hover:bg-brand-blue-700 shadow-nivel-1"
              >
                <Save className="h-4 w-4" />
                SALVAR E GERAR QR CODE
              </button>
            </div>
          </form>
        </Painel>
      </div>
    </AppShell>
  );
}