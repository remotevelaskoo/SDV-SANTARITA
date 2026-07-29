"use client";

import { useMemo, useState } from "react";

type Stage = "activation" | "login" | "profiles" | "shift" | "app";
type Role = "operator" | "clientAdmin" | "platformAdmin";
type AccessState = "pending" | "approved" | "denied" | "held";

const roleLabels: Record<Role, string> = {
  operator: "Operação da portaria",
  clientAdmin: "Administração do condomínio",
  platformAdmin: "Central técnica da plataforma",
};

const navByRole: Record<Role, { label: string; icon: string }[]> = {
  operator: [
    { label: "Validação de entrada", icon: "✓" },
    { label: "Pré-cadastros", icon: "◎" },
    { label: "Movimentações", icon: "↔" },
    { label: "Caixa", icon: "▣" },
  ],
  clientAdmin: [
    { label: "Visão geral", icon: "⌂" },
    { label: "Pessoas e imóveis", icon: "◉" },
    { label: "Perfis e permissões", icon: "⌘" },
    { label: "Equipamentos", icon: "◇" },
    { label: "Auditoria", icon: "≡" },
  ],
  platformAdmin: [
    { label: "Clientes", icon: "▦" },
    { label: "Instalações", icon: "◇" },
    { label: "Saúde dos serviços", icon: "◌" },
    { label: "Versões", icon: "↑" },
    { label: "Suporte e auditoria", icon: "≡" },
  ],
};

function Logo({ compact = false }: { compact?: boolean }) {
  return (
    <div className={`brand ${compact ? "brandCompact" : ""}`}>
      <div className="brandMark" aria-hidden="true">
        <span />
        <span />
        <span />
      </div>
      <div>
        <strong>SDV ACCESS</strong>
        {!compact && <small>Soluções do Vale</small>}
      </div>
    </div>
  );
}

function StatusDot({ tone = "green" }: { tone?: "green" | "amber" | "red" }) {
  return <span className={`statusDot ${tone}`} aria-hidden="true" />;
}

function ActivationScreen({ onComplete }: { onComplete: () => void }) {
  const [code, setCode] = useState("SR-PORTARIA-01");
  const [location, setLocation] = useState("Portaria Principal");

  return (
    <main className="authLayout">
      <section className="authHero">
        <Logo />
        <div className="heroCopy">
          <span className="eyebrow">Configuração segura do terminal</span>
          <h1>Conecte esta portaria à plataforma.</h1>
          <p>
            A ativação identifica o condomínio, a portaria e o equipamento antes
            de qualquer usuário entrar.
          </p>
          <div className="heroSignals">
            <span>Conexão criptografada</span>
            <span>Certificado individual</span>
            <span>Revogação remota</span>
          </div>
        </div>
        <p className="heroFoot">Ambiente de demonstração · SDV Access 0.1</p>
      </section>

      <section className="authPanel">
        <div className="authCard">
          <div className="stepBadge">1 de 2</div>
          <span className="eyebrow blue">Primeira instalação</span>
          <h2>Ativar terminal</h2>
          <p className="muted">
            Use o código entregue pelo administrador da plataforma.
          </p>

          <label>
            Código de ativação
            <input value={code} onChange={(event) => setCode(event.target.value)} />
          </label>
          <label>
            Ponto de operação
            <select
              value={location}
              onChange={(event) => setLocation(event.target.value)}
            >
              <option>Portaria Principal</option>
              <option>Portaria de Serviço</option>
              <option>Administração</option>
            </select>
          </label>

          <div className="deviceSummary">
            <div>
              <span>Cliente</span>
              <strong>Residencial Santa Rita</strong>
            </div>
            <div>
              <span>Terminal</span>
              <strong>Terminal 01</strong>
            </div>
          </div>

          <button
            className="primaryButton wide"
            onClick={onComplete}
            disabled={!code.trim() || !location}
          >
            Ativar e gerar certificado
          </button>
          <p className="securityNote">
            Nenhum dado pessoal é armazenado nesta etapa.
          </p>
        </div>
      </section>
    </main>
  );
}

function LoginScreen({
  onLogin,
  onActivation,
}: {
  onLogin: () => void;
  onActivation: () => void;
}) {
  const [email, setEmail] = useState("joao.silva@demo.sdv");
  const [password, setPassword] = useState("123456");

  return (
    <main className="authLayout">
      <section className="authHero">
        <Logo />
        <div className="heroCopy">
          <span className="eyebrow">Controle inteligente de acesso</span>
          <h1>Uma entrada segura começa por uma decisão clara.</h1>
          <p>
            Pessoas, autorizações, veículos e eventos reunidos em uma operação
            simples para a portaria.
          </p>
          <div className="terminalCard">
            <div className="terminalIcon">T1</div>
            <div>
              <span>Terminal registrado</span>
              <strong>Santa Rita · Portaria Principal</strong>
            </div>
            <div className="onlineBadge">
              <StatusDot /> Online
            </div>
          </div>
        </div>
        <p className="heroFoot">Ambiente de demonstração · SDV Access 0.1</p>
      </section>

      <section className="authPanel">
        <div className="authCard">
          <span className="eyebrow blue">Acesso individual</span>
          <h2>Entrar no SDV Access</h2>
          <p className="muted">
            Cada ação ficará vinculada ao seu usuário, perfil e terminal.
          </p>
          <label>
            Usuário ou e-mail
            <input
              value={email}
              onChange={(event) => setEmail(event.target.value)}
              autoComplete="username"
            />
          </label>
          <label>
            Senha ou PIN
            <input
              type="password"
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              autoComplete="current-password"
            />
          </label>
          <div className="loginOptions">
            <label className="checkLabel">
              <input type="checkbox" defaultChecked /> Manter terminal ativo
            </label>
            <button className="linkButton">Esqueci meu acesso</button>
          </div>
          <button
            className="primaryButton wide"
            onClick={onLogin}
            disabled={!email.trim() || !password}
          >
            Entrar na demonstração
          </button>
          <button className="quietButton wide" onClick={onActivation}>
            Simular primeira instalação
          </button>
        </div>
      </section>
    </main>
  );
}

function ProfilePicker({ onSelect }: { onSelect: (role: Role) => void }) {
  const profiles: { role: Role; icon: string; description: string; meta: string }[] =
    [
      {
        role: "operator",
        icon: "✓",
        description: "Validar entradas, aprovar pré-cadastros e operar o caixa.",
        meta: "Santa Rita · Portaria Principal",
      },
      {
        role: "clientAdmin",
        icon: "⌘",
        description: "Gerenciar pessoas, imóveis, permissões e equipamentos.",
        meta: "Residencial Santa Rita",
      },
      {
        role: "platformAdmin",
        icon: "◇",
        description: "Acompanhar clientes, instalações, versões e suporte técnico.",
        meta: "Soluções do Vale",
      },
    ];

  return (
    <main className="profilePage">
      <header className="profileHeader">
        <Logo />
        <div className="profileUser">
          <div className="avatar">JS</div>
          <div>
            <strong>João da Silva</strong>
            <span>Usuário autenticado</span>
          </div>
        </div>
      </header>
      <section className="profileContent">
        <span className="eyebrow blue">Contexto de trabalho</span>
        <h1>Como você vai entrar agora?</h1>
        <p>
          O perfil escolhido determina o menu, os dados e as ações disponíveis
          nesta sessão.
        </p>
        <div className="profileGrid">
          {profiles.map((profile) => (
            <button
              className="profileCard"
              key={profile.role}
              onClick={() => onSelect(profile.role)}
            >
              <span className="profileIcon">{profile.icon}</span>
              <span className="profileBody">
                <strong>{roleLabels[profile.role]}</strong>
                <span>{profile.description}</span>
                <small>{profile.meta}</small>
              </span>
              <span className="profileArrow">→</span>
            </button>
          ))}
        </div>
        <div className="profileHint">
          <strong>Protótipo:</strong> os três perfis estão disponíveis para
          permitir a navegação. Na versão real, cada usuário verá somente os
          perfis atribuídos pelo administrador.
        </div>
      </section>
    </main>
  );
}

function ShiftStart({ onStart, onBack }: { onStart: () => void; onBack: () => void }) {
  const [openCash, setOpenCash] = useState(true);

  return (
    <main className="shiftPage">
      <div className="shiftTop">
        <Logo />
        <button className="quietButton" onClick={onBack}>
          Trocar perfil
        </button>
      </div>
      <section className="shiftCard">
        <span className="eyebrow blue">Operação da portaria</span>
        <h1>Iniciar turno</h1>
        <p>Confirme o terminal e a responsabilidade operacional desta sessão.</p>
        <div className="shiftIdentity">
          <div className="avatar large">JS</div>
          <div>
            <strong>João da Silva</strong>
            <span>Operador · Portaria Principal</span>
          </div>
          <span className="verifiedBadge">Identidade verificada</span>
        </div>
        <div className="shiftChoices">
          <label className="choiceCard">
            <input
              type="checkbox"
              checked={openCash}
              onChange={(event) => setOpenCash(event.target.checked)}
            />
            <span>
              <strong>Abrir caixa do turno</strong>
              <small>Saldo inicial simulado: R$ 100,00</small>
            </span>
          </label>
          <div className="choiceCard static">
            <span className="choiceCheck">✓</span>
            <span>
              <strong>Terminal 01 conectado</strong>
              <small>Sincronização e equipamentos disponíveis</small>
            </span>
          </div>
        </div>
        <button className="primaryButton wide" onClick={onStart}>
          {openCash ? "Abrir caixa e iniciar operação" : "Iniciar sem operar caixa"}
        </button>
      </section>
    </main>
  );
}

function OperatorWorkspace({
  accessState,
  onDecision,
}: {
  accessState: AccessState;
  onDecision: (state: AccessState) => void;
}) {
  const stateCopy = {
    pending: ["Aguardando decisão", "amber"],
    approved: ["Entrada liberada", "green"],
    denied: ["Entrada negada", "red"],
    held: ["Salvo para continuar depois", "amber"],
  } as const;

  return (
    <div className="workspace">
      <div className="pageHeading">
        <div>
          <span className="eyebrow blue">Operação em tempo real</span>
          <h1>Validação de entrada</h1>
          <p>Confira a pessoa, o vínculo e o veículo antes de tomar a decisão.</p>
        </div>
        <div className="syncPill">
          <StatusDot />
          Equipamentos sincronizados
        </div>
      </div>

      <div className="searchBar">
        <span>⌕</span>
        <input
          aria-label="Pesquisar pessoa, CPF, placa ou imóvel"
          placeholder="Pesquisar pessoa, CPF, placa ou imóvel"
        />
        <button>Buscar</button>
      </div>

      <div className="validationGrid">
        <section className="panel personPanel">
          <div className="sectionTitle">
            <span className="numberTag">1</span>
            <div>
              <h2>Identificação da pessoa</h2>
              <p>Dados do cadastro e autorização</p>
            </div>
            <span className={`resultBadge ${stateCopy[accessState][1]}`}>
              {stateCopy[accessState][0]}
            </span>
          </div>
          <div className="personContent">
            <div className="personPhoto">
              <div className="photoInitials">MV</div>
              <span>Foto validada</span>
            </div>
            <dl className="detailList">
              <div>
                <dt>Nome completo</dt>
                <dd>Marcos Vinicius da Silva</dd>
              </div>
              <div>
                <dt>Tipo de vínculo</dt>
                <dd><span className="softBadge green">Morador</span></dd>
              </div>
              <div>
                <dt>Documento</dt>
                <dd>***.654.321-**</dd>
              </div>
              <div>
                <dt>Unidade / imóvel</dt>
                <dd>Bloco A · Apto 102</dd>
              </div>
              <div>
                <dt>Validade</dt>
                <dd>Acesso permanente</dd>
              </div>
            </dl>
            <div className="validationChecklist">
              <strong>Cadastro ativo</strong>
              <span>✓ Facial sincronizada</span>
              <span>✓ Documento validado</span>
              <span>✓ Vínculo vigente</span>
            </div>
          </div>
        </section>

        <section className="panel vehiclePanel">
          <div className="sectionTitle">
            <span className="numberTag">2</span>
            <div>
              <h2>Veículo identificado</h2>
              <p>Leitura realizada agora</p>
            </div>
          </div>
          <div className="vehicleContent">
            <div className="plateVisual">
              <span>BRASIL</span>
              <strong>ABC1D23</strong>
            </div>
            <dl className="detailList compact">
              <div><dt>Marca / modelo</dt><dd>Toyota Corolla</dd></div>
              <div><dt>Cor / ano</dt><dd>Prata · 2022</dd></div>
              <div><dt>Proprietário</dt><dd>Marcos Vinicius</dd></div>
            </dl>
            <div className="confidence">
              <div><span>Confiança da leitura</span><strong>98%</strong></div>
              <div className="progress"><span /></div>
              <small>Placa cadastrada e vinculada ao morador.</small>
            </div>
          </div>
        </section>

        <section className="panel contributionPanel">
          <div className="sectionTitle">
            <span className="numberTag">3</span>
            <div>
              <h2>Contribuição desta entrada</h2>
              <p>Regra configurada pelo condomínio</p>
            </div>
            <span className="amount">R$ 15,00</span>
          </div>
          <div className="radioRow">
            <label><input type="radio" name="fee" defaultChecked /> Contribui</label>
            <label><input type="radio" name="fee" /> Isento</label>
            <label><input type="radio" name="fee" /> Não se aplica</label>
          </div>
        </section>
      </div>

      <div className="decisionBar">
        <button className="dangerButton" onClick={() => onDecision("denied")}>
          <span>×</span>
          <div><strong>Negar entrada</strong><small>Registrar motivo</small></div>
        </button>
        <button className="holdButton" onClick={() => onDecision("held")}>
          <span>□</span>
          <div><strong>Salvar sem liberar</strong><small>Continuar depois</small></div>
        </button>
        <button className="successButton" onClick={() => onDecision("approved")}>
          <span>✓</span>
          <div><strong>Validar e liberar</strong><small>Registrar a entrada</small></div>
        </button>
      </div>
    </div>
  );
}

function ClientAdminWorkspace({ activeNav }: { activeNav: string }) {
  const [permissions, setPermissions] = useState<Record<string, boolean>>({
    viewPeople: true,
    createVisitor: true,
    manualRelease: true,
    viewDocuments: false,
    exportData: false,
    manageUsers: false,
  });

  if (activeNav === "Perfis e permissões") {
    const items = [
      ["viewPeople", "Visualizar pessoas e imóveis", "Consulta operacional básica"],
      ["createVisitor", "Cadastrar visitante", "Criar e corrigir pré-cadastros"],
      ["manualRelease", "Realizar liberação manual", "Exige justificativa"],
      ["viewDocuments", "Visualizar documento completo", "Dado pessoal protegido"],
      ["exportData", "Exportar dados", "Ação crítica e auditada"],
      ["manageUsers", "Administrar usuários", "Reservado a supervisores"],
    ];
    return (
      <div className="workspace">
        <div className="pageHeading">
          <div>
            <span className="eyebrow blue">Administração do condomínio</span>
            <h1>Perfis e permissões</h1>
            <p>Defina exatamente o que o perfil Operador da Portaria pode fazer.</p>
          </div>
          <button className="outlineButton">+ Novo perfil</button>
        </div>
        <div className="adminSplit">
          <section className="panel profileList">
            <button className="selectedProfile">
              <span className="profileMiniIcon">OP</span>
              <span><strong>Operador da Portaria</strong><small>8 usuários</small></span>
            </button>
            <button>
              <span className="profileMiniIcon">SU</span>
              <span><strong>Supervisor</strong><small>2 usuários</small></span>
            </button>
            <button>
              <span className="profileMiniIcon">CX</span>
              <span><strong>Operador de Caixa</strong><small>3 usuários</small></span>
            </button>
          </section>
          <section className="panel permissionPanel">
            <div className="sectionTitle">
              <div>
                <h2>Operador da Portaria</h2>
                <p>Permissões aplicadas imediatamente após salvar.</p>
              </div>
              <span className="softBadge blue">Perfil operacional</span>
            </div>
            <div className="permissionList">
              {items.map(([key, title, description]) => (
                <label className="permissionItem" key={key}>
                  <span>
                    <strong>{title}</strong>
                    <small>{description}</small>
                  </span>
                  <input
                    type="checkbox"
                    checked={permissions[key]}
                    onChange={(event) =>
                      setPermissions((current) => ({
                        ...current,
                        [key]: event.target.checked,
                      }))
                    }
                  />
                </label>
              ))}
            </div>
            <div className="panelActions">
              <span>Alterações ficam registradas na auditoria.</span>
              <button className="primaryButton">Salvar permissões</button>
            </div>
          </section>
        </div>
      </div>
    );
  }

  return (
    <div className="workspace">
      <div className="pageHeading">
        <div>
          <span className="eyebrow blue">Residencial Santa Rita</span>
          <h1>{activeNav}</h1>
          <p>Visão administrativa da operação e dos cadastros do condomínio.</p>
        </div>
        <button className="primaryButton">+ Novo cadastro</button>
      </div>
      <div className="statsGrid">
        {[
          ["1.256", "Pessoas cadastradas", "+12 este mês"],
          ["87", "Visitantes hoje", "15 aguardando"],
          ["245", "Entradas hoje", "94% autorizadas"],
          ["12", "Equipamentos", "11 online"],
        ].map(([value, label, note]) => (
          <div className="statCard" key={label}>
            <span>{label}</span><strong>{value}</strong><small>{note}</small>
          </div>
        ))}
      </div>
      <div className="dashboardGrid">
        <section className="panel">
          <div className="sectionTitle">
            <div><h2>Atividade recente</h2><p>Últimos eventos registrados</p></div>
            <button className="linkButton">Ver todos</button>
          </div>
          <div className="activityList">
            {[
              ["Marcos Vinicius", "Entrada autorizada", "08:42", "green"],
              ["João Pereira", "Pré-cadastro pendente", "08:35", "amber"],
              ["Carlos Souza", "Acesso negado", "08:18", "red"],
              ["Maria Oliveira", "Saída registrada", "08:05", "green"],
            ].map(([name, action, time, tone]) => (
              <div key={`${name}-${time}`}>
                <div className="avatar small">{name.slice(0, 2).toUpperCase()}</div>
                <span><strong>{name}</strong><small>{action}</small></span>
                <span className={`eventTone ${tone}`}>{time}</span>
              </div>
            ))}
          </div>
        </section>
        <section className="panel">
          <div className="sectionTitle">
            <div><h2>Estado da operação</h2><p>Atualizado agora</p></div>
          </div>
          <div className="healthList">
            <div><span><StatusDot /> Servidor local</span><strong>Online</strong></div>
            <div><span><StatusDot /> Controladora principal</span><strong>Online</strong></div>
            <div><span><StatusDot /> Reconhecimento facial</span><strong>Online</strong></div>
            <div><span><StatusDot tone="amber" /> Câmera de serviço</span><strong>Revisar</strong></div>
          </div>
        </section>
      </div>
    </div>
  );
}

function PlatformAdminWorkspace({ activeNav }: { activeNav: string }) {
  const [revoked, setRevoked] = useState(false);

  return (
    <div className="workspace">
      <div className="pageHeading">
        <div>
          <span className="eyebrow blue">Soluções do Vale</span>
          <h1>{activeNav}</h1>
          <p>Controle técnico da plataforma sem exposição desnecessária de dados pessoais.</p>
        </div>
        <div className="secureAdmin"><span>◆</span> MFA verificado</div>
      </div>
      <div className="statsGrid">
        {[
          ["4", "Clientes ativos", "Todos operacionais"],
          ["7", "Instalações", "6 sincronizadas"],
          ["99,98%", "Disponibilidade", "Últimos 30 dias"],
          ["0", "Incidentes críticos", "Operação normal"],
        ].map(([value, label, note]) => (
          <div className="statCard" key={label}>
            <span>{label}</span><strong>{value}</strong><small>{note}</small>
          </div>
        ))}
      </div>
      <section className="panel installationPanel">
        <div className="sectionTitle">
          <div>
            <h2>Residencial Santa Rita</h2>
            <p>Portaria Principal · Terminal 01</p>
          </div>
          <span className={`resultBadge ${revoked ? "red" : "green"}`}>
            {revoked ? "Certificado revogado" : "Instalação saudável"}
          </span>
        </div>
        <div className="installationGrid">
          <div><span>Última sincronização</span><strong>Agora</strong></div>
          <div><span>Versão instalada</span><strong>0.1.0-demo</strong></div>
          <div><span>Fila local</span><strong>0 eventos</strong></div>
          <div><span>Certificado</span><strong>{revoked ? "Revogado" : "Válido"}</strong></div>
        </div>
        <div className="technicalActions">
          <button className="outlineButton">Abrir diagnóstico</button>
          <button className="outlineButton">Programar atualização</button>
          <button
            className={revoked ? "primaryButton" : "dangerOutlineButton"}
            onClick={() => setRevoked((value) => !value)}
          >
            {revoked ? "Reativar demonstração" : "Revogar terminal"}
          </button>
        </div>
      </section>
      <div className="dashboardGrid">
        <section className="panel">
          <div className="sectionTitle"><div><h2>Serviços</h2><p>Ambiente de produção</p></div></div>
          <div className="healthList">
            <div><span><StatusDot /> Aplicação principal</span><strong>Saudável</strong></div>
            <div><span><StatusDot /> Banco de dados</span><strong>Saudável</strong></div>
            <div><span><StatusDot /> Fila de eventos</span><strong>Saudável</strong></div>
            <div><span><StatusDot /> Armazenamento</span><strong>Saudável</strong></div>
          </div>
        </section>
        <section className="panel">
          <div className="sectionTitle"><div><h2>Privacidade por padrão</h2><p>Acesso técnico controlado</p></div></div>
          <div className="privacyMessage">
            <span>◈</span>
            <div>
              <strong>Dados pessoais protegidos</strong>
              <p>
                O administrador técnico vê saúde, versões e logs técnicos. Acesso
                excepcional aos dados do cliente exige justificativa e auditoria.
              </p>
            </div>
          </div>
        </section>
      </div>
    </div>
  );
}

function AppShell({
  role,
  onSwitchProfile,
  onLogout,
}: {
  role: Role;
  onSwitchProfile: () => void;
  onLogout: () => void;
}) {
  const nav = navByRole[role];
  const [activeNav, setActiveNav] = useState(nav[0].label);
  const [accessState, setAccessState] = useState<AccessState>("pending");
  const [toast, setToast] = useState("");

  const roleShort = useMemo(() => {
    if (role === "operator") return "Operador";
    if (role === "clientAdmin") return "Admin do condomínio";
    return "Admin da plataforma";
  }, [role]);

  const handleDecision = (state: AccessState) => {
    setAccessState(state);
    const messages = {
      approved: "Entrada registrada e comando de liberação enviado.",
      denied: "Entrada negada e ocorrência registrada.",
      held: "Validação salva para continuar depois.",
      pending: "",
    };
    setToast(messages[state]);
    window.setTimeout(() => setToast(""), 3600);
  };

  return (
    <main className="appShell">
      <aside className="sidebar">
        <div className="sidebarLogo"><Logo /></div>
        <div className="contextBlock">
          <span>{role === "platformAdmin" ? "ORGANIZAÇÃO" : "CONTEXTO ATIVO"}</span>
          <strong>{role === "platformAdmin" ? "Soluções do Vale" : "Santa Rita"}</strong>
          <small>{role === "operator" ? "Portaria Principal · T01" : roleLabels[role]}</small>
        </div>
        <nav aria-label="Navegação principal">
          {nav.map((item) => (
            <button
              key={item.label}
              className={activeNav === item.label ? "active" : ""}
              onClick={() => setActiveNav(item.label)}
            >
              <span>{item.icon}</span>{item.label}
              {item.label === "Pré-cadastros" && <em>12</em>}
            </button>
          ))}
        </nav>
        <div className="sidebarStatus">
          <div><StatusDot /><span><strong>Operação online</strong><small>Sincronizado agora</small></span></div>
        </div>
        <div className="sidebarUser">
          <div className="avatar">JS</div>
          <button onClick={onSwitchProfile}>
            <strong>João da Silva</strong>
            <small>{roleShort} · Trocar</small>
          </button>
          <button className="logoutButton" onClick={onLogout} aria-label="Sair">↪</button>
        </div>
      </aside>
      <section className="appMain">
        <header className="topbar">
          <div className="mobileBrand"><Logo compact /></div>
          <div className="breadcrumb">
            <span>{roleLabels[role]}</span>
            <strong>{activeNav}</strong>
          </div>
          <div className="topbarRight">
            {role === "operator" && (
              <div className="cashBadge"><StatusDot /><span><strong>Caixa aberto</strong><small>R$ 115,00</small></span></div>
            )}
            <span className="clock">24/05/2026 · 08:42</span>
          </div>
        </header>
        {role === "operator" ? (
          <OperatorWorkspace accessState={accessState} onDecision={handleDecision} />
        ) : role === "clientAdmin" ? (
          <ClientAdminWorkspace activeNav={activeNav} />
        ) : (
          <PlatformAdminWorkspace activeNav={activeNav} />
        )}
      </section>
      {toast && <div className="toast">{toast}</div>}
    </main>
  );
}

export default function Home() {
  const [stage, setStage] = useState<Stage>("login");
  const [role, setRole] = useState<Role>("operator");

  if (stage === "activation") {
    return <ActivationScreen onComplete={() => setStage("login")} />;
  }
  if (stage === "login") {
    return (
      <LoginScreen
        onLogin={() => setStage("profiles")}
        onActivation={() => setStage("activation")}
      />
    );
  }
  if (stage === "profiles") {
    return (
      <ProfilePicker
        onSelect={(selectedRole) => {
          setRole(selectedRole);
          setStage(selectedRole === "operator" ? "shift" : "app");
        }}
      />
    );
  }
  if (stage === "shift") {
    return (
      <ShiftStart
        onStart={() => setStage("app")}
        onBack={() => setStage("profiles")}
      />
    );
  }
  return (
    <AppShell
      role={role}
      onSwitchProfile={() => setStage("profiles")}
      onLogout={() => setStage("login")}
    />
  );
}
