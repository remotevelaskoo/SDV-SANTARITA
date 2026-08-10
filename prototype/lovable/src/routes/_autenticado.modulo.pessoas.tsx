import { createFileRoute } from "@tanstack/react-router";
import { Users, Search, Plus, Filter, User, Phone, MapPin, BadgeCheck, ShieldAlert, History } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";

import { Painel, PainelCabecalho, BadgeStatus, EstadoVazio } from "@/components/ds";
import { AppShell } from "@/components/shell/app-shell";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { cn } from "@/lib/utils";

export const Route = createFileRoute("/_autenticado/modulo/pessoas")({
  head: () => ({
    meta: [
      { title: "Gestão de Pessoas — SDV Access Santa Rita" },
      {
        name: "description",
        content: "Administração de moradores, dependentes e prestadores de serviço do condomínio Santa Rita.",
      },
    ],
  }),
  component: PessoasModulo,
});

const PESSOAS_MOCK = [
  {
    id: "pes-1",
    nome: "RICARDO VELASKO",
    tipo: "Morador",
    local: "Bloco A — Apto 102",
    documento: "044.***.***-91",
    telefone: "(11) 98822-1102",
    status: "Ativo",
    restricoes: false
  },
  {
    id: "pes-2",
    nome: "ELIANE SANTOS",
    tipo: "Prestador",
    local: "Bloco C — Apto 504",
    documento: "122.***.***-05",
    telefone: "(11) 97711-0504",
    status: "Ativo",
    restricoes: false
  },
  {
    id: "pes-3",
    nome: "MARIA OLIVEIRA",
    tipo: "Morador",
    local: "Bloco B — Apto 304",
    documento: "215.***.***-44",
    telefone: "(11) 96633-4404",
    status: "Bloqueado",
    restricoes: true
  },
  {
    id: "pes-4",
    nome: "CARLOS MENDES",
    tipo: "Visitante",
    local: "Bloco B — Apto 304",
    documento: "332.***.***-88",
    telefone: "(11) 95544-8804",
    status: "Inativo",
    restricoes: false
  }
];

function PessoasModulo() {
  const [busca, setBusca] = useState("");
  const [filtroTipo, setFiltroTipo] = useState<string | null>(null);
  const [pessoaEdicao, setPessoaEdicao] = useState<typeof PESSOAS_MOCK[0] | null>(null);

  const handleSalvarEdicao = () => {
    toast.success("Cadastro atualizado", {
      description: `As alterações para ${pessoaEdicao?.nome} foram salvas.`
    });
    setPessoaEdicao(null);
  };

  if (pessoaEdicao) {
    return (
      <AppShell 
        titulo={`Editar: ${pessoaEdicao.nome}`}
        descricao="Atualização de dados cadastrais e permissões"
      >
        <div className="mx-auto max-w-2xl space-y-6">
          <button 
            onClick={() => setPessoaEdicao(null)}
            className="flex items-center gap-2 text-sm font-medium text-muted-foreground hover:text-foreground transition-colors"
          >
            <History className="h-4 w-4" rotate={180} />
            Voltar para lista
          </button>

          <Painel>
            <PainelCabecalho titulo="Dados Gerais" descricao="Informações básicas de identificação" />
            <div className="p-6 space-y-4">
              <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-2">
                  <Label htmlFor="edit-nome">Nome Completo</Label>
                  <Input id="edit-nome" defaultValue={pessoaEdicao.nome} />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="edit-doc">Documento (CPF/RG)</Label>
                  <Input id="edit-doc" defaultValue={pessoaEdicao.documento} />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="edit-tel">Telefone</Label>
                  <Input id="edit-tel" defaultValue={pessoaEdicao.telefone} />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="edit-local">Vínculo (Imóvel/Local)</Label>
                  <Input id="edit-local" defaultValue={pessoaEdicao.local} />
                </div>
              </div>

              <div className="flex items-center justify-between pt-4 border-t border-border">
                <div className="space-y-0.5">
                  <Label className="text-sm font-bold">Bloqueio Administrativo</Label>
                  <p className="text-xs text-muted-foreground italic">Impedir acesso imediato deste usuário.</p>
                </div>
                <Switch defaultChecked={pessoaEdicao.status === 'Bloqueado'} />
              </div>

              <div className="flex gap-3 pt-6">
                <Button onClick={handleSalvarEdicao} className="flex-1 bg-brand-blue-600 hover:bg-brand-blue-700">
                  SALVAR ALTERAÇÕES
                </Button>
                <Button variant="outline" onClick={() => setPessoaEdicao(null)} className="flex-1">
                  CANCELAR
                </Button>
              </div>
            </div>
          </Painel>
        </div>
      </AppShell>
    );
  }

  const filtradas = PESSOAS_MOCK.filter(p => {
    const bateBusca = p.nome.toLowerCase().includes(busca.toLowerCase()) || 
                     p.local.toLowerCase().includes(busca.toLowerCase());
    const bateFiltro = filtroTipo ? p.tipo === filtroTipo : true;
    return bateBusca && bateFiltro;
  });

  return (
    <AppShell 
      titulo="Gestão de Pessoas" 
      descricao="Moradores, dependentes e prestadores"
    >
      <div className="space-y-6">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="relative max-w-md flex-1">
            <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input 
              placeholder="Buscar por nome, imóvel ou documento..." 
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
            <Button className="gap-2 bg-brand-blue-600 hover:bg-brand-blue-700">
              <Plus className="h-4 w-4" />
              NOVO CADASTRO
            </Button>
          </div>
        </div>

        <div className="flex items-center gap-2 overflow-x-auto pb-1">
          {["Morador", "Prestador", "Visitante"].map(tipo => (
            <button
              key={tipo}
              onClick={() => setFiltroTipo(filtroTipo === tipo ? null : tipo)}
              className={cn(
                "rounded-full px-4 py-1.5 text-xs font-bold transition-all border",
                filtroTipo === tipo 
                  ? "bg-brand-blue-600 text-white border-brand-blue-600" 
                  : "bg-card text-muted-foreground border-border hover:bg-neutral-50"
              )}
            >
              {tipo.toUpperCase()}S
            </button>
          ))}
        </div>

        <Painel>
          <PainelCabecalho 
            titulo="Registros" 
            descricao={`${filtradas.length} pessoas encontradas na base atual`}
          />
          
          {filtradas.length > 0 ? (
            <div className="divide-y divide-border">
              {filtradas.map((p) => (
                <div key={p.id} className="group flex flex-col items-start justify-between gap-4 p-5 transition-colors hover:bg-neutral-50 sm:flex-row sm:items-center">
                  <div className="flex items-center gap-4">
                    <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-secondary text-muted-foreground transition-colors group-hover:bg-brand-blue-50 group-hover:text-brand-blue-600">
                      <User className="h-6 w-6" />
                    </div>
                    <div className="min-w-0">
                      <div className="flex items-center gap-2">
                        <h4 className="font-bold text-foreground">{p.nome}</h4>
                        <BadgeStatus tom={p.tipo === 'Morador' ? 'informativo' : p.tipo === 'Prestador' ? 'neutro' : 'atencao'}>
                          {p.tipo.toUpperCase()}
                        </BadgeStatus>
                        {p.restricoes && (
                          <ShieldAlert className="h-4 w-4 text-danger" />
                        )}
                      </div>
                      <div className="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                        <span className="flex items-center gap-1">
                          <MapPin className="h-3.5 w-3.5" />
                          {p.local}
                        </span>
                        <span className="flex items-center gap-1">
                          <Phone className="h-3.5 w-3.5" />
                          {p.telefone}
                        </span>
                        <span>DOC: {p.documento}</span>
                      </div>
                    </div>
                  </div>
                  
                  <div className="flex w-full items-center justify-end gap-2 sm:w-auto">
                    <BadgeStatus tom={p.status === 'Ativo' ? 'sucesso' : p.status === 'Bloqueado' ? 'negativo' : 'neutro'}>
                      {p.status.toUpperCase()}
                    </BadgeStatus>
                    <Button variant="ghost" size="icon" title="Histórico">
                      <History className="h-4 w-4" />
                    </Button>
                    <Button 
                      variant="ghost" 
                      size="icon" 
                      title="Editar"
                      onClick={() => setPessoaEdicao(p)}
                    >
                      <BadgeCheck className="h-4 w-4" />
                    </Button>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <EstadoVazio 
              icone={Users}
              titulo="Nenhum registro encontrado"
              descricao="Não há pessoas que correspondam aos filtros aplicados."
            />
          )}
        </Painel>
      </div>
    </AppShell>
  );
}
