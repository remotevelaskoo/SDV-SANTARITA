/**
 * Acessos recentes — 004_UX_UI_DASHBOARD §9.
 * Tabela em telas largas, cartões no mobile. Documento mascarado quando o
 * perfil não possui a permissão de visualização completa.
 */
import { ArrowDownLeft, ArrowUpRight, Car, Inbox } from "lucide-react";

import { BadgeStatus, EstadoVazio } from "@/components/ds";
import type { AcessoRecente, ResultadoAcesso } from "@/data/dashboard";
import { cn } from "@/lib/utils";

const TOM_RESULTADO: Record<ResultadoAcesso, "sucesso" | "negativo" | "atencao"> = {
  liberado: "sucesso",
  negado: "negativo",
  pendente: "atencao",
};

const ROTULO_RESULTADO: Record<ResultadoAcesso, string> = {
  liberado: "Liberado",
  negado: "Negado",
  pendente: "Pendente",
};

export function mascararDocumento(documento: string) {
  const digitos = documento.replace(/\D/g, "");
  if (digitos.length < 4) return "•••";
  return `•••.•••.${digitos.slice(-5, -2)}-${digitos.slice(-2)}`;
}

function TipoAcessoBadge({ tipo }: { tipo: AcessoRecente["tipo"] }) {
  const Icone = tipo === "entrada" ? ArrowDownLeft : ArrowUpRight;
  return (
    <span
      className={cn(
        "inline-flex items-center gap-1 text-xs font-semibold",
        tipo === "entrada" ? "text-info-foreground" : "text-muted-foreground",
      )}
    >
      <Icone className="h-3.5 w-3.5 shrink-0" aria-hidden />
      {tipo === "entrada" ? "Entrada" : "Saída"}
    </span>
  );
}

export function ListaAcessosRecentes({
  acessos,
  documentoCompleto,
}: {
  acessos: AcessoRecente[];
  documentoCompleto: boolean;
}) {
  if (acessos.length === 0) {
    return (
      <EstadoVazio
        icone={Inbox}
        titulo="Nenhum acesso registrado"
        descricao="Os acessos aparecem aqui assim que forem validados na portaria."
      />
    );
  }

  return (
    <>
      <div className="hidden overflow-x-auto lg:block">
        <table className="w-full text-sm">
          <caption className="sr-only">Últimos acessos validados</caption>
          <thead>
            <tr className="border-b border-border">
              {["Hora", "Pessoa", "Vínculo", "Imóvel", "Ponto de acesso", "Tipo", "Resultado"].map(
                (coluna) => (
                  <th
                    key={coluna}
                    scope="col"
                    className="texto-rotulo px-4 py-2 text-left text-muted-foreground"
                  >
                    {coluna}
                  </th>
                ),
              )}
            </tr>
          </thead>
          <tbody>
            {acessos.map((acesso) => (
              <tr key={acesso.id} className="border-b border-border last:border-b-0">
                <td className="numerico px-4 py-2.5 text-muted-foreground">{acesso.horario}</td>
                <td className="px-4 py-2.5">
                  <span className="block font-medium text-foreground">{acesso.nome}</span>
                  <span className="numerico block text-xs text-muted-foreground">
                    {documentoCompleto ? acesso.documento : mascararDocumento(acesso.documento)}
                  </span>
                </td>
                <td className="px-4 py-2.5 text-muted-foreground">{acesso.vinculo}</td>
                <td className="px-4 py-2.5 text-muted-foreground">{acesso.imovel}</td>
                <td className="px-4 py-2.5 text-muted-foreground">
                  <span className="block">{acesso.pontoAcesso}</span>
                  {acesso.placa ? (
                    <span className="numerico flex items-center gap-1 text-xs">
                      <Car className="h-3 w-3" aria-hidden />
                      {acesso.placa}
                    </span>
                  ) : null}
                </td>
                <td className="px-4 py-2.5">
                  <TipoAcessoBadge tipo={acesso.tipo} />
                </td>
                <td className="px-4 py-2.5">
                  <BadgeStatus tom={TOM_RESULTADO[acesso.resultado]}>
                    {ROTULO_RESULTADO[acesso.resultado]}
                  </BadgeStatus>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <ul className="divide-y divide-border lg:hidden">
        {acessos.map((acesso) => (
          <li key={acesso.id} className="px-4 py-3">
            <div className="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-2">
              <div className="min-w-0">
                <p className="truncate text-sm font-medium text-foreground">{acesso.nome}</p>
                <p className="numerico truncate text-xs text-muted-foreground">
                  {documentoCompleto ? acesso.documento : mascararDocumento(acesso.documento)}
                </p>
              </div>
              <BadgeStatus tom={TOM_RESULTADO[acesso.resultado]}>
                {ROTULO_RESULTADO[acesso.resultado]}
              </BadgeStatus>
            </div>
            <p className="mt-1 truncate text-xs text-muted-foreground">
              {acesso.vinculo} · {acesso.imovel}
            </p>
            <div className="mt-1.5 grid grid-cols-[minmax(0,1fr)_auto] items-center gap-2">
              <span className="truncate text-xs text-muted-foreground">
                {acesso.pontoAcesso}
                {acesso.placa ? ` · ${acesso.placa}` : ""}
              </span>
              <span className="flex shrink-0 items-center gap-2">
                <TipoAcessoBadge tipo={acesso.tipo} />
                <span className="numerico text-xs text-muted-foreground">{acesso.horario}</span>
              </span>
            </div>
          </li>
        ))}
      </ul>
    </>
  );
}
