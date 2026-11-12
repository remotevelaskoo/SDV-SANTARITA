# SDV One — pré-cadastro público integrado ao nó local

**Data:** 11/11/2026  
**Base verificada:** main `fc0497a`, atualizada antes da implementação.  
**Referências:** documentos 006, 019, 021 (inclusive revisão do PO de 08/11/2026) e ADR-018.  
**Situação:** portal publicado em homologação restrita, com envio real ao Supabase e integração comprovada com Laravel isolado de teste. Produção desativada; instalação e homologação no equipamento real da portaria pendentes.

## 1. Decisão e escopo

O PO descartou a prévia do sistema completo no Vercel e solicitou um processo público de pré-cadastro integrado ao sistema. Manter o núcleo administrativo e operacional no servidor local, em Laravel/Blade/Livewire. O serviço separado `services/pre-cadastro-publico` usa HTML/CSS/JavaScript e pequenas Functions Node.js, compatíveis com Vercel; não migra o produto para Next.js/React.

O sistema legado de certificados é independente e não recebe alterações. Não publicar a raiz Laravel como uma nova demonstração completa do sistema.

## 2. Infraestrutura conferida em 11/11/2026

| Recurso | Situação real |
|---|---|
| Organização Supabase | `remotevelaskoo`, escolhida pelo usuário |
| Novo projeto isolado | `sdv-santa-rita` (`tbcxutrdstdiymgtzhhm`), região `sa-east-1` |
| Custo de criação cotado pelo Supabase | US$ 0/mês; não é promessa de gratuidade além dos limites do plano |
| Projeto `certificados` | Mantido; nenhuma migração executada nele |
| Banco do portal | Duas tabelas, políticas explícitas de bloqueio ao acesso direto e funções de quota/ACK |
| Imagens | Bucket `pre-cadastro-privado`, não público, limite 8 MB por objeto |
| Segurança do banco | RLS e ausência de SELECT para anon/authenticated conferidos por SQL; auditor Supabase sem avisos após políticas explícitas |
| Dados no banco | Uma solicitação sintética usada na homologação; nenhuma pessoa real cadastrada |
| Teste antigo Vercel | `sdv-santarita-vercel`; exclusão ainda exige confirmação do usuário no painel, conforme ferramenta do provedor |
| Novo projeto Vercel do portal | `sdv-santa-rita-pre-cadastro` (`prj_UuOLXXPCxRdHJzxiUx0W3FYMumQ8`), raiz `services/pre-cadastro-publico`; chave secreta configurada pelo proprietário como variável sensível |
| Novo portal publicado | Sim, somente homologação (`target=staging`, variáveis Preview), deployment `dpl_GAQPzqLdSiHBVuHkAkbW7se5iGu3`; produção com `PORTAL_ENABLED=false` |

Migrações remotas aplicadas: `pre_cadastro_publico_hibrido` e `pre_cadastro_politicas_privadas_explicitas`. `services/pre-cadastro-publico/supabase/bootstrap.sql` reproduz a estrutura final em um projeto novo. Não reaplicar esse bootstrap sobre o banco já criado; alterações futuras exigem migrações próprias.

## 3. Fluxo

1. Usuário abre link/QR de convite do portal.
2. Preenche identificação e dados opcionais; envia frente documental, verso de RG/CIN e selfie em arquivos separados.
3. Backend valida convite, dados e imagens. Imagens sobem diretamente por URL assinada, evitando o limite de corpo das Functions Vercel.
4. Somente depois de todos os arquivos validados a solicitação sai de `rascunho` para `enviado`. O visitante recebe protocolo e recibo privado de acompanhamento.
5. O comando Laravel `pre-cadastro:sincronizar-nuvem` busca até dez solicitações por ciclo, por conexão HTTPS de saída. Não abre porta no servidor do condomínio.
6. O nó confere implantação, campos, categorias e checksums; grava o `PreRegistration` existente e os arquivos via `PrivateFileService`. O registro aparece na fila já usada pela portaria, com situação `aguardando` e conferência manual obrigatória.
7. Confirmação à nuvem só acontece após o commit local. Reentrega usa o vínculo único da solicitação externa e não recria registros ou arquivos.
8. Decisões locais posteriores retornam à nuvem por ACK com versão crescente. Versões antigas ou divergentes são recusadas, sem sobrescrever silenciosamente a decisão.

## 4. Campos e interface

O formulário público separado tem blocos de identificação, documento, selfie, veículo e confirmação, com identidade SDV One, campos legíveis e uma coluna no celular. Não possui menus administrativos, microfone, caixa ou acesso a terminais.

- Nome e CPF obrigatórios; telefone, e-mail, nascimento e endereço opcionais, validados quando informados.
- Tipos públicos preservados: turista, visitante e prestador. Não confundir com todos os tipos do cadastro rápido.
- **Revisão atual do PO:** visitante tem imóvel e responsável obrigatórios. O portal valida o imóvel no catálogo real e resolve o responsável no servidor; nomes de responsáveis não aparecem na lista pública. Não aceitar o responsável indicado pelo navegador como autoridade.
- Turista não possui vínculo com imóvel nem responsável; destino Praia do Santa Rita. Prestador mantém destino no formulário público, sem responsável por esta regra.
- Catálogo de imóveis vem do **cadastro local** (imóveis ativos e responsável principal vigente), publicado à nuvem a cada sincronização (§12). A variável `PORTAL_DESTINATIONS` e a lista fixa `DestinationDirectory` foram removidas. Sem catálogo publicado, não aceitar visitante/prestador com destino inventado.
- Frente documental e selfie obrigatórias; verso adicional obrigatório no RG/CIN. Fotografias válidas, com lado mínimo de 320 px e limite de 40 milhões de pixels. Metadados EXIF removidos na normalização JPEG; objetos originais também são privados.
- Veículo opcional; placa obrigatória se indicado veículo. Modelo e cor opcionais.
- Nome digitado manualmente. OCR automático ainda não exposto por esta primeira fatia na nuvem; o formulário informa conferência manual. O motor e o formulário Laravel atuais não foram removidos ou alterados.
- Recibo informa recebimento/situação e não apresenta dados pessoais ou documentos. Aprovação de pré-cadastro não equivale à autorização de entrada.

## 5. Limites de autoridade e privacidade

O portal não cria pessoas operacionais, vínculos, autorização, credencial, contribuição ou comando físico. Caixa e contribuição de turista de R$ 25,00 permanecem exclusivamente nas regras atuais do atendimento local. O envio facial conserva permissões, vigência, decisão humana e homologação existentes.

Supabase é acessado pelo backend com chave secreta; essa chave não pode aparecer no frontend, repositório ou nó local. O nó usa segredo próprio e revogável. O convite também tem segredo diferente. As tabelas não concedem leitura/escrita a `anon`/`authenticated` e o bucket não tem política pública. Cabeçalhos proíbem cache e referer; sem analytics de terceiros.

Antes da produção: formalizar aviso e versão de privacidade, retenção dos rascunhos/objetos originais, exclusão de órfãos e limpeza dos contadores antigos. Backup externo e restauração continuam independentes da sincronização. O projeto não implementa sincronização de caixa, acessos ou todos os cadastros, nem homologa equipamentos offline.

## 6. Queda e recuperação

- Falha da internet do condomínio: portal continua recebendo na nuvem; os itens aguardam busca, sem aprovação automática.
- Falha da nuvem: operações já existentes no nó local continuam conforme as regras atuais; esse conector não bloqueia o cadastro rápido.
- Falha de upload: solicitação fica como rascunho, fora da fila local; reenvio não deve sobrescrever objetos já aceitos.
- Falha de ACK após commit: o pré-cadastro e a foto permanecem locais; o comando confirma na próxima execução sem duplicá-los.
- Campos mudados e solicitações inválidas: não sobrescrever fatos locais; retorno de versão conflitante fica como pendência.
- Comando retorna contagens e código de falha sem payload sensível. Painel dedicado de conectividade, alertas de atraso e demais metas do ADR-018 ainda são pendências antes do go-live.

## 7. Configuração e ativação

1. Revisar/integração da branch antes de atualizar o nó local. Executar sua migração, que só acrescenta `pre_cadastro_nuvem_recebimentos`; não move nem apaga tabelas existentes.
2. Configurar projeto Vercel separado com raiz `services/pre-cadastro-publico`. Usar Node.js, saída `public` e Functions `api/`, nunca a raiz administrativa.
3. Configurar no backend as variáveis do `.env.example` do portal: URL Supabase, chave secreta, UUID da implantação operacional, segredos independentes do convite/nó/quota, nome e aviso de privacidade (o catálogo vem do sistema local, §12). Começar com `PORTAL_ENABLED=false`.
4. O UUID deve corresponder à implantação do nó local, e não ao ID do projeto Supabase. Não inventar/copiar UUID de dados demo.
5. As ferramentas Supabase conectadas fornecem as chaves publicáveis, mas não a chave secreta. O proprietário deve configurá-la diretamente como variável protegida do projeto Vercel; não enviar a chave pelo chat ou colocá-la no código. Sem ela não habilitar o endpoint nem prometer publicação funcional.
6. No nó local configurar `SDV_PRE_CADASTRO_NUVEM_URL`, `SDV_PRE_CADASTRO_NUVEM_TOKEN` (segredo exclusivo do nó) e `SDV_PRE_CADASTRO_NUVEM_IMPLANTACAO_ID`; habilitar só no ambiente de teste. Ativar o scheduler Laravel com `schedule:run`.
7. Homologar envio, arquivos privados, importação na fila, decisão, reenvio e queda/retorno usando dados sintéticos. Só então liberar o convite aos usuários reais.

A exclusão do projeto Vercel antigo exige ação explícita no painel, pelo link devolvido pelo provedor. Ela não é condição para testar a nova integração, e não deve atingir `certificados` nem o repositório principal.

## 8. Validação executada

- `php artisan test`: **571 testes passaram**, incluindo oito novos de importação/retentativa, isolamento, integridade, verso obrigatório, status, visitante com responsável e configuração desativada. Executado após build do Vite.
- `pnpm build`: aprovado; `pnpm test:js`: **22 testes passaram**.
- `vendor/bin/pint --test`: aprovado.
- `npm test` no portal: **10 testes passaram**, com imagens sintéticas, transporte simulado e PostgreSQL PGlite para esquema, RLS, concessões, quota e ACK.
- SQL no Supabase novo: RLS ligado, privilégios públicos negados e bucket privado. A verificação inicial ocorreu sem solicitações; a homologação posterior acrescentou somente a solicitação sintética descrita no §9. Auditor de segurança sem avisos após políticas explícitas.
- Revisão inicial por navegador local foi bloqueada pelo ambiente. Posteriormente, o portal publicado foi aberto no navegador compartilhado: carregamento real do formulário, identificação de HOMOLOGAÇÃO, alternância CNH/RG com verso e campos de veículo conferidos. Tela inicial capturada. Celular/iPad e dispositivos da portaria ainda não homologados.
- Não executados: importação no servidor físico do condomínio, OCR remoto, aprovação real ou envio à facial. Upload real e importação em Laravel isolado foram executados posteriormente (§9).

**Próximo marco:** revisão da PR, configuração da implantação real e instalação do conector no servidor da portaria. Homologar dispositivos e catálogo antes de liberar a produção; esta entrega não deve ser marcada como produção pronta.


## 9. Publicação e prova integrada após configuração da chave pelo proprietário

- Código publicado: commit `22b6c6c98ed1ff5e1a7e53fec00939ea8a334c63`, PR #87, somente o diretório do portal; a main não foi substituída.
- Endereço de homologação: `https://sdv-santa-rita-pre-cadastro-8jxaoqzdh-remotevelaskoo-1198.vercel.app`. O acesso requer link temporário do Vercel e convite; não registrar esses tokens no Git. Link de compartilhamento inicial com validade de 23 horas, encerrando em 12/11/2026 conforme horário informado pelo provedor.
- O primeiro deployment foi publicado com o alvo padrão de produção, **desativado pelas variáveis de produção**. A versão habilitada para testes foi criada depois, explicitamente com `target=staging` (equivalente ao ambiente Preview na API Vercel). Não há aceite de cadastros em produção.
- A chave do Supabase permaneceu como variável sensível; seu valor não foi lido nem copiado para o código. Três segredos independentes foram configurados somente para Preview. A implantação usada é uma identidade de teste e **não deve ser copiada para o servidor real**.
- Catálogo de destinos de teste vazio: testar primeiro **Turista**; visitante/prestador continuam condicionados ao catálogo real, sem criar imóveis ou responsáveis fictícios para liberar o envio.
- Portal publicado: GET de configuração com convite aceito; sem convite, 401; fila de sincronização sem credencial do nó, 401.
- Upload real ao Supabase de PNGs totalmente sintéticos (sem pessoas), documento e selfie separados; normalização JPEG concluída; recibo público sem nome, CPF ou links de imagens.
- Repetição do mesmo submit devolveu o mesmo protocolo e não criou outra solicitação.
- Laravel em SQLite isolado recebeu efetivamente a solicitação da nuvem, verificou hashes e gravou os dois arquivos no disco privado. Uma segunda sincronização não duplicou cadastro ou arquivos.
- A mudança de teste para `correcao`, versão 2, foi confirmada na nuvem; SQL no Supabase verificou `state=importado`, `review_status=correcao`, `local_version=2`, quantidade 1. Não se trata de decisão sobre pessoa real.
- Foram confirmados zero pessoas operacionais e zero autorizações no banco local de teste. Nenhuma facial ou relé foi acionado.
- Falha 503 simulada no cliente preservou os registros/arquivos locais. Depois, a conexão real foi restabelecida e a sincronização concluiu sem duplicação.
- O cliente PHP deste ambiente necessitou das configurações do proxy e de timeout de conexão maior no harness de teste. Isso não substitui verificar TLS, latência e conectividade no servidor físico. O segredo de proteção do Preview usado no harness também não pertence à configuração de produção do nó.

A prova demonstra o transporte e a idempotência do pré-cadastro. Não representa homologação do funcionamento offline integral da portaria, de relógios, revogações, backup/restauração ou hardware.


## 10. Captura direta pela câmera — complemento do PO em 11/11/2026

O seletor `input type=file` com `capture` é apenas uma indicação ao navegador e não abria prévia de webcam no computador. Foram adicionados botões **Abrir câmera** separados para frente, verso e fotografia da pessoa, preservando a escolha de arquivos como alternativa.

- O clique explícito solicita `getUserMedia` somente com vídeo (`audio=false`), preferindo câmera frontal para a pessoa e traseira para documentos.
- Modal com vídeo, instrução de permissão, **Tirar foto** e **Cancelar**. Só captura depois de haver vídeo com dimensões válidas; usa JPEG de até 1600 px e mostra prévia no campo correto.
- Foto capturada participa do mesmo upload privado e das validações existentes, sem exigir seleção adicional de arquivo. Cancelar ou negar permissão preserva a imagem anterior. Refazer substitui apenas após nova captura bem-sucedida.
- A câmera é encerrada ao capturar, cancelar, fechar por Escape, sair da página, ocultar a aba ou iniciar o envio. Permissão respondida após cancelamento não deixa stream aberto.
- Falhas de permissão, dispositivo ausente ou câmera ocupada têm orientação simples. Sem suporte à prévia, permanece o seletor nativo de câmera/arquivo.
- `Permissions-Policy` permite câmera da própria origem e bloqueia microfone; CSP permite mídia local/blob. Nenhuma mudança em autorização, facial, banco, chave Supabase ou modo de produção.
- Testes do portal: **15 aprovados** (os dez anteriores e cinco para ciclo da câmera, cancelamento/permissão tardia, troca de stream, recusa e JPEG limitado). Webcam/celular físicos continuam dependendo da permissão no dispositivo do usuário.


## 11. Revisão para homologação — 11/11/2026

**Base revisada:** `codex/hibrido-vercel-supabase` em `5acc152` (PR #87), já sobre a main `fc0497a` (inclui PRs #82–#86). Revisão na branch `claude/project-thread-nwk4hb`, sem merge nem push na main.

### 11.1 Integração com a main

A branch do PR #87 já parte da main atual: **nenhum conflito** com foto no pré-cadastro, identificação com câmera/edição, CPF por voz, relatório final por turno e contribuição fixa do turista (#82–#86). O PR #87 só acrescenta arquivos, a migração `pre_cadastro_nuvem_recebimentos`, o agendamento desligado por padrão e as variáveis vazias do `.env.example`. Nenhum segredo, token ou chave foi encontrado no diff.

### 11.2 Testes executados nesta revisão

- `php artisan test` (PHP 8.4): **571 aprovados**, 3716 asserções, incluindo `tests/Feature/Hibrido/PreCadastroNuvemTest.php`.
- `vendor/bin/pint --test`: aprovado (388 arquivos).
- `pnpm build` aprovado; `pnpm test:js`: **22 aprovados**.
- `npm test` no portal: **15 aprovados** (validação, transporte, câmera e PostgreSQL embarcado com RLS/ACK).
- CI do GitHub no `5acc152`: testes PHP/JS/compilação e migrações aprovados.

### 11.3 Fluxo completo de ponta a ponta (ambiente isolado, dados sintéticos)

Harness local: handlers reais `api/portal.js` e `api/sync.js` servidos em HTTPS local, com o Supabase substituído por armazenamento em memória; Chromium com câmera simulada; Laravel em PHP 8.4 com SQLite separado e o comando real `pre-cadastro:sincronizar-nuvem`. Não usa nem altera o Supabase `sdv-santa-rita`, o projeto `certificados` ou o Vercel.

| Verificação | Resultado |
|---|---|
| Abrir câmera → Tirar foto (frente do documento) | Prévia exibida; câmera encerrada após a foto (0 trilhas ativas) |
| Abrir câmera → Cancelar | Foto anterior mantida; câmera encerrada |
| Refazer | Nova captura substitui a prévia |
| Escape no modal | Câmera encerrada |
| Microfone | Todas as chamadas `getUserMedia` com `audio:false`; permissão de microfone negada pela política da página |
| Selecionar imagem já salva (selfie) | Prévia exibida e enviada normalmente |
| Envio | Protocolo `PRE-SRA-…`, recibo “Aguardando análise da portaria”, sem erros de JavaScript |
| 1ª sincronização | 1 recebido, 1 confirmado; pré-cadastro `aguardando`, turista, destino “Praia do Santa Rita”, sem responsável; 2 arquivos privados (documento e selfie) |
| 2ª sincronização | 0 recebidos; nenhum cadastro ou arquivo duplicado |
| Decisão local `correcao` (versão 2) com a nuvem fora (503) | Comando falha com mensagem genérica; cadastro e arquivos locais preservados; ACK fica pendente |
| Nuvem de volta | ACK pendente confirmado; nuvem `importado/correcao/v2`; recibo público mostra a nova situação |
| Efeitos colaterais | 0 pessoas, 0 autorizações, 0 credenciais, 0 sincronizações faciais, 0 contribuições, 0 movimentações de caixa |
| Sincronização sem credencial do nó | 401 |

Capturas e o harness ficaram nos arquivos do projeto (`pre-cadastro-hibrido/`), fora do Git.

### 11.4 Divergências para decisão do PO (não alteradas)

1. **Prestador com responsável no formulário Laravel.** Decidido pelo PO: alinhar (feito no §12). A revisão do PO de 08/11 (doc 021) diz que prestador não tem responsável, e o portal segue isso. O formulário público Laravel já existente (`PublicPreRegistration`) ainda grava o responsável do imóvel para prestador. Decidir se o formulário local deve ser alinhado.
2. **Origem do catálogo de imóveis e responsáveis do portal.** Era digitada no Vercel (`PORTAL_DESTINATIONS`). Decidido pelo PO: vir do sistema local (feito no §12).
3. **Período do pré-cadastro.** Decidido pelo PO: perguntar a data prevista (feito no §12). Segue o formulário local: 24 h a partir do envio. Um visitante que se cadastra dias antes chega com o período vencido. Aprovação não autoriza entrada, mas o PO deve confirmar se o portal precisa perguntar a data da visita.

### 11.5 Riscos técnicos encontrados

- **Fila presa por solicitações inválidas.** Confirmado pelo PO e corrigido no §12. A nuvem sempre devolve as 10 mais antigas em `enviado`. Se uma delas for recusada pela validação local (por exemplo, relógio da portaria atrasado ou mudança futura de regra), ela é reapresentada a cada ciclo; dez casos assim impedem novas importações. Hoje as validações dos dois lados coincidem, então o risco é baixo; recomenda-se, antes da produção, um estado de “recusado na importação” na nuvem (exige migração no Supabase do portal, não aplicada aqui).
- **Relógio do computador da portaria.** `submitted_at` não pode estar no futuro em relação ao relógio local; manter NTP ativo.
- **Lock de execução.** Usa `Cache::lock`; o `CACHE_STORE` do servidor deve suportar locks (`database`, `file` ou `redis`).
- Mensagem “Foto capturada…” permanece na tela ao escolher depois uma imagem salva em outro campo (cosmético).

### 11.6 Pendente — não concluído

Configuração e homologação no computador da portaria, catálogo real, implantação real, aviso de privacidade definitivo, retenção, celulares/iPad reais e produção (`PORTAL_ENABLED=false` mantido). Nada disso foi feito nesta revisão.


## 12. Ajustes da revisão do PO no PR #88 — 11/11/2026

O PO revisou o PR #88 e pediu quatro correções antes do merge. Todas foram feitas; o merge na main continua aguardando aprovação explícita.

### 12.1 Fila sem bloqueio: retentativa com espera e conferência

- Quando a importação local recusa uma solicitação, o nó avisa a nuvem com um **código sem dados pessoais** (`dados_invalidos`, `arquivos_incompletos`, `arquivo_integridade`, `imagem_invalida`, `erro_interno`). A nuvem conta a tentativa e agenda a próxima com espera crescente (1, 2, 4, 8 min… até 60 min). Enquanto espera, a solicitação **não ocupa a fila**: as seguintes continuam sendo importadas.
- Na 5ª falha a solicitação sai da fila e fica em `conferencia`. O recibo público passa a mostrar "Em conferência pela administração", sem detalhes.
- No sistema local, a tabela `pre_cadastro_nuvem_falhas` guarda só protocolo, código, nomes dos campos recusados, tentativas e situação. A tela **Pré-cadastros** mostra o aviso "N envios do portal precisam de conferência" com protocolo, motivo e campos (ex.: "Dados fora das regras atuais (CPF)"). Quem tem `pre-registro.editar` pode clicar em **Tentar novamente**: a próxima sincronização reabre a solicitação na nuvem. Nada é aprovado ou liberado por isso; a ação é auditada.
- Diferença de relógio de até 5 minutos entre nuvem e portaria passou a ser tolerada.

### 12.2 Data prevista da visita

- Portal e formulário local pedem a **data prevista da visita**, de hoje até 30 dias à frente (fuso do condomínio). O formulário vem com a data de hoje preenchida.
- O período do pré-cadastro passa a ser o dia da visita inteiro (00h00 até 00h00 do dia seguinte, 24 h). Envios antigos sem data mantêm as 24 h desde o envio.
- **Suposição a confirmar pelo PO:** limite de 30 dias de antecedência.
- Aprovação continua não sendo autorização; a regra de 24 h do turista e a visita do visitante ao enviar à facial não mudaram.

### 12.3 Prestador sem responsável no formulário antigo

O formulário público Laravel e a correção na tela de análise não gravam mais responsável para prestador (revisão do PO de 08/11/2026). Ao corrigir um prestador antigo que tinha responsável, o valor é removido e o histórico de edição registra a mudança.

### 12.4 Uma só lista de imóveis: o cadastro local

- Novo serviço `CatalogoDestinos`: imóveis **ativos** da implantação, com o **responsável principal** vigente (vínculo ativo). Visitante só vê imóveis com responsável; prestador vê todos; turista não usa imóvel.
- O formulário local, a correção na análise e o portal usam essa mesma lista. A lista fixa de demonstração (`DestinationDirectory`) e a variável `PORTAL_DESTINATIONS` foram removidas.
- A cada sincronização o nó publica a lista na nuvem (só rótulo e nome do responsável, sem identificadores internos) quando ela muda, e ao menos a cada hora. O portal devolve ao navegador só os rótulos; os nomes dos responsáveis não saem do servidor.

### 12.5 Ordem obrigatória de implantação (nuvem)

1. Aplicar `services/pre-cadastro-publico/supabase/migrations/20261111200000_fila_conferencia_catalogo_local.sql` **somente** no projeto `sdv-santa-rita`. Não aplicar em `certificados`.
2. Publicar o novo código do portal no Vercel (Preview).
3. Remover a variável `PORTAL_DESTINATIONS` do Vercel (não é mais lida).
4. Só então atualizar o sistema local e rodar `php artisan migrate`. Um nó novo com o portal antigo falha ao publicar o catálogo e não importa nada (os dados ficam preservados na nuvem).

Nada disso foi aplicado no Supabase ou no Vercel nesta revisão.

### 12.6 Testes

- `php artisan test`: **581 aprovados** (10 novos: catálogo local, falha → conferência → reabertura, permissão para reabrir, data da visita, tolerância de relógio, prestador sem responsável, janela de 30 dias).
- `npm test` do portal: **20 aprovados** (5 novos, incluindo a migração no PostgreSQL embarcado: espera, conferência na 5ª falha, reabertura, RLS do catálogo).
- `pnpm test:js` 22 aprovados; `pnpm build` e `vendor/bin/pint --test` aprovados.
- Ponta a ponta no harness isolado: catálogo publicado pelo nó (visitante vê só o imóvel com responsável, prestador vê os dois); visitante com câmera e data D+3 recebido; segundo envio importado **mesmo com o primeiro sendo recusado**; primeiro foi para conferência na 5ª tentativa, apareceu na fila local com "Dados fora das regras atuais (CPF)" e "Tentar novamente"; reabertura devolveu à fila da nuvem. Nenhuma autorização, credencial ou contribuição criada.

### 12.7 Continua pendente

Configuração e homologação no computador da portaria, aplicação da migração no Supabase e publicação no Vercel, aviso de privacidade e retenção definitivos, dispositivos reais e produção (`PORTAL_ENABLED=false`).

## 13. Pré-cadastro público divulgado a todos — decisão do PO em 11/11/2026

O PO decidiu deixar o formulário aberto a todos e orientar visitantes, turistas e prestadores a se cadastrarem antes de chegar, para reduzir filas na portaria.

- **Convite = link público.** O código do convite continua no fragmento do link (`https://portal/#convite=CÓDIGO`), mas passa a ser divulgado amplamente (cartaz com QR code, WhatsApp, site). Ele não identifica ninguém nem autoriza nada; só impede envios de quem não tem o link oficial.
- **Risco aceito:** qualquer pessoa com o link pode enviar, inclusive cadastros falsos.
- **Proteções que permanecem:** todo envio fica `aguardando` a análise humana; nada cria autorização, credencial ou envio à facial; limite de 120 tentativas por hora por endereço de rede; imagens e dados privados no Supabase. Se houver abuso, trocar `PORTAL_INVITE_SECRET` no portal e `SDV_PRE_CADASTRO_LINK_PUBLICO` no nó invalida o link e o QR antigos.
- **Sistema local:** nova variável `SDV_PRE_CADASTRO_LINK_PUBLICO` (fora do Git). O botão **Abrir formulário público** da tela Pré-cadastros abre o portal; sem link válido configurado, o botão não aparece. O formulário Laravel de demonstração (`/pre-cadastro/convite-demonstracao`) continua existindo, mas deixou de ser o link da fila.
- **Cartaz:** a tela **Link e QR code para divulgar** (`/pre-cadastros/divulgacao`, permissão `pre-registro.analisar`) mostra o QR code gerado no próprio servidor (biblioteca `bacon/bacon-qr-code`, sem serviço externo), as instruções e o botão **Imprimir cartaz**. Na impressão, o link em texto e a orientação técnica são ocultados.
- Testes: `PreCadastroDivulgacaoTest` (5): botão abre o portal configurado; sem link não há botão quebrado; QR em SVG; link sem HTTPS ou sem convite válido não gera QR; página exige login e permissão. Suíte completa: 586 aprovados.

## 14. Botão "Buscar envios da nuvem" — pedido do PO em 12/11/2026

Na homologação local, o agendador não importou sozinho e o comando manual trouxe o envio. Para não depender do terminal, a tela **Pré-cadastros** ganhou o botão **Buscar envios da nuvem**.

- Executa a mesma sincronização do agendador (`PreCadastroNuvem::sincronizar`): publica o catálogo, confirma pendências e importa até dez envios. Não aprova, não libera entrada e não envia à facial.
- Aparece só com a sincronização ligada (`SDV_PRE_CADASTRO_NUVEM_ENABLED=true`) e para quem tem `pre-registro.editar`; a ação reconfere a permissão no servidor e é auditada (`buscou_pre_cadastros_nuvem`, só contagens).
- Cliques repetidos: o botão fica desabilitado enquanto busca, há intervalo mínimo de 15 s por implantação e o mesmo bloqueio do agendador impede duas sincronizações ao mesmo tempo ("Outra sincronização está em andamento").
- Resultado na tela: novos recebidos, pendências com nova tentativa automática e envios mandados para conferência; falha de rede mostra mensagem genérica e preserva os registros locais.
- Testes: dois novos em `PreCadastroNuvemTest` (resultado e clique repetido; permissão, configuração desligada e nuvem fora do ar). Suíte completa: 588 aprovados.

**Se a busca automática não rodar:** confirmar que `php artisan schedule:work` está ativo depois da atualização do código (reiniciar o processo), rodar `php artisan config:clear` se houver cache de configuração e conferir `php artisan schedule:list`. O agendamento usa `withoutOverlapping(60)`: se um processo for interrompido no meio de uma sincronização, o próximo ciclo automático pode esperar até 60 minutos; o botão e o comando manual continuam funcionando.
