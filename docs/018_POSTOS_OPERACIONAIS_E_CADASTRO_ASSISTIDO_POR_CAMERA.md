# SDV Access — Postos operacionais e cadastro assistido por câmera

## 1. Objetivo

Especificar os postos operacionais, a seleção contextual de equipamentos, a liberação excepcional simplificada e o cadastro rápido assistido por câmera e OCR. Permanecem separadas as responsabilidades de cadastro, vínculo, autorização, credencial e evento de acesso.

## 2. Postos da implantação Santa Rita

1. Guarita Principal;
2. Guarita — Atendimento de Veículos;
3. Guarita — Atendimento de Pedestres.

O posto é o contexto operacional assumido pelo usuário durante o turno; não substitui perfil, permissão, ponto de acesso ou equipamento.

Após autenticar-se, o operador escolhe um posto livre (qualquer operador assume qualquer posto, um por vez; ver §14). O sistema registra início, término e troca de posto, mantém o posto ativo visível e carrega suas câmeras, pontos e equipamentos preferenciais.

## 3. Equipamentos preferenciais e alcance

Cada posto pode possuir câmera local de documentos, câmera local de placas, terminais faciais, relés, pontos de acesso e outros periféricos aprovados.

O posto determina a seleção inicial e a ordem de exibição, mas não cria bloqueio absoluto sobre os demais equipamentos da implantação.

- A Guarita Principal poderá visualizar e acionar todos os relés autorizados.
- Veículos e Pedestres apresentam primeiro seus equipamentos preferenciais.
- Operadores com permissão ampliada podem selecionar outros relés da implantação.
- Ao selecionar equipamento externo ao posto ativo, a confirmação deve destacar o posto atual e o ponto que será acionado.

Permissões distintas devem controlar: relés do posto, outros relés, configuração de vínculos, configuração de câmeras locais e consulta de auditoria. O backend revalida implantação, operador, posto, ponto, equipamento, direção, estado, homologação e permissão. IP, porta, endpoint e credencial não podem ser aceitos como autoridade a partir do navegador.

## 4. Liberação excepcional simplificada

A liberação manual permanece vinculada a pessoa, autorização ou ocorrência identificada sempre que possível.

Para agilizar o atendimento, a excepcionalidade será ativada por um checkbox inequívoco:

> Liberação excepcional — prosseguir sem autorização regular confirmada

Ao marcar:

- o sistema classifica automaticamente a operação como excepcional;
- não exige justificativa textual na operação padrão;
- exige confirmação final destacada;
- grava o motivo padronizado `liberacao_excepcional_operador`;
- registra operador, posto, pessoa/ocorrência disponível, equipamento, ponto, direção, data, resultado, correlation ID e idempotência;
- destaca a operação nos relatórios para revisão.

Um complemento textual pode ser opcional. A administração poderá torná-lo obrigatório por configuração em cenários específicos. O checkbox não elimina autenticação, permissão, validação técnica, confirmação, idempotência ou auditoria.

## 5. Cadastro rápido assistido por câmera

O Cadastro rápido poderá capturar frente e verso do documento com câmera instalada no computador:

1. carregar ou selecionar a câmera de documentos do posto;
2. mostrar pré-visualização e moldura;
3. capturar frente e verso quando aplicável;
4. avaliar qualidade;
5. sugerir nova captura se necessário;
6. executar OCR e extração estruturada;
7. preencher o formulário como sugestão;
8. destacar campos de baixa confiança;
9. permitir correção, nova captura ou digitação manual;
10. consultar duplicidade antes de salvar;
11. manter autorização e liberação separadas do cadastro.

Deve existir envio de arquivo como contingência.

## 6. Câmeras por computador e posto

As três estações podem operar simultaneamente com câmeras distintas.

- No servidor ficam posto, finalidade e política.
- No navegador/computador fica a associação do dispositivo físico às finalidades `documentos` e `placas`.
- A preferência é local por navegador e posto, não global.
- Ao selecionar o posto, o sistema tenta recuperar as câmeras configuradas.
- Se a câmera estiver ausente, substituída ou sem permissão, solicita nova seleção.
- Solicitar somente vídeo, nunca microfone.
- Não transmitir vídeo continuamente ao servidor; enviar apenas a captura confirmada.

## 7. Qualidade e repetição da captura

Antes do OCR, avaliar: bordas/enquadramento, foco, movimento, resolução, iluminação, reflexos, inclinação, obstruções e face esperada do documento.

Se a qualidade for insuficiente, não preencher silenciosamente. Informar o problema e oferecer nova captura, por exemplo:

- imagem desfocada: aproximar e repetir;
- documento cortado: enquadrar as quatro bordas;
- reflexo: mudar ângulo ou iluminação;
- documento não identificado: repetir ou digitar manualmente.

## 8. OCR e conferência humana

O OCR pode sugerir nome, CPF, número do documento, nascimento, validade e campos posteriormente aprovados. Cada campo terá confiança; abaixo do limite ficará como `Requer conferência`.

O operador confirma, corrige ou repete a captura. OCR/IA não aprovam cadastro, autorização ou acesso.

## 9. Privacidade e segurança

- imagens e documentos não aparecem em logs;
- visualização exige permissão e implantação;
- transporte e armazenamento são protegidos;
- retenção e descarte seguem política aprovada e LGPD;
- OCR externo depende de decisão arquitetural e contratual;
- fotografia documental não será convertida em biometria nesta entrega;
- captura, leitura, correção, confirmação e descarte serão auditáveis sem conteúdo sensível.

A captura utilizará APIs `MediaDevices/getUserMedia`, mantendo Blade e Livewire. OCR especializado poderá usar serviço Python/FastAPI desacoplado quando necessário.

## 10. Critérios de aceite

- três postos operam simultaneamente;
- cada navegador memoriza suas câmeras por posto;
- câmera ausente gera recuperação orientada;
- imagem ruim solicita nova captura;
- dados extraídos exigem conferência humana;
- posto prioriza seus relés;
- operador autorizado pode escolher outro relé;
- acionamento fora do posto exige confirmação destacada;
- excepcionalidade é checkbox rápido e auditado;
- nenhuma decisão de acesso é automática pelo OCR.

## 11. Pendências

- aprovar modelos mínimos das câmeras;
- aprovar retenção das imagens;
- decidir OCR local ou externo;
- definir limiares de qualidade/confiança;
- homologar navegadores e computadores;
- definir perfis com alcance sobre relés externos ao posto.

## 12. Notas de implementação (11/10/2026)

Precisões da primeira implementação, sem alterar as decisões acima.

**Postos.** `postos.tipo` (principal, veiculos, pedestres) e `operador_turnos` (início, término,
troca, encerramento e saída do sistema). O Modo Portaria leva o operador designado a
`/selecionar-posto`; o posto ativo aparece no cabeçalho. Permissões: `acessos.liberar-reles-do-posto`,
`acessos.liberar-outros-reles`, `acessos.liberar-excepcionalmente`. Configuração da observação
obrigatória na exceção: `SDV_LIBERACAO_EXCEPCIONAL_OBSERVACAO_OBRIGATORIA` (padrão `false`).

**Câmeras locais.** Associação guardada no `localStorage` do navegador com a chave
`sdv.cameras.{implantação}.{posto}` e as finalidades `documentos` e `placas`
(`resources/js/camera-preferencias.js`). A tela de seleção do posto tem o painel "Câmeras deste
computador". `getUserMedia` é sempre chamado com `audio: false`. Câmera ausente, substituída ou
sem autorização gera aviso e pede nova seleção. Testes em `tests/js` (`npm run test:js`).
A câmera de placas é só associada nesta entrega; o uso dela fica para a leitura de placas.

**Captura e qualidade.** O Cadastro rápido captura frente e verso pela câmera de documentos ou
recebe arquivo (contingência). A avaliação roda no servidor, em memória, com GD
(`App\Documentos\Infra\AvaliadorQualidadeImagem`): resolução, iluminação, reflexo, foco,
movimento e enquadramento. Inclinação, obstrução e face esperada ainda não são medidas (dependem
do OCR real). Os limiares em `config/documentos.php` são provisórios até a homologação das
câmeras. A permissão `documentos.capturar` controla a captura.

**OCR.** Porta `App\Documentos\Dominio\PortaOcrDocumento`; nesta entrega só o simulador
(`OcrSimulador`), que por padrão responde "documento não identificado". Confiança mínima:
`SDV_OCR_CONFIANCA_MINIMA` (padrão 0,85). Campos abaixo dela ficam "Requer conferência" e
impedem salvar até o operador confirmar ou corrigir. Nada é aprovado, autorizado ou liberado pela
leitura.

**Leitor interno do AJ-005 (20/10/2026).** Com a integração da `main`, o cadastro rápido usa o
mesmo leitor do pré-cadastro ([SDV-UXP-019](019_OCR_ASSISTIDO_PRE_CADASTRO.md), serviço Python
`services/document-ocr`, ADR-011) por meio do adaptador `OcrServicoDocumento`, escolhido quando
`DOCUMENT_OCR_ENABLED=true`; desligado, vale o simulador. O operador escolhe CNH ou RG/CIN antes
da captura. A checagem de qualidade continua antes do leitor (imagem ruim não é enviada), o
recorte da foto não é solicitado e a captura continua sem persistência. Leitor fora do ar vira
"não reconhecido" e o operador digita os dados. Nenhum OCR de terceiros é usado.

**Retenção.** `SDV_CAPTURA_DOCUMENTO_RETENCAO=nao_persistir`: o upload temporário é apagado logo
após a análise e nenhuma imagem é guardada. A auditoria (`documento_capturado_analisado`) registra
origem, resultado, códigos de qualidade e nomes de campos, nunca imagem, CPF ou outro valor.


## 13. Evolução solicitada — AJ-010 (06/11/2026)

O Product Owner solicitou a experiência integrada: fotografar documento, extrair e conferir os dados, fotografar a pessoa e salvar/enviar para o terminal facial no próprio cadastro rápido. A especificação está em [SDV-OPE-021](021_CADASTRO_RAPIDO_FOTO_OCR_E_FACIAL.md). É escopo para implementação; não é evidência de função já entregue. Reutilizar o leitor interno e os serviços de foto existentes, preservando a análise temporária dos documentos. O envio atual de cadastro sem foto não atende, sozinho, ao envio de credencial facial solicitado; a extensão depende de implementação, revisão do ADR-013 e homologação específica.

**Atualização da implementação (06/11/2026):** entregue no PR #74. O cadastro rápido agora tem as etapas Documento → Conferência → Foto, leitura automática após a captura, câmera própria para o rosto e envio da foto ao terminal implementado mas desligado até homologação. Detalhes e pendências em [SDV-OPE-021 §10](021_CADASTRO_RAPIDO_FOTO_OCR_E_FACIAL.md).

## 14. Um operador por posto e um caixa por posto (12/11/2026)

Decisões do Product Owner ao validar a tela de postos. Substituem a designação de operadores por posto descrita no §2 e nas notas do §12.

**Quem assume qual posto.** Todos os operadores podem trabalhar em todos os postos. A designação por posto (`operador_postos`) deixou de ser usada: a tela de escolha lista todos os postos ativos. A tabela fica no banco só como histórico.

**Um posto, uma pessoa.** Um posto só pode estar com um operador por vez (índice único parcial em `operador_turnos (posto_id)` com o turno aberto). Posto ocupado aparece como "Ocupado por *nome* desde *hora*" e a tentativa de assumir é recusada (`posto_ocupado`). Para liberar o posto, o operador fecha o caixa e encerra o turno.

**Um caixa por posto.** `caixa_turnos.posto_id` liga o caixa ao posto do turno; só um caixa aberto por posto. Abrir o caixa exige ter assumido um posto, e o caixa recebe o nome do posto. O Caixa e o Modo Portaria mostram só o caixa do próprio operador. Caixas abertos antes da regra ficam sem posto e continuam com o seu operador.

**Caixa aberto prende o operador ao posto.** Com o caixa aberto não se troca de posto, não se encerra o turno e o botão Sair é recusado com o aviso "Feche o caixa antes de sair". Sem caixa aberto, sair do sistema encerra o turno e libera o posto (como antes).

**Posto preso.** Gestor e Administrador (permissão `postos.liberar`) liberam o posto na tela Equipamentos › Postos, com motivo obrigatório. O turno é encerrado (`liberado_pelo_gestor`) e o caixa aberto do operador é fechado como `aguardando_conferencia`, sem valor informado e com o saldo esperado registrado, para conferência posterior. Tudo vai para a auditoria (`posto_operacional_liberado`).

**Sessão do operador liberado (decisão do PO, 14/11/2026).** Liberar o posto derruba a sessão do operador: na próxima requisição (navegação ou clique, inclusive em tela já aberta) ele é deslogado e volta para "Entrar no sistema" com o aviso "Sua sessão foi encerrada", o nome do posto e o horário da liberação. Só caem as sessões abertas antes da liberação; quem entra de novo segue normalmente e escolhe um posto. Auditoria: `sessao_encerrada_por_liberacao_de_posto`.

**Liberar para a facial exige caixa aberto (decisão do PO, 14/11/2026).** Toda liberação para o terminal facial exige o caixa aberto de quem libera. Vale para contribuição paga, "não contribui" e "isento", e para todos os perfis, inclusive Gestor e Administrador. Assim nenhuma entrada é liberada sem registro no caixa, o que fecha a brecha para desvio interno. Sem caixa aberto, a portaria só consulta. A regra vale nos três caminhos que levam à facial:
- **"Liberar entrada" da Validação:** o botão fica desabilitado com o aviso "Abra o caixa do seu posto para liberar a entrada". A checagem roda antes de gravar qualquer coisa, então não nasce pessoa, autorização, histórico nem pedido ao terminal.
- **"Enviar ao terminal" na ficha do Cadastro de pessoas:** o envio é recusado com o código `caixa_fechado`.
- **"Salvar e enviar para a facial" do cadastro rápido:** o cadastro fica salvo, mas nenhuma visita é criada e nada vai à facial.

A recusa central fica em `EnvioFacial::enviar` e é auditada como `facial_envio_recusado`. "Salvar sem liberar" e "Negar" continuam sem exigir caixa. Revogação e vencimento automáticos também não exigem caixa.

**Todo envio vai para todas as faciais (decisão do PO, 15/11/2026).** Toda vez que alguém é enviado à facial, o SDV manda **o mesmo cadastro, a mesma validade e a mesma foto para todos os terminais faciais ativos** (com ponto de acesso vigente), não só para o do posto. Assim a pessoa passa em qualquer entrada do condomínio. O operador não escolhe mais terminal: a ficha mostra a lista de terminais que vão receber. Vale nos três caminhos acima. O resultado na tela junta os terminais ("Cadastro confirmado nos 2 terminais faciais" ou "em 1 de 2"), e a foto é acompanhada terminal a terminal. A entrada central é `EnvioFacial::enviarParaTodos`.

**Conferência no aparelho antes de dizer "já enviado" (15/11/2026).** Se o SDV registra o cadastro como confirmado num terminal, ele pergunta ao aparelho se a pessoa e a face ainda estão lá. Se alguém apagou o usuário no terminal, o SDV envia de novo cadastro e foto, com o aviso "O terminal não tinha mais o cadastro desta pessoa".

**Quem é levado à escolha do posto.** Ao abrir o Modo Portaria sem posto, quem opera caixa (`caixa.proprio.gerenciar`) ou libera relés é levado à escolha. Quem também consulta o caixa consolidado (Gestor, Administrador) pode "Continuar sem assumir posto (supervisão)", para não ocupar uma guarita só para consultar.

**Tela Postos.** A antiga "Postos e operadores" virou "Postos": mostra quem está em cada posto agora, permite editar nome e tipo, desativar e reativar (posto ocupado não é desativado), os pontos de acesso de cada posto num bloco recolhido e o botão "Liberar posto". Cadastro e pontos continuam com `integracoes.gerenciar`.

**Posto pedido logo ao entrar (decisão do PO, 26/11/2026).** Quem opera posto (`caixa.proprio.gerenciar` ou libera relés) vai, logo depois do login, para a tela "Posto de trabalho", e não só ao abrir o Modo Portaria. Quem não opera posto segue direto para a área inicial.
- **Caiu ou desligou com o caixa aberto:** nada se perde. O posto e o caixa ficam mantidos. Ao entrar de novo, a tela mostra "Você estava no posto *X*", com a hora em que o caixa foi aberto, e o botão "Continuar no posto *X*". Enquanto o caixa estiver aberto, não dá para trocar de posto.
- **Turno de um login anterior com o caixa já fechado:** por exemplo, quando o computador desligou sem clicar em Sair. Esse turno é encerrado no novo login (`sessao_anterior_sem_caixa`, auditado em `posto_operacional_encerrado`), o posto fica livre e o operador escolhe o posto de novo.
- **Supervisão:** Gestor e Administrador continuam podendo "Continuar sem assumir posto".

**Fechar o navegador com o caixa aberto (26/11/2026).** O botão Sair já é recusado com o caixa aberto. Fechar a aba ou o navegador mostra a confirmação do navegador. O navegador não deixa impedir de fato: se fecharem mesmo assim, vale a regra acima. Cliques, formulários e redirecionamentos dentro do sistema não mostram o aviso.

**Caixa fechado avisado na entrada do atendimento (decisão do PO, 26/11/2026).** Ao abrir a tela de atendimento (`/validacao`) com o caixa fechado, quem opera caixa vê no topo o aviso "Seu caixa está fechado". O aviso traz o saldo inicial (sugerido pela configuração `caixa.saldo_sugerido_abertura`) e o botão "Abrir caixa" ali mesmo, no posto do turno. Sem posto, o botão leva a "Escolher o posto". "Só consultar" esconde o aviso, e a tela segue servindo para consultar e "Salvar sem liberar". Depois de abrir, o botão "Liberar entrada" passa a valer na hora.

Testes em `tests/Feature/PostosCaixaPorPostoTest.php`, `tests/Feature/PostoAoEntrarECaixaNoAtendimentoTest.php` e `tests/Feature/Integracoes/LiberacaoManualTest.php`.
