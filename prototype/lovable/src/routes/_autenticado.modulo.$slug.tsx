import { createFileRoute } from "@tanstack/react-router";
import { Construction } from "lucide-react";

import { EstadoVazio, Painel } from "@/components/ds";
import { AppShell } from "@/components/shell/app-shell";
import { ROTULOS_MODULO } from "@/components/shell/navegacao-lateral";

export const Route = createFileRoute("/_autenticado/modulo/$slug")({
  head: () => ({
    meta: [
      { title: "Módulo — SDV Access Santa Rita" },
      {
        name: "description",
        content: "Módulo do sistema de controle de acesso SDV Access Santa Rita.",
      },
      { property: "og:title", content: "Módulo — SDV Access Santa Rita" },
      {
        property: "og:description",
        content: "Módulo do sistema de controle de acesso SDV Access Santa Rita.",
      },
    ],
  }),
  component: ModuloEmConstrucao,
});

function ModuloEmConstrucao() {
  const { slug } = Route.useParams();
  const titulo = ROTULOS_MODULO[slug] ?? "Módulo";

  return (
    <AppShell titulo={titulo} descricao="Operação e gestão do sistema">
      <Painel>
        <EstadoVazio
          icone={Construction}
          titulo={`${titulo} ainda não implementado`}
          descricao="Este módulo entra nas próximas fases do roadmap. A navegação e o design system já estão disponíveis."
        />
      </Painel>
    </AppShell>
  );
}
