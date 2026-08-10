/**
 * DS-CMP-002 — Cabeçalho operacional
 * Fonte: 003_DESIGN_SYSTEM §11.3 e 004_UX_UI_DASHBOARD §6.3 e §7.
 * Contém: recolher menu, busca global, notificações, usuário autenticado,
 * perfil, situação do caixa (condicional) e data/hora da sessão.
 */
import { Bell, Clock, LogOut, Menu, PanelLeft, Search, Wallet } from "lucide-react";
import { useEffect, useState } from "react";

import { BadgeStatus } from "@/components/ds";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { NOTIFICACOES, SITUACAO_CAIXA } from "@/data/dashboard";
import { useSessao } from "@/lib/session-context";
import { cn } from "@/lib/utils";

function useRelogioSessao() {
  const [agora, setAgora] = useState<Date | null>(null);

  useEffect(() => {
    setAgora(new Date());
    const intervalo = window.setInterval(() => setAgora(new Date()), 30_000);
    return () => window.clearInterval(intervalo);
  }, []);

  if (!agora) return null;
  return new Intl.DateTimeFormat("pt-BR", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  }).format(agora);
}

export function CabecalhoOperacional({
  titulo,
  descricao,
  onAbrirMenuMobile,
  onAlternarRecolhida,
}: {
  titulo: string;
  descricao?: string | undefined;
  onAbrirMenuMobile: () => void;
  onAlternarRecolhida: () => void;
}) {
  const { sessao, perfil, pode, sair } = useSessao();
  const dataHora = useRelogioSessao();
  const naoLidas = NOTIFICACOES.filter((item) => !item.lida).length;
  const iniciais = (sessao?.nome ?? "")
    .split(" ")
    .filter(Boolean)
    .slice(0, 2)
    .map((parte) => parte[0])
    .join("");

  return (
    <header className="sticky top-0 z-20 border-b border-border bg-card">
      <div className="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3 px-4 py-2.5 sm:px-6">
        <div className="flex min-w-0 items-center gap-2">
          <Button
            variant="ghost"
            size="icon"
            className="lg:hidden"
            onClick={onAbrirMenuMobile}
            aria-label="Abrir navegação"
          >
            <Menu className="h-5 w-5" aria-hidden />
          </Button>
          <Button
            variant="ghost"
            size="icon"
            className="hidden lg:inline-flex"
            onClick={onAlternarRecolhida}
            aria-label="Recolher ou abrir navegação"
          >
            <PanelLeft className="h-5 w-5" aria-hidden />
          </Button>
          <div className="min-w-0">
            <h1 className="truncate text-base font-semibold text-foreground sm:text-lg">
              {titulo}
            </h1>
            {descricao ? (
              <p className="truncate text-xs text-muted-foreground">{descricao}</p>
            ) : null}
          </div>
        </div>

        <div className="flex shrink-0 items-center gap-1.5 sm:gap-2">
          {pode("caixa.operar") ? (
            <BadgeStatus
              tom={SITUACAO_CAIXA.situacao === "aberto" ? "sucesso" : "neutro"}
              icone={Wallet}
              className="hidden xl:inline-flex"
            >
              {SITUACAO_CAIXA.identificacao}{" "}
              {SITUACAO_CAIXA.situacao === "aberto"
                ? `aberto às ${SITUACAO_CAIXA.abertoEm}`
                : "fechado"}
            </BadgeStatus>
          ) : null}

          {dataHora ? (
            <span className="numerico hidden items-center gap-1.5 text-xs text-muted-foreground lg:flex">
              <Clock className="h-3.5 w-3.5" aria-hidden />
              {dataHora}
            </span>
          ) : null}

          <Popover>
            <PopoverTrigger asChild>
              <Button variant="ghost" size="icon" aria-label="Notificações" className="relative">
                <Bell className="h-5 w-5" aria-hidden />
                {naoLidas > 0 ? (
                  <span
                    className="absolute top-1.5 right-1.5 h-2 w-2 rounded-full bg-danger"
                    aria-hidden
                  />
                ) : null}
              </Button>
            </PopoverTrigger>
            <PopoverContent align="end" className="w-80 p-0">
              <p className="border-b border-border px-4 py-2.5 text-sm font-semibold">
                Notificações
              </p>
              <ul className="max-h-72 overflow-y-auto">
                {NOTIFICACOES.map((item) => (
                  <li
                    key={item.id}
                    className="grid grid-cols-[minmax(0,1fr)_auto] gap-2 border-b border-border px-4 py-2.5 last:border-b-0"
                  >
                    <div className="min-w-0">
                      <p
                        className={cn(
                          "truncate text-sm",
                          item.lida ? "text-muted-foreground" : "font-semibold text-foreground",
                        )}
                      >
                        {item.titulo}
                      </p>
                      <p className="truncate text-xs text-muted-foreground">{item.detalhe}</p>
                    </div>
                    <span className="numerico shrink-0 text-xs text-muted-foreground">
                      {item.horario}
                    </span>
                  </li>
                ))}
              </ul>
            </PopoverContent>
          </Popover>

          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <button
                type="button"
                className="flex items-center gap-2 rounded-md py-1 pr-2 pl-1 transition-colors hover:bg-secondary focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
              >
                <span
                  className="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-brand-navy-900 text-xs font-bold text-shell-foreground uppercase"
                  aria-hidden
                >
                  {iniciais || "SD"}
                </span>
                <span className="hidden min-w-0 text-left sm:block">
                  <span className="block max-w-[10rem] truncate text-sm font-medium text-foreground">
                    {sessao?.nome}
                  </span>
                  <span className="block max-w-[10rem] truncate text-xs text-muted-foreground">
                    {perfil?.nome}
                  </span>
                </span>
              </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-64">
              <DropdownMenuLabel>
                <span className="block truncate text-sm font-semibold">{sessao?.nome}</span>
                <span className="block truncate text-xs font-normal text-muted-foreground">
                  {perfil?.nome} · {sessao?.pontoAcesso}
                </span>
              </DropdownMenuLabel>
              <DropdownMenuSeparator />
              <DropdownMenuItem disabled>{sessao?.condominio}</DropdownMenuItem>
              <DropdownMenuSeparator />
              <DropdownMenuItem onSelect={sair}>
                <LogOut className="mr-2 h-4 w-4" aria-hidden />
                Sair
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>

      {pode("busca.global") ? (
        <div className="border-t border-border px-4 py-2 sm:px-6">
          <label className="relative block max-w-xl">
            <span className="sr-only">Busca global</span>
            <Search
              className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
              aria-hidden
            />
            <Input
              type="search"
              placeholder="Buscar pessoa, documento, imóvel, placa ou protocolo"
              className="h-9 pl-9"
            />
          </label>
        </div>
      ) : null}
    </header>
  );
}
