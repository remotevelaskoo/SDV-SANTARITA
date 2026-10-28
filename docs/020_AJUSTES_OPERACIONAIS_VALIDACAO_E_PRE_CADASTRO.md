# AJ-006 — Ajustes operacionais da validação, do pré-cadastro e do envio aos terminais

**Projeto:** SDV Access — Santa Rita
**Branch:** `claude/project-thread-1rctvb` (sobre `claude/retomada-hikvision-8p360v`)
**Situação:** implementado em 7 fatias; conciliação documental de 28/10/2026 registrada na seção 12; itens dependentes de equipamento real permanecem na seção 9.

Este documento registra o que o AJ-006 entregou, as regras que cada fatia segue e o que ainda depende de bancada. Ele não reescreve decisões aprovadas: ADR-013 (biometria adiada), ADR-016 (integração direta com o terminal) e ADR-017 (liberação manual) continuam valendo como estão.

## 1. Fatia 1 — Busca e identificação na Validação de Entrada

- A tela não escolhe mais a primeira pessoa com vínculo ativo. O operador busca e escolhe; nem resultado único é selecionado sozinho.
- Busca no servidor, com debounce e limite, por nome parcial sem acento, CPF ou documento completo, telefone, protocolo e código do imóvel, sempre na implantação ativa. Colunas normalizadas e índices novos sustentam a busca.
- Cada resultado mostra foto, documento mascarado, tipo, destino, vigência e situação.
- A foto real fica em armazenamento privado (`pessoa_arquivos`), vinda do cadastro da pessoa ou da selfie do pré-cadastro aprovado. Na falta dela aparece um avatar neutro com "Sem foto cadastrada".
- A seleção é auditada e a pessoa escolhida é protegida contra troca pelo navegador.

## 2. Fatia 2 — Número do ticket de acesso e relatório

- Campo "Número do ticket de acesso", guardado como texto para preservar zeros à esquerda. É obrigatório para Contribui/Pago e opcional para Não contribui e Isento.
- A contribuição é gravada com o atendimento na mesma transação.
- Ticket repetido gera alerta e só segue com confirmação. Não há série fiscal.
- A correção posterior exige a permissão `contribuicao.corrigir-ticket` e justificativa, guarda o valor anterior de forma imutável e é auditada.
- O relatório e o CSV de acessos trazem pagamento e ticket. Valor e forma de pagamento aparecem conforme a permissão de caixa.

## 3. Fatia 3 — Análise do pré-cadastro em página própria

- A página `/pre-cadastros/{id}/analise` substitui o painel lateral.
- Selfie, foto do documento e frente e verso aparecem em tamanho de conferência. Só carregam depois de um clique explícito, e esse carregamento é auditado.
- A comparação campo a campo mostra a leitura automática, o valor confirmado pelo solicitante e o valor atual. Cada campo traz a confiança e uma situação: confere, alterado pelo solicitante, corrigido na análise ou confiança baixa.
- Cada decisão (aprovar, rejeitar, pedir correção) confere a sua permissão no backend e só sai de "aguardando".

## 4. Fatia 4 — Diagnóstico do OCR

- O serviço Python passou a:
  - orientar a imagem pelo EXIF;
  - repetir a leitura girada quando a foto está de lado;
  - medir a qualidade da imagem: resolução, brilho, reflexo, foco, contraste e enquadramento;
  - devolver só códigos de diagnóstico, sem texto lido.
- O SDV separa as falhas em captura, qualidade, comunicação, serviço, OCR, interpretação e validação. Para cada caso orienta o operador em português, com nova foto ou preenchimento manual.
- O log técnico registra só categoria e códigos.
- **O OCR não está homologado.** Os limiares de qualidade são provisórios e os testes usam apenas imagens sintéticas.

## 5. Fatia 5 — Câmera IP de placas

- A câmera é um equipamento do tipo `camera_placas`, com vínculo próprio ao ponto. Ela não ocupa o vínculo do terminal facial.
- Adaptadores disponíveis:
  - HTTP/HTTPS genérico, com Digest, sem redirecionamento, pin TLS e só JPEG/PNG;
  - código `gateway-video` para câmeras RTSP/ONVIF atrás de um gateway;
  - simulador de imagem sintética marcado como **SIMULADOR**, sem rede.
- IP, porta, caminho e credencial ficam no backend. A credencial é sempre uma referência `env:NOME`.
- Na validação, o operador captura uma imagem estática, digita a placa (contingência) e compara com os veículos do cadastro. A evidência fica privada e, quando entra numa decisão, passa a ser imutável.
- A leitura automática da placa (LPR) é uma etapa separada e ainda não está configurada.

## 6. Fatia 6 — Ações finais do cadastro e envio para a facial

Tela **Cadastro de pessoa** (`/pessoas/nova`):

| Ação | Comportamento |
|---|---|
| Salvar e enviar para a facial | Valida a escolha e a confirmação do terminal e salva a pessoa. Depois pede o envio pela outbox. Os resultados do salvamento e do envio aparecem separados, e uma falha da facial não desfaz o salvamento. |
| Enviar para a facial | Só com a pessoa já salva. Se houver alteração pendente, a ação é bloqueada com a orientação de salvar ou descartar. Nada é salvo em silêncio. |
| Cancelar cadastro | Encerra só o atendimento atual e descarta o que não foi salvo. Pessoa, vínculo, autorização e histórico já salvos ficam. |
| Salvar / Salvar alterações / Descartar alterações | Depois do primeiro salvamento, o formulário edita a mesma pessoa. A versão sobe, e o contato antigo é encerrado em vez de apagado. CPF e imóvel não mudam por aqui. |

- **Terminal:** a lista mostra só terminais faciais ativos, com ponto vigente e adaptador permitido. Os do posto do turno aparecem primeiro, e se só houver um ele já vem escolhido. O envio exige marcar "Confirmo o terminal escolhido".
- **Idempotência:** a chave é `envio-facial:{credencial}:{terminal}:{tentativa}`. Repetir com o mesmo conteúdo devolve o pedido existente ("já tinha sido pedido e não foi repetido"). Depois de uma falha, um novo envio gera um novo pedido.
- **Sincronização facial não é abertura de relé.** O envio só cadastra a pessoa no terminal. Nenhuma operação de abertura é criada.
- **Sem foto nem template:** a credencial enviada é do tipo `cadastro_pessoa`. A credencial facial continua bloqueada pelo ADR-013.
- **Permissão:** `facial.enviar`, concedida a Porteiro/Caixa, Gestor e Administrador.
- **Auditoria:** registra `pessoa_cadastrada`, `pessoa_atualizada` (só os nomes dos campos), `facial_envio_solicitado` e `facial_envio_recusado` (com o código do motivo), sempre sem nome, documento ou imagem.

## 7. Fatia 7 — Turista com credencial de no máximo 24 horas

- A pessoa continua no SDV. **Cada visita cria uma autorização própria** (`autorizacoes_acesso`, tipo `turista_24h`) com uma credencial própria (`credenciais_acesso`).
- **Validade:** o início é o momento aprovado, ou a data pedida se for futura. O fim é no máximo 24 horas depois, ou antes disso se o fim pedido for anterior. Configurar mais de 24 horas é ignorado.
- **Turista que volta:** se o CPF já existir, a tela oferece "Usar o cadastro existente e registrar nova visita". Os dados da pessoa não mudam, e a nova autorização gera um novo envio. Uma visita vigente impede abrir outra ao mesmo tempo.
- **Validade no terminal:** início e fim vão no pedido ao terminal. A operação na outbox expira no fim da vigência, para que uma retentativa atrasada não recoloque a credencial. Se o terminal não declarar a capacidade `validade_nativa`, o operador é avisado de que a remoção depende do SDV.
- **Vencimento:** o comando `acessos:processar-vencimentos`, agendado a cada minuto:
  1. expira as autorizações vencidas;
  2. marca as credenciais como expiradas;
  3. pede a revogação em cada terminal que pode ter a credencial.

  O comando é idempotente: rodar de novo não duplica pedidos.
- **Falha de comunicação:** a revogação usa a retentativa da outbox. Se esgotar as tentativas sem comunicação, o pedido é repetido depois de `SDV_REVOGACAO_REPETIR_MINUTOS` até o terminal confirmar. Uma recusa definitiva ou uma capacidade ausente não é repetida às cegas: fica visível como falha.
- **Encerramento antecipado:** existe no serviço (`AutorizacoesAcesso::encerrar`), com motivo obrigatório. Ainda não tem tela.
- **Fuso e relógio:** antes de enviar uma visita, o SDV confere duas coisas:
  - se o fuso do servidor é o fuso da portaria (`SDV_FUSO_OPERACIONAL` igual a `APP_TIMEZONE`);
  - se o relógio do terminal está certo, pela divergência registrada nos eventos recebidos nas últimas 24 horas.

  Divergência conhecida bloqueia o envio. Sem eventos recentes, o envio segue com um aviso de relógio não conferido.
- **Nada é apagado:** autorizações e credenciais recusam exclusão no próprio model. Encerrar, expirar ou revogar só muda a situação.

## 8. Migrações novas

Todas são novas e compatíveis com PostgreSQL. Nenhuma migração publicada foi alterada.

| Migração | Conteúdo |
|---|---|
| `2026_10_25_100000_add_busca_normalizada_to_pessoas` | Colunas e índices de busca normalizada |
| `2026_10_25_100100_create_pessoa_arquivos_table` | Foto da pessoa em armazenamento privado |
| `2026_10_25_110000_create_contribuicoes_table` | Contribuição e ticket por atendimento, com histórico de correção |
| `2026_10_25_120000_create_cameras_placa_e_capturas` | Câmera de placas, vínculo ao ponto, capturas e campos de placa no histórico |
| `2026_10_25_130000_create_autorizacoes_e_credenciais_acesso` | Autorização de acesso e credencial distribuída aos terminais |

## 9. O que depende de equipamento ou de decisão

| Item | Situação |
|---|---|
| Cadastro de pessoa e validade no terminal Hikvision | **Validado em bancada em 25/10/2026** (firmware V3.18.0, usuário fictício, sem foto). O roteiro `scripts/bancada/hikvision_usuario_validade.php` gravou, alterou (`UserInfo/SetUp`), consultou (`UserInfo/Search`) e removeu (`UserInfo/Delete`) o usuário, e o terminal devolveu o período igual ao enviado. O adaptador agora grava a pessoa com `Valid` (início e fim, hora local), consulta e remove. O `employeeNo` é derivado do UUID da credencial (32 caracteres) e fica como referência externa. Antes de gravar, o adaptador lê `/ISAPI/System/time` e recusa se o fuso do terminal for diferente do da portaria ou se o relógio passar da tolerância. **Ainda falta observar** o terminal negando o acesso depois do fim (exige uma credencial de teste), por isso `validade_nativa` segue em homologação. |
| Fuso da facial da bancada | Na bancada o terminal estava em `CST-8` (UTC+8) e cerca de 7 minutos adiantado. Nesse estado o envio é recusado com uma orientação clara. É preciso ajustar o fuso para UTC-3 e sincronizar a hora (NTP ou manual) em Configuração › Hora. |
| Credencial facial (foto/template) | Bloqueada pelo ADR-013. Nada de imagem vai ao terminal. |
| Câmera de placas real | Fundação, adaptador genérico e simulador prontos. Falta escolher o modelo e testar a URL de snapshot em bancada. |
| Leitura automática da placa (LPR) | Não configurada. A placa é digitada pelo operador. |
| OCR | Não homologado. Faltam amostras autorizadas e a calibragem dos limiares. |
| Agendador | `acessos:processar-vencimentos` só roda com `php artisan schedule:run` no cron do servidor. `integracoes:despachar`, que republica pedidos com retentativa, continua sem agendamento até haver worker em produção (docs/015). |
| Tela de encerramento antecipado da visita | Serviço pronto; falta a tela. |

## 10. Variáveis de ambiente

| Variável | Uso |
|---|---|
| `DOCUMENT_OCR_ENABLED`, `DOCUMENT_OCR_URL`, `DOCUMENT_OCR_TOKEN`, `DOCUMENT_OCR_TIMEOUT` | Leitor de documentos (SDV) |
| `DOCUMENT_OCR_WARMUP` | Aquecimento dos modelos na subida do serviço Python (padrão 1) |
| `SDV_FUSO_OPERACIONAL` | Fuso da portaria (padrão `America/Sao_Paulo`) |
| `SDV_TURISTA_VALIDADE_HORAS` | Validade da visita, com teto de 24 |
| `SDV_REVOGACAO_REPETIR_MINUTOS` | Intervalo para repetir a revogação sem comunicação (padrão 15) |
| Senha de câmera ou terminal | Só como referência `env:NOME` cadastrada na tela; a variável `NOME` é definida no ambiente de execução, fora do Git |

## 11. Testes

Os testes ficam em `tests/Feature/Aj006/` e nos testes do serviço `services/document-ocr/tests/`.

- Usam só o simulador de terminal e de câmera, respostas HTTP simuladas e imagens sintéticas.
- **Nenhuma pessoa real é enviada a um terminal em teste automatizado.** Um teste real só acontece por ação explícita na bancada.

## 12. Conciliação com o AJ-005 e autoria anterior

### 12.1 Linha de base preservada

Esta branch foi construída sobre a `main` que contém o trabalho do João Lucas no AJ-005. A fatia 4 deste documento melhora diagnóstico, qualidade e orientação de recaptura; ela não substitui a captura estruturada, o armazenamento privado, a conferência por campo nem o recorte protegido da fotografia documental.

### 12.2 Regra canônica da implantação Santa Rita

Para o pré-cadastro da implantação Santa Rita:

- a frente de RG/CIN ou CNH é obrigatória;
- o verso é solicitado quando aplicável ao documento ou aos dados necessários;
- a selfie permanece separada e obrigatória;
- o OCR pode estar indisponível sem impedir o preenchimento manual, mas o arquivo do documento continua exigido;
- OCR não autentica documento, não cria biometria, não aprova cadastro e não libera acesso;
- a portaria precisa visualizar as evidências protegidas e comparar dados extraídos, confirmados e atuais.

### 12.3 Correção necessária antes da homologação

A configuração `SDV_PRE_CADASTRO_ETAPA_DOCUMENTO`, introduzida posteriormente com padrão desligado, conflita com a jornada aprovada. O Claude deverá conservar as melhorias de selfie desse trabalho, mas corrigir o fluxo para não pular a etapa documental por padrão. A chave não poderá mudar a regra de negócio silenciosamente.

Essa correção deve incluir testes que comprovem:

1. impossibilidade de enviar pré-cadastro sem a frente;
2. verso solicitado quando aplicável;
3. continuidade manual quando o OCR falhar;
4. selfie e documento armazenados como arquivos distintos;
5. conferência protegida na análise da portaria;
6. ausência de autorização, credencial ou liberação causada apenas pelo OCR.

## 13. Simplificação da tela de análise do pré-cadastro

### 13.1 Problema observado em 28/10/2026

A página própria de análise reúne as informações necessárias, porém a composição atual dificulta a decisão rápida da portaria: a selfie ocupa uma área excessiva, alertas aparecem como blocos grandes, instruções técnicas competem com os dados da pessoa e as ações ficam distantes das evidências. A correção é de hierarquia visual e não autoriza retirar controles de segurança, auditoria ou permissões.

### 13.2 Hierarquia operacional aprovada

A tela deverá apresentar, nesta ordem:

1. **Cabeçalho compacto da solicitação:** voltar à fila, foto/avatar pequeno, nome, tipo de acesso, destino, período, situação e protocolo;
2. **Resumo de pendências:** um único painel compacto contendo somente os impedimentos e avisos que exigem ação do operador;
3. **Área principal de conferência:** evidências visuais à esquerda e dados essenciais à direita em telas largas; uma coluna em telas menores;
4. **Divergências do OCR:** mostrar primeiro apenas campos divergentes, alterados ou de baixa confiança, com opção de expandir a comparação completa;
5. **Decisão:** ações permanentemente visíveis no final da área útil ou em barra fixa, sem obrigar o operador a percorrer toda a página;
6. **Informações secundárias:** histórico, detalhes técnicos e comparação completa recolhidos por padrão.

### 13.3 Evidências visuais

- Nenhuma selfie ou documento deverá ocupar sozinho a largura total da página.
- Depois de `Exibir imagens para conferência`, apresentar uma grade de miniaturas com rótulos claros: selfie, documento frente, documento verso e foto recortada do documento.
- As miniaturas devem usar área limitada e proporção previsível, sem cortar o conteúdo; a ampliação continua disponível em visualizador protegido.
- Imagens ausentes devem aparecer como cartão compacto, e não como uma grande área vazia.
- O clique para revelar, o controle de permissão, a URL privada, a ausência de download e o registro de auditoria permanecem obrigatórios.

### 13.4 Dados e decisão

- O resumo inicial deve priorizar: nome, documento, nascimento, telefone, tipo de acesso, destino/imóvel, responsável e período.
- Veículo, endereço, metadados, versão e histórico podem ficar em `Ver mais detalhes`.
- Documento mascarado continua sendo o padrão; a revelação controlada permanece disponível apenas para o perfil autorizado e deve ser auditada.
- Exibir inicialmente somente as ações principais: `Editar dados`, `Solicitar correção`, `Rejeitar` e `Aprovar pré-cadastro`.
- Campos de motivo ou seleção aparecem somente após o operador escolher a respectiva ação. Não manter todos os formulários de decisão abertos simultaneamente.
- `Aprovar pré-cadastro` permanece a ação primária. Aprovar não libera entrada e não substitui a Validação de Entrada.

### 13.5 Critérios de aceite

1. Em resolução desktop comum, o operador identifica a pessoa, vê as pendências, abre as evidências e alcança as ações sem enfrentar uma imagem em tamanho desproporcional.
2. Selfie e documentos aparecem juntos como miniaturas após a revelação autorizada.
3. A ampliação individual continua protegida e auditada.
4. Alertas simultâneos são agrupados em um resumo, mantendo título e orientação de cada ocorrência.
5. Divergências e baixa confiança do OCR têm prioridade sobre campos que conferem.
6. Histórico e dados secundários permanecem acessíveis, porém recolhidos por padrão.
7. Ações condicionais exibem seus campos somente quando acionadas.
8. Layout responsivo, navegação por teclado, foco visível e títulos acessíveis são preservados.
9. Nenhuma mudança reduz permissões, mascaramento, auditoria ou armazenamento privado.
10. Testes existentes de segurança e decisão continuam passando; novos testes cobrem a presença da hierarquia e dos controles essenciais.
