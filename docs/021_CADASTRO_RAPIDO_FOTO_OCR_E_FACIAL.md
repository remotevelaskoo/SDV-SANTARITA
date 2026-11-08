# AJ-010 — Cadastro rápido por foto, OCR compartilhado e foto para a facial

**Produto:** SDV Access — Santa Rita
**Data:** 06/11/2026
**Origem:** solicitação do Product Owner: aplicar ao cadastro rápido o modelo de extração por foto do formulário público, com captura da foto da pessoa para envio ao terminal facial.
**Situação:** implementado no PR #74 (entrega 1 completa; entrega 2 com contrato simulado e adaptador prontos, **sem homologação de bancada e desligada**). Ver §10 e §11.
**Branch documental:** `codex/aj010-cadastro-rapido-foto-ocr`

## 1. Resultado esperado

O porteiro deve concluir o atendimento no mesmo contexto, em uma sequência curta:

1. **Fotografar documento** — selecionar CNH ou RG/CIN, enquadrar e capturar a frente; solicitar verso quando aplicável, seguindo a regra documental vigente.
2. **Conferir dados** — extrair automaticamente, apresentar nome, CPF, nascimento e número do documento quando disponíveis, permitir correção e destacar campos não lidos ou de baixa confiança.
3. **Fotografar pessoa** — abrir a câmera do posto, capturar uma fotografia atual, exibir prévia e permitir refazer.
4. **Salvar e enviar para a facial** — mostrar o terminal de destino, obter confirmação explícita e apresentar separadamente o resultado do cadastro e o da integração.

A foto da pessoa é uma captura própria. O recorte do retrato impresso no documento não a substitui. A tela não deve exigir que o operador abra o cadastro completo para concluir essas etapas.

## 2. Linha de base verificada

Já existem na main:

- `AccessValidation` com cadastro rápido e o trait `CapturaDocumentoAssistida`;
- `CadastroAssistidoDocumento`, `PortaOcrDocumento` e `OcrServicoDocumento`, compartilhando o leitor interno Python/FastAPI/RapidOCR do AJ-005 quando `DOCUMENT_OCR_ENABLED=true`;
- câmera por posto/navegador e upload como contingência;
- preenchimento atual de nome, CPF e nascimento; número documental ainda aparece como extra da análise;
- foto privada em `PersonRegistration` e serviços existentes de arquivos/foto da pessoa;
- `EnvioFacial`, que atualmente envia **cadastro_pessoa sem foto nem template**, com autorização vigente, outbox e idempotência.

Portanto, não construir outro motor OCR nem afirmar que o envio atual já cadastra uma face. A lacuna principal é a experiência integrada do cadastro rápido, a persistência segura da foto e o envio biométrico real.

## 3. Interface prática

Usar painel ou modal amplo, responsivo, com três etapas visíveis: **Documento → Conferência → Foto** e resumo final compacto.

- Reaproveitar componentes de captura, processamento e diagnóstico do formulário público; preservar Blade/Livewire e o Design System.
- Após confirmar a captura, iniciar a leitura automaticamente, sem um segundo botão redundante de “extrair”.
- Campos devem continuar editáveis; uma nova leitura não pode apagar correções do operador sem aviso.
- Erro deve indicar ação: refazer foto, usar arquivo ou digitar manualmente. Falha de OCR não apaga o atendimento.
- Conferência humana permanece obrigatória antes de salvar. A leitura não comprova autenticidade.
- Pedir apenas os complementos exigidos pela regra vigente do tipo de acesso; nenhuma mudança de obrigatoriedade fica implícita neste ajuste.
- Oferecer **Refazer foto**, **Salvar cadastro**, **Salvar e enviar para a facial** e **Cancelar** no contexto adequado.
- Preservar contribuição, ticket, veículo e observações já preenchidos na validação.
- Cancelar ou iniciar outro atendimento deve limpar capturas, sugestões e prévias anteriores e encerrar a câmera.
- Em tela pequena, manter uma coluna, ações alcançáveis e nenhuma rolagem horizontal.

## 4. Cadastro e duplicidade

Consultar o CPF na implantação ativa antes de criar uma pessoa. Se já existir, oferecer seleção explícita do cadastro; não duplicar nem sobrescrever silenciosamente identidade, contato ou fotografia.

Persistir pessoa, documentos e vínculos necessários em transação. Atribuir o número de documento ao tipo correto; nunca converter automaticamente número de RG/CNH em CPF. Reutilizar serviços de domínio existentes e tratar concorrência.

Tipo de acesso, destino, responsável e vigência seguem suas regras próprias. Turista, quando atendido por este fluxo, deve reutilizar o modelo de visita/autorização do AJ-006, com teto de 24 horas, sem vínculo artificial a um imóvel. Não confundir o salvamento do cadastro com concessão de autorização.

## 5. Captura e armazenamento da foto

Reutilizar a captura do formulário público e o armazenamento privado da foto da pessoa.

- Câmera do posto pré-selecionada quando válida; permitir troca e usar câmera adequada para o rosto, que pode ser diferente da câmera de documentos.
- Somente vídeo, sem microfone; enviar apenas a captura confirmada.
- Validar conteúdo, formato, tamanho, dimensões e qualidade no servidor; permitir refazer.
- Não prometer prova de vida ou reconhecimento facial apenas pela presença de um rosto.
- Foto da pessoa, frente, verso e recorte documental permanecem objetos distintos.
- Associar foto à pessoa e à implantação com versão, autoria e instante. Substituição exige ação explícita e histórico.
- Não gravar imagem, base64, CPF, URL privada ou segredo em logs, payloads públicos, localStorage ou auditoria.
- Para documentos do cadastro rápido, preservar inicialmente a política existente de análise temporária sem persistência. Não copiar automaticamente a retenção do formulário público.
- Referências de arquivos retornadas pelo navegador não constituem autorização: reler e conferir propriedade e implantação no backend.
- Remover temporários em cancelamento/falha; tratar inconsistência entre transação no banco e armazenamento de objetos.

## 6. Envio real para a facial

A solicitação do PO autoriza desenvolver esse fluxo. Ela retoma o tema do ADR-013 para este caso de uso, mas não resolve automaticamente as pendências de política de dados ou de homologação do equipamento.

Desenvolver em duas entregas rastreáveis:
1. captura, OCR compartilhado, conferência e foto privada, com simulador contratual para o envio;
2. adaptador e homologação do envio real de foto ao terminal Hikvision.

O desenvolvedor deverá atualizar/propor a revisão do ADR-013 com o escopo solicitado, registrar decisões faltantes e implementar os controles necessários. Enquanto os requisitos de ativação não estiverem atendidos, apresentar o estado **Foto salva; envio facial pendente de habilitação/homologação**. Essa condição não impede implementar e testar o restante.

- Usar somente protocolo oficial compatível com modelo/firmware, validado em bancada; não inventar endpoint.
- Diferenciar cadastro de usuário no terminal e credencial facial com fotografia. Não mostrar “face sincronizada” quando só o usuário foi criado.
- Revalidar operador, permissão específica, implantação, pessoa, autorização vigente, terminal, capacidades e foto confirmada no processamento.
- Preferir o terminal do posto; com um único destino elegível, pré-selecioná-lo e deixá-lo visível. Confirmar o destino antes do envio.
- Gravar o cadastro primeiro; depois registrar pedido transacional na outbox com referências privadas resolvidas pelo worker autorizado.
- Aplicar idempotência por pessoa/credencial, terminal, autorização e versão da foto. Duplo clique não duplica pessoa ou credencial.
- Falha de envio mantém cadastro e foto salvos e permite nova tentativa consciente. Timeout exige reconciliação; não afirmar sincronização sem comprovação.
- Foto nova precisa invalidar o estado “sincronizado” da versão anterior e gerar atualização controlada.
- Preservar validade, bloqueio, expiração e revogação, incluindo remoção da credencial no terminal e confirmação do resultado.
- Enviar foto não aciona relé e não comprova passagem física.

## 7. Permissões e segurança

Conferir permissões em toda requisição Livewire e nos serviços de aplicação, inclusive depois de revogação. Aproveitar `documentos.capturar`, `pessoas.gerenciar`, `facial.enviar` e permissões de arquivos conforme a finalidade; criar permissão adicional somente se a matriz exigir distinção para biometria.

Auditar captura/conferência, criação/reutilização da pessoa, versão da foto, pedido de envio e resultado sanitizado. Mostrar mensagens simples ao porteiro e manter diagnóstico técnico na área autorizada.

## 8. Critérios de aceite e testes

- Documento capturado no cadastro rápido usa o mesmo leitor do formulário público.
- CNH e RG/CIN, frente/verso aplicável, imagem ruim, OCR desligado/timeout e digitação manual funcionam.
- Dados extraídos preenchem campos corretos, permanecem corrigíveis e exigem conferência.
- CPF existente não cria duplicidade, inclusive em duas requisições concorrentes.
- Foto atual pode ser capturada, visualizada, refeita e persistida privadamente na pessoa correta.
- Cancelamento e troca de pessoa não reutilizam foto ou dados do atendimento anterior.
- Foto de outra pessoa/implantação, usuário sem permissão e permissão revogada são recusados no servidor.
- Salvar e enviar distingue cadastro, foto e sincronização. Terminal offline não perde os dados salvos.
- Duplo clique, falha parcial no terminal, timeout, atualização de foto, vencimento e revogação têm cobertura.
- Fotografar, ler ou enviar não concede autorização nem produz abertura física.
- Testes automatizados usam imagens sintéticas e simuladores. Teste real usa amostras autorizadas e registra firmware, capacidades e resultado.
- Executar suíte PHP, Pint, testes JS/Python pertinentes e build; validar computador e celular. Registrar o que realmente foi executado.

## 9. Orientação ao implementador

Continuar nesta branch ou abrir uma branch de implementação referenciando este documento e PR. Trabalhar em entregas pequenas, com testes e atualização do acompanhamento no documento 013. Não incorporar automaticamente os PRs #69 ou #70. Não fazer merge ou implantação nesta entrega documental.

Atualizar documentos 018, 019 e 020 conforme a implementação, além do ADR-013 quando suas decisões forem formalizadas. Relatar separadamente o que está implementado, simulado, homologado em bancada e habilitado para operação.


## 10. Situação da implementação (06/11/2026)

| Item | Implementado | Simulado / testado | Homologado em bancada | Habilitado para operação |
|---|---|---|---|---|
| Etapas Documento → Conferência → Foto no cadastro rápido | sim | testes Livewire | não se aplica | sim (após merge) |
| Leitura automática do documento (mesmo leitor do formulário público) | sim | `Http::fake` + imagens sintéticas | leitor já homologado no AJ-005 | sim, quando `DOCUMENT_OCR_ENABLED=true` |
| Número do documento (RG/CNH) separado do CPF | sim | testes | — | sim |
| Reuso explícito de cadastro existente pelo CPF | sim | testes, inclusive CPF gravado por outro atendimento antes do salvamento | — | sim |
| Foto atual da pessoa, prévia, refazer, validação no servidor, armazenamento privado versionado | sim | testes com imagens sintéticas | — | sim |
| Envio do usuário ao terminal (`cadastro_pessoa`) | já existia (AJ-006) | simulador | sim (DS-K1T673DX-BR V3.18.0) | conforme `docs/016` |
| Envio da foto ao terminal (`enviar_foto_facial`) | sim | simulador + contrato Hikvision com `Http::fake` | **não** | **não** (`SDV_INTEGRACAO_FOTO_FACIAL=false` e capacidade `credencial_facial` bloqueada) |

### 10.1 Como ficou

- **Sequência:** o cadastro rápido abre na etapa Documento. Ao capturar a frente (e o verso, obrigatório para RG/CIN), a leitura começa sozinha; não há botão “Analisar”. A conferência mostra nome, CPF, nascimento e número do documento, todos editáveis. Uma nova leitura não sobrescreve campo já corrigido pelo operador e avisa isso. “Digitar os dados manualmente” pula para a conferência a qualquer momento.
- **Foto da pessoa:** câmera própria de finalidade `rosto` (pode ser diferente da câmera de documentos), apenas vídeo, prévia e “Refazer foto”. O servidor valida tipo (JPEG/PNG), tamanho (até 5 MB), lado mínimo de 320 px e brilho. Se a pessoa já tem foto, ela é mantida, a menos que o operador marque “Substituir a foto atual”.
- **Duplicidade:** o CPF é consultado na implantação ativa; se existir, o operador escolhe “Usar este cadastro” (auditoria `cadastro_rapido_reutilizou_pessoa`). Identidade e contato do cadastro existente não são sobrescritos. A criação usa transação com `lockForUpdate` e trata violação do índice único. Se o número do RG/CNH já pertence a outra pessoa, o salvamento volta para a conferência com o erro no campo do número. Qualquer erro de conferência encontrado ao salvar também leva de volta à etapa Conferência.
- **Salvar / Salvar e enviar:** um bloqueio por atendimento (`Cache::lock`) e o token do formulário impedem duplo clique. O resultado mostra quatro linhas separadas: cadastro, foto, terminal (cadastro do usuário) e terminal (foto). Terminal fora do ar não desfaz o cadastro nem a foto.
- **Autorização:** salvar não cria autorização. Ao enviar para a facial, turista e visitante com imóvel recebem a visita de até 24 h do AJ-006 (turista sem vínculo com imóvel); morador, prestador e outro usam a autorização do vínculo. Ver §10.1.1. **Decisão a confirmar pelo PO:** visitante usar a mesma visita de 24 h do turista.
- **Permissões:** toda ação do cadastro rápido exige `validacao.registrar` e `pessoas.gerenciar`; o envio exige também `facial.enviar`. As permissões são conferidas a cada requisição Livewire.
- **Limpeza:** cancelar, iniciar outro atendimento ou concluir o cadastro descarta capturas, prévias e temporários e fecha as câmeras (evento `cadastro-rapido-encerrado`).
- **Envio da foto:** depois do pedido `cadastro_pessoa`, o `EnvioFacial` registra, quando permitido, um pedido `enviar_foto_facial` na outbox com chave `foto-facial:{credencial}:{terminal}:{checksum}:{tentativa}`. O payload leva apenas identificadores e a versão (checksum) da foto. O worker só envia depois que o usuário estiver sincronizado no terminal (senão repete), relê e confere a foto, reduz em memória para JPEG de até 640 px e chama o adaptador. Foto nova marca a anterior como “não enviada” em todos os terminais; revogação confirmada marca a face como removida. Estado em `foto_facial_sincronizacoes`.

### 10.1.1 Telefone e imóvel opcionais (complemento do PO, 08/11/2026)

No cadastro rápido, **telefone** e **código do imóvel** não são obrigatórios, para nenhum tipo de acesso.

- A interface marca os dois como “(opcional)”, e o backend não os exige.
- **Telefone:** quando informado, só aceita números, espaços, parênteses, `+`, `-` e `.`, com 10 a 13 dígitos (DDD obrigatório). Em branco, nenhum contato é criado.
- **Imóvel:** quando informado, precisa existir na implantação; se não existir, o salvamento volta à Conferência com o erro no campo. Em branco, nenhum imóvel ou vínculo é criado.
- A ausência dos dois não impede salvar o cadastro e a foto.
- **Envio à facial:** continua exigindo uma autorização vigente. Visitante só recebe a visita de 24 h quando há imóvel informado. Sem imóvel, nenhuma autorização é criada automaticamente, e o envio é recusado com “sem autorização vigente” se a pessoa não tiver outra. Turista mantém a visita de 24 h do AJ-006, que por definição não tem imóvel. Morador, prestador e outro dependem do vínculo; sem imóvel, o envio é recusado da mesma forma.
- Testes: `CadastroRapidoFotoTest` (salvar sem telefone, sem imóvel e sem os dois, para visitante, morador, prestador e outro; telefone e imóvel inválidos recusados; telefone válido gravado) e `FotoFacialEnvioTest` (visitante sem imóvel não ganha autorização automática).

### 10.2 Pendências para ativar o envio da foto

1. Confirmar no guia ISAPI do firmware V3.18.0 os nomes das partes do multipart de `PUT /ISAPI/Intelligent/FDLib/FDSetUp` (`FaceDataRecord`, `img`), a biblioteca (`blackFD`, `FDID=1`) e o limite de tamanho (provisório: 200 KB / 640 px).
2. Confirmar em bancada que remover o usuário (`UserInfo/Delete`) também remove a face; se não remover, implementar a remoção oficial da face antes da ativação.
3. Executar o roteiro do §11 com amostras autorizadas e registrar o resultado no `docs/016`.
4. Marcar `credencial_facial` como homologada no perfil do firmware (`docs/015` §8) e ligar `SDV_INTEGRACAO_FOTO_FACIAL=true` no ambiente.
5. Formalizar com o PO as decisões do ADR-013 §21.3 (finalidade, base legal, transparência, retenção, alternativa).

## 11. Roteiro de homologação de bancada do envio da foto

Pré-requisitos: terminal de bancada DS-K1T673DX-BR V3.18.0 já homologado para usuário (`docs/016`), pessoa de teste com autorização expressa, ambiente de homologação com a bandeira e a capacidade ligadas **somente nele**.

1. Registrar modelo, firmware, número de série e `numOfFace` inicial (consulta de sincronização).
2. Cadastro rápido com a pessoa de teste → “Salvar e enviar para a facial”. Conferir: cadastro do usuário confirmado; depois foto confirmada; `numOfFace` = 1.
3. Reconhecimento no terminal com a pessoa presente: registrar se reconheceu e se **nenhum relé foi acionado pelo envio**.
4. Reenviar sem mudar a foto: o pedido não deve ser repetido.
5. Trocar a foto e reenviar: o estado anterior vira “não enviada” e a nova versão é confirmada.
6. Enviar com terminal desligado: cadastro e foto continuam salvos; estado “pendente/falhou” sem afirmar sincronização; após religar, reprocessar.
7. Imagem acima do limite ou PNG: recusa clara sem chamar o terminal.
8. Revogar a autorização (ou deixar expirar): usuário e face removidos; `numOfFace` = 0; estado “removido”.
9. Registrar resultados, respostas sanitizadas e versão do SDV no `docs/016`. Apagar a pessoa de teste do terminal ao final.
