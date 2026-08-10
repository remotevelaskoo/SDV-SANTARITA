/**
 * Dados de exemplo do Dashboard.
 * Fonte: 004_UX_UI_DASHBOARD §8 (indicadores), §9 (acessos recentes), §10 (gráfico).
 * Estrutura pensada para ser substituída por API sem alterar as telas.
 */

import type { Permissao } from "./perfis";

export type TendenciaComparacao = "alta" | "baixa" | "estavel";

export interface Indicador {
  id: string;
  codigo: string;
  rotulo: string;
  valor: number;
  unidade: "quantidade" | "moeda";
  periodo: string;
  comparacao?: {
    rotulo: string;
    variacao: number;
    tendencia: TendenciaComparacao;
  };
  atualizadoEm: string;
  destino?: string;
  permissao: Permissao;
}

export const INDICADORES: Indicador[] = [
  {
    id: "pessoas-cadastradas",
    codigo: "UXD-MET-001",
    rotulo: "Pessoas cadastradas",
    valor: 4182,
    unidade: "quantidade",
    periodo: "Base atual",
    comparacao: { rotulo: "vs. mês anterior", variacao: 2.4, tendencia: "alta" },
    atualizadoEm: "16:02",
    destino: "pessoas",
    permissao: "dashboard.metricas.pessoas",
  },
  {
    id: "visitantes-hoje",
    codigo: "UXD-MET-002",
    rotulo: "Visitantes hoje",
    valor: 137,
    unidade: "quantidade",
    periodo: "Hoje, desde 00h00",
    comparacao: { rotulo: "vs. mesmo dia da semana", variacao: 11.8, tendencia: "alta" },
    atualizadoEm: "16:02",
    destino: "pessoas",
    permissao: "dashboard.metricas.operacao",
  },
  {
    id: "entradas-hoje",
    codigo: "UXD-MET-003",
    rotulo: "Entradas hoje",
    valor: 612,
    unidade: "quantidade",
    periodo: "Hoje, desde 00h00",
    comparacao: { rotulo: "vs. ontem", variacao: 4.1, tendencia: "alta" },
    atualizadoEm: "16:02",
    destino: "entradas-saidas",
    permissao: "dashboard.metricas.operacao",
  },
  {
    id: "saidas-hoje",
    codigo: "UXD-MET-004",
    rotulo: "Saídas hoje",
    valor: 574,
    unidade: "quantidade",
    periodo: "Hoje, desde 00h00",
    comparacao: { rotulo: "vs. ontem", variacao: 1.3, tendencia: "baixa" },
    atualizadoEm: "16:02",
    destino: "entradas-saidas",
    permissao: "dashboard.metricas.operacao",
  },
  {
    id: "moradores",
    codigo: "UXD-MET-005",
    rotulo: "Moradores",
    valor: 2914,
    unidade: "quantidade",
    periodo: "Vínculos vigentes",
    comparacao: { rotulo: "vs. mês anterior", variacao: 0, tendencia: "estavel" },
    atualizadoEm: "16:00",
    destino: "pessoas",
    permissao: "dashboard.metricas.pessoas",
  },
  {
    id: "prestadores",
    codigo: "UXD-MET-006",
    rotulo: "Prestadores",
    valor: 268,
    unidade: "quantidade",
    periodo: "Autorizações vigentes",
    comparacao: { rotulo: "vs. mês anterior", variacao: 6.7, tendencia: "alta" },
    atualizadoEm: "16:00",
    destino: "prestadores",
    permissao: "dashboard.metricas.pessoas",
  },
  {
    id: "veiculos-cadastrados",
    codigo: "UXD-MET-007",
    rotulo: "Veículos cadastrados",
    valor: 1903,
    unidade: "quantidade",
    periodo: "Base atual",
    comparacao: { rotulo: "vs. mês anterior", variacao: 1.9, tendencia: "alta" },
    atualizadoEm: "16:00",
    destino: "veiculos",
    permissao: "dashboard.metricas.pessoas",
  },
  {
    id: "arrecadacao-hoje",
    codigo: "UXD-MET-008",
    rotulo: "Arrecadação hoje",
    valor: 3487.5,
    unidade: "moeda",
    periodo: "Turno do caixa aberto",
    comparacao: { rotulo: "vs. ontem", variacao: 8.2, tendencia: "alta" },
    atualizadoEm: "16:02",
    permissao: "dashboard.metricas.arrecadacao",
  },
];

export type TipoAcesso = "entrada" | "saida";
export type ResultadoAcesso = "liberado" | "negado" | "pendente";
export type VinculoPessoa = "Morador" | "Inquilino" | "Visitante" | "Prestador" | "Outro ocupante";

export interface AcessoRecente {
  id: string;
  horario: string;
  nome: string;
  documento: string;
  vinculo: VinculoPessoa;
  imovel: string;
  pontoAcesso: string;
  tipo: TipoAcesso;
  resultado: ResultadoAcesso;
  placa?: string;
}

export const ACESSOS_RECENTES: AcessoRecente[] = [
  {
    id: "acs-1",
    horario: "16:01",
    nome: "Camila Andrade",
    documento: "412.908.331-07",
    vinculo: "Visitante",
    imovel: "Bloco B — Apto 304",
    pontoAcesso: "Portaria Principal",
    tipo: "entrada",
    resultado: "liberado",
    placa: "RQK8H21",
  },
  {
    id: "acs-2",
    horario: "15:58",
    nome: "Eduardo Nogueira",
    documento: "228.114.760-55",
    vinculo: "Morador",
    imovel: "Bloco A — Apto 112",
    pontoAcesso: "Portaria Principal",
    tipo: "saida",
    resultado: "liberado",
    placa: "GFT4A09",
  },
  {
    id: "acs-3",
    horario: "15:54",
    nome: "Luciana Ferraz",
    documento: "905.443.218-90",
    vinculo: "Prestador",
    imovel: "Área comum — Manutenção",
    pontoAcesso: "Portão de Serviço",
    tipo: "entrada",
    resultado: "pendente",
  },
  {
    id: "acs-4",
    horario: "15:47",
    nome: "Rafael Domingues",
    documento: "336.771.004-12",
    vinculo: "Inquilino",
    imovel: "Bloco C — Apto 501",
    pontoAcesso: "Portaria Principal",
    tipo: "entrada",
    resultado: "liberado",
  },
  {
    id: "acs-5",
    horario: "15:41",
    nome: "Bianca Moretti",
    documento: "774.200.615-38",
    vinculo: "Visitante",
    imovel: "Bloco A — Apto 208",
    pontoAcesso: "Portaria Principal",
    tipo: "entrada",
    resultado: "negado",
  },
  {
    id: "acs-6",
    horario: "15:33",
    nome: "Sérgio Aparecido Luz",
    documento: "150.982.447-61",
    vinculo: "Prestador",
    imovel: "Bloco B — Apto 706",
    pontoAcesso: "Portão de Serviço",
    tipo: "entrada",
    resultado: "liberado",
    placa: "LMD7C44",
  },
  {
    id: "acs-7",
    horario: "15:26",
    nome: "Priscila Tavares",
    documento: "601.339.872-04",
    vinculo: "Morador",
    imovel: "Bloco C — Apto 402",
    pontoAcesso: "Pedestres",
    tipo: "saida",
    resultado: "liberado",
  },
  {
    id: "acs-8",
    horario: "15:19",
    nome: "Otávio Bastos",
    documento: "089.657.110-23",
    vinculo: "Outro ocupante",
    imovel: "Bloco A — Apto 903",
    pontoAcesso: "Portaria Principal",
    tipo: "entrada",
    resultado: "liberado",
  },
];

export function obterHistoricoAcessos(): AcessoRecente[] {
  if (typeof window === "undefined") return ACESSOS_RECENTES;
  const historicoLocal = JSON.parse(window.localStorage.getItem("sdv-access.historico") || "[]");
  return [...historicoLocal, ...ACESSOS_RECENTES].slice(0, 50);
}

export function registrarAcessoLocal(acesso: Omit<AcessoRecente, "id">) {
  if (typeof window === "undefined") return;
  const historico = JSON.parse(window.localStorage.getItem("sdv-access.historico") || "[]");
  const novoAcesso = { ...acesso, id: `acs-local-${Date.now()}` };
  window.localStorage.setItem("sdv-access.historico", JSON.stringify([novoAcesso, ...historico].slice(0, 50)));
}

export type AgrupamentoGrafico = "hoje" | "7dias" | "30dias";

export interface PontoSerie {
  rotulo: string;
  entradas: number;
  saidas: number;
}

export const SERIES_ENTRADAS_SAIDAS: Record<AgrupamentoGrafico, PontoSerie[]> = {
  hoje: [
    { rotulo: "00h", entradas: 8, saidas: 14 },
    { rotulo: "03h", entradas: 4, saidas: 6 },
    { rotulo: "06h", entradas: 41, saidas: 96 },
    { rotulo: "09h", entradas: 122, saidas: 71 },
    { rotulo: "12h", entradas: 158, saidas: 129 },
    { rotulo: "15h", entradas: 181, saidas: 148 },
    { rotulo: "18h", entradas: 74, saidas: 82 },
    { rotulo: "21h", entradas: 24, saidas: 28 },
  ],
  "7dias": [
    { rotulo: "Seg", entradas: 588, saidas: 561 },
    { rotulo: "Ter", entradas: 604, saidas: 592 },
    { rotulo: "Qua", entradas: 631, saidas: 608 },
    { rotulo: "Qui", entradas: 612, saidas: 574 },
    { rotulo: "Sex", entradas: 702, saidas: 688 },
    { rotulo: "Sáb", entradas: 489, saidas: 512 },
    { rotulo: "Dom", entradas: 352, saidas: 377 },
  ],
  "30dias": [
    { rotulo: "Sem. 1", entradas: 3921, saidas: 3844 },
    { rotulo: "Sem. 2", entradas: 4108, saidas: 4021 },
    { rotulo: "Sem. 3", entradas: 3987, saidas: 3902 },
    { rotulo: "Sem. 4", entradas: 4212, saidas: 4160 },
  ],
};

export interface AlertaCritico {
  id: string;
  severidade: "danger" | "warning" | "info";
  titulo: string;
  descricao: string;
}

export const ALERTAS_CRITICOS: AlertaCritico[] = [
  {
    id: "alr-1",
    severidade: "warning",
    titulo: "3 pré-cadastros aguardando análise há mais de 24 horas",
    descricao: "Solicitações de visitantes pendentes de decisão da administração.",
  },
  {
    id: "alr-2",
    severidade: "danger",
    titulo: "Controladora do Portão de Serviço sem comunicação",
    descricao: "Última sincronização às 14:52. Operação em modo de contingência.",
  },
];

export interface NotificacaoOperacional {
  id: string;
  titulo: string;
  detalhe: string;
  horario: string;
  lida: boolean;
}

export const NOTIFICACOES: NotificacaoOperacional[] = [
  {
    id: "ntf-1",
    titulo: "Pré-cadastro recebido",
    detalhe: "Visitante para Bloco B — Apto 304",
    horario: "15:57",
    lida: false,
  },
  {
    id: "ntf-2",
    titulo: "Vínculo de inquilino encerrado",
    detalhe: "Bloco C — Apto 501, acesso revogado automaticamente",
    horario: "15:12",
    lida: false,
  },
  {
    id: "ntf-3",
    titulo: "Caixa aberto",
    detalhe: "Guarita — Caixa 01, turno de Tatiane Souza",
    horario: "13:00",
    lida: true,
  },
];

export interface SituacaoCaixa {
  situacao: "aberto" | "fechado";
  identificacao: string;
  abertoEm: string;
}

export const SITUACAO_CAIXA: SituacaoCaixa = {
  situacao: "aberto",
  identificacao: "Caixa 01",
  abertoEm: "13:00",
};
