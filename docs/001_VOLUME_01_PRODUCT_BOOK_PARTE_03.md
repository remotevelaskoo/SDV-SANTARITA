# SDV ACCESS — PRODUCT BOOK
## Volume 01 — MVP, Roadmap, Backlog e Homologação

**Documento:** SDV-PBK-003  
**Versão:** 1.0.1  
**Status:** Aprovado  
**Produto:** SDV Access — Implantação Santa Rita  
**Empresa proprietária:** Soluções do Vale  
**Responsável pelo produto:** Vinicius Velasco de Azevedo  
**Data:** Julho de 2026

---

## Controle de versões

| Versão | Data | Responsável | Alteração |
|---|---|---|---|
| 1.0.0 | Julho/2026 | Soluções do Vale | Definição do MVP, prioridades, roadmap, backlog, riscos, homologação e encerramento do Volume 01 |
| 1.0.1 | 28/07/2026 | Product Owner | Aprovação formal da Parte 03 e encerramento do Volume 01 |

---

# 1. Objetivo e definição formal do MVP

Esta parte encerra o Volume 01 do Product Book e transforma a visão, as regras, os requisitos e as jornadas das Partes 01 e 02 em um recorte executável para a primeira implantação do SDV Access.

Para este volume, o **Produto Mínimo Viável — MVP Santa Rita** é definido como a menor versão da plataforma capaz de operar, de ponta a ponta e com segurança, o controle de acesso de pessoas e veículos da implantação Santa Rita, desde o cadastro ou pré-cadastro até a decisão de acesso, o acionamento integrado ou contingencial, o registro do evento e sua posterior auditoria.

O MVP somente será considerado entregue quando:

1. o imóvel estiver implementado como entidade central dos vínculos;
2. cadastro, vínculo, autorização, credencial e evento de acesso forem entidades e ciclos distintos;
3. as jornadas prioritárias funcionarem de ponta a ponta;
4. perfis e permissões aplicarem menor privilégio;
5. operações relevantes produzirem auditoria;
6. históricos forem preservados por encerramento, inativação ou expiração, sem exclusão destrutiva;
7. documentos e imagens forem armazenados com acesso protegido;
8. os fluxos operacionais aderirem às referências visuais aprovadas;
9. integrações com equipamentos estiverem desacopladas do núcleo;
10. backup, restauração, observabilidade e contingência mínima estiverem homologados;
11. os critérios da seção 13 forem aprovados;
12. as pendências classificadas como bloqueadoras estiverem resolvidas ou formalmente aceitas com plano de tratamento.

## 1.1 Resultado operacional mínimo

Ao final do MVP, um operador autorizado deverá conseguir:

```text
Localizar ou receber um cadastro
        ↓
Confirmar pessoa, vínculo e vigência
        ↓
Validar documento, face, veículo e autorização aplicáveis
        ↓
Registrar contribuição, quando exigida
        ↓
Autorizar, negar ou manter pendente
        ↓
Acionar o ponto de acesso ou aplicar contingência autorizada
        ↓
Consultar o evento, o resultado técnico e a trilha de auditoria
```

## 1.2 Limites do MVP

O MVP não compreende os itens declarados fora do escopo na Parte 01, seção 9. Também não inclui portal completo do morador, aplicativo móvel nativo, ERP financeiro, PIX integrado, inteligência comportamental ou gestão completa de ocorrências.

OCR poderá apoiar a digitação e a conferência, mas não será requisito para ativação do cadastro. Reconhecimento facial, leitura de placas e controladora pertencem ao recorte da implantação inicial; sua ativação em produção depende das definições de fabricante, protocolo, infraestrutura, base legal e critérios de contingência registrados na seção 15.

---

# 2. Funcionalidades por prioridade

As prioridades abaixo não substituem os requisitos `RF-001` a `RF-040`, as regras `RN-001` a `RN-054`, os requisitos não funcionais `RNF-001` a `RNF-020` nem os casos de uso `UC-001` a `UC-012`. Elas determinam a ordem e o compromisso de entrega.

| Prioridade | Significado | Compromisso |
|---|---|---|
| P0 — Essencial | Sem o item não existe operação segura ou homologável | Obrigatório no MVP |
| P1 — Necessário | Completa a operação inicial e reduz contingências manuais | Obrigatório para entrada em produção, salvo aceite formal |
| P2 — Evolução | Gera eficiência adicional sem impedir a operação mínima | Pós-MVP planejado |
| P3 — Futuro | Amplia o produto para novos cenários ou mercados | Fora do horizonte inicial |

## 2.1 P0 — Essenciais ao MVP

- autenticação, recuperação de acesso, sessões seguras e inativação de usuários;
- usuários, perfis e permissões granulares;
- estrutura condomínio, bloco opcional e imóvel;
- cadastro único de pessoa e gestão de vínculos;
- moradores, proprietários, inquilinos, outros ocupantes, visitantes, turistas, empresas e prestadores;
- vigências, expiração automática, suspensão e encerramento com histórico;
- veículos e vínculos com pessoa, imóvel, empresa ou autorização;
- autorizações e credenciais com estado e vigência próprios;
- pré-cadastro responsivo, protocolo e análise pela portaria;
- validação de entrada com autorização, negativa, pendência e justificativas;
- registro de entradas, saídas, falhas e liberações manuais;
- caixa operacional e contribuição, quando a cobrança estiver habilitada;
- auditoria de cadastros, vínculos, permissões, decisões, exportações e operações críticas;
- consultas e relatórios operacionais mínimos;
- proteção de fotos e documentos;
- observabilidade, backup e restauração;
- integração desacoplada e rastreável com os equipamentos definidos para Santa Rita;
- aderência funcional e visual às referências oficiais.

## 2.2 P1 — Necessárias à entrada em produção

- dashboard operacional com dados reais;
- notificações de pré-cadastros e falhas;
- exportações autorizadas em PDF e planilha;
- filas de sincronização, retentativas idempotentes e consulta do estado de integração;
- contingência operacional para indisponibilidade de equipamento ou comunicação;
- relatórios de caixa, acessos, cadastros, equipamentos e auditoria;
- parametrização de pontos de acesso, horários, áreas e motivos;
- rotinas automáticas de expiração e revogação;
- orientação operacional e treinamento dos perfis da implantação.

## 2.3 P2 — Evoluções planejadas

- OCR assistido para documentos;
- validações avançadas de qualidade de selfie;
- canais automáticos de notificação;
- portal web limitado do morador;
- convite por QR Code com autosserviço ampliado;
- indicadores gerenciais avançados;
- regras de estacionamento;
- aprimoramento da operação degradada e cache seguro;
- parametrização ampliada para múltiplas implantações.

## 2.4 P3 — Futuro

- aplicativo móvel nativo;
- PIX e conciliação bancária;
- inteligência comportamental;
- gestão completa de ocorrências;
- módulos patrimoniais;
- integrações com novos ecossistemas de controle de acesso;
- recursos comerciais ou financeiros externos ao controle operacional previsto.

---

# 3. Módulos obrigatórios

| MOD | Módulo | Responsabilidade no MVP | Rastreabilidade principal |
|---|---|---|---|
| MOD-001 | Identidade e acesso | Autenticação, sessões, usuários, perfis e permissões | `RN-050` a `RN-054`; `RF-001`, `RF-002`, `RF-017`, `RF-018`, `RF-040` |
| MOD-002 | Estrutura e imóveis | Condomínio, blocos opcionais, imóveis e endereços centrais | `RN-001` a `RN-006`; `RF-005`; `UC-001` |
| MOD-003 | Pessoas e vínculos | Cadastro único, tipos de vínculo, vigências e histórico | `RN-007` a `RN-022`; `RF-006`, `RF-007`, `RF-021` a `RF-025`; `UC-002`, `UC-003`, `UC-011` |
| MOD-004 | Visitantes e pré-cadastro | Convites, coleta responsiva, protocolo, análise e correção | `RN-023` a `RN-028`; `RF-008`, `RF-026` a `RF-030`; `UC-004`, `UC-005` |
| MOD-005 | Empresas e prestadores | Empresas, prestadores, atividade, documentação e vigência | `RN-029` a `RN-033`; `RF-009`, `RF-010` |
| MOD-006 | Veículos | Cadastro, placa normalizada, vínculos, estado e consulta | `RN-034` a `RN-038`; `RF-011`, `RF-033`; `UC-007` |
| MOD-007 | Autorizações e credenciais | Permissões temporais, áreas, horários, face, placa, tag, QR Code ou código | `RN-039`, `RN-044`, `RN-045`; `RF-012`, `RF-032` |
| MOD-008 | Validação e eventos de acesso | Decisão, entrada, saída, negativa, liberação manual e contingência | `RN-040` a `RN-043`; `RF-012` a `RF-016`, `RF-031`, `RF-038`; `UC-006`, `UC-009`, `UC-010` |
| MOD-009 | Caixa operacional | Abertura, contribuição, movimentos, ajustes e fechamento | `RF-034` a `RF-036`; `UC-008`, `UC-012`; Parte 02, seção 11 |
| MOD-010 | Integrações de acesso | Adaptadores, filas, idempotência, sincronização e retorno técnico | `RNF-009`, `RNF-014`, `RNF-015`; `RF-032`, `RF-033`, `RF-037` |
| MOD-011 | Relatórios e dashboard | Indicadores rastreáveis, consultas, filtros e exportações | `RF-003`, `RF-004`, `RF-020`; Parte 02, seção 12 |
| MOD-012 | Auditoria e observabilidade | Trilha imutável, valores anterior/posterior, logs técnicos e alertas | `RN-046` a `RN-049`; `RF-019`, `RF-039`; `RNF-008`, `RNF-017` |
| MOD-013 | Arquivos protegidos | Fotos, selfies e documentos em armazenamento compatível com S3 | `RNF-002`, `RNF-016`; `RF-026`, `RF-028` |
| MOD-014 | Administração e parâmetros | Configurações da implantação, equipamentos, motivos, áreas e pontos de acesso | Parte 02, seções 3, 10 e 16 |

Os módulos representam fronteiras funcionais. Não autorizam uma arquitetura de microsserviços nem uma separação física prematura; essa decisão caberá ao documento de arquitetura e, quando necessário, a ADR.

---

# 4. Módulos futuros

| MOD-FUT | Módulo | Objetivo | Condição para avaliação |
|---|---|---|---|
| MOD-FUT-001 | Portal do morador | Convites, autorizações e consultas restritas ao próprio imóvel | Política de identidade externa e escopo aprovado |
| MOD-FUT-002 | Notificações multicanal | E-mail, mensageria e alertas transacionais | Canais, custos, consentimentos e templates definidos |
| MOD-FUT-003 | OCR e IA ampliados | Extração, qualidade de imagem e apoio à análise | Benefício mensurável, base legal e operação humana de conferência |
| MOD-FUT-004 | Financeiro integrado | PIX, conciliação e cobrança bancária | Nova decisão de produto; permanece fora do MVP |
| MOD-FUT-005 | Ocorrências | Registro e tratamento completo de ocorrências | Regras próprias, perfis e retenção definidos |
| MOD-FUT-006 | Inteligência operacional | Anomalias, tendências e comportamento | Base de dados suficiente e avaliação de privacidade |
| MOD-FUT-007 | Aplicativos móveis | Experiência nativa para públicos selecionados | Validação da necessidade após o portal responsivo |
| MOD-FUT-008 | Gestão multicliente | Operação comercial de múltiplas organizações | Modelo de segregação, governança e administração global aprovado |
| MOD-FUT-009 | Ecossistema de integrações | Novos fabricantes, serviços e automações | Contrato de integração estável e demanda validada |

---

# 5. Roadmap funcional

O roadmap é orientado por marcos e critérios de saída. Datas deverão ser definidas após estimativa técnica, resolução das dependências bloqueadoras e aprovação das especificações UX/UI.

| Marco | Objetivo | Entregas principais | Critério de saída |
|---|---|---|---|
| M0 — Consolidação documental | Preparar a execução sem decisões implícitas | Brand Book, Design System, UX/UI, regras consolidadas, ADRs iniciais e pendências priorizadas | Telas e decisões estruturais aprovadas |
| M1 — Fundação segura | Estabelecer base da plataforma e segregação | MOD-001, estrutura de implantação, arquivos protegidos, auditoria base e observabilidade | Autenticação, autorização e trilha auditável validadas |
| M2 — Núcleo imobiliário | Habilitar cadastro e histórico do domínio central | MOD-002, MOD-003, MOD-005, MOD-006 | Imóveis, pessoas e vínculos operáveis sem duplicação ou perda histórica |
| M3 — Jornada antecipada | Reduzir atendimento manual na portaria | MOD-004 e autorizações temporárias do MOD-007 | Pré-cadastro móvel concluído, protocolado e analisável |
| M4 — Operação de portaria | Executar a decisão de acesso ponta a ponta | MOD-008, caixa do MOD-009 e relatórios operacionais mínimos | Entradas, saídas, negativas e contribuições rastreáveis |
| M5 — Integrações Santa Rita | Conectar equipamentos sem acoplar o núcleo | MOD-010, credenciais, face, LPR e retorno de comando | Cenários integrados, falhas e retentativas homologados |
| M6 — Homologação e implantação | Validar segurança, operação e continuidade | MOD-011 a MOD-014, migração/carga, treinamento, backup e contingência | Seção 13 aprovada e go-live autorizado |
| M7 — Estabilização | Corrigir desvios e medir resultado real | monitoramento assistido, ajustes parametrizáveis e backlog pós-go-live | Operação estável e indicadores basais registrados |
| M8 — Evolução | Entregar P2 conforme valor e risco | OCR, notificações, portal limitado e multicliente evolutivo | Priorização formal do Product Owner |

Cada passagem de marco deverá produzir evidências de teste, decisões atualizadas e rastreabilidade dos requisitos afetados.

---

# 6. Backlog inicial

O backlog abaixo organiza o trabalho funcional inicial. Estimativas, responsáveis e datas serão definidos no planejamento de execução.

| BL | Prioridade | Item | Resultado verificável | Dependências |
|---|---:|---|---|---|
| BL-001 | P0 | Definir estrutura da implantação Santa Rita | Condomínio, blocos e imóveis identificáveis de forma única | PEN-004 |
| BL-002 | P0 | Implementar identidade e menor privilégio | Usuários só visualizam e executam ações autorizadas | Matriz granular |
| BL-003 | P0 | Implementar cadastro único de pessoa | Busca de duplicidade e estados do cadastro operantes | UX/UI aprovada |
| BL-004 | P0 | Implementar vínculos com imóvel | Vínculos independentes, temporais e históricos | BL-001, BL-003 |
| BL-005 | P0 | Implementar moradores, proprietários e ocupantes | Múltiplas pessoas por imóvel e responsável identificável | PEN-009 |
| BL-006 | P0 | Implementar inquilinos e turistas | Vigência obrigatória e revogação automática | Rotinas agendadas |
| BL-007 | P0 | Implementar empresas e prestadores | Empresa, serviço, destino e autorização rastreáveis | Regras documentais |
| BL-008 | P0 | Implementar veículos | Placa normalizada, vínculos e conflitos detectáveis | Política de veículos |
| BL-009 | P0 | Implementar arquivos protegidos | Documento e selfie acessíveis somente por autorização | S3 compatível |
| BL-010 | P0 | Implementar pré-cadastro responsivo | Protocolo gerado e fila de análise alimentada | Privacidade, convite |
| BL-011 | P0 | Implementar análise pela portaria | Aprovação, rejeição e correção auditadas | BL-010 |
| BL-012 | P0 | Implementar autorizações e credenciais | Estados e vigências independentes do cadastro | BL-004 |
| BL-013 | P0 | Implementar validação de entrada | Pessoa, veículo, contribuição e decisão no fluxo aprovado | BL-008, BL-012 |
| BL-014 | P0 | Implementar eventos de acesso | Entrada, saída, negativa, pendência e falha registradas | BL-013 |
| BL-015 | P0 | Implementar caixa operacional | Abertura, movimento, contribuição e fechamento conciliáveis | PEN-003 |
| BL-016 | P0 | Implementar auditoria completa | Autor, origem e valores anterior/posterior consultáveis | Política de retenção |
| BL-017 | P0 | Implementar adaptadores de equipamentos | Núcleo independente do fabricante e comandos idempotentes | PEN-001, PEN-002 |
| BL-018 | P1 | Implementar dashboard real | Indicadores conciliáveis com eventos e caixa | BL-014, BL-015 |
| BL-019 | P1 | Implementar relatórios e exportações | Filtros, permissões e auditoria de exportação | BL-016 |
| BL-020 | P1 | Implementar notificações operacionais | Pendências e falhas visíveis por perfil | Canais internos |
| BL-021 | P0 | Validar backup e restauração | Restauração executada com evidência | Infraestrutura |
| BL-022 | P0 | Definir e ensaiar contingência | Procedimento executável sem registrar falso sucesso | PEN-002 |
| BL-023 | P1 | Preparar carga inicial | Dados validados, reconciliados e importados com relatório | Fonte de dados |
| BL-024 | P1 | Treinar e habilitar usuários | Perfis treinados e acessos individuais entregues | Manuais |
| BL-025 | P0 | Executar homologação do MVP | Evidências aprovadas para todos os critérios aplicáveis | BL-001 a BL-024 |

---

# 7. Épicos e histórias de usuário

## EP-001 — Fundação segura e administração

**Objetivo:** garantir acesso individual, segregado, auditável e administrável.

- **HU-001:** Como administrador, quero criar e inativar usuários individualmente para impedir o uso de credenciais compartilhadas.
- **HU-002:** Como administrador, quero conceder permissões por ação para aplicar menor privilégio.
- **HU-003:** Como auditor, quero consultar alterações relevantes com autor, data, origem e valores anterior/posterior para reconstruir decisões.
- **HU-004:** Como operador, quero visualizar somente os módulos permitidos ao meu perfil para reduzir exposição e erro operacional.

## EP-002 — Imóveis, pessoas e vínculos

**Objetivo:** manter o imóvel como centro do domínio sem duplicar pessoas ou apagar históricos.

- **HU-005:** Como administrador, quero cadastrar um imóvel com identificação única para vinculá-lo corretamente às pessoas e aos veículos.
- **HU-006:** Como usuário autorizado, quero localizar uma pessoa existente antes de cadastrá-la para evitar duplicidade.
- **HU-007:** Como usuário autorizado, quero vincular múltiplos moradores ao mesmo imóvel para representar a ocupação real.
- **HU-008:** Como administrador, quero definir início e término de um inquilino para que seu acesso expire automaticamente.
- **HU-009:** Como gestor, quero consultar vínculos encerrados sem reativá-los para preservar o histórico de ocupação.
- **HU-010:** Como usuário autorizado, quero encerrar ou inativar um vínculo em vez de excluí-lo para manter rastreabilidade.

## EP-003 — Visitantes, turistas e prestadores

**Objetivo:** antecipar a coleta e submeter acessos temporários à aprovação.

- **HU-011:** Como visitante, quero preencher um pré-cadastro no celular para reduzir meu tempo de atendimento.
- **HU-012:** Como solicitante, quero receber um protocolo para acompanhar a situação do envio.
- **HU-013:** Como operador de portaria, quero revisar documento, selfie, destino, responsável e veículo para decidir a solicitação.
- **HU-014:** Como operador, quero solicitar correção sem perder o histórico para tratar dados incompletos.
- **HU-015:** Como administrador, quero vincular prestador à empresa, atividade e vigência para limitar sua autorização.

## EP-004 — Veículos, credenciais e integrações

**Objetivo:** identificar e sincronizar credenciais sem transferir a decisão de negócio ao equipamento.

- **HU-016:** Como operador, quero comparar placa capturada e cadastrada para identificar divergências.
- **HU-017:** Como administrador, quero consultar o estado de sincronização facial para tratar pendências e falhas.
- **HU-018:** Como sistema, quero reenviar comandos de modo idempotente para não duplicar cadastros, eventos ou liberações.
- **HU-019:** Como integrador, quero usar um adaptador por fabricante para manter o núcleo independente do equipamento.
- **HU-020:** Como operador autorizado, quero aplicar contingência com justificativa quando o equipamento estiver indisponível.

## EP-005 — Validação e eventos de acesso

**Objetivo:** apoiar uma decisão rápida, segura e completamente rastreável.

- **HU-021:** Como operador, quero ver pessoa, vínculo, vigência, veículo e alertas em uma única jornada para decidir com rapidez.
- **HU-022:** Como operador, quero negar entrada informando um motivo para registrar a tentativa.
- **HU-023:** Como operador, quero salvar o atendimento sem liberar para concluir uma pendência sem acionar o acesso.
- **HU-024:** Como operador autorizado, quero validar e liberar para registrar o evento e enviar o comando ao equipamento.
- **HU-025:** Como gestor, quero distinguir autorização concedida de comando físico bem-sucedido para não ocultar falhas técnicas.
- **HU-026:** Como auditor, quero consultar entradas, saídas, negativas, liberações manuais e falhas para reconstruir a operação.

## EP-006 — Caixa operacional

**Objetivo:** controlar somente os movimentos financeiros necessários à contribuição de acesso.

- **HU-027:** Como operador, quero abrir meu caixa antes de receber valores para individualizar a responsabilidade.
- **HU-028:** Como operador, quero associar a contribuição ao evento de acesso para manter conciliação.
- **HU-029:** Como operador, quero fechar o caixa com totais esperados e informados para identificar diferenças.
- **HU-030:** Como gestor, quero exigir justificativa para ajustes, cancelamentos e diferenças para preservar a auditoria.

## EP-007 — Informação, continuidade e homologação

**Objetivo:** tornar a operação observável, recuperável e mensurável.

- **HU-031:** Como gestor, quero consultar indicadores derivados de dados reais para acompanhar a operação.
- **HU-032:** Como usuário autorizado, quero filtrar e exportar relatórios para analisar acessos e cadastros.
- **HU-033:** Como responsável técnico, quero receber alertas de falha para agir antes de comprometer a operação.
- **HU-034:** Como responsável pela implantação, quero restaurar um backup testado para garantir recuperação.
- **HU-035:** Como Product Owner, quero evidências de homologação por jornada para decidir o go-live.

As histórias deverão ser refinadas com critérios de aceite específicos, cenários de exceção, dados de teste e vínculos aos requisitos antes do desenvolvimento.

---

# 8. Riscos do produto

| RIS | Risco | Probabilidade | Impacto | Resposta prevista |
|---|---|---:|---:|---|
| RIS-001 | Indefinição de fabricante ou protocolo atrasar integração | Alta | Alto | Fechar PEN-001 antes do M5; usar contrato de adaptador e simulador |
| RIS-002 | Falha de internet ou equipamento interromper a portaria | Média | Crítico | Definir contingência, monitoramento, fila e reconciliação; ensaiar BL-022 |
| RIS-003 | Tratamento inadequado de biometria, documentos ou imagens | Média | Crítico | Definir base legal, finalidade, retenção, acesso e descarte antes de produção |
| RIS-004 | Duplicidade de pessoas ou placas comprometer decisões | Média | Alto | Normalização, busca prévia, alertas, reconciliação e auditoria |
| RIS-005 | Vínculo expirado continuar autorizando acesso | Baixa | Crítico | Rotinas automáticas, revalidação no acesso e testes de fronteira temporal |
| RIS-006 | Exclusão visual ser implementada como destrutiva | Média | Alto | Tratar ações como inativação/encerramento; restringir expurgo a política formal |
| RIS-007 | Divergência entre tela aprovada e implementação | Média | Alto | Aprovar UX/UI detalhada e executar aceite visual por jornada |
| RIS-008 | Sobrecarga do operador aumentar tempo e erro de decisão | Média | Alto | Teste com portaria, alertas claros, dados essenciais visíveis e medição de tempo |
| RIS-009 | Escopo financeiro crescer para ERP | Média | Médio | Limitar caixa à operação de acesso e submeter expansão a decisão formal |
| RIS-010 | Dependência de um fornecedor contaminar o núcleo | Média | Alto | Adaptadores, identificadores internos, contratos versionados e ADR |
| RIS-011 | Permissões excessivas exporem dados sensíveis | Média | Crítico | Menor privilégio, testes negativos, revisão periódica e auditoria de exportação |
| RIS-012 | Migração ou carga inicial conter dados incompletos | Alta | Alto | Perfilamento, validação, relatório de rejeições e reconciliação com responsáveis |
| RIS-013 | Backup existir sem restauração viável | Média | Crítico | Teste periódico de restauração com evidência e metas aprovadas |
| RIS-014 | Expansão multicliente ocorrer sem segregação suficiente | Baixa | Crítico | Segregação desde a fundação e evolução condicionada a ADR e testes |
| RIS-015 | Indicadores divergirem dos eventos de origem | Média | Médio | Definir fórmulas, fontes e reconciliação para cada indicador |

Os riscos deverão possuir responsável, situação e revisão periódica no instrumento de gestão adotado pelo projeto.

---

# 9. Dependências

| DEP | Dependência | Natureza | Efeito |
|---|---|---|---|
| DEP-001 | Aprovação do Brand Book, Design System e UX/UI por jornada | Produto | Condiciona implementação visual |
| DEP-002 | Consolidação das regras de negócio e do modelo de dados | Documental | Condiciona consistência e migração |
| DEP-003 | ADRs de segregação, integrações, auditoria, arquivos e contingência | Arquitetura | Impede decisões estruturais silenciosas |
| DEP-004 | PostgreSQL, serviço S3 compatível e ambiente Docker | Infraestrutura | Base técnica aprovada |
| DEP-005 | Inventário de controladora, facial, LPR, câmeras e pontos de acesso | Hardware | Condiciona M5 e homologação integrada |
| DEP-006 | Documentação, credenciais e ambiente de testes dos fornecedores | Externa | Condiciona adaptadores e testes |
| DEP-007 | Definições de LGPD, biometria, retenção e privacidade | Jurídica e produto | Condiciona coleta e produção |
| DEP-008 | Regras de contribuição, isenção, formas de pagamento e caixa | Negócio | Condiciona MOD-009 |
| DEP-009 | Dados confiáveis de imóveis, pessoas, vínculos e veículos | Operacional | Condiciona carga inicial |
| DEP-010 | Disponibilidade de representantes da portaria, gestão e administração | Organizacional | Condiciona validação e homologação |
| DEP-011 | Certificados, DNS, rede, energia protegida e monitoramento | Infraestrutura | Condiciona go-live seguro |
| DEP-012 | Plano de suporte, escalonamento e responsáveis | Operacional | Condiciona estabilização |

---

# 10. Premissas

| PRE | Premissa |
|---|---|
| PRE-001 | Santa Rita fornecerá dados de implantação e representantes para validação no prazo acordado. |
| PRE-002 | Cada operador utilizará usuário individual, sem compartilhamento de credenciais. |
| PRE-003 | O imóvel continuará sendo a entidade central do domínio. |
| PRE-004 | Bloco será opcional no produto e configurado conforme a estrutura real da implantação. |
| PRE-005 | Uma pessoa poderá possuir múltiplos vínculos sem multiplicação do cadastro principal. |
| PRE-006 | A decisão de autorização permanecerá no SDV Access, ainda que equipamentos usem cache autorizado. |
| PRE-007 | Haverá ambiente de homologação separado do ambiente de produção. |
| PRE-008 | Fotos, documentos e biometria somente entrarão em produção após definição de finalidade, acesso e retenção. |
| PRE-009 | Integrações externas disponibilizarão documentação ou mecanismo verificável de comunicação. |
| PRE-010 | A operação terá procedimento de contingência aprovado e pessoas treinadas. |
| PRE-011 | Datas e volumes serão estimados após refinamento técnico, sem redução silenciosa dos critérios de qualidade. |
| PRE-012 | Evoluções para outros clientes preservarão segregação de dados e parametrização por implantação. |

Se uma premissa deixar de ser verdadeira, o impacto deverá ser avaliado no backlog, nos riscos e no plano de implantação.

---

# 11. Restrições

| RES | Restrição |
|---|---|
| RES-001 | Backend em Laravel. |
| RES-002 | Frontend em Blade e Livewire. |
| RES-003 | Banco de dados PostgreSQL. |
| RES-004 | Arquivos em armazenamento compatível com S3. |
| RES-005 | Python/FastAPI somente para OCR ou IA quando necessário. |
| RES-006 | Implantação por Docker. |
| RES-007 | React não será utilizado no MVP sem nova decisão arquitetural formal. |
| RES-008 | Nenhuma tela será implementada antes de sua aprovação visual e funcional. |
| RES-009 | As referências de `docs/references/` são fontes oficiais funcionais e visuais. |
| RES-010 | Cadastros, vínculos, autorizações, credenciais e eventos não poderão ser fundidos conceitualmente. |
| RES-011 | Registros relevantes não poderão ser removidos de forma destrutiva na operação comum. |
| RES-012 | Integrações não poderão acoplar as regras centrais a um fabricante. |
| RES-013 | Mudanças estruturais exigirão ADR e atualização da rastreabilidade. |
| RES-014 | O caixa do MVP ficará limitado à contribuição ou taxa operacional de acesso. |
| RES-015 | Dados pessoais e exportações deverão respeitar necessidade, finalidade e permissão. |
| RES-016 | A implantação inicial não poderá impedir a evolução segura para outras organizações. |

---

# 12. Indicadores de sucesso

Metas numéricas finais deverão ser aprovadas antes do piloto, após levantamento da linha de base. Até essa aprovação, os indicadores abaixo são obrigatórios e não autorizam a invenção de valores.

| IND | Indicador | Cálculo ou evidência | Frequência | Direção desejada |
|---|---|---|---|---|
| IND-001 | Disponibilidade operacional | tempo disponível ÷ tempo previsto | Mensal | Aumentar |
| IND-002 | Tempo de validação na portaria | mediana e percentil 95 entre início e decisão | Diário/semanal | Reduzir |
| IND-003 | Taxa de decisões rastreáveis | eventos com operador/regra, motivo e resultado ÷ total | Diário | Atingir 100% |
| IND-004 | Taxa de acessos negados corretamente registrados | negativas completas ÷ negativas totais | Semanal | Atingir 100% |
| IND-005 | Taxa de pré-cadastro concluído | protocolos enviados ÷ fluxos iniciados | Semanal | Aumentar |
| IND-006 | Tempo de análise do pré-cadastro | mediana entre envio e decisão | Semanal | Reduzir |
| IND-007 | Duplicidade confirmada de pessoa | cadastros duplicados confirmados ÷ pessoas ativas | Mensal | Reduzir |
| IND-008 | Revogação no prazo | vínculos expirados revogados no prazo ÷ vínculos expirados | Diário | Atingir 100% |
| IND-009 | Sucesso de sincronização | operações sincronizadas ÷ operações enviadas, por adaptador | Diário | Aumentar |
| IND-010 | Falhas técnicas conciliadas | falhas com resultado final reconciliado ÷ falhas totais | Diário | Atingir 100% |
| IND-011 | Divergência de caixa | valor absoluto e percentual entre esperado e informado | Por fechamento | Reduzir |
| IND-012 | Restauração validada | execução aprovada conforme periodicidade definida | Por ciclo | Atingir 100% |
| IND-013 | Incidentes por permissão indevida | quantidade de acessos ou ações não autorizadas confirmadas | Mensal | Atingir zero |
| IND-014 | Aderência à homologação | critérios aprovados ÷ critérios aplicáveis | Por ciclo | Atingir 100% dos bloqueadores |
| IND-015 | Satisfação dos operadores | avaliação após piloto e estabilização | Por marco | Aumentar |

Cada indicador deverá possuir fonte, responsável, fórmula versionada, meta, limite de alerta e regra de tratamento.

---

# 13. Critérios de homologação do MVP

## 13.1 Condições de entrada

A homologação somente deverá iniciar quando:

1. o escopo da versão estiver identificado;
2. as telas aplicáveis estiverem aprovadas;
3. os requisitos e casos de teste estiverem rastreados;
4. o ambiente de homologação representar os componentes relevantes;
5. os dados de teste estiverem preparados e protegidos;
6. integrações indisponíveis possuírem simulador ou plano de teste aprovado;
7. defeitos conhecidos estiverem classificados;
8. responsáveis por produto, operação e tecnologia estiverem definidos.

## 13.2 Critérios funcionais

**HOM-001 — Identidade e permissão:** autenticação, recuperação, inativação, sessões e testes negativos de permissão aprovados.  
**HOM-002 — Imóvel central:** pessoas e veículos são vinculados sem duplicar endereço indevidamente.  
**HOM-003 — Cadastro único:** busca e tratamento de documentos duplicados aprovados.  
**HOM-004 — Histórico:** suspensão, encerramento, renovação e expiração preservam os registros anteriores.  
**HOM-005 — Pré-cadastro:** fluxo móvel, protocolo, análise, correção, aprovação, rejeição e expiração aprovados.  
**HOM-006 — Validação:** pessoa, veículo, autorização, contribuição, observação e decisão são processados conforme a jornada aprovada.  
**HOM-007 — Eventos:** entrada, saída, negativa, pendência, liberação manual e falha técnica são distinguíveis e consultáveis.  
**HOM-008 — Caixa:** abertura, contribuição, cancelamento/ajuste autorizado, fechamento e diferença são conciliáveis.  
**HOM-009 — Credenciais:** estado, vigência, associação e revogação são independentes do cadastro da pessoa.  
**HOM-010 — Integrações:** envio, retorno, falha, retentativa, idempotência e identificador externo são verificados por adaptador.  
**HOM-011 — Relatórios:** filtros, totais, exportações autorizadas e conciliação com dados de origem são aprovados.  
**HOM-012 — Auditoria:** operações relevantes registram conteúdo mínimo, incluindo valores anterior e posterior quando aplicável.

## 13.3 Critérios não funcionais e visuais

**HOM-013 — Segurança:** revisão de autenticação, autorização, sessão, arquivos, segredos e comunicação não apresenta defeito crítico aberto.  
**HOM-014 — Privacidade:** finalidade, avisos, acesso, retenção e tratamento de documentos e biometria estão aprovados.  
**HOM-015 — Desempenho:** consultas críticas atendem às metas a serem aprovadas para volumes representativos.  
**HOM-016 — Responsividade:** pré-cadastro e telas previstas funcionam nos dispositivos e resoluções homologados.  
**HOM-017 — Acessibilidade:** contraste, foco, teclado, rótulos e mensagens passam pela verificação definida para o MVP.  
**HOM-018 — Aderência visual:** composição, hierarquia, nomenclatura, estados, ações e alertas correspondem às referências e especificações UX/UI aprovadas.  
**HOM-019 — Observabilidade:** erros e integrações críticas produzem logs e alertas acionáveis sem exposição indevida de dados.  
**HOM-020 — Continuidade:** backup e restauração são executados com evidência, e a contingência é ensaiada.  
**HOM-021 — Segregação:** não existe acesso cruzado entre implantações ou contextos de dados.  
**HOM-022 — Implantação:** instalação e atualização em Docker são repetíveis e documentadas.

## 13.4 Cenários críticos obrigatórios

Deverão ser testados, no mínimo:

- documento ou pessoa já cadastrada;
- vínculo ainda não iniciado, suspenso, encerrado ou expirado;
- autorização expirada, cancelada ou rejeitada;
- placa divergente, duplicada, bloqueada ou com baixa confiança;
- foto inadequada e sincronização facial com falha;
- controladora indisponível e comando sem confirmação;
- reenvio do mesmo comando;
- negativa e liberação excepcional com justificativa;
- contribuição com caixa fechado;
- diferença de caixa;
- tentativa de exportação sem permissão;
- tentativa de acessar arquivo por endereço não autorizado;
- revogação automática na fronteira de data e hora;
- restauração do serviço e reconciliação de filas pendentes.

## 13.5 Critérios de saída e aceite

O MVP poderá ser recomendado para produção quando:

1. todos os critérios P0 aplicáveis estiverem aprovados;
2. não houver defeito crítico ou alto sem solução;
3. defeitos médios aceitos possuírem impacto, responsável e prazo;
4. riscos residuais estiverem formalmente aceitos;
5. treinamento, suporte, contingência e restauração estiverem validados;
6. Product Owner e representantes designados da operação registrarem o aceite.

Aceites condicionais não poderão ocultar requisito P0 não atendido. Qualquer exceção deverá indicar prazo, risco e medida compensatória.

---

# 14. Estratégia de implantação inicial

## 14.1 Preparação

- confirmar escopo físico e operacional da Santa Rita;
- resolver pendências bloqueadoras;
- aprovar UX/UI e documentos técnicos dependentes;
- inventariar equipamentos, rede, terminais, usuários e fontes de dados;
- definir ambientes de desenvolvimento, homologação e produção;
- configurar domínio, certificados, segredos, monitoramento, backup e S3 compatível;
- preparar plano de carga, validação e reversão.

## 14.2 Carga e saneamento

A carga inicial deverá:

1. receber dados de fonte identificada;
2. validar imóveis antes dos vínculos;
3. normalizar documentos, placas e contatos;
4. detectar duplicidades;
5. separar registros aceitos, rejeitados e pendentes;
6. preservar a origem e a data da importação;
7. produzir relatório de reconciliação;
8. obter validação dos responsáveis de negócio.

Importação não deverá conceder acesso automaticamente sem as regras de ativação, vigência, autorização e credencial.

## 14.3 Piloto controlado

O primeiro uso deverá ocorrer em escopo controlado, com:

- conjunto definido de pontos de acesso e operadores;
- cadastros representativos;
- acompanhamento conjunto de produto, tecnologia e operação;
- contingência pronta;
- coleta dos indicadores basais;
- registro e priorização diária de desvios.

## 14.4 Entrada em produção

A liberação deverá ocorrer por decisão formal de go-live, após:

- homologação aprovada;
- backup inicial e restauração testada;
- usuários e perfis conferidos;
- equipamentos e filas sincronizados;
- suporte e escalonamento divulgados;
- plano de comunicação executado;
- janela e responsáveis confirmados.

## 14.5 Estabilização

Durante o período assistido deverão ser acompanhados:

- disponibilidade e tempos de resposta;
- decisões e falhas de acesso;
- filas e sincronizações;
- duplicidades e correções cadastrais;
- fechamento de caixa;
- chamados e dúvidas dos operadores;
- indicadores da seção 12;
- riscos residuais e necessidade de reversão.

Encerrado o período assistido, deverá ser emitido relatório de estabilização com resultados, pendências remanescentes e recomendação para operação regular.

---

# 15. Pendências abertas

As pendências abaixo consolidam pontos ainda não decididos nas Partes 01 e 02 ou identificados na comparação com as referências visuais. Nenhuma delas autoriza alteração silenciosa das decisões vigentes.

| PEN | Pendência | Classificação | Impacto | Encaminhamento |
|---|---|---|---|---|
| PEN-001 | Confirmar fabricante, modelo, protocolo, licenças e capacidades da controladora, facial e LPR | Bloqueadora para integração | M5 e go-live automatizado | Inventário técnico, prova de integração e ADR |
| PEN-002 | Definir operação sem internet, falha de servidor ou equipamento, incluindo cache e reconciliação | Bloqueadora para produção | Continuidade e segurança | Procedimento operacional e ADR |
| PEN-003 | Definir valor, obrigatoriedade, isenções, descontos, formas de pagamento, cancelamentos e responsabilidade pela contribuição | Bloqueadora para caixa | MOD-009 e relatórios | Regra de negócio aprovada |
| PEN-004 | Confirmar estrutura real de condomínio, blocos, imóveis e pontos de acesso de Santa Rita | Bloqueadora para carga | Modelo e dados iniciais | Levantamento cadastral |
| PEN-005 | Definir base legal, finalidade, transparência, retenção e descarte de biometria, documentos e imagens | Bloqueadora para produção | LGPD e segurança | Avaliação jurídica e política de dados |
| PEN-006 | Definir política de retenção para eventos, auditoria, arquivos, imagens LPR e dados financeiros operacionais | Alta | Armazenamento e conformidade | Política versionada |
| PEN-007 | Definir regras específicas de turista, hospedagem e locação curta | Alta | Vínculos temporários | Regras e critérios de aceite |
| PEN-008 | Definir saída automática, estacionamento e tratamento de veículo sem placa ou com troca temporária | Alta | Jornada veicular | Regras operacionais |
| PEN-009 | Separar formalmente os conceitos de proprietário, morador, titular, responsável e parentesco | Alta | Modelo de vínculos e permissões | Regras de negócio e modelo de dados |
| PEN-010 | Definir finalidade do endereço coletado no pré-cadastro, pois a referência solicita endereço da pessoa e o domínio centraliza o endereço residencial no imóvel | Contradição funcional | Privacidade e modelo de dados | Decisão de produto; não copiar endereço para moradores |
| PEN-011 | Confirmar canais de notificação e mensagens apresentadas ao solicitante | Média | Pré-cadastro | Definição de canais e templates |
| PEN-012 | Decidir se haverá portal do morador no MVP | Média; atualmente fora do MVP | Escopo e identidade externa | Decisão formal de produto |
| PEN-013 | Definir política detalhada de bloqueios, listas de observação e acesso excepcional | Alta | Segurança e operação | Regra de negócio e controle de acesso |
| PEN-014 | Definir metas numéricas de disponibilidade, desempenho, recuperação, retenção e indicadores | Alta | Homologação objetiva | Linha de base e acordo de serviço |
| PEN-015 | Definir política de multicliente/multiempresa e administração global | Média | Expansão futura | ADR antes da evolução comercial |
| PEN-016 | Resolver a divergência técnica da prancha `01-cadastro-pessoa-dados.png`, que cita React.js e MySQL, frente às decisões aprovadas de Blade/Livewire e PostgreSQL | Contradição documental resolvida por precedência | Pode induzir implementação incorreta | Manter `RES-002`, `RES-003` e `RES-007`; corrigir a referência em revisão futura sem substituir a atual silenciosamente |
| PEN-017 | Confirmar a API denominada “BRAVAS API” na prancha e a nomenclatura “Controladora BRAVA” nas telas | Contradição de nomenclatura | Integração e UX | Validar fabricante/nome oficial junto a PEN-001 |
| PEN-018 | Definir o comportamento das ações visuais de remoção em pessoas vinculadas, fotos e veículos | Contradição com preservação histórica | Auditoria e integridade | Usar encerramento/inativação no MVP; expurgo somente por política autorizada |
| PEN-019 | Confirmar se OCR integra o MVP ou permanece evolução P2 | Média | Prazo e dependência Python/FastAPI | Manter conferência manual como caminho obrigatório |
| PEN-020 | Definir quantidade, localização e sentido dos pontos de acesso, câmeras e terminais | Bloqueadora para implantação | Infraestrutura e testes | Levantamento físico |
| PEN-021 | Definir fonte, qualidade e responsável pelos dados da carga inicial | Alta | Migração | Plano de saneamento e aceite |
| PEN-022 | Definir responsáveis, horários e níveis do suporte operacional | Alta | Go-live e estabilização | Plano de suporte |

Para efeito deste volume, as decisões técnicas formais prevalecem sobre textos técnicos conflitantes presentes em pranchas visuais. A composição funcional e visual das telas permanece oficial; a divergência fica preservada nesta lista para correção rastreável.

---

# 16. Encerramento e aprovação do Volume 01

O Volume 01 do Product Book passa a ser composto por:

1. **SDV-PBK-001 — Visão do Produto e Requisitos de Negócio**, contendo visão, escopo, princípios, regras, requisitos e diretrizes técnicas;
2. **SDV-PBK-002 — Jornadas, Casos de Uso e Fluxos Operacionais**, contendo jornadas, estados, exceções, permissões e critérios por fluxo;
3. **SDV-PBK-003 — MVP, Roadmap, Backlog e Homologação**, contendo o recorte executável, prioridades, módulos, planejamento funcional, riscos, dependências, indicadores, homologação, implantação e pendências.

## 16.1 Decisões consolidadas

Ficam consolidados para as etapas seguintes:

- o produto é uma plataforma web denominada SDV Access;
- Santa Rita é a primeira implantação, não uma limitação da arquitetura;
- o imóvel é a entidade central do domínio;
- pessoas possuem cadastro único e vínculos independentes;
- cadastro, vínculo, autorização, credencial e evento de acesso permanecem separados;
- histórico substitui exclusão destrutiva em registros relevantes;
- vigências temporárias expiram e revogam permissões derivadas;
- decisões e operações relevantes são auditáveis;
- menor privilégio orienta usuários e perfis;
- integrações utilizam adaptadores desacoplados;
- Laravel, Blade/Livewire, PostgreSQL, S3 compatível e Docker são as tecnologias aprovadas;
- Python/FastAPI fica restrito a OCR e IA quando necessário;
- React permanece fora do MVP sem nova decisão formal;
- referências visuais aprovadas orientam UX/UI e homologação;
- caixa limita-se à contribuição operacional prevista;
- desenvolvimento de telas depende de especificação visual e funcional aprovada;
- mudanças estruturais exigem rastreabilidade e ADR quando aplicável.

## 16.2 Efeito da aprovação

A aprovação deste volume:

- autoriza o avanço para os documentos especializados previstos nas diretrizes;
- não autoriza o início de telas ainda não especificadas e aprovadas;
- não converte pendências em decisões;
- não substitui Brand Book, Design System, UX/UI, regras consolidadas, modelo de dados, APIs, arquitetura, plano de testes, deploy ou manuais;
- estabelece a linha de base funcional contra a qual mudanças deverão ser avaliadas.

## 16.3 Registro de aprovação

| Papel | Nome | Decisão | Data | Observações |
|---|---|---|---|---|
| Product Owner | Vinicius Velasco de Azevedo | Aprovado | 28/07/2026 | Parte 03 aprovada e conteúdo do Volume 01 encerrado |
| Soluções do Vale | Representante designado | Ciente | 28/07/2026 | Aprovação registrada no repositório oficial |
| Santa Rita | Representante designado | Homologação futura | — | A aprovação documental não substitui a homologação operacional do MVP |

Após aprovação, os três documentos do Volume 01 deverão ter seu status atualizado de forma rastreável. Alterações posteriores deverão preservar os identificadores existentes, atualizar o controle de versões e indicar requisitos, riscos, testes e documentos afetados.

---

## Situação desta parte

Esta parte conclui o conteúdo planejado para o Volume 01 do Product Book e encontra-se **aprovada**. As pendências abertas permanecem rastreadas e deverão ser resolvidas ou formalmente aceitas conforme o marco afetado, sem invalidar a aprovação documental deste volume.
