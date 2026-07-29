# SDV ACCESS — ADMINISTRAÇÃO
## UX/UI de usuários, permissões, configurações, equipamentos e auditoria

**Documento:** SDV-ADM-008  
**Versão:** 1.0.1  
**Status:** Aprovado  
**Produto:** SDV Access — Implantação Santa Rita  
**Marca proprietária:** Soluções do Vale Tecnologia  
**Responsável pelo produto:** Vinicius Velasco de Azevedo  
**Data:** Julho de 2026

---

## Controle de versões

| Versão | Data | Responsável | Alteração |
|---|---|---|---|
| 1.0.0 | Julho/2026 | Soluções do Vale | Especificação inicial dos módulos administrativos |
| 1.0.1 | 28/07/2026 | Product Owner | Aprovação formal da especificação de Administração |

---

# 1. Objetivo

Este documento especifica a experiência administrativa do SDV Access para:

- usuários;
- perfis de acesso;
- permissões granulares;
- sessões e credenciais administrativas;
- configurações da implantação;
- áreas, horários e pontos de acesso;
- equipamentos e integrações;
- catálogos de motivos;
- notificações operacionais;
- logs e auditoria;
- operações críticas e histórico.

A Administração deverá aplicar menor privilégio, impedir alterações silenciosas e manter segregação entre a implantação Santa Rita e futuras implantações.

---

# 2. Fontes e rastreabilidade

## 2.1 Referências visuais

As referências em `docs/references/` mostram:

- menu lateral azul-marinho;
- grupo “Administração”;
- Usuários;
- Perfis de Acesso;
- Configurações;
- Logs e Auditoria;
- equipamentos ou controladora;
- reconhecimento facial;
- leitura de placas;
- usuário autenticado e função.

Não existem telas administrativas completas para todos esses módulos. Portanto:

- a arquitetura de navegação e identidade visual é oficial;
- comportamentos e requisitos são especificados neste documento;
- lista, formulário, detalhe e fluxos críticos deverão ser prototipados;
- nenhum painel administrativo genérico poderá substituir a experiência aprovada.

## 2.2 Regras de negócio

| Identificador | Relação |
|---|---|
| `RN-040` | Decisão centralizada no SDV Access |
| `RN-043` e `RN-044` | Liberação manual e exceção justificadas |
| `RN-045` | Credenciais com estado e vigência |
| `RN-046` | Operações auditáveis |
| `RN-047` | Conteúdo mínimo do log |
| `RN-048` | Imutabilidade lógica |
| `RN-049` | Valores anterior e posterior |
| `RN-050` | Usuário individual |
| `RN-051` | Perfil e exceção individual |
| `RN-052` | Menor privilégio |
| `RN-053` | Confirmação ou permissão para ações críticas |
| `RN-054` | Inativação imediata |

## 2.3 Requisitos

| Identificador | Relação |
|---|---|
| `RF-001` e `RF-002` | Autenticação e recuperação |
| `RF-017` | Gerenciar usuários |
| `RF-018` | Gerenciar perfis |
| `RF-019` | Consultar auditoria |
| `RF-032` e `RF-033` | Integrações facial e LPR |
| `RF-037` | Situação da integração |
| `RF-039` | Notificações operacionais |
| `RF-040` | Permissões por ação |
| `RNF-001` e `RNF-011` | Segurança |
| `RNF-002` | LGPD |
| `RNF-008` | Observabilidade |
| `RNF-009` | Integrações desacopladas |
| `RNF-012` | Segregação |
| `RNF-014` e `RNF-015` | Idempotência e tolerância a falhas |
| `RNF-017` | Auditoria de exportação |
| `RNF-019` | Acessibilidade |

## 2.4 Componentes do Design System

- app shell;
- sidebar;
- cabeçalho operacional;
- botões;
- formulários;
- autocomplete;
- data e período;
- checkbox, radio e switch;
- badges;
- alertas;
- tabelas;
- listas de atividade;
- tabs;
- paginação;
- modal;
- drawer;
- estado de sincronização.

---

# 3. Escopo administrativo

```text
Administração
├── Identidade e Acesso
│   ├── Usuários
│   ├── Perfis
│   ├── Permissões
│   ├── Exceções individuais
│   └── Sessões
├── Configurações da Implantação
│   ├── Dados gerais
│   ├── Regras operacionais
│   ├── Catálogos
│   └── Notificações
├── Controle de Acesso
│   ├── Áreas
│   ├── Horários
│   ├── Pontos de acesso
│   ├── Equipamentos
│   └── Adaptadores
└── Governança
    ├── Auditoria
    ├── Exportações
    └── Histórico de configurações
```

---

# 4. Limites de autoridade

## 4.1 Administrador da implantação

Gerencia apenas:

- usuários da implantação;
- perfis permitidos;
- configurações locais;
- equipamentos locais;
- parâmetros autorizados;
- auditoria conforme permissão.

## 4.2 Administração global futura

Ficam fora do MVP:

- criação comercial de clientes;
- administração irrestrita de todas as implantações;
- movimentação de dados entre clientes;
- impersonação;
- faturamento da plataforma;
- configuração global sem segregação.

Qualquer administração global exigirá modelo próprio, ADR e controles adicionais.

## 4.3 Suporte técnico

Suporte não receberá acesso permanente por pressuposto. Acesso excepcional deverá possuir:

- finalidade;
- autorização;
- duração;
- escopo;
- auditoria;
- revogação.

---

# 5. Perfis administrativos

## 5.1 Administrador

Gerencia usuários, perfis, parâmetros e equipamentos conforme escopo.

## 5.2 Gestor ou síndico

Consulta configurações, relatórios e auditoria, com manutenção limitada.

## 5.3 Auditor

Consulta sem alteração.

## 5.4 Administrador técnico

Quando aprovado, gerencia equipamentos e integrações sem receber automaticamente acesso a documentos pessoais ou caixa.

## 5.5 Administrador de identidade

Quando necessário, gerencia usuários e perfis sem acessar dados operacionais além do mínimo.

Perfis especializados são preferíveis a um superadministrador cotidiano.

---

# 6. Navegação administrativa

## 6.1 Estrutura

O menu deverá:

- manter identidade visual aprovada;
- agrupar itens;
- mostrar somente módulos autorizados;
- destacar módulo atual;
- exibir alertas de configuração crítica;
- preservar acesso ao contexto da implantação.

## 6.2 Itens

- Usuários;
- Perfis de Acesso;
- Configurações;
- Equipamentos;
- Pontos de Acesso;
- Logs e Auditoria.

Itens poderão ser agrupados sem alterar suas permissões.

## 6.3 Acesso direto

Uma rota administrativa acessada sem permissão deverá retornar acesso negado, mesmo que o item esteja oculto.

---

# 7. Usuários — lista

## 7.1 Busca e filtros

- nome;
- e-mail ou identificador;
- perfil;
- situação;
- último acesso;
- data de criação;
- unidade ou implantação;
- bloqueio;
- expiração.

## 7.2 Colunas

- usuário;
- identificador;
- perfis;
- situação;
- último acesso;
- sessões;
- atualização;
- ações.

## 7.3 Situações

- convidado;
- pendente de ativação;
- ativo;
- bloqueado;
- inativo;
- credencial expirada;
- recuperação pendente.

## 7.4 Ações

- visualizar;
- editar dados permitidos;
- atribuir perfil;
- bloquear;
- inativar;
- encerrar sessões;
- enviar ativação;
- iniciar recuperação segura;
- consultar auditoria.

Não haverá exclusão destrutiva de usuário operacional.

---

# 8. Usuários — criação

## 8.1 Campos

- nome;
- nome de exibição;
- e-mail ou identificador;
- contato;
- implantação;
- perfil inicial;
- vigência;
- situação;
- observação administrativa;
- exigências de segurança.

## 8.2 Convite

Fluxo recomendado:

1. administrador informa dados;
2. sistema verifica duplicidade;
3. administrador atribui menor perfil;
4. sistema cria usuário pendente;
5. envia convite temporário;
6. usuário define credencial;
7. autenticação é validada;
8. usuário torna-se ativo.

Senha inicial não deverá ser compartilhada em texto claro.

## 8.3 Duplicidade

Uma identidade existente deverá ser localizada e vinculada ao contexto permitido, sem criar credenciais paralelas indevidas.

## 8.4 Vigência

Acesso temporário deverá possuir início e término. Ao expirar:

- novas sessões são impedidas;
- sessões existentes seguem política aprovada;
- histórico permanece.

---

# 9. Usuários — detalhe

## 9.1 Resumo

- nome;
- identificador;
- situação;
- perfis;
- exceções;
- vigência;
- último acesso;
- sessões ativas;
- eventos de segurança.

## 9.2 Tabs

- Dados;
- Perfis e permissões;
- Sessões;
- Histórico.

## 9.3 Ações críticas

- bloquear;
- inativar;
- encerrar sessões;
- retirar perfil;
- redefinir fatores;
- alterar identidade principal.

Cada ação deverá explicar impacto e exigir confirmação ou justificativa conforme risco.

---

# 10. Inativação e bloqueio

## 10.1 Bloquear

Uso temporário ou de segurança:

- impede novos acessos;
- registra motivo;
- pode manter cadastro;
- possui revisão.

## 10.2 Inativar

Uso para encerramento:

- impede novas sessões;
- revoga ou encerra autorizações administrativas derivadas;
- preserva histórico;
- registra operador e motivo.

## 10.3 Sessões existentes

O efeito deverá ser explícito:

- encerramento imediato;
- encerramento no próximo uso;
- política por risco.

Para `RN-054`, usuário inativado não poderá iniciar novas sessões.

## 10.4 Autoalteração

O usuário não deverá:

- elevar o próprio privilégio;
- remover controles essenciais;
- reativar-se após bloqueio;
- ocultar sua própria auditoria.

---

# 11. Sessões

## 11.1 Lista

- dispositivo;
- navegador;
- origem aproximada permitida;
- início;
- última atividade;
- expiração;
- situação.

## 11.2 Ações

- encerrar sessão;
- encerrar outras sessões;
- encerrar todas;
- marcar evento suspeito.

## 11.3 Privacidade

Informações de origem deverão ser proporcionais à finalidade e retenção aprovada.

---

# 12. Recuperação e autenticação

## 12.1 Recuperação

- token temporário;
- não revelar existência de usuário publicamente;
- expiração;
- uso único;
- auditoria;
- invalidação de tokens anteriores;
- orientação segura.

## 12.2 Política de senha

Deverá ser definida pela arquitetura de segurança. A interface:

- informa requisitos antes do envio;
- permite colar;
- oferece revelar temporariamente;
- não impõe regras arbitrárias não documentadas;
- não registra senha em log.

## 12.3 Autenticação adicional

MFA para perfis críticos permanece pendente, mas a UX deverá prever:

- cadastro de fator;
- desafio;
- recuperação;
- fatores de reserva;
- revogação.

---

# 13. Perfis de acesso — lista

## 13.1 Conteúdo

- nome;
- descrição;
- usuários vinculados;
- permissões;
- situação;
- tipo;
- atualização;
- ações.

## 13.2 Tipos

- padrão do produto;
- configurável da implantação;
- legado;
- descontinuado.

Perfis de sistema poderão ser protegidos contra alteração incompatível.

## 13.3 Ações

- visualizar;
- criar;
- duplicar;
- editar;
- inativar;
- comparar;
- consultar histórico.

---

# 14. Perfil — criação e edição

## 14.1 Campos

- nome;
- descrição;
- finalidade;
- situação;
- vigência opcional;
- permissões;
- restrições;
- observação.

## 14.2 Matriz

Permissões deverão ser agrupadas por:

- módulo;
- recurso;
- ação;
- escopo.

Exemplos de ações:

- consultar;
- criar;
- editar;
- inativar;
- aprovar;
- liberar;
- exportar;
- configurar;
- auditar.

## 14.3 Seleção

- estado explícito;
- busca;
- filtros;
- expandir grupo;
- seleção em grupo com confirmação;
- resumo de impacto;
- comparação com versão anterior.

## 14.4 Herança

Herança de perfis não será pressuposta. Se adotada, deverá:

- mostrar origem;
- impedir ciclo;
- calcular permissão efetiva;
- possuir ADR ou decisão formal.

---

# 15. Permissões efetivas

## 15.1 Visão

Para cada usuário:

- permissão;
- resultado efetivo;
- origem;
- perfil;
- exceção;
- restrição;
- vigência.

## 15.2 Exceção individual

Deverá:

- ser excepcional;
- possuir justificativa;
- possuir prazo quando possível;
- mostrar impacto;
- ser auditada;
- não ficar invisível dentro do perfil.

## 15.3 Conflito

A precedência entre concessão e negação individual permanece pendente. A UI deverá suportar resultado explícito e origem, sem ocultar conflito.

## 15.4 Teste de permissão

Ferramenta administrativa poderá simular:

**“Este usuário pode executar esta ação neste contexto?”**

O resultado deverá explicar a decisão sem permitir alteração por esse mecanismo.

---

# 16. Proteções contra perda de acesso

O sistema deverá tratar:

- inativação do último administrador autorizado;
- remoção do último perfil crítico;
- alteração do próprio perfil;
- perfil sem nenhum responsável;
- configuração que bloqueie toda a operação.

Medidas:

- alerta;
- confirmação adicional;
- exigência de outro administrador;
- procedimento de recuperação;
- auditoria.

A regra definitiva de “último administrador” depende do modelo de identidade aprovado.

---

# 17. Configurações da implantação

## 17.1 Categorias

- dados gerais;
- identidade configurável;
- fuso e localização;
- regras de acesso;
- pré-cadastro;
- contribuição e caixa;
- notificações;
- integrações;
- retenção;
- segurança;
- recursos habilitados.

## 17.2 Padrão de formulário

Cada configuração deverá exibir:

- nome;
- descrição;
- valor atual;
- origem;
- impacto;
- valor padrão;
- validação;
- vigência;
- histórico.

## 17.3 Alteração

Fluxo:

1. editar;
2. validar;
3. visualizar impacto;
4. confirmar;
5. versionar;
6. aplicar;
7. registrar resultado.

## 17.4 Aplicação imediata ou futura

A configuração deverá indicar:

- imediata;
- após nova sessão;
- após sincronização;
- agendada;
- exige reinício;
- exige publicação.

---

# 18. Versionamento de configurações

## 18.1 Versão

Mudanças relevantes deverão produzir:

- número ou identificador;
- autor;
- data;
- motivo;
- valores anterior e posterior;
- início de vigência;
- estado de aplicação.

## 18.2 Reversão

Reverter:

- cria nova versão;
- não apaga a anterior;
- exige permissão;
- mostra impacto;
- registra resultado.

## 18.3 Rascunho

Configurações complexas poderão ser:

- rascunho;
- em revisão;
- agendadas;
- publicadas;
- substituídas.

---

# 19. Catálogos parametrizados

## 19.1 Exemplos

- motivos de negativa;
- motivos de rejeição;
- motivos de bloqueio;
- motivos de contingência;
- tipos de vínculo;
- papéis;
- categorias de prestador;
- formas de pagamento;
- isenções;
- áreas;
- horários;
- tipos documentais.

## 19.2 Campos

- código interno;
- rótulo;
- descrição;
- situação;
- ordem;
- exige complemento;
- visibilidade;
- vigência;
- categoria.

## 19.3 Inativação

Item usado em histórico:

- não será excluído;
- poderá ser inativado para novos usos;
- continuará legível em registros existentes.

---

# 20. Áreas e horários

## 20.1 Área

- nome;
- descrição;
- pontos associados;
- situação;
- regras.

## 20.2 Faixa horária

- nome;
- dias;
- início;
- término;
- fuso;
- exceções;
- vigência.

## 20.3 Faixas noturnas

Horários que atravessam meia-noite deverão possuir visualização e validação inequívocas.

## 20.4 Impacto

Alterar área ou horário poderá afetar autorizações existentes. A UI deverá:

- calcular impacto;
- listar quantidade;
- pedir confirmação;
- versionar;
- sincronizar.

---

# 21. Pontos de acesso

## 21.1 Campos

- nome;
- código;
- localização;
- direção suportada;
- área;
- controladora;
- dispositivo;
- câmera;
- situação;
- contingência;
- observação.

## 21.2 Direção

- entrada;
- saída;
- bidirecional.

## 21.3 Situação

- em implantação;
- ativo;
- manutenção;
- indisponível;
- inativo.

## 21.4 Ações

- visualizar;
- editar;
- testar integração;
- colocar em manutenção;
- inativar;
- consultar eventos.

---

# 22. Equipamentos — lista

## 22.1 Tipos

- controladora;
- terminal facial;
- câmera LPR;
- câmera;
- leitor;
- atuador;
- serviço lógico.

## 22.2 Colunas

- nome;
- tipo;
- fabricante;
- modelo;
- ponto;
- adaptador;
- situação;
- última comunicação;
- sincronizações pendentes;
- ações.

## 22.3 Estados

- não configurado;
- configurando;
- conectado;
- degradado;
- indisponível;
- manutenção;
- inativo;
- credencial inválida.

---

# 23. Equipamento — cadastro e detalhe

## 23.1 Identificação

- nome;
- tipo;
- fabricante;
- modelo;
- número de série quando necessário;
- ponto;
- adaptador;
- versão;
- situação.

## 23.2 Conexão

- endereço técnico;
- porta;
- protocolo;
- identificador;
- timeout;
- política de retentativa;
- credencial protegida.

Segredos:

- não serão exibidos integralmente;
- não retornarão ao frontend após gravação;
- terão ação própria de substituição;
- serão armazenados por mecanismo seguro.

## 23.3 Estado técnico

- última comunicação;
- latência;
- fila;
- último erro sanitizado;
- versão;
- relógio;
- capacidade;
- saúde.

## 23.4 Ações

- testar;
- sincronizar;
- pausar;
- reativar;
- substituir segredo;
- consultar logs;
- inativar.

## 23.5 Teste

Teste não poderá:

- abrir acesso real sem confirmação específica;
- cadastrar pessoa de produção sem controle;
- misturar evento de teste com operação normal.

---

# 24. Adaptadores e desacoplamento

## 24.1 Princípio

O equipamento não define o domínio central.

## 24.2 Exibição

A interface deverá mostrar:

- adaptador;
- versão;
- capacidades;
- estado;
- fabricante;
- compatibilidade;
- fila;
- erro.

## 24.3 Identificadores

Identificador externo:

- nunca substitui identificador interno;
- é exibido apenas quando necessário;
- pode variar por equipamento;
- possui histórico.

## 24.4 Capacidade

Recursos deverão ser declarados:

- envio de pessoa;
- face;
- remoção;
- LPR;
- comando;
- confirmação;
- eventos;
- cache.

A UI não oferecerá ação não suportada.

---

# 25. Sincronizações

## 25.1 Fila

- entidade;
- operação;
- equipamento;
- criada;
- tentativa;
- próxima tentativa;
- estado;
- erro;
- correlação.

## 25.2 Estados

- aguardando;
- processando;
- concluída;
- falhou;
- cancelada;
- exige intervenção;
- substituída.

## 25.3 Ações

- consultar;
- tentar novamente;
- cancelar quando seguro;
- corrigir origem;
- reconciliar.

## 25.4 Idempotência

Reprocessamento não poderá duplicar:

- pessoa;
- credencial;
- veículo;
- evento;
- comando.

---

# 26. Notificações operacionais

## 26.1 Regras

- evento;
- público;
- canal;
- severidade;
- horário;
- repetição;
- escalonamento;
- situação.

## 26.2 Exemplos

- pré-cadastro aguardando;
- equipamento indisponível;
- fila acumulada;
- vínculo próximo do término;
- caixa irregular;
- falha de rotina.

## 26.3 Canais

Canais externos permanecem pendentes. Configuração deverá distinguir:

- notificação interna;
- e-mail;
- mensageria;
- integração futura.

---

# 27. Logs e auditoria — lista

## 27.1 Filtros

- período;
- usuário;
- perfil;
- operação;
- entidade;
- identificador;
- módulo;
- origem;
- resultado;
- implantação;
- criticidade.

## 27.2 Colunas

- data e hora;
- usuário ou sistema;
- operação;
- entidade;
- identificador;
- origem;
- resultado;
- ação de detalhe.

## 27.3 Ordenação

Mais recentes primeiro, com paginação estável.

## 27.4 Exportação

- permissão específica;
- filtros preservados;
- aviso de sensibilidade;
- justificativa quando exigida;
- auditoria da exportação;
- arquivo protegido e temporário.

---

# 28. Auditoria — detalhe

## 28.1 Conteúdo

- identificador do log;
- instante;
- ator;
- perfil;
- sessão;
- origem;
- operação;
- entidade;
- resultado;
- motivo;
- correlação;
- valores anterior e posterior;
- contexto.

## 28.2 Redação e mascaramento

O log deverá ser útil sem armazenar:

- senha;
- segredo;
- token;
- biometria;
- documento integral desnecessário;
- conteúdo de arquivo.

## 28.3 Eventos do sistema

Ator poderá ser:

- usuário;
- rotina;
- integração;
- sistema;
- suporte autorizado.

## 28.4 Imutabilidade

Usuário operacional não edita log. Correções serão novos eventos.

---

# 29. Operações críticas

## 29.1 Exemplos

- conceder permissão administrativa;
- remover último administrador;
- inativar usuário;
- encerrar sessões;
- alterar ponto de acesso;
- substituir segredo;
- executar comando de teste;
- alterar contingência;
- exportar auditoria;
- mudar retenção;
- reverter configuração.

## 29.2 Confirmação

Modal deverá apresentar:

- ação;
- alvo;
- impacto;
- reversibilidade;
- justificativa;
- confirmação específica.

## 29.3 Aprovação adicional

Dupla aprovação poderá ser exigida no futuro. Não será simulada sem fluxo e regras aprovados.

---

# 30. Estados visuais

## 30.1 Carregamento

- skeleton;
- ação bloqueada durante envio;
- layout estável.

## 30.2 Vazio

- nenhum usuário;
- nenhum perfil;
- nenhum equipamento;
- nenhum log no filtro;
- orientação autorizada.

## 30.3 Erro parcial

Uma região com falha não torna outra configuração válida por omissão.

## 30.4 Conflito

Alteração concorrente:

- bloquear sobrescrita;
- mostrar versão;
- permitir recarregar ou comparar;
- preservar entrada do usuário.

## 30.5 Somente leitura

Gestor ou auditor visualiza sem controles de edição ocultos apenas por CSS.

---

# 31. Responsividade

## 31.1 Desktop

- sidebar;
- tabela;
- filtros;
- formulário e painel contextual;
- drawer de detalhe.

## 31.2 Tablet

- sidebar recolhível;
- filtros em painel;
- tabelas adaptadas;
- drawer em largura maior.

## 31.3 Celular

- uma coluna;
- listas em cartões quando necessário;
- filtros em tela;
- detalhe em tela completa;
- ações críticas separadas;
- nenhum segredo exibido;
- sem rolagem horizontal como única navegação.

Administração crítica poderá exigir viewport mínimo aprovado, sem impedir consulta responsiva quando prevista.

---

# 32. Acessibilidade

- títulos e regiões;
- foco visível;
- teclado;
- tabelas semânticas;
- matriz de permissões acessível;
- checkbox com rótulo;
- estados não dependentes de cor;
- modal com foco;
- mensagens associadas;
- zoom;
- texto ampliado;
- redução de movimento;
- filtros identificados;
- alterações dinâmicas anunciadas;
- descrição de impacto compreensível.

Matriz de permissões deverá possuir alternativa linear por módulo, evitando grade impossível de navegar.

---

# 33. Conteúdo e microcopy

## 33.1 Termos

- Usuários;
- Perfis de Acesso;
- Permissões;
- Permissões efetivas;
- Exceção individual;
- Configurações;
- Pontos de Acesso;
- Equipamentos;
- Sincronizações;
- Logs e Auditoria;
- Inativar;
- Bloquear;
- Encerrar sessões.

## 33.2 Evitar

| Evitar | Preferir |
|---|---|
| Excluir usuário | Inativar usuário |
| Apagar perfil | Inativar perfil |
| Limpar logs | Aplicar política de retenção autorizada |
| Resetar tudo | Restaurar configuração anterior |
| Testar porta | Enviar comando de teste ao ponto {nome} |
| Super admin | Nome do perfil formalmente aprovado |

## 33.3 Mensagens

| Situação | Mensagem |
|---|---|
| Usuário inativado | Usuário inativado. Novos acessos estão bloqueados. |
| Sessões encerradas | As sessões selecionadas foram encerradas. |
| Permissão alterada | Permissões atualizadas. A mudança foi registrada na auditoria. |
| Equipamento falhou | Não foi possível concluir o teste. Nenhum sucesso foi confirmado. |
| Conflito | Esta configuração foi alterada por outro usuário. Recarregue e compare as versões. |

---

# 34. Segurança e privacidade

- usuário individual;
- menor privilégio;
- segregação;
- autenticação segura;
- proteção de sessão;
- CSRF;
- rate limit;
- segredo fora do frontend;
- mascaramento;
- autorização no servidor;
- logs sanitizados;
- exportação controlada;
- reautenticação em ação crítica quando aprovada;
- inativação rastreável;
- acesso de suporte temporário;
- cache por implantação e perfil.

---

# 35. Auditoria da Administração

Registrar:

- usuário criado;
- convite;
- ativação;
- bloqueio;
- inativação;
- sessão encerrada;
- perfil criado ou alterado;
- permissão concedida ou retirada;
- exceção individual;
- configuração;
- catálogo;
- área;
- horário;
- ponto;
- equipamento;
- segredo substituído;
- teste;
- sincronização;
- exportação;
- consulta sensível quando aplicável.

---

# 36. Desempenho e observabilidade

## 36.1 Listas

- paginação;
- filtros indexáveis;
- busca controlada;
- não carregar matriz inteira desnecessariamente.

## 36.2 Auditoria

Volume alto exige:

- consulta por período;
- paginação estável;
- índices;
- exportação assíncrona;
- arquivo temporário.

## 36.3 Telemetria

Medir:

- falha de autenticação;
- bloqueio;
- alteração crítica;
- tempo de aplicação;
- falha de equipamento;
- fila;
- exportação;
- consulta lenta;
- conflito.

---

# 37. Diretrizes para Blade e Livewire

## 37.1 Componentização

```text
Administration
├── UserManagement
├── RoleManagement
├── PermissionMatrix
├── EffectivePermissions
├── SessionManagement
├── SettingsManagement
├── CatalogManagement
├── AccessPointManagement
├── EquipmentManagement
├── SyncQueue
└── AuditExplorer
```

## 37.2 Servidor

- Policies e Gates;
- escopo de implantação;
- transações;
- controle de versão;
- filas;
- idempotência;
- armazenamento seguro de segredos;
- auditoria;
- validação de transições.

## 37.3 Interface

Livewire poderá gerir busca, filtros, formulários e drawers, mas não calculará autoridade com base apenas no estado do cliente.

---

# 38. Contrato funcional de dados

## 38.1 Usuário

- identidade;
- implantação;
- perfis;
- exceções;
- situação;
- vigência;
- sessões;
- histórico.

## 38.2 Perfil

- nome;
- descrição;
- situação;
- permissões;
- usuários;
- versão;
- histórico.

## 38.3 Permissão

- módulo;
- recurso;
- ação;
- escopo;
- origem;
- vigência;
- resultado efetivo.

## 38.4 Configuração

- chave;
- valor protegido;
- origem;
- versão;
- vigência;
- estado;
- impacto.

## 38.5 Equipamento

- identificação;
- tipo;
- ponto;
- adaptador;
- capacidades;
- conexão protegida;
- situação;
- saúde;
- filas.

## 38.6 Auditoria

- ator;
- operação;
- entidade;
- origem;
- valores;
- correlação;
- resultado;
- instante.

---

# 39. Cenários de teste

## 39.1 Usuários

- criar;
- duplicidade;
- convite expirado;
- ativar;
- bloquear;
- inativar;
- sessão existente;
- recuperação;
- autoelevação.

## 39.2 Perfis

- criar;
- duplicar;
- conceder;
- retirar;
- inativar;
- usuário afetado;
- último administrador;
- exceção.

## 39.3 Configurações

- validar;
- publicar;
- agendar;
- reverter;
- conflito;
- valor protegido;
- impacto.

## 39.4 Equipamentos

- cadastrar;
- testar;
- segredo;
- conectado;
- degradado;
- indisponível;
- fila;
- retentativa;
- capacidade ausente.

## 39.5 Auditoria

- filtros;
- detalhe;
- valores;
- mascaramento;
- exportação;
- tentativa de edição;
- segregação.

## 39.6 Responsividade e acessibilidade

- desktop;
- tablet;
- celular;
- teclado;
- leitor de tela;
- matriz;
- modal;
- zoom.

## 39.7 Segurança

- rota direta;
- requisição adulterada;
- cache cruzado;
- segredo no frontend;
- exportação sem permissão;
- suporte expirado;
- CSRF;
- sessão expirada.

---

# 40. Critérios de aceite

## 40.1 Usuários e sessões

**CA-ADM-001:** cada operador possui usuário individual.  
**CA-ADM-002:** criação evita duplicidade e usa ativação segura.  
**CA-ADM-003:** inativação impede novos acessos.  
**CA-ADM-004:** sessões podem ser consultadas e encerradas conforme permissão.  
**CA-ADM-005:** autoelevação é impedida.  

## 40.2 Perfis e permissões

**CA-ADM-006:** permissões são granulares por ação.  
**CA-ADM-007:** perfil mostra permissões e usuários afetados.  
**CA-ADM-008:** exceção individual possui justificativa e origem.  
**CA-ADM-009:** permissão efetiva é explicável.  
**CA-ADM-010:** alteração crítica mostra impacto.  
**CA-ADM-011:** último acesso administrativo é protegido conforme regra.  

## 40.3 Configurações

**CA-ADM-012:** configuração é validada e versionada.  
**CA-ADM-013:** valores anterior e posterior são auditados.  
**CA-ADM-014:** reversão cria nova versão.  
**CA-ADM-015:** item de catálogo usado não é excluído destrutivamente.  
**CA-ADM-016:** área e horário apresentam impacto.  

## 40.4 Equipamentos

**CA-ADM-017:** equipamento pertence a ponto e adaptador identificados.  
**CA-ADM-018:** segredo não retorna ao frontend.  
**CA-ADM-019:** capacidades determinam ações disponíveis.  
**CA-ADM-020:** teste não simula sucesso.  
**CA-ADM-021:** sincronização é idempotente e rastreável.  
**CA-ADM-022:** identificador externo não substitui chave interna.  

## 40.5 Auditoria

**CA-ADM-023:** operações relevantes geram log imutável.  
**CA-ADM-024:** log contém conteúdo mínimo e correlação.  
**CA-ADM-025:** dados sensíveis são mascarados.  
**CA-ADM-026:** exportação exige permissão e é auditada.  
**CA-ADM-027:** filtros preservam segregação.  

## 40.6 Visuais, responsivos e acessíveis

**CA-ADM-028:** navegação preserva o padrão das referências.  
**CA-ADM-029:** telas completas são prototipadas antes da implementação.  
**CA-ADM-030:** módulos funcionam nos viewports homologados.  
**CA-ADM-031:** operações críticas funcionam por teclado.  
**CA-ADM-032:** matriz de permissões possui alternativa acessível.  
**CA-ADM-033:** estados não dependem apenas de cor.  

## 40.7 Segurança

**CA-ADM-034:** autorização é aplicada no servidor.  
**CA-ADM-035:** dados e caches são segregados.  
**CA-ADM-036:** suporte excepcional possui prazo e auditoria.  
**CA-ADM-037:** alterações concorrentes não sobrescrevem silenciosamente.  
**CA-ADM-038:** nenhuma credencial ou segredo aparece em log.  

---

# 41. Pendências abertas

| PEN-ADM | Pendência | Impacto | Encaminhamento |
|---|---|---|---|
| PEN-ADM-001 | Prototipar listas, formulários e detalhes administrativos | Não há telas completas | UX/UI antes da implementação |
| PEN-ADM-002 | Definir catálogo granular de permissões | Perfis e testes | Regras de negócio |
| PEN-ADM-003 | Definir precedência de concessão, negação e exceção | Permissão efetiva | Arquitetura e segurança |
| PEN-ADM-004 | Definir proteção do último administrador | Continuidade | Segurança |
| PEN-ADM-005 | Definir política de senha e autenticação adicional | Login e perfis críticos | Arquitetura de segurança |
| PEN-ADM-006 | Definir efeito sobre sessões após bloqueio e inativação | Segurança | Política de sessão |
| PEN-ADM-007 | Definir modelo de suporte temporário | Operação técnica | Segurança e governança |
| PEN-ADM-008 | Definir categorias e chaves de configuração | Administração | Regras e arquitetura |
| PEN-ADM-009 | Definir fluxo de publicação e aprovação | Configurações críticas | Produto |
| PEN-ADM-010 | Definir catálogos e motivos iniciais | Operação | Regras de negócio |
| PEN-ADM-011 | Confirmar áreas, horários e pontos da Santa Rita | Controle de acesso | `PEN-020` |
| PEN-ADM-012 | Confirmar fabricante, protocolo e capacidades | Equipamentos | `PEN-001` |
| PEN-ADM-013 | Definir armazenamento de segredos | Integrações | ADR |
| PEN-ADM-014 | Definir limites de testes de equipamento | Segurança física | Operação e arquitetura |
| PEN-ADM-015 | Definir canais e regras de notificação | Alertas | `PEN-011` |
| PEN-ADM-016 | Definir política de retenção da auditoria | Volume e LGPD | `PEN-006` |
| PEN-ADM-017 | Definir campos e mascaramento do log | Segurança e utilidade | Arquitetura |
| PEN-ADM-018 | Definir exportações permitidas | Privacidade | Permissões |
| PEN-ADM-019 | Definir metas de desempenho | Homologação | RNF |
| PEN-ADM-020 | Aprovar protótipos responsivos | Aceite visual | Prototipação |
| PEN-ADM-021 | Definir administração global multicliente | Expansão futura | `PEN-015` e ADR |
| PEN-ADM-022 | Definir necessidade de dupla aprovação | Operações críticas | Avaliação de risco |

---

# 42. Decisões consolidadas

Ficam consolidados:

- administração local segregada por implantação;
- administração global fora do MVP;
- usuários individuais;
- inativação em vez de exclusão;
- menor privilégio;
- perfis e permissões granulares;
- exceções individuais visíveis e auditadas;
- permissão efetiva explicável;
- configurações versionadas;
- catálogos inativáveis com histórico;
- áreas, horários e pontos como cadastros próprios;
- equipamentos ligados por adaptadores;
- segredos protegidos;
- capacidades controlando ações;
- sincronizações idempotentes;
- auditoria imutável e mascarada;
- exportações auditadas;
- telas administrativas completas condicionadas a protótipos;
- regras e autorização no servidor.

## 42.1 Registro de aprovação

| Papel | Nome | Decisão | Data | Observações |
|---|---|---|---|---|
| Product Owner | Vinicius Velasco de Azevedo | Aprovado | 28/07/2026 | Administração aprovada como referência para prototipação, testes e implementação futura |
| Soluções do Vale | Representante designado | Ciente | 28/07/2026 | Aprovação registrada no repositório oficial |

---

# 43. Próximo documento

Após a aprovação desta especificação, deverá ser produzido:

**`docs/009_REGRAS_DE_NEGOCIO.md`**

O próximo documento deverá consolidar:

- regras existentes;
- decisões tomadas nas especificações UX/UI;
- estados;
- validações;
- fórmulas;
- conflitos;
- permissões;
- pendências;
- matriz de rastreabilidade.

---

## Situação do documento

Esta especificação consolida a experiência administrativa de usuários, perfis, configurações, equipamentos e auditoria e encontra-se **aprovada**. As pendências de prototipação, segurança, permissões, infraestrutura e governança permanecem rastreadas e deverão ser resolvidas antes da implementação definitiva dos elementos afetados, sem invalidar esta aprovação documental.
