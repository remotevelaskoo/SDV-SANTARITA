# AJ-010 — Cadastro rápido por foto, OCR compartilhado e foto para a facial

**Produto:** SDV Access — Santa Rita
**Data:** 06/11/2026
**Origem:** solicitação do Product Owner: aplicar ao cadastro rápido o modelo de extração por foto do formulário público, com captura da foto da pessoa para envio ao terminal facial.
**Situação:** implementado no PR #74 (entrega 1 completa; entrega 2 com contrato simulado e adaptador prontos, **sem homologação de bancada e desligada**). Ver §10 e §11.
**Complemento aprovado em 07/11/2026:** preenchimento por voz somente do nome completo no cadastro rápido (§12), pendente de implementação e testes.
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

> **Revisão de 08/11/2026 (§14):** o imóvel passou a ser obrigatório para **visitante**. Para os demais tipos, telefone e imóvel continuam opcionais; o telefone continua opcional para todos.

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


## 12. Nome completo por voz — complemento aprovado pelo PO em 07/11/2026

**Situação:** implementado no PR #74 em 08/11/2026 e testado com reconhecimento simulado (§12.7). Falta a verificação manual com microfone real nos computadores e celulares da portaria.

### 12.1 Objetivo e escopo

Facilitar o atendimento por operadores com pouca familiaridade com informática. Adicionar preenchimento por voz **exclusivamente no campo Nome completo do cadastro rápido**, na etapa Conferência. Não incluir microfone no cadastro completo, formulário público, análise do pré-cadastro ou demais telas/campos.

Manter Blade/Livewire, os componentes e o Design System existentes. A digitação manual permanece disponível durante todo o fluxo. O recurso é assistivo: transcrição não verifica identidade e exige conferência humana.

### 12.2 Interação aprovada

1. Ao lado do Nome completo, apresentar botão de microfone fácil de identificar, com nome acessível **Preencher por voz**, foco visível e acionamento por teclado.
2. Iniciar reconhecimento em português do Brasil (`pt-BR`) somente após o clique explícito. Solicitar permissão quando necessário e mostrar **Ouvindo… fale o nome completo.** O botão de microfone não submete o formulário.
3. Oferecer cancelamento visível. Reconhecimento e captura devem terminar ao concluir, cancelar, sair da tela, mudar de etapa/pessoa ou encerrar o atendimento. Impedir sessões simultâneas e descartar resultados atrasados de um atendimento anterior.
4. Preencher o campo somente com o resultado final não vazio. Exemplo: “Vinícius Velasco”. Não substituir o conteúdo com resultados parciais.
5. Se já houver nome, preservar o texto até uma transcrição bem-sucedida. Falha, resultado vazio ou cancelamento não apagam o nome. Se o operador editar o texto enquanto o reconhecimento estiver ativo, cancelar/ignorar a transcrição pendente para não sobrescrever a edição.
6. O texto transcrito deve atualizar o estado Livewire correto e seguir as validações existentes de nome. Permitir corrigir digitando ou iniciar nova gravação. A transcrição deve contar como edição manual para a proteção contra sobrescrita pelo OCR.
7. Não salvar cadastro, capturar fotografia, conceder autorização ou enviar à facial automaticamente. As ações de conferência e salvamento existentes permanecem explícitas.

### 12.3 Falhas, compatibilidade e privacidade

- Não entendeu a fala: **Não consegui entender. Toque no microfone e tente novamente ou digite.**
- Permissão negada ou dispositivo indisponível: explicar de forma simples e manter digitação, foto e salvamento disponíveis; não repetir pedidos de permissão em loop.
- Navegador sem suporte: apresentar indicação discreta de indisponibilidade e manter o fluxo manual funcional, sem botão que falha silenciosamente. Documentar os navegadores efetivamente verificados.
- Detectar suporte antes da ativação e tratar erros de rede/serviço, interrupção e ausência de fala sem perder o atendimento. Não presumir funcionamento offline.
- Não armazenar áudio na aplicação nem registrar áudio ou transcrições em logs, auditoria técnica ou armazenamento local do navegador. O nome confirmado continua seguindo a persistência normal do cadastro.
- Se o mecanismo enviar áudio a serviço externo, documentar provedor/dependência, necessidade de rede e tratamento dos dados; não afirmar processamento exclusivamente local sem comprovação. Não adicionar serviço pago ou novas credenciais silenciosamente; registrar a decisão técnica necessária.
- A câmera de documento e de rosto continua solicitando **somente vídeo**, conforme §5 e documento 018. O microfone desta melhoria é uma ação separada, explícita e temporária; não ampliar a permissão da câmera para áudio.

### 12.4 Regras do AJ-010 preservadas

Telefone e código do imóvel continuam opcionais na interface e no backend para todos os tipos de acesso (§10.1.1), com validação somente quando informados. A ausência não bloqueia cadastro nem foto e não gera contato vazio, imóvel ou vínculo fictício.

Envio à facial continua sujeito às permissões, autorização e vigência aplicáveis. O reconhecimento de voz não cria autorização, vínculo, credencial ou liberação física, nem contorna a ausência de imóvel. Preservar as regras já documentadas para turista e visitante.

### 12.5 Critérios de aceite e testes exigidos ao implementador

Usar reconhecimento simulado nos testes automatizados, sem áudio ou pessoas reais e sem chamadas externas. Separar testes de interação no navegador dos testes Livewire/backend; testes de presença de marcação sozinhos não comprovam reconhecimento funcional.

| Cenário | Resultado esperado |
|---|---|
| Fala reconhecida em `pt-BR` | Resultado final preenche Nome completo e seu estado Livewire; demais campos inalterados |
| Resultado parcial ou vazio | Não substitui o nome existente |
| Correção manual e nova tentativa | Correção disponível; nova transcrição concluída substitui o nome sem submissão automática |
| Digitação durante reconhecimento ou OCR posterior | Resultado pendente não sobrescreve a digitação; OCR respeita nome conferido/editado |
| Cancelamento, erro ou falta de fala | Preserva nome anterior e permite nova tentativa ou digitação |
| Permissão negada, microfone ausente, serviço indisponível ou navegador sem suporte | Mensagem simples; cadastro manual, captura de foto e salvamento seguem disponíveis |
| Cliques repetidos, mudança de etapa/pessoa ou saída da tela | Uma sessão por vez; captura encerrada e callbacks antigos ignorados |
| Escopo | Microfone somente no Nome completo do cadastro rápido; ausente nas demais telas/campos |
| Transcrição concluída | Não salva, fotografa, autoriza, envia à facial ou abre relé automaticamente |
| Sem telefone, sem imóvel e sem ambos | Cadastro e fotografia salvos, sem contato vazio nem imóvel/vínculo fictício |
| Telefone e imóvel preenchidos | Valores válidos aceitos e inválidos recusados conforme §10.1.1 |
| Pessoa sem autorização ou com autorização vencida | Voz não cria autorização; envio facial continua impedido pelas regras vigentes |

Verificar manualmente computador e celular usados pela portaria, incluindo permissão inicial, cancelamento, nova tentativa, foco/teclado e convivência com a câmera. Executar testes pertinentes, Pint e build conforme os padrões do projeto, registrando comandos e resultados reais.

### 12.6 Orientação ao Claude

Puxar a atualização de `codex/aj010-cadastro-rapido-foto-ocr` e ler este documento, principalmente §10.1.1 e §12, antes de implementar. Manter a entrega restrita a este complemento e preservar o trabalho do AJ-010 em andamento. Atualizar a situação desta seção e o acompanhamento do documento 013 somente após implementação e testes, distinguindo simulação, verificação manual e limitações de compatibilidade. Esta entrega documental não autoriza merge nem implantação.

### 12.7 Implementação (08/11/2026)

- **Onde:** só no campo Nome completo da etapa Conferência do cadastro rápido (`access-validation.blade.php`, componente Alpine `nomePorVoz` em `resources/js/nome-por-voz.js`). Não há microfone no cadastro completo, no formulário público nem na análise do pré-cadastro, e o teste confere isso.
- **Mecanismo:** Web Speech API do navegador (`SpeechRecognition` / `webkitSpeechRecognition`), `lang = pt-BR`, sem resultados parciais, uma frase por vez. Não há dependência nova, serviço pago nem credencial. **Atenção:** no Chrome e no Edge o áudio é enviado ao serviço de reconhecimento do fabricante do navegador (Google ou Microsoft) e exige internet; o SDV não recebe nem guarda áudio. O processamento não é local. O Firefox não oferece a API; nele aparece só o aviso “Preenchimento por voz indisponível neste navegador. Digite o nome.”. O Safari oferece a API com prefixo; não foi verificado.
- **Fluxo:** o botão “Preencher por voz” (com ícone, foco visível, acionável por teclado) inicia a escuta e passa a se chamar “Cancelar”. A mensagem “Ouvindo… fale o nome completo.” fica visível. O resultado final, não vazio, chama `preencherNomePorVoz` no Livewire, que confere permissão, normaliza espaços, limita a 120 caracteres, marca o campo como editado (o OCR não o sobrescreve) e valida só o nome. Não salva, não fotografa, não autoriza e não envia.
- **Proteções:** uma sessão por vez; cada sessão tem número e os retornos de uma sessão encerrada são ignorados; digitar no campo durante a escuta cancela a transcrição pendente; mudar de etapa, cancelar o atendimento ou sair da página encerra a escuta. Falha, silêncio ou cancelamento não apagam o nome. Não há nova tentativa automática nem novo pedido de permissão sem clique.
- **Erros:** sem fala → “Não consegui entender…”; permissão negada → orientação para liberar o microfone ou digitar; sem microfone; serviço sem internet; outros → mensagem genérica com nova tentativa.
- **Privacidade:** nenhuma transcrição em log, auditoria ou localStorage; as câmeras continuam pedindo só vídeo.
- **Testes executados:**
  - `tests/js/nome-por-voz.test.mjs` (9 testes, reconhecimento simulado): início só no clique e em pt-BR, resultado final, parcial/vazio, digitação durante a escuta, cancelamento e nova tentativa, sessão única e retorno atrasado, erros, sem suporte;
  - `tests/Feature/Aj010/NomePorVozTest.php` (7 testes): escopo do microfone, preenchimento sem salvar/enviar/autorizar, vazio preserva, validação, OCR posterior respeita o nome, etapa Foto/atendimento encerrado/permissão, visitante sem imóvel continua sem autorização;
  - navegador (Playwright, Chromium com reconhecimento simulado), computador 1366×900 e celular Pixel 7: teclado, mensagem de escuta, cancelamento, erro, nova tentativa, digitação durante a escuta, sem rolagem horizontal e sem erro de JavaScript; navegador sem a API mostra só o aviso.
- **Pendente:** teste manual com microfone real (permissão inicial, ruído da portaria, convivência com a câmera) nos computadores e celulares usados na portaria.


## 13. Cadastro manual em destaque — complemento aprovado pelo PO em 07/11/2026

**Situação:** implementado na branch `codex/aj010-destaque-cadastro-manual` em 08/11/2026, aguardando revisão e merge (§13.4). O PO havia identificado dificuldade para encontrar a entrada manual.

### 13.1 Resultado esperado

Ao abrir o cadastro rápido, o porteiro deve encontrar imediatamente duas ações claras: **Fotografar documento** e **Cadastrar manualmente**. A entrada manual deve ser um botão de destaque no topo da etapa Documento, antes da câmera e das instruções, visível sem expandir menus ou rolar a tela em computador e celular. Não deixar essa opção apenas como link discreto, dentro de detalhes ou como alternativa apresentada somente após erro do OCR.

Usar botões com legibilidade e área de toque adequadas ao Design System. Em telas largas podem ficar lado a lado; em telas pequenas, empilhados. Não alterar o cadastro completo nem adicionar novos caminhos ou formulários duplicados.

### 13.2 Comportamento

- **Cadastrar manualmente** abre diretamente a etapa Conferência e coloca o foco no Nome completo, reutilizando o fluxo manual existente e suas validações.
- O operador pode digitar ou usar o microfone do nome. Não exigir tentativa de fotografia documental, OCR ou permissão de câmera/microfone para iniciar a digitação.
- A escolha não salva automaticamente, não cria pessoa, vínculo ou autorização e não envia nada ao terminal.
- Preservar dados já digitados/conferidos e não apagar o atendimento ao alternar para entrada manual; encerrar qualquer captura/reconhecimento pendente para impedir retorno tardio sobrescrevendo campos.
- Manter a etapa Foto da pessoa, as ações finais e suas regras já existentes. Entrar manualmente não dispensa a fotografia nem muda sua regra vigente.
- Preservar telefone e imóvel opcionais (§10.1.1), microfone somente no Nome completo do cadastro rápido (§12), permissões, vigência e autorização exigidas para a facial.

### 13.3 Critérios de aceite e orientação ao Claude

1. A ação **Cadastrar manualmente** aparece com destaque no primeiro bloco do cadastro rápido, antes da câmera, sem abrir menu, em computador e celular.
2. Clique ou ativação por teclado abre Conferência, com foco no nome e sem iniciar câmera, microfone ou OCR.
3. Digitação, nome por voz e continuação para Foto funcionam pelo mesmo fluxo existente; nenhuma gravação ou integração é disparada pela escolha do modo.
4. Dados preenchidos são preservados; processamento atrasado não sobrescreve correções ao trocar para manual.
5. Cadastro e fotografia continuam salvando sem telefone, sem imóvel e sem ambos; ausência de imóvel não gera vínculo fictício ou autorização automática.
6. Validar visualmente computador e celular, foco visível, contraste, área de toque e ausência de rolagem horizontal. Registrar verificações efetivamente realizadas.

Claude: partir da main atual, implementar somente este ajuste visual/de navegação e reaproveitar a ação manual existente. Incluir testes pertinentes de abertura da etapa manual e preservação das regras; atualizar esta seção e o acompanhamento do documento 013 com os resultados reais. Não registrar como implementado antes da entrega. Não fazer merge nem implantação automaticamente.

### 13.4 Implementação (08/11/2026)

- **Abertura:** o topo da etapa Documento (`partials/captura-documento.blade.php`) tem dois botões grandes (48 px de altura): **Fotografar documento** (primário) e **Cadastrar manualmente** (secundário). Ficam lado a lado a partir de 640 px e empilhados no celular. O bloco rola para a área visível ao abrir o cadastro rápido. O título do bloco da câmera passou a “Foto do documento” para não repetir o nome do botão.
- **Sem caminho duplicado:** o antigo link “Digitar os dados manualmente”, no fim do bloco, foi substituído pelo novo botão; os dois chamam o mesmo `digitarManualmente`. O cadastro completo não mudou.
- **Fotografar documento:** abre a câmera já escolhida para o posto ou pede a autorização, e leva a tela até a câmera.
- **Cadastrar manualmente:** fecha a câmera do documento no navegador, descarta capturas temporárias, abre a Conferência e põe o foco no Nome completo (evento `cadastro-rapido-foco-nome`). Não inicia câmera, microfone nem OCR, e não grava pessoa, vínculo, autorização ou operação de terminal. Os dados já digitados e o atendimento são preservados. Exige `validacao.registrar` e `pessoas.gerenciar`.
- **Retorno atrasado:** upload ou leitura de documento que chegue depois de o operador sair da etapa Documento é descartado e não sobrescreve o que foi digitado.
- **Regras mantidas:** etapa Foto, ações finais, telefone e imóvel opcionais (§10.1.1), voz só no Nome completo (§12), permissões e regras de autorização da facial.
- **Testes executados:**
  - `tests/Feature/Aj010/CadastroManualDestaqueTest.php` (5 testes): botões antes da câmera e fora de menus, um único caminho manual; abertura da Conferência com foco sem gravar nada e preservando dados; leitura atrasada descartada; fluxo manual até a Foto, salvando sem telefone e sem imóvel e sem vínculo ou autorização; permissão.
  - Suíte completa `php artisan test`: 487 testes passando; Pint sem pendências; `node --test tests/js/*.test.mjs`: 15 passando; `pnpm build` sem erros.
  - Navegador (Playwright, Chromium), computador 1366×900 e celular Pixel 7: os dois botões visíveis sem rolar depois de abrir o cadastro rápido e acima da câmera; ativação por teclado (Enter) abre a Conferência com foco no Nome completo; nenhuma chamada de câmera ou de voz ao escolher a entrada manual; digitação funciona; sem rolagem horizontal e sem erro de JavaScript. Contraste seguiu as cores dos botões do Design System; não foi medido com ferramenta.
- **Não verificado:** dispositivos reais da portaria, Safari e Firefox.


## 14. Responsável somente para morador — decisão do PO em 07/11/2026

**Situação:** implementado na branch `codex/aj010-visitante-sem-responsavel` em 08/11/2026, aguardando revisão e merge (§14.5).

> **Revisão do PO em 08/11/2026 — prevalece sobre §14.1 a §14.4 onde houver conflito:** o **turista** tem entrada de até 24 h **sem responsável** (e sem imóvel). O **visitante** fica **atrelado a um imóvel e a um responsável**, ambos obrigatórios. Morador mantém o responsável opcional; prestador e outro não têm responsável. Isso também resolve o ponto da RN-023 (todo visitante autorizado tem responsável identificado).

### 14.1 Regra aprovada e precedência

- No cadastro da pessoa atendido pelo AJ-010, **visitante não precisa informar responsável**. Não exibir nem exigir esse campo para visitante, seja por entrada manual, OCR ou nome por voz.
- O campo de responsável é aplicável **somente quando o tipo for morador**. Preservar a regra de preenchimento aplicável a morador; esta decisão não define uma nova relação de tutela nem autoriza inventar um responsável.
- Corrigir a interface, textos de ajuda e validações do backend que ainda exigem responsável de visitante. Não basta ocultar um campo que continua obrigatório no servidor.
- Nenhum tipo diferente de morador deve herdar a exigência de responsável deste formulário. A primeira entrega e sua homologação focam visitante; não remover outros tipos nem alterar suas regras de autorização por causa desta simplificação.
- Ao mudar de morador para visitante, limpar o responsável do estado do formulário e ignorar valor residual/forjado no backend; não persistir responsável ou vínculo fictício para satisfazer validação. Preservar dados históricos existentes; não executar limpeza retroativa do banco.
- Cadastro e fotografia de visitante devem salvar sem responsável, inclusive sem telefone, sem imóvel ou sem ambos, mantendo as demais validações de identidade/foto vigentes.

Esta decisão substitui, para o campo de responsável deste cadastro, exigências anteriores incompatíveis. Não elimina o responsável operacional por aprovação, a autoria/auditoria do porteiro, autorizações de acesso ou relações de domínio existentes fora deste formulário.

### 14.2 Cadastro não equivale a autorização

Ausência de responsável não deve impedir salvar pessoa e foto. O envio à facial continua exigindo permissão, autorização válida e vigência. Não criar autorização, responsável ou vínculo fictício para contornar a ausência. Preservar a regra de visitante sem imóvel do §10.1.1 e não alterar implicitamente o processo de aprovação/validação de entrada.

### 14.3 Critérios de aceite e testes

1. Visitante: campo e indicação de obrigatoriedade de responsável ausentes; backend aceita responsável ausente nas ações de salvamento.
2. Visitante cadastrado manualmente ou após OCR: salva pessoa e fotografia sem responsável, também nas combinações sem telefone, sem imóvel e sem ambos.
3. Morador: campo de responsável visível e comportamento vigente desse tipo preservado; demais tipos não recebem a antiga exigência deste formulário.
4. Troca de morador para visitante não mantém responsável residual; valor forjado enviado para visitante é ignorado, sem gerar relação fictícia. Cadastro existente não tem histórico apagado.
5. Sem autorização vigente, o envio facial permanece impedido; salvar sem responsável não gera autorização automática.
6. Preservar isolamento por implantação, permissões, autoria/auditoria, proteção da fotografia e demais validações existentes.

### 14.4 Orientação ao Claude

Partir da main atual e implementar esta seção, verificando onde o campo é apresentado e validado (inclusive `AccessValidation`, serviços de cadastro e views). Conciliar as especificações dependentes de cadastro/regras de negócio que ainda digam que visitante deve preencher este campo. Não ampliar a alteração para dispensar aprovação ou autorização de acesso. Executar testes pertinentes e atualizar esta seção e o documento 013 com os resultados reais, sem marcar como implementado antecipadamente. Não fazer merge nem implantação automaticamente.

### 14.5 Implementação (08/11/2026, com a revisão do PO)

| Tipo | Responsável | Imóvel | Envio à facial |
|---|---|---|---|
| Visitante | **obrigatório** (“Responsável pelo visitante”) | **obrigatório** | visita de até 24 h ao imóvel, com o responsável informado |
| Turista | não existe | opcional, sem vínculo | entrada de até 24 h sem imóvel nem responsável (AJ-006) |
| Morador | opcional | opcional | autorização do vínculo; sem imóvel, recusado |
| Prestador, outro | não existe | opcional | autorização do vínculo; sem imóvel, recusado |

- **Interface:** o campo de responsável aparece só para visitante (obrigatório, com asterisco) e morador (opcional). O imóvel aparece como obrigatório para visitante e “(opcional)” para os demais, com ajuda própria de cada tipo.
- **Backend:** `quickResponsible` e `quickPropertyCode` são `required_if:quickAccessType,visitante`, com as mensagens “Informe o responsável pelo visitante.” e “Informe o imóvel que o visitante vai visitar.”. O erro aparece ao tentar avançar para a Foto e ao salvar. Ao trocar o tipo para um sem responsável, o valor é limpo; valor residual ou forjado para esses tipos é descartado antes de validar e gravar.
- **Autorização:** salvar continua sem criar autorização. A visita automática do visitante passa a levar sempre o responsável informado; a do turista fica sem responsável. Sem autorização vigente (por exemplo, prestador sem imóvel), o envio continua recusado. Dados históricos não foram alterados.
- **Testes executados:**
  - `tests/Feature/Aj010/ResponsavelEImovelPorTipoTest.php` (9 testes): campos por tipo; visitante sem imóvel ou sem responsável não avança nem salva; visitante com os dois salva pessoa e foto, telefone opcional, sem autorização ao salvar; visitante enviado à facial ganha visita de até 24 h com o responsável e o imóvel; turista entra por até 24 h sem imóvel nem responsável e valor forjado é ignorado; visitante após OCR pede os dois campos; responsável residual descartado; morador opcional, prestador e outro sem responsável; sem autorização vigente o envio é recusado.
  - Testes anteriores ajustados à nova regra: `CadastroRapidoFotoTest` (combinações sem imóvel usam turista no lugar de visitante), `FotoFacialEnvioTest`, `NomePorVozTest` e `CadastroManualDestaqueTest`.
  - Suíte completa `php artisan test`: 496 testes passando; Pint sem pendências; `node --test tests/js/*.test.mjs`: 15 passando; `pnpm build` sem erros.
  - Navegador (Playwright, Chromium), computador 1366×900 e celular Pixel 7: campos corretos para visitante, turista, morador e prestador; visitante sem os dois campos mostra os dois erros ao avançar; sem rolagem horizontal e sem erro de JavaScript.
