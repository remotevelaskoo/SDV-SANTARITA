/**
 * Catálogo de perfis, permissões e usuários de exemplo.
 * Fonte: 001_PRODUCT_BOOK §7 (personas) e 004_UX_UI_DASHBOARD §3 e §3.5.
 *
 * ATENÇÃO: dados de exemplo, sem backend. A verificação de permissão real
 * deverá ocorrer no servidor (003_DESIGN_SYSTEM §11.2) quando houver API.
 */

export type Permissao =
  | "dashboard.visualizar"
  | "dashboard.metricas.pessoas"
  | "dashboard.metricas.operacao"
  | "dashboard.metricas.arrecadacao"
  | "dashboard.acessos_recentes"
  | "dashboard.grafico"
  | "dashboard.documento.completo"
  | "busca.global"
  | "validacao.operar"
  | "pre_cadastro.analisar"
  | "imovel.visualizar"
  | "imovel.editar"
  | "pessoa.visualizar"
  | "veiculo.visualizar"
  | "acesso.historico"
  | "caixa.operar"
  | "administracao.acessar"
  | "relatorio.visualizar"
  | "log.visualizar";

export type PerfilId = "portaria" | "administrador" | "gestor" | "caixa" | "auditor";

export interface Perfil {
  id: PerfilId;
  nome: string;
  descricao: string;
  permissoes: Permissao[];
}

const PERMISSOES_OPERACAO: Permissao[] = [
  "dashboard.visualizar",
  "dashboard.metricas.pessoas",
  "dashboard.metricas.operacao",
  "dashboard.acessos_recentes",
  "busca.global",
];

export const PERFIS: Record<PerfilId, Perfil> = {
  portaria: {
    id: "portaria",
    nome: "Operador de portaria",
    descricao: "Consulta, valida e libera acessos com rapidez.",
    permissoes: [
      ...PERMISSOES_OPERACAO,
      "validacao.operar",
      "pessoa.visualizar",
      "imovel.visualizar",
      "veiculo.visualizar",
      "acesso.historico",
    ],
  },
  administrador: {
    id: "administrador",
    nome: "Administrador",
    descricao: "Gerencia usuários, perfis, permissões, parâmetros e cadastros estruturais.",
    permissoes: [
      ...PERMISSOES_OPERACAO,
      "dashboard.metricas.arrecadacao",
      "dashboard.grafico",
      "dashboard.documento.completo",
      "validacao.operar",
      "pre_cadastro.analisar",
      "pessoa.visualizar",
      "imovel.visualizar",
      "imovel.editar",
      "veiculo.visualizar",
      "acesso.historico",
      "caixa.operar",
      "administracao.acessar",
      "relatorio.visualizar",
      "log.visualizar",
    ],
  },
  gestor: {
    id: "gestor",
    nome: "Gestor ou síndico",
    descricao: "Consulta informações operacionais, históricos, indicadores e auditoria.",
    permissoes: [
      "dashboard.visualizar",
      "dashboard.metricas.pessoas",
      "dashboard.metricas.operacao",
      "dashboard.metricas.arrecadacao",
      "dashboard.acessos_recentes",
      "dashboard.grafico",
      "busca.global",
      "pessoa.visualizar",
      "imovel.visualizar",
      "veiculo.visualizar",
      "acesso.historico",
      "relatorio.visualizar",
    ],
  },
  caixa: {
    id: "caixa",
    nome: "Operador de caixa",
    descricao: "Opera a situação do caixa e acompanha a arrecadação do turno.",
    permissoes: [
      ...PERMISSOES_OPERACAO,
      "dashboard.metricas.arrecadacao",
      "caixa.operar",
      "pessoa.visualizar",
    ],
  },
  auditor: {
    id: "auditor",
    nome: "Auditor",
    descricao: "Consulta registros, histórico e logs sem alterar cadastros.",
    permissoes: [
      "dashboard.visualizar",
      "dashboard.metricas.pessoas",
      "dashboard.metricas.operacao",
      "dashboard.acessos_recentes",
      "dashboard.grafico",
      "busca.global",
      "acesso.historico",
      "relatorio.visualizar",
      "log.visualizar",
    ],
  },
};

export interface UsuarioExemplo {
  identificacao: string;
  senha: string;
  nome: string;
  perfil: PerfilId;
  condominio: string;
  pontoAcesso: string;
}

/** Credenciais de demonstração. Substituir por autenticação real do backend. */
export const USUARIOS_EXEMPLO: UsuarioExemplo[] = [
  {
    identificacao: "portaria",
    senha: "sdv2026",
    nome: "Marcos Ribeiro",
    perfil: "portaria",
    condominio: "Residencial Santa Rita",
    pontoAcesso: "Portaria Principal",
  },
  {
    identificacao: "admin",
    senha: "sdv2026",
    nome: "Vinicius Velasco",
    perfil: "administrador",
    condominio: "Residencial Santa Rita",
    pontoAcesso: "Administração",
  },
  {
    identificacao: "gestor",
    senha: "sdv2026",
    nome: "Helena Prado",
    perfil: "gestor",
    condominio: "Residencial Santa Rita",
    pontoAcesso: "Administração",
  },
  {
    identificacao: "caixa",
    senha: "sdv2026",
    nome: "Tatiane Souza",
    perfil: "caixa",
    condominio: "Residencial Santa Rita",
    pontoAcesso: "Guarita — Caixa 01",
  },
  {
    identificacao: "auditor",
    senha: "sdv2026",
    nome: "Rogério Lima",
    perfil: "auditor",
    condominio: "Residencial Santa Rita",
    pontoAcesso: "Remoto",
  },
];
