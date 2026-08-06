# SDV Access — Protótipo navegável do frontend

**Documento:** SDV-UX-002  
**Versão:** 0.1.0  
**Status:** Em validação  
**Data:** Julho/2026  
**Escopo:** autenticação, perfis e primeira operação de acesso

## 1. Objetivo

Registrar o comportamento do primeiro protótipo navegável do SDV Access. O artefato permite que Product Owner, desenvolvedores e futuros usuários validem o fluxo antes da implementação do backend.

O protótipo está publicado em:

<https://sdv-access-santa-rita-demo.lucaspastorelli.chatgpt.site>

O código correspondente está no diretório [`prototype/`](../prototype/).

## 2. Natureza do protótipo

Esta versão é uma demonstração funcional de frontend:

- navega entre telas e perfis;
- altera estados locais da interface;
- simula decisões operacionais;
- não autentica pessoas de verdade;
- não persiste dados;
- não envia informações para banco de dados;
- não se comunica com controladoras, câmeras, OCR, reconhecimento facial ou leitura de placas.

A tecnologia React/vinext foi usada para acelerar a validação visual. Ela não altera a decisão oficial de implementar o MVP em Laravel, Blade e Livewire, conforme `000_DIRETRIZES_DO_PROJETO.md`.

## 3. Modelo de autenticação representado

O acesso foi dividido em três camadas para evitar o compartilhamento de uma única senha entre todas as pessoas:

1. **Ativação do terminal:** vincula o computador ou dispositivo à instalação Santa Rita.
2. **Login individual:** identifica a pessoa que está usando o sistema.
3. **Perfil ativo:** define quais telas e ações aquela pessoa pode utilizar.

```text
Terminal autorizado
        ↓
Login individual
        ↓
Seleção de perfil permitido
        ↓
Ambiente correspondente ao perfil
```

Uma mesma pessoa poderá possuir mais de um perfil autorizado. A troca de perfil deverá ser registrada na auditoria da solução final.

## 4. Perfis

### 4.1 Operacional

Usuário da portaria ou operação diária. O protótipo permite:

- abrir turno e caixa;
- consultar o cadastro apresentado;
- conferir foto, documento, vínculo, validade e veículo;
- liberar entrada;
- negar entrada;
- salvar a análise sem liberar.

O perfil operacional não deve administrar permissões, configurações globais ou manutenção da plataforma.

### 4.2 Administrador do cliente

Responsável pelo ambiente Santa Rita. O protótipo representa:

- visão geral da operação;
- quantidade de acessos e pendências;
- usuários do ambiente;
- concessão e retirada demonstrativa de permissões operacionais;
- acesso às configurações do cliente.

Esse administrador controla o que o perfil operacional pode visualizar e executar dentro do próprio ambiente.

### 4.3 Administrador da plataforma

Perfil técnico dos responsáveis pelo desenvolvimento e manutenção. O protótipo representa:

- visão global dos ambientes;
- acompanhamento de saúde dos serviços;
- manutenção e atualizações;
- auditoria técnica;
- revogação e reativação demonstrativa de terminais.

Esse perfil deve ser restrito, protegido por autenticação forte e separado do administrador do cliente.

## 5. Fluxos implementados

### 5.1 Primeiro acesso

1. Informar código de ativação do terminal.
2. Confirmar o vínculo com Santa Rita.
3. Informar credenciais individuais.
4. Selecionar um dos perfis disponíveis.
5. Entrar no ambiente correspondente.

### 5.2 Fluxo operacional

1. Abrir turno e caixa.
2. Receber ou localizar um cadastro.
3. Conferir pessoa, documento, veículo, vínculo e validade.
4. Informar a contribuição/taxa quando aplicável.
5. Registrar observação.
6. Liberar, negar ou deixar pendente.
7. Exibir o resultado da decisão na interface.

### 5.3 Gestão do cliente

1. Acessar o painel administrativo.
2. Consultar indicadores e situação da operação.
3. Acessar a gestão de equipe.
4. Alterar permissões demonstrativas de um usuário.

### 5.4 Gestão da plataforma

1. Acessar o console técnico.
2. Consultar ambientes e terminais.
3. Revogar um terminal.
4. Reativar o terminal.
5. Registrar futuramente toda ação em auditoria.

## 6. Dados usados

As pessoas, CPFs, veículos, valores, horários e credenciais exibidos são dados fictícios de demonstração. Não devem ser tratados como cadastros reais nem reutilizados na base de produção.

## 7. Segurança esperada para a implementação real

O backend deverá aplicar as permissões independentemente do que estiver visível no frontend. Ocultar um botão não é controle de segurança.

Requisitos mínimos:

- senha armazenada com hash forte;
- autenticação multifator para administradores;
- sessão com expiração e revogação;
- autorização por ambiente e por ação;
- privilégio mínimo;
- trilha de auditoria imutável para acessos e decisões;
- criptografia em trânsito e proteção dos dados armazenados;
- backups automáticos com teste de restauração;
- segregação entre dados de clientes;
- proteção de documentos, selfies e dados biométricos;
- política de retenção e descarte compatível com a LGPD;
- revisão humana de resultados de OCR ou IA antes da confirmação;
- segredos fora do código-fonte e do repositório.

## 8. Critérios de validação desta etapa

O protótipo estará aprovado quando os responsáveis confirmarem:

- os três perfis e seus limites;
- a diferença entre ativação do terminal e login individual;
- as informações necessárias para validar uma entrada;
- os estados liberar, negar e pendente;
- os indicadores necessários para o administrador do cliente;
- as ferramentas realmente necessárias ao administrador da plataforma;
- a linguagem e a ordem das etapas apresentadas.

## 9. Limitações conhecidas

- não existe backend ou API;
- não existe banco de dados;
- credenciais são apenas demonstrativas;
- estados são perdidos ao recarregar a página;
- não há upload ou leitura real de documentos;
- não há integração com hardware;
- não há reconhecimento facial ou LPR real;
- não há cálculo financeiro real;
- não há logs persistentes;
- os menus ainda não representam todos os módulos do Product Book.

## 10. Como executar

Pré-requisito: Node.js 22.13 ou superior.

```bash
cd prototype
npm install
npm run dev
```

Para gerar e verificar a versão compilada:

```bash
npm run build
npm test
```

## 11. Próximas decisões

1. Homologar nomes, perfis e fluxos com o Product Owner.
2. Criar mapa completo de telas e permissões.
3. Transformar as telas aprovadas em especificações de aceite.
4. Modelar imóveis, pessoas, vínculos, veículos e acessos.
5. Definir contratos de API e eventos de auditoria.
6. Implementar autenticação real e autorização no backend.
7. Criar prova de conceito separada para OCR de documentos.
8. Definir integração com controladoras e equipamentos.
9. Elaborar plano de segurança, backup, restauração e resposta a incidentes.

## 12. Controle de alterações

| Versão | Data | Alteração |
|---|---|---|
| 0.1.0 | Julho/2026 | Registro inicial do protótipo de autenticação, perfis e operação |
