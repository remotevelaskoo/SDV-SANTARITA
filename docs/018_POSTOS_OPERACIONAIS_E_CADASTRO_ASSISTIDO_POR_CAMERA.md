# SDV Access — Postos operacionais e cadastro assistido por câmera

## 1. Objetivo

Especificar os postos operacionais, a seleção contextual de equipamentos, a liberação excepcional simplificada e o cadastro rápido assistido por câmera e OCR. Permanecem separadas as responsabilidades de cadastro, vínculo, autorização, credencial e evento de acesso.

## 2. Postos da implantação Santa Rita

1. Guarita Principal;
2. Guarita — Atendimento de Veículos;
3. Guarita — Atendimento de Pedestres.

O posto é o contexto operacional assumido pelo usuário durante o turno; não substitui perfil, permissão, ponto de acesso ou equipamento.

Após autenticar-se, o operador escolhe um posto autorizado. O sistema registra início, término e troca de posto, mantém o posto ativo visível e carrega suas câmeras, pontos e equipamentos preferenciais.

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
