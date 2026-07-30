# ADR-006 — ARMAZENAMENTO S3 E CICLO DE VIDA DE ARQUIVOS

**Identificador:** ADR-006
**Versão:** 1.0.1
**Status:** Aprovado
**Prioridade:** P1 — Obrigatório do MVP
**Produto:** SDV Access — Implantação Santa Rita
**Responsável pelo produto:** Vinicius Velasco de Azevedo
**Responsável técnico:** Soluções do Vale Tecnologia
**Data:** Julho de 2026

---

## Controle de versões

| Versão | Data | Responsável | Alteração |
|---|---|---|---|
| 1.0.0 | Julho/2026 | Soluções do Vale | Proposta de armazenamento S3 e ciclo de vida dos arquivos |
| 1.0.1 | 30/07/2026 | Product Owner | Aprovação formal do armazenamento S3 e ciclo de vida dos arquivos |

---

# 1. Contexto

O SDV Access armazenará arquivos como:

- documentos pessoais;
- selfies e fotografias;
- evidências LPR;
- imagens de veículos;
- documentos de prestadores;
- anexos de pré-cadastro;
- exports;
- relatórios;
- arquivos de importação;
- evidências técnicas autorizadas.

Esses arquivos podem conter dados pessoais ou sensíveis. Eles precisam permanecer privados, segregados por implantação, verificáveis, auditáveis e sujeitos a retenção.

O PostgreSQL deverá guardar metadados e relacionamentos, não os binários principais. O armazenamento aprovado é um serviço compatível com S3.

---

# 2. Problema

Definir:

- topologia de buckets e prefixos;
- forma de upload e download;
- privacidade e autorização;
- metadados persistidos;
- estados de processamento;
- verificação de conteúdo e malware;
- integridade;
- criptografia;
- versionamento;
- retenção e descarte;
- recuperação e reconciliação;
- portabilidade entre fornecedores.

A decisão bloqueia uploads, documentos, selfies, evidências, exports e integrações que manipulam arquivos.

---

# 3. Objetivos

- arquivos privados por padrão;
- segregação por implantação;
- independência de fornecedor;
- integração com Laravel;
- acesso temporário e autorizado;
- proteção em trânsito e repouso;
- integridade verificável;
- substituição sem perda de histórico;
- retenção controlada;
- descarte auditável;
- recuperação de falhas;
- operação com grandes volumes.

---

# 4. Não objetivos

Este ADR não define:

- fornecedor final;
- região de hospedagem;
- preço;
- política jurídica completa;
- base legal de biometria;
- ferramenta de antivírus;
- CDN pública;
- edição de imagens;
- OCR ou IA;
- backup completo da plataforma;
- tamanho final de cada categoria;
- Object Lock obrigatório para todas as classes.

---

# 5. Base técnica

O Laravel oferece abstração de filesystem compatível com S3, discos privados, filesystems com prefixo e URLs temporárias.

O modelo S3 oferece recursos como versionamento, regras de ciclo de vida, criptografia e, quando necessário, Object Lock. URLs pré-assinadas concedem acesso temporário e devem ser tratadas como credenciais durante sua validade.

Fontes oficiais:

- [Laravel — File Storage](https://laravel.com/docs/12.x/filesystem);
- [Amazon S3 — Presigned URLs](https://docs.aws.amazon.com/AmazonS3/latest/userguide/using-presigned-url.html);
- [Amazon S3 — Versioning](https://docs.aws.amazon.com/AmazonS3/latest/userguide/Versioning.html);
- [Amazon S3 — Lifecycle](https://docs.aws.amazon.com/AmazonS3/latest/userguide/object-lifecycle-mgmt.html);
- [Amazon S3 — Object Lock](https://docs.aws.amazon.com/AmazonS3/latest/userguide/object-lock.html).

Recursos específicos somente serão considerados disponíveis após teste no fornecedor compatível escolhido.

---

# 6. Terminologia

| Termo | Definição |
|---|---|
| Objeto | conteúdo binário armazenado |
| Bucket | contêiner lógico do armazenamento |
| Chave | caminho opaco do objeto |
| Arquivo | entidade do SDV que referencia um objeto |
| Versão da aplicação | nova entidade ou revisão criada pelo SDV |
| Versão S3 | versão gerenciada pelo serviço de objetos |
| URL temporária | URL assinada e limitada no tempo |
| Quarentena | estado sem acesso operacional até validação |
| Retenção | período em que o arquivo deve permanecer |
| Descarte | remoção controlada após elegibilidade |
| Legal hold | bloqueio de descarte por obrigação formal |
| Órfão | objeto sem entidade válida ou entidade sem objeto |

---

# 7. Princípios

1. privado por padrão;
2. PostgreSQL controla metadados e autorização;
3. S3 armazena o binário;
4. chave não contém dado pessoal;
5. upload não significa disponibilidade;
6. arquivo disponível passou pelas validações exigidas;
7. substituição cria nova versão;
8. retenção precede descarte;
9. lifecycle técnico não decide regra jurídica sozinho;
10. URL temporária é credencial;
11. versionamento não substitui backup;
12. falha parcial é reconciliada.

---

# 8. Alternativas consideradas

## 8.1 Alternativa A — Arquivos no PostgreSQL

### Vantagens

- transação única;
- backup conjunto;
- integridade referencial direta.

### Desvantagens

- banco cresce rapidamente;
- backup e restore ficam pesados;
- streaming e distribuição menos adequados;
- custo e desempenho incompatíveis com evidências.

---

## 8.2 Alternativa B — Filesystem local

### Vantagens

- implementação simples;
- desenvolvimento local direto;
- baixa dependência inicial.

### Desvantagens

- dificulta múltiplas réplicas;
- backup e disponibilidade próprios;
- risco de perda em container;
- baixa portabilidade operacional;
- acesso compartilhado complexo.

---

## 8.3 Alternativa C — Armazenamento compatível com S3

### Vantagens

- aprovado tecnicamente;
- objetos privados;
- escalabilidade;
- versionamento;
- lifecycle;
- URLs temporárias;
- compatibilidade com Laravel;
- portabilidade por contrato.

### Desvantagens

- consistência entre banco e objeto exige orquestração;
- recursos variam por fornecedor;
- custos de armazenamento e transferência;
- segurança e lifecycle exigem configuração.

---

## 8.4 Alternativa D — Serviço de arquivos acoplado a fornecedor

### Vantagens

- recursos gerenciados;
- integração específica;
- potencial CDN e transformação.

### Desvantagens

- lock-in;
- contrato proprietário;
- migração mais difícil;
- não atende ao requisito de compatibilidade desacoplada.

---

# 9. Matriz de avaliação

Escala: 1 — desfavorável; 5 — muito favorável.

| Critério | Peso | PostgreSQL | Local | S3 compatível | Proprietário |
|---|---:|---:|---:|---:|---:|
| Escalabilidade | 4 | 2 | 2 | 5 | 5 |
| Privacidade | 5 | 4 | 3 | 5 | 4 |
| Alta disponibilidade | 4 | 3 | 2 | 5 | 5 |
| Compatibilidade Laravel | 4 | 3 | 5 | 5 | 3 |
| Portabilidade | 4 | 3 | 3 | 5 | 1 |
| Versionamento/lifecycle | 4 | 2 | 1 | 5 | 5 |
| Operação em containers | 4 | 2 | 1 | 5 | 5 |
| Adequação ao produto | 5 | 2 | 2 | 5 | 3 |

A alternativa C é a única alinhada às decisões aprovadas e aos requisitos.

---

# 10. Decisão proposta

Adotar:

- armazenamento compatível com S3;
- bucket ou conjunto de buckets privado por ambiente;
- prefixos opacos por implantação e categoria;
- PostgreSQL como catálogo de arquivos;
- Laravel Filesystem como abstração principal;
- objetos imutáveis por chave;
- versionamento habilitado nos buckets de produção;
- criptografia em trânsito e repouso;
- URLs temporárias de curta duração;
- upload direto pré-assinado somente por sessão autorizada;
- quarentena e validação antes da disponibilidade;
- checksum e metadados técnicos;
- lifecycle alinhado à política do SDV;
- Object Lock somente quando obrigação aprovada exigir;
- reconciliação entre banco e objetos;
- fornecedor selecionado por teste de compatibilidade.

---

# 11. Topologia de buckets

## 11.1 Ambientes

Ambientes terão buckets distintos:

- desenvolvimento;
- testes;
- homologação;
- produção.

Dados reais não serão copiados livremente para ambientes inferiores.

## 11.2 Produção

O desenho inicial poderá usar um bucket privado para os arquivos operacionais, com prefixos por implantação e categoria.

Buckets adicionais poderão separar:

- quarentena;
- exports temporários;
- backups;
- logs técnicos;
- classes com Object Lock.

A quantidade final dependerá das capacidades do fornecedor e das políticas de acesso.

---

# 12. Segregação por implantação

Chave conceitual:

```text
{ambiente}/{implantacao_opaca}/{categoria}/{ano}/{mes}/{uuid}
```

## 12.1 Regras

- identificador da implantação será opaco;
- chave não conterá nome do condomínio;
- chave não conterá pessoa, CPF, placa ou imóvel;
- aplicação atribuirá o prefixo;
- entrada do usuário não controlará caminho;
- metadado PostgreSQL terá `implantacao_id`;
- autorização validará implantação;
- listagem ampla do bucket não será exposta.

Discos com prefixo poderão ser usados como defesa adicional, sem substituir policies.

---

# 13. Entidade arquivo

Campos conceituais:

- `id` UUIDv7;
- `implantacao_id`;
- provedor lógico;
- bucket lógico;
- chave do objeto;
- versão S3 quando disponível;
- nome original protegido;
- nome de exibição;
- extensão observada;
- MIME declarado;
- MIME detectado;
- tamanho;
- checksum;
- classificação;
- finalidade;
- estado;
- origem;
- criador;
- timestamps;
- regra de retenção;
- data de elegibilidade;
- legal hold;
- erro sanitizado.

Segredos e URLs temporárias não serão persistidos.

---

# 14. Vínculos do arquivo

Arquivo e entidade de negócio se relacionarão por associação explícita.

Exemplos:

- pessoa;
- pré-cadastro;
- veículo;
- prestador;
- captura LPR;
- auditoria autorizada;
- export;
- importação.

## 14.1 Regras

- finalidade obrigatória;
- implantação igual;
- vigência quando aplicável;
- vínculo histórico preservado;
- arquivo não será duplicado apenas para múltiplas referências autorizadas;
- compartilhamento entre implantações é proibido;
- exclusão da entidade não apaga o objeto automaticamente.

---

# 15. Estados

```text
Iniciado
Upload pendente
Recebido
Em quarentena
Em validação
Disponível
Rejeitado
Falha de processamento
Bloqueado
Substituído
Descarte pendente
Descartado
Órfão em análise
```

## 15.1 Regras

- somente `Disponível` poderá ser usado normalmente;
- rejeição preservará evidência técnica mínima permitida;
- substituição não sobrescreverá a entidade anterior;
- descarte será transição, não simples delete;
- falha externa não produzirá estado disponível.

---

# 16. Fluxo de upload pela aplicação

Adequado para arquivos pequenos e fluxos controlados:

```text
requisição autenticada
  → autorização
  → validação preliminar
  → criar entidade Iniciado
  → receber stream
  → gravar em quarentena
  → calcular/verificar checksum
  → confirmar metadados
  → enfileirar validação
```

O conteúdo não deverá ser carregado integralmente em memória quando streaming for possível.

---

# 17. Upload direto pré-assinado

Adequado para arquivos maiores ou para reduzir tráfego na aplicação.

## 17.1 Fluxo

1. usuário solicita sessão;
2. aplicação autentica e autoriza;
3. aplicação define implantação, chave, tipo e limite;
4. cria entidade `Upload pendente`;
5. gera URL curta para operação e chave específicas;
6. cliente envia;
7. aplicação confirma existência, tamanho e checksum;
8. move logicamente para quarentena;
9. enfileira validação.

## 17.2 Restrições

- URL limitada a método e objeto;
- validade curta;
- tamanho e tipo validados;
- chave não reutilizada;
- confirmação obrigatória;
- upload não confirmado vira órfão;
- URL não entra em log;
- sessão poderá ser uso único no SDV.

---

# 18. URLs temporárias de leitura

Somente serão geradas após:

- autenticação;
- implantação válida;
- autorização do recurso;
- arquivo disponível;
- finalidade compatível;
- registro de acesso quando exigido.

## 18.1 Regras

- curta duração;
- método restrito;
- disposição e MIME seguros;
- nome de download sanitizado;
- sem cache público;
- sem persistência da URL;
- revogação indireta por remoção de acesso ou objeto;
- acesso altamente sensível poderá passar por proxy da aplicação.

URL temporária poderá ser reutilizada durante sua validade pelo portador; por isso será tratada como credencial.

---

# 19. Validação de entrada

Validações:

- tamanho;
- extensão permitida;
- MIME declarado;
- MIME detectado;
- assinatura mágica;
- checksum;
- dimensões quando imagem;
- quantidade de páginas quando necessário;
- conteúdo corrompido;
- malware;
- arquivo protegido por senha;
- nome seguro;
- política por finalidade.

Extensão e header enviados pelo cliente não serão considerados prova do tipo.

---

# 20. Quarentena e malware

Arquivos novos permanecerão inacessíveis operacionalmente até validação exigida.

## 20.1 Regras

- quarentena privada;
- worker com acesso mínimo;
- ferramenta de análise isolada;
- timeout e limite de recursos;
- resultado registrado;
- assinatura/versão da ferramenta quando disponível;
- arquivo suspeito bloqueado;
- operador sem download direto de malware;
- reanálise controlada;
- alerta em detecção.

A ferramenta será escolhida posteriormente e testada com amostras seguras.

---

# 21. Integridade

## 21.1 Checksum

- calculado ou verificado no upload;
- persistido em formato e algoritmo definidos;
- usado para detectar corrupção;
- não usado automaticamente para deduplicação entre pessoas;
- conferido em migração;
- protegido contra valor fornecido sem validação.

## 21.2 Tamanho

Tamanho persistido será comparado ao objeto.

## 21.3 Reconciliação

Rotinas verificarão existência, tamanho, versão e checksum conforme amostragem ou criticidade.

---

# 22. Deduplicação

Deduplicação física global não será adotada no MVP.

Motivos:

- risco de correlação entre implantações;
- retenções distintas;
- descarte de uma referência;
- complexidade de ownership;
- segurança de acesso;
- benefício não medido.

Checksum poderá identificar envio repetido dentro do mesmo fluxo para alerta ou idempotência, sem compartilhar objeto entre implantações.

---

# 23. Imutabilidade e substituição

Objetos disponíveis não serão sobrescritos na mesma chave.

Substituição:

1. cria novo arquivo;
2. usa nova chave;
3. passa por validação;
4. cria vínculo de substituição;
5. mantém versão anterior conforme retenção;
6. atualiza referência corrente transacionalmente;
7. gera auditoria.

O versionamento S3 é proteção adicional e não substitui o histórico da aplicação.

---

# 24. Versionamento S3

Buckets de produção deverão ter versionamento habilitado quando o fornecedor suportar semântica compatível homologada.

## 24.1 Objetivos

- recuperar sobrescrita ou exclusão acidental;
- preservar versões técnicas;
- apoiar investigação;
- reduzir risco operacional.

## 24.2 Regras

- `version_id` será persistido quando relevante;
- aplicação evitará sobrescrever mesmo com versionamento;
- delete marker não será tratado como descarte definitivo;
- versões antigas terão lifecycle;
- suspensão de versionamento exigirá mudança controlada;
- custo e quantidade de versões serão monitorados.

---

# 25. Criptografia

## 25.1 Em trânsito

- TLS;
- validação de certificado;
- endpoints privados quando disponíveis;
- bloqueio de HTTP sem proteção.

## 25.2 Em repouso

- criptografia do serviço obrigatória;
- chave gerenciada pelo provedor ou cliente conforme risco;
- política distinta para dados sensíveis quando necessário;
- rotação sem perda de acesso;
- acesso à chave separado do acesso ao objeto;
- segredos fora do código.

A estratégia final de chaves será alinhada ao ADR-009.

---

# 26. Controle de acesso

- bucket não público;
- bloqueio de ACL pública;
- credencial da aplicação com menor privilégio;
- worker de validação limitado à quarentena;
- leitura normal por URL temporária ou proxy;
- lifecycle com papel separado;
- exclusão definitiva com autoridade específica;
- administração humana excepcional;
- acesso auditável;
- credenciais separadas por ambiente.

Compatibilidade S3 não autoriza usar credencial administrativa ampla na aplicação.

---

# 27. Classificação

Categorias mínimas:

| Classe | Exemplos | Controle |
|---|---|---|
| Interno | arquivos técnicos não pessoais | acesso autenticado |
| Pessoal | documento, foto, contato | permissão e auditoria |
| Sensível | biometria e evidências específicas | finalidade e proteção reforçada |
| Temporário | upload e export | validade curta |
| Quarentena | conteúdo não validado | sem acesso operacional |

Classificação orientará:

- acesso;
- retenção;
- criptografia;
- auditoria;
- export;
- descarte;
- ambiente permitido.

---

# 28. Retenção

Cada categoria deverá possuir:

- finalidade;
- evento inicial da contagem;
- prazo;
- base;
- responsável;
- bloqueios;
- tratamento ao vencer;
- exceção por litígio;
- evidência do descarte.

Enquanto a política definitiva não estiver aprovada:

- nenhum descarte automático de arquivos de negócio;
- limpeza apenas de temporários tecnicamente seguros;
- retenção conservadora;
- alertas de volume;
- pendência explícita.

---

# 29. Lifecycle do provedor

Lifecycle poderá:

- expirar uploads incompletos;
- remover temporários vencidos;
- transicionar versões antigas;
- remover versões não correntes elegíveis;
- expirar exports;
- reduzir custo.

## 29.1 Limite

Regra do provedor não será a única autoridade para apagar arquivo de negócio.

O PostgreSQL deverá marcar elegibilidade ou prefixo controlado antes da regra destrutiva, salvo temporários que nunca se tornaram entidades válidas.

---

# 30. Legal hold e Object Lock

Object Lock ou recurso WORM equivalente será adotado somente quando:

- obrigação regulatória, contratual ou risco exigir;
- fornecedor for compatível;
- versionamento estiver habilitado;
- papéis e bypass forem definidos;
- recuperação de chaves estiver garantida;
- custo e operação forem aceitos.

## 30.1 Estado atual

- não obrigatório para todas as classes no MVP;
- legal hold será modelado conceitualmente no PostgreSQL;
- descarte respeitará bloqueio;
- ativação futura exigirá atualização deste ADR ou decisão complementar;
- auditoria lógica continuará obrigatória.

---

# 31. Descarte

Fluxo:

```text
arquivo elegível
  → verificar retenção
  → verificar vínculos
  → verificar legal hold
  → autorização
  → marcar descarte pendente
  → remover versões elegíveis
  → confirmar ausência
  → marcar descartado
  → auditar
```

## 31.1 Falha

Se o objeto não for removido:

- estado permanece pendente ou falho;
- retentativa controlada;
- alerta;
- sem falso sucesso.

---

# 32. Exclusão lógica

Ações do usuário:

- inativar vínculo;
- ocultar arquivo corrente;
- substituir;
- solicitar descarte quando permitido.

Não executarão delete físico imediato.

Arquivo descartado manterá metadados mínimos permitidos para evidenciar:

- identidade;
- categoria;
- decisão;
- autor;
- instante;
- resultado;
- sem reter conteúdo além do permitido.

---

# 33. Órfãos

Tipos:

- objeto sem entidade;
- entidade sem objeto;
- upload expirado;
- objeto em chave inesperada;
- versão sem metadado;
- descarte parcial.

## 33.1 Reconciliação

- inventariar prefixos;
- comparar PostgreSQL;
- classificar;
- colocar em análise;
- recuperar ou descartar;
- registrar resultado;
- nunca apagar automaticamente arquivo desconhecido de produção sem janela e política.

---

# 34. Falhas parciais

## 34.1 Objeto gravado, banco falhou

- objeto fica em prefixo de upload/quarentena;
- sessão permite identificação;
- rotina encontra órfão;
- remove após janela técnica aprovada.

## 34.2 Banco criado, upload falhou

- estado permanece pendente/falho;
- permite retentativa;
- expira sessão;
- não fica disponível.

## 34.3 Validação falhou

- arquivo bloqueado;
- motivo sanitizado;
- nova submissão cria nova tentativa;
- evidência mínima conforme retenção.

---

# 35. Processamento assíncrono

Usará ADR-004 e ADR-005:

- outbox após confirmação;
- fila de arquivos;
- job com implantação;
- idempotência;
- retentativas;
- timeout;
- intervenção;
- métricas;
- payload com ID, não binário.

Worker obterá o objeto por acesso técnico restrito.

---

# 36. OCR e IA

Arquivo somente será enviado a OCR/IA quando:

- ADR-011 estiver aprovado;
- finalidade permitir;
- arquivo estiver validado;
- acesso temporário e restrito;
- correlação existir;
- resultado for assistivo;
- material temporário for descartado;
- fornecedor ou serviço não reter indevidamente.

Selfie não criará credencial biométrica automaticamente.

---

# 37. Evidências LPR

Deverão preservar:

- arquivo original;
- leitura original;
- confiança;
- câmera;
- instante do equipamento;
- instante de recebimento;
- correção humana;
- vínculo com evento;
- versão da análise.

Correção da placa não sobrescreverá a evidência. Retenção e acesso permanecem pendentes de política.

---

# 38. Exports

- bucket/prefixo temporário;
- arquivo privado;
- solicitante e filtros persistidos;
- validade;
- URL curta;
- limite de downloads quando necessário;
- auditoria;
- descarte automático após prazo aprovado;
- classificação igual ou superior aos dados de origem;
- nenhum link permanente.

Export falho não será marcado como concluído.

---

# 39. Imports

- arquivo em quarentena;
- checksum;
- origem;
- lote;
- validação;
- erros estruturados;
- idempotência;
- retenção definida;
- acesso restrito;
- arquivo original preservado pelo período aprovado.

Import não contornará segregação ou regras de domínio.

---

# 40. Backup e recuperação

Versionamento não substitui backup.

Estratégia deverá considerar:

- cópia ou replicação autorizada;
- separação de credenciais;
- proteção contra exclusão;
- criptografia;
- inventário;
- teste de restauração;
- reconciliação com PostgreSQL;
- RPO e RTO;
- desastre do provedor;
- portabilidade.

A topologia final será definida no ADR-012.

---

# 41. Portabilidade

O fornecedor compatível deverá suportar, no mínimo:

- operações de objeto usadas;
- uploads e downloads;
- multipart quando necessário;
- URLs assinadas compatíveis ou alternativa segura;
- metadados;
- checksum;
- listagem para reconciliação;
- versionamento em produção;
- lifecycle;
- criptografia;
- controle de acesso;
- logs e métricas essenciais.

Migração deverá preservar catálogo, checksums, chaves lógicas e versões necessárias.

---

# 42. Observabilidade

Métricas:

- uploads iniciados e concluídos;
- bytes;
- falhas;
- quarentena;
- tempo de validação;
- malware;
- órfãos;
- downloads;
- URLs geradas;
- descartes;
- falhas de lifecycle;
- divergências;
- versões;
- custo e crescimento;
- latência e erros do provedor.

Labels não conterão dados pessoais.

---

# 43. Alertas

- armazenamento indisponível;
- upload falhando;
- validação parada;
- quarentena acumulada;
- malware detectado;
- órfãos acima do limite;
- divergência de checksum;
- descarte falho;
- bucket público;
- criptografia desabilitada;
- versionamento suspenso;
- lifecycle alterado;
- credencial próxima de expiração;
- crescimento anormal.

Alterações críticas de configuração deverão ser auditadas.

---

# 44. Desenvolvimento e testes

Ambiente local poderá usar serviço S3 compatível em container ou fake controlado.

Testes obrigatórios:

- upload;
- download autorizado;
- negação entre implantações;
- URL expirada;
- URL com método incorreto;
- tipo falso;
- arquivo grande;
- checksum divergente;
- malware simulado;
- falha após upload;
- falha antes do upload;
- substituição;
- versionamento;
- descarte bloqueado;
- órfão;
- indisponibilidade;
- reconciliação.

Fake não substituirá teste de contrato com o serviço real de homologação.

---

# 45. Consequências positivas

- banco protegido de binários;
- escalabilidade de objetos;
- arquivos privados;
- acesso temporário;
- isolamento por implantação;
- recuperação de sobrescrita;
- lifecycle controlado;
- portabilidade;
- integração nativa com Laravel;
- quarentena e validação;
- histórico de substituição;
- observabilidade.

---

# 46. Consequências negativas

- consistência distribuída entre PostgreSQL e objetos;
- nova dependência;
- custo de armazenamento e transferência;
- lifecycle exige governança;
- URLs temporárias podem vazar;
- versionamento aumenta volume;
- malware exige ferramenta;
- restauração exige reconciliação;
- recursos variam por fornecedor;
- descarte é assíncrono.

Esses custos são aceitos com estados, filas e reconciliação.

---

# 47. Riscos e mitigações

| Risco | Mitigação |
|---|---|
| bucket público | política, teste e alerta |
| URL temporária vazada | validade curta e escopo |
| chave com dado pessoal | gerador opaco |
| objeto órfão | sessão e reconciliação |
| arquivo malicioso | quarentena |
| MIME falso | detecção de conteúdo |
| sobrescrita | chave imutável e versionamento |
| lifecycle apagar cedo | elegibilidade controlada |
| perda de chave de criptografia | gestão e recuperação |
| fornecedor incompatível | suíte de contrato |
| versionamento aumentar custo | lifecycle e métricas |
| backup inconsistente | reconciliação com PostgreSQL |

---

# 48. Estratégia de implementação

1. aprovar este ADR;
2. definir categorias e limites;
3. selecionar fornecedor para prova;
4. configurar bucket privado de homologação;
5. provar URLs temporárias;
6. provar versionamento e lifecycle;
7. criar entidade arquivo;
8. implementar upload em quarentena;
9. implementar validação;
10. criar worker;
11. implementar substituição;
12. implementar reconciliação;
13. testar descarte;
14. criar métricas e alertas;
15. documentar operação.

---

# 49. Validação

A decisão será validada quando:

- objeto não for público;
- implantação A não acessar B;
- URL expirar;
- upload inválido permanecer indisponível;
- checksum detectar divergência;
- substituição preservar anterior;
- versão excluída acidentalmente puder ser recuperada;
- lifecycle respeitar elegibilidade;
- descarte falho permanecer visível;
- órfão for detectado;
- restart do worker retomar processamento;
- fornecedor passar na suíte de contrato.

---

# 50. Critérios de aceite

**CA-ADR-006-001:** binários principais ficam fora do PostgreSQL.

**CA-ADR-006-002:** armazenamento usa contrato compatível com S3.

**CA-ADR-006-003:** fornecedor não é fixado por este ADR.

**CA-ADR-006-004:** buckets são privados por padrão.

**CA-ADR-006-005:** ambientes usam buckets distintos.

**CA-ADR-006-006:** chaves não contêm dados pessoais.

**CA-ADR-006-007:** implantação aparece no catálogo e prefixo opaco.

**CA-ADR-006-008:** autorização ocorre antes da URL temporária.

**CA-ADR-006-009:** URLs possuem duração curta e não são persistidas.

**CA-ADR-006-010:** upload não implica disponibilidade.

**CA-ADR-006-011:** arquivos passam por quarentena e validação aplicável.

**CA-ADR-006-012:** tipo declarado não é a única validação.

**CA-ADR-006-013:** checksum é persistido e verificável.

**CA-ADR-006-014:** objetos disponíveis não são sobrescritos.

**CA-ADR-006-015:** substituição cria nova versão na aplicação.

**CA-ADR-006-016:** versionamento S3 é habilitado em produção após homologação.

**CA-ADR-006-017:** criptografia em trânsito e repouso é obrigatória.

**CA-ADR-006-018:** credenciais seguem menor privilégio.

**CA-ADR-006-019:** lifecycle não decide sozinho retenção jurídica.

**CA-ADR-006-020:** Object Lock permanece condicional.

**CA-ADR-006-021:** descarte verifica retenção, vínculos e hold.

**CA-ADR-006-022:** exclusão lógica não remove imediatamente o objeto.

**CA-ADR-006-023:** falhas parciais são reconciliadas.

**CA-ADR-006-024:** versionamento não substitui backup.

**CA-ADR-006-025:** processamento assíncrono usa IDs e não binários no job.

**CA-ADR-006-026:** teste real complementa fakes.

---

# 51. Rastreabilidade

## 51.1 Documentos

- `docs/006_UX_UI_PRE_CADASTRO.md`;
- `docs/008_ADMINISTRACAO.md`;
- `docs/009_REGRAS_DE_NEGOCIO.md`;
- `docs/010_BANCO_DE_DADOS.md`;
- `docs/011_ARQUITETURA_DO_SISTEMA.md`;
- `docs/ADR/000_CATALOGO_DE_ADRS.md`;
- `docs/ADR/ADR-002_MULTI_IMPLANTACAO_E_ISOLAMENTO.md`;
- `docs/ADR/ADR-003_IDENTIFICADORES_INTERNOS_E_PUBLICOS.md`;
- `docs/ADR/ADR-004_AUDITORIA_EVENTOS_E_OUTBOX.md`;
- `docs/ADR/ADR-005_FILAS_CACHE_LOCKS_E_IDEMPOTENCIA.md`.

## 51.2 Regras

- `RN-028` — documento e imagem;
- `RN-046` a `RN-049` — auditoria;
- `RN-055` — segregação;
- `RN-065`, `RN-066` — finalidade e arquivos;
- `RN-072`, `RN-074`, `RN-075` — versões, OCR e selfie;
- `RN-086` — evidência LPR;
- `RN-088` — falha externa;
- `RN-100` — segredos.

---

# 52. Dependências

| ADR | Relação |
|---|---|
| ADR-002 | segregação por implantação |
| ADR-003 | UUIDv7 e chaves opacas |
| ADR-004 | auditoria e outbox |
| ADR-005 | filas e processamento |
| ADR-009 | credenciais e criptografia |
| ADR-010 | métricas e alertas |
| ADR-011 | OCR/IA condicional |
| ADR-012 | backup e implantação |
| ADR-013 | biometria |
| ADR-014 | retenção e particionamento |

---

# 53. Pendências

| PEN-ADR-006 | Pendência | Tratamento |
|---|---|---|
| PEN-ADR-006-001 | Fornecedor e região | infraestrutura |
| PEN-ADR-006-002 | Limites por categoria | produto e segurança |
| PEN-ADR-006-003 | Ferramenta de malware | prova técnica |
| PEN-ADR-006-004 | Política de retenção | privacidade |
| PEN-ADR-006-005 | Estratégia de chaves de criptografia | ADR-009 |
| PEN-ADR-006-006 | Object Lock por classe | política jurídica |
| PEN-ADR-006-007 | Backup e replicação | ADR-012 |
| PEN-ADR-006-008 | Upload direto por categoria | APIs e UX |
| PEN-ADR-006-009 | Política de acesso à evidência LPR | segurança |
| PEN-ADR-006-010 | Processo de portabilidade | manual operacional |

---

# 54. Aprovação

| Papel | Nome | Decisão | Data | Observações |
|---|---|---|---|---|
| Product Owner | Vinicius Velasco de Azevedo | Aprovado | 30/07/2026 | S3 privado, versionado, criptografado e controlado pelo catálogo PostgreSQL aprovado |
| Responsável técnico | Soluções do Vale Tecnologia | Recomendado | Julho/2026 | S3 privado, versionado, criptografado e controlado pelo catálogo PostgreSQL |

---

# 55. Decisão resultante

Com este ADR **Aprovado**:

- o catálogo será atualizado no mesmo commit;
- arquivos usarão armazenamento compatível com S3;
- buckets serão privados e segregados por ambiente;
- objetos terão chaves opacas;
- uploads passarão por quarentena;
- versionamento será homologado para produção;
- política de retenção resolverá descarte e Object Lock.

---

## Situação do ADR

**Aprovado.** S3 privado, versionado, criptografado e controlado pelo catálogo PostgreSQL constitui a estratégia vigente.
