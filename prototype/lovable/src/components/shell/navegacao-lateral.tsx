/**
 * DS-CMP-001 — Sidebar (navegação lateral)
 * Fonte: 003_DESIGN_SYSTEM §11.2 e 004_UX_UI_DASHBOARD §6.2.
 * Fundo azul-marinho, marca SDV Access, módulos agrupados, item ativo destacado,
 * itens filtrados por permissão, usuário e ação de saída no rodapé.
 */
import { Link, useRouterState } from "@tanstack/react-router";
import {
  BadgeCheck,
  Building2,
  Car,
  ChevronRight,
  ChevronsLeft,
  ClipboardList,
  DoorOpen,
  FileBarChart2,
  LayoutDashboard,
  LogOut,
  Package,
  ScrollText,
  Settings2,
  ShieldCheck,
  Users,
  UsersRound,
  Wrench,
  type LucideIcon,
} from "lucide-react";

import type { Permissao } from "@/data/perfis";
import { useSessao } from "@/lib/session-context";
import { cn } from "@/lib/utils";

interface ItemMenu {
  rotulo: string;
  icone: LucideIcon;
  /** Ausente no Dashboard, que possui rota própria. */
  slug?: string;
  permissao: Permissao;
  contador?: number;
}

interface GrupoMenu {
  titulo: string;
  itens: ItemMenu[];
}

export const GRUPOS_MENU: GrupoMenu[] = [
  {
    titulo: "Operação",
    itens: [
      { rotulo: "Dashboard", icone: LayoutDashboard, permissao: "dashboard.visualizar" },
      { rotulo: "Validação de entrada", icone: ShieldCheck, slug: "validacao", permissao: "validacao.operar" },
      { rotulo: "Pré-cadastro", icone: BadgeCheck, slug: "pre-cadastro", permissao: "pre_cadastro.analisar", contador: 3 },
      { rotulo: "Entradas e saídas", icone: DoorOpen, slug: "entradas-saidas", permissao: "acesso.historico" },
    ],
  },
  {
    titulo: "Cadastros",
    itens: [
      { rotulo: "Imóveis", icone: Building2, slug: "imoveis", permissao: "imovel.visualizar" },
      { rotulo: "Pessoas", icone: Users, slug: "pessoas", permissao: "pessoa.visualizar" },
      { rotulo: "Empresas e prestadores", icone: UsersRound, slug: "prestadores", permissao: "pessoa.visualizar" },
      { rotulo: "Veículos", icone: Car, slug: "veiculos", permissao: "veiculo.visualizar" },
    ],
  },
  {
    titulo: "Gestão",
    itens: [
      { rotulo: "Administração", icone: Settings2, slug: "administracao", permissao: "administracao.acessar" },
      { rotulo: "Relatórios", icone: FileBarChart2, slug: "relatorios", permissao: "relatorio.visualizar" },
      { rotulo: "Encomendas", icone: Package, slug: "encomendas", permissao: "caixa.operar" },
      { rotulo: "Logs e auditoria", icone: ScrollText, slug: "logs", permissao: "log.visualizar" },
      { rotulo: "Manutenção", icone: Wrench, slug: "manutencao", permissao: "dashboard.visualizar" },
      { rotulo: "Caixa", icone: ClipboardList, slug: "caixa", permissao: "caixa.operar" },
    ],
  },
];

export const ROTULOS_MODULO: Record<string, string> = Object.fromEntries(
  GRUPOS_MENU.flatMap((grupo) =>
    grupo.itens.filter((item) => item.slug).map((item) => [item.slug as string, item.rotulo]),
  ),
);

export function NavegacaoLateral({
  recolhida,
  onRecolher,
  onNavegar,
}: {
  recolhida: boolean;
  onRecolher?: () => void;
  onNavegar?: () => void;
}) {
  const { sessao, perfil, pode, sair } = useSessao();
  const caminho = useRouterState({ select: (estado) => estado.location.pathname });

  return (
    <div className="flex h-full flex-col bg-shell text-shell-foreground">
      <div
        className={cn(
          "flex h-16 shrink-0 items-center gap-2 border-b border-shell-border px-4",
          recolhida && "justify-center px-0",
        )}
      >
        <MarcaSdv compacta={recolhida} />
        {!recolhida && onRecolher ? (
          <button
            type="button"
            onClick={onRecolher}
            aria-label="Recolher navegação"
            className="ml-auto hidden rounded-md p-1.5 text-shell-muted transition-colors hover:bg-shell-elevated hover:text-shell-foreground lg:block"
          >
            <ChevronsLeft className="h-4 w-4" aria-hidden />
          </button>
        ) : null}
      </div>

      <nav aria-label="Módulos do sistema" className="min-h-0 flex-1 overflow-y-auto px-2 py-3">
        {GRUPOS_MENU.map((grupo) => {
          const itens = grupo.itens.filter((item) => pode(item.permissao));
          if (itens.length === 0) return null;

          return (
            <div key={grupo.titulo} className="mb-4">
              {!recolhida ? (
                <p className="texto-rotulo px-3 pb-2 text-shell-muted">{grupo.titulo}</p>
              ) : (
                <div className="mx-3 mb-2 border-t border-shell-border" aria-hidden />
              )}
              <ul className="space-y-0.5">
                {itens.map((item) => {
                  const destino = item.slug ? `/modulo/${item.slug}` : "/painel";
                  const ativo = caminho === destino;
                  const classes = cn(
                    "flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-shell-muted transition-colors",
                    "hover:bg-shell-elevated hover:text-shell-foreground",
                    "focus-visible:ring-2 focus-visible:ring-brand-blue-500 focus-visible:outline-none",
                    ativo && "bg-shell-active text-shell-foreground hover:bg-shell-active",
                    recolhida && "justify-center px-0",
                  );
                  const interior = (
                    <>
                      <item.icone className="h-4.5 w-4.5 shrink-0" aria-hidden />
                      {!recolhida ? (
                        <>
                          <span className="min-w-0 flex-1 truncate">{item.rotulo}</span>
                          {item.contador ? (
                            <span className="numerico shrink-0 rounded-full bg-warning px-1.5 text-xs font-bold text-neutral-950">
                              {item.contador}
                            </span>
                          ) : null}
                        </>
                      ) : null}
                    </>
                  );

                  return (
                    <li key={destino}>
                      {item.slug ? (
                        <Link
                          to="/modulo/$slug"
                          params={{ slug: item.slug }}
                          onClick={onNavegar}
                          title={recolhida ? item.rotulo : undefined}
                          aria-current={ativo ? "page" : undefined}
                          className={classes}
                        >
                          {interior}
                        </Link>
                      ) : (
                        <Link
                          to="/painel"
                          onClick={onNavegar}
                          title={recolhida ? item.rotulo : undefined}
                          aria-current={ativo ? "page" : undefined}
                          className={classes}
                        >
                          {interior}
                        </Link>
                      )}
                    </li>
                  );
                })}
              </ul>
            </div>
          );
        })}
      </nav>

      <div className="shrink-0 border-t border-shell-border p-3">
        {!recolhida ? (
          <div className="mb-2 min-w-0">
            <p className="truncate text-sm font-semibold text-shell-foreground">
              {sessao?.nome ?? "—"}
            </p>
            <p className="truncate text-xs text-shell-muted">{perfil?.nome ?? "—"}</p>
          </div>
        ) : null}
        <button
          type="button"
          onClick={sair}
          title="Sair"
          className={cn(
            "flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-shell-muted transition-colors hover:bg-shell-elevated hover:text-shell-foreground",
            recolhida && "justify-center px-0",
          )}
        >
          <LogOut className="h-4 w-4 shrink-0" aria-hidden />
          {!recolhida ? "Sair" : null}
        </button>
      </div>
    </div>
  );
}

export function MarcaSdv({ compacta = false }: { compacta?: boolean }) {
  return (
    <span className="flex min-w-0 items-center gap-2.5">
      <span
        className="grid h-9 w-9 shrink-0 place-items-center rounded-md bg-brand-blue-600 text-sm font-black tracking-tight text-shell-foreground"
        aria-hidden
      >
        SDV
      </span>
      {!compacta ? (
        <span className="min-w-0 leading-tight">
          <span className="block truncate text-sm font-bold text-shell-foreground">
            SDV Access
          </span>
          <span className="block truncate text-[0.6875rem] text-shell-muted">Santa Rita</span>
        </span>
      ) : null}
    </span>
  );
}
