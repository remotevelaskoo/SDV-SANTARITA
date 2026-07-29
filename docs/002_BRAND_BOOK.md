# SDV ACCESS — BRAND BOOK
## Identidade da marca e diretrizes de aplicação

**Documento:** SDV-BRB-002  
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
| 1.0.0 | Julho/2026 | Soluções do Vale | Consolidação inicial da identidade visual a partir das referências oficiais |
| 1.0.1 | 28/07/2026 | Product Owner | Aprovação formal do Brand Book |

---

# 1. Objetivo

Este Brand Book estabelece a base de identidade do **SDV Access**, produto da **Soluções do Vale Tecnologia**, para orientar os documentos de Design System, UX/UI, comunicação, desenvolvimento, testes visuais e implantação.

O documento foi elaborado a partir das imagens aprovadas em `docs/references/`, consideradas fonte oficial funcional e visual conforme o documento `SDV-DIR-000`.

Seus objetivos são:

- diferenciar a marca do produto da marca institucional proprietária;
- preservar a identidade observada nas referências;
- estabelecer usos consistentes de nome, assinatura, cor, tipografia, iconografia e linguagem;
- evitar substituição por layouts ou estilos genéricos;
- permitir personalização futura por implantação sem descaracterizar o produto;
- registrar lacunas que dependem de arquivos mestres ou decisão formal;
- fornecer critérios verificáveis para Design System, UX/UI e homologação visual.

Este documento não redesenha os logotipos, não cria arquivos vetoriais e não substitui a especificação detalhada de componentes do Design System.

---

# 2. Fontes oficiais

## 2.1 Referências analisadas

| REF | Arquivo | Conteúdo principal |
|---|---|---|
| REF-BR-001 | `docs/references/01-cadastro-pessoa-dados.png` | Prancha institucional, marcas, arquitetura visual, módulos e exemplos de telas |
| REF-BR-002 | `docs/references/06-validacao-entrada.png` | Tela completa de validação de entrada |
| REF-BR-003 | `docs/references/ChatGPT Image 27 de jul. de 2026, 13_49_55.png` | Cópia visualmente idêntica de REF-BR-002 |
| REF-BR-004 | `docs/references/ChatGPT Image 27 de jul. de 2026, 13_55_44.png` | Pré-cadastro público e análise pela portaria |
| REF-BR-005 | `docs/references/ChatGPT Image 27 de jul. de 2026, 14_01_27.png` | Cadastro de pessoa, vínculos, veículos e histórico |
| REF-BR-006 | `docs/references/ChatGPT Image 27 de jul. de 2026, 14_05_13.png` | Cadastro de pessoa, endereço do imóvel e vínculos |
| REF-BR-007 | `docs/references/ChatGPT Image 27 de jul. de 2026, 14_07_54.png` | Variação refinada do cadastro, endereço e vínculos |

## 2.2 Precedência

Em caso de divergência:

1. decisões formais aprovadas e ADRs prevalecem em assuntos técnicos e arquiteturais;
2. as referências visuais prevalecem para identidade, hierarquia, composição e comportamento visual;
3. este Brand Book consolida padrões recorrentes, sem apagar divergências;
4. o Design System deverá transformar esta base em tokens e componentes verificáveis;
5. detalhes não demonstráveis pelas imagens permanecem como pendência.

As menções a React.js e MySQL em REF-BR-001 não constituem diretriz de marca nem alteram as tecnologias aprovadas no Product Book.

---

# 3. Arquitetura de marca

## 3.1 Marca institucional

**Soluções do Vale Tecnologia** é a marca proprietária e institucional.

Ela identifica:

- a empresa responsável pelo produto;
- comunicações corporativas;
- materiais institucionais;
- rodapés, créditos e contatos da empresa;
- apresentações comerciais e técnicas;
- autoria e propriedade intelectual, conforme política corporativa.

Nas referências, sua assinatura combina:

- símbolo geométrico azul em forma de cubo ou estrutura tridimensional;
- logotipo “SOLUÇÕES DO VALE”;
- complemento “TECNOLOGIA”;
- aplicação preferencial sobre fundo azul-marinho ou branco.

## 3.2 Marca do produto

**SDV Access** é a marca do produto de gestão e controle de acesso.

Ela identifica:

- a plataforma;
- a autenticação;
- a navegação principal;
- as telas operacionais e administrativas;
- mensagens e documentos próprios do produto;
- manuais do sistema;
- integrações e APIs do produto;
- versões futuras aplicáveis a outras organizações.

Nas telas, a assinatura do produto utiliza:

- o símbolo geométrico herdado do universo visual da Soluções do Vale;
- o nome “SDV ACCESS”;
- o complemento institucional “Soluções do Vale” ou a descrição funcional do produto, conforme o contexto.

## 3.3 Marca da implantação

**Santa Rita** é a identificação da primeira implantação do SDV Access, não o nome do produto.

A relação correta é:

```text
Soluções do Vale Tecnologia
        └── SDV Access
                └── Implantação Santa Rita
```

A implantação poderá possuir nome, dados operacionais e identidade institucional configuráveis, desde que:

- “SDV Access” permaneça identificável como produto;
- a propriedade da Soluções do Vale seja preservada nos locais definidos;
- a personalização não altere estados, alertas ou padrões essenciais de usabilidade;
- cores do cliente não substituam cores semânticas sem validação de contraste;
- qualquer modelo white-label seja objeto de decisão comercial e visual específica.

---

# 4. Essência da marca

## 4.1 Propósito

**Tornar o controle de acesso mais seguro, claro, integrado e rastreável.**

## 4.2 Promessa

O SDV Access organiza pessoas, imóveis, veículos, autorizações e eventos em uma operação única, permitindo decisões rápidas sem perder segurança ou histórico.

## 4.3 Posicionamento

O SDV Access é uma plataforma web profissional de gestão e controle de acesso para organizações que precisam operar continuamente, integrar equipamentos e manter rastreabilidade.

Não deve ser apresentado como:

- simples cadastro de visitantes;
- aplicativo isolado de portaria;
- produto dependente de um único equipamento;
- ERP financeiro;
- solução experimental de inteligência artificial;
- sistema exclusivo para condomínios residenciais.

## 4.4 Atributos

| Atributo | Expressão na experiência |
|---|---|
| Segurança | decisões explícitas, estados visíveis, proteção de dados e menor privilégio |
| Confiança | informações rastreáveis, histórico e confirmação de operações |
| Clareza | hierarquia forte, linguagem direta e alertas compreensíveis |
| Tecnologia | integrações, automação e dados apresentados sem excesso visual |
| Eficiência | ações principais evidentes e redução de passos desnecessários |
| Solidez | azul-marinho, organização modular e consistência entre telas |
| Escalabilidade | marca de produto independente da implantação Santa Rita |

## 4.5 Personalidade

A marca deve ser percebida como:

- segura, sem ser intimidadora;
- tecnológica, sem ser futurista de forma artificial;
- profissional, sem ser burocrática;
- objetiva, sem ser fria;
- moderna, sem seguir modismos que reduzam sua longevidade;
- confiável, sem prometer infalibilidade.

---

# 5. Nomenclatura

## 5.1 Forma oficial

Em texto corrido, utilizar:

**SDV Access**

Em assinaturas gráficas e títulos de interface, a forma em caixa alta observada nas referências é permitida:

**SDV ACCESS**

## 5.2 Formas complementares

| Contexto | Forma recomendada |
|---|---|
| Primeira menção institucional | SDV Access, da Soluções do Vale Tecnologia |
| Documento da implantação | SDV Access — Implantação Santa Rita |
| Navegação ou assinatura compacta | SDV ACCESS |
| Nome da empresa | Soluções do Vale Tecnologia |
| Referência curta à empresa | Soluções do Vale |

## 5.3 Usos incorretos

Não utilizar:

- “SDVAccess” sem espaço;
- “Sdv Access”;
- “SDV Santa Rita” como substituto permanente do nome do produto;
- “Santa Rita Access”;
- traduções do nome da marca;
- pluralização de “Access”;
- abreviações não aprovadas;
- nomes de fabricantes como parte do nome do produto.

## 5.4 Descritor

As referências utilizam descritores próximos de:

- “Plataforma Inteligente de Controle de Acesso”;
- “Sistema Inteligente de Controle de Acesso”.

Até decisão final, o descritor institucional recomendado é:

**Plataforma de gestão e controle de acesso**

O termo “inteligente” poderá ser empregado em material institucional, mas não deverá sugerir que toda decisão depende de IA.

---

# 6. Assinaturas visuais

## 6.1 Assinatura institucional principal

Composição:

```text
[Símbolo geométrico]
SOLUÇÕES DO VALE
TECNOLOGIA
```

Uso preferencial:

- apresentações institucionais;
- materiais corporativos;
- rodapés;
- contatos institucionais;
- créditos de propriedade.

## 6.2 Assinatura principal do produto

Composição:

```text
[Símbolo geométrico] SDV ACCESS
                    Soluções do Vale
```

Uso preferencial:

- topo ou lateral da aplicação;
- login;
- manuais do produto;
- cabeçalhos de documentos;
- materiais de implantação.

## 6.3 Assinatura vertical ou de navegação

As referências mostram aplicação vertical na base do menu lateral:

```text
[Símbolo]
SDV ACCESS
Plataforma de controle de acesso
Versão
```

Essa assinatura poderá ser utilizada quando houver espaço vertical suficiente. Informações de versão devem permanecer tipograficamente secundárias.

## 6.4 Símbolo isolado

O símbolo poderá ser utilizado isoladamente em:

- favicon;
- avatar do produto;
- ícone de aplicativo web;
- estados compactos do menu;
- carregamento;
- marcas d’água discretas.

Seu uso isolado depende da disponibilidade de arquivo mestre aprovado e não autoriza redesenho manual.

## 6.5 Área de proteção

Até a disponibilização da malha construtiva, adotar como área mínima de proteção um espaço equivalente à altura visual da letra “S” de “SDV” em todos os lados da assinatura.

Nenhum texto, ícone, borda, fotografia ou extremidade deverá invadir essa área.

## 6.6 Tamanho mínimo

Os tamanhos definitivos dependem dos arquivos vetoriais. Provisoriamente:

- assinatura completa em tela: largura mínima de 120 px;
- assinatura institucional completa em tela: largura mínima de 140 px;
- símbolo isolado: mínimo de 24 px;
- abaixo desses limites, usar a versão simplificada aprovada.

O critério principal é a legibilidade do nome e dos complementos.

## 6.7 Fundos

Aplicações preferenciais:

- versão clara ou branca sobre azul-marinho;
- versão azul-marinho ou colorida sobre branco;
- símbolo colorido sobre superfície clara neutra;
- assinatura monocromática apenas quando exigida pelo meio.

Não aplicar diretamente sobre fotografias sem área de contraste controlada.

---

# 7. Usos indevidos da marca

É proibido:

- distorcer horizontal ou verticalmente;
- rotacionar;
- alterar proporções entre símbolo, nome e complemento;
- redesenhar o símbolo;
- substituir as cores por gradientes não aprovados;
- aplicar sombras, contornos, brilhos ou efeitos decorativos;
- utilizar baixa resolução quando houver alternativa adequada;
- remover partes da assinatura completa sem adotar versão oficial;
- colocar sobre fundo com contraste insuficiente;
- inserir o nome do cliente dentro do logotipo do produto;
- combinar o símbolo com tipografia não aprovada;
- usar o logotipo como botão ou indicador de estado;
- animar continuamente a marca em telas operacionais;
- utilizar cores de sucesso, alerta ou erro como cor do logotipo.

---

# 8. Sistema cromático

## 8.1 Natureza dos valores

As imagens disponíveis são arquivos rasterizados e não contêm especificação de cor. Os valores abaixo são **referências cromáticas iniciais aproximadas**, derivadas visualmente das telas, e deverão ser confirmados a partir dos arquivos mestres no Design System.

Não devem ser tratados como prova de correspondência para impressão.

## 8.2 Cores institucionais

| Token provisório | Hex aproximado | Função |
|---|---|---|
| `brand.navy.900` | `#001C3D` | fundo institucional, menu lateral e áreas de alta solidez |
| `brand.navy.800` | `#002A58` | variação de superfície escura |
| `brand.blue.700` | `#0759B8` | azul primário de produto |
| `brand.blue.600` | `#0867D1` | ações primárias, item ativo e destaques |
| `brand.blue.500` | `#1788E8` | detalhes luminosos do símbolo e foco visual |
| `brand.cyan.400` | `#21A6E8` | acentos tecnológicos e variações do símbolo |
| `brand.white` | `#FFFFFF` | assinatura clara e superfícies |

## 8.3 Cores neutras

| Token provisório | Hex aproximado | Função |
|---|---|---|
| `neutral.950` | `#09162D` | texto de maior ênfase |
| `neutral.800` | `#263750` | texto principal secundário |
| `neutral.600` | `#66748A` | texto de apoio |
| `neutral.400` | `#A9B4C3` | texto desabilitado e elementos discretos |
| `neutral.300` | `#D5DDE8` | bordas |
| `neutral.200` | `#E5EAF1` | divisores e estados leves |
| `neutral.100` | `#F2F5F9` | fundos alternativos |
| `neutral.050` | `#F8FAFC` | fundo geral |
| `neutral.000` | `#FFFFFF` | cartões e áreas de conteúdo |

## 8.4 Cores semânticas

| Token provisório | Hex aproximado | Significado |
|---|---|---|
| `success.700` | `#168A3C` | texto e ícone de sucesso |
| `success.600` | `#20A447` | ação positiva e confirmação |
| `success.100` | `#E8F7EC` | fundo de sucesso |
| `warning.700` | `#B66B00` | texto de alerta |
| `warning.500` | `#F2A000` | ação de atenção ou pendência |
| `warning.100` | `#FFF4D9` | fundo de alerta |
| `danger.700` | `#C62D2D` | texto e ícone de erro |
| `danger.600` | `#E33B35` | negativa e ação destrutiva restrita |
| `danger.100` | `#FDE9E7` | fundo de erro ou negativa |
| `info.700` | `#0759B8` | texto informativo |
| `info.100` | `#EAF3FF` | fundo informativo |
| `special.purple` | `#7B3FF2` | informação temporária ou categoria específica, quando prevista |

## 8.5 Proporção recomendada

Em telas administrativas:

- neutros e branco devem ocupar a maior área;
- azul-marinho organiza navegação e identidade;
- azul primário destaca ações, seleção e informação;
- verde, amarelo e vermelho ficam reservados a significado semântico;
- roxo ou outras cores de categoria devem ser usados de forma limitada.

## 8.6 Regras de aplicação

- uma cor semântica não poderá ser o único meio de comunicar estado;
- texto, ícone ou rótulo deve acompanhar sucesso, alerta, pendência e erro;
- vermelho não deverá ser usado em ações neutras;
- verde deverá representar confirmação, validade, sucesso ou liberação;
- amarelo ou âmbar deverá representar espera, atenção ou continuidade posterior;
- azul deverá representar navegação, informação ou ação primária não destrutiva;
- cores de marcas de clientes não deverão modificar esses significados;
- combinações deverão passar por validação de contraste no Design System.

---

# 9. Tipografia

## 9.1 Direção tipográfica

As referências utilizam tipografia sem serifa, contemporânea, de alta legibilidade e com diferenciação clara de pesos.

Como os arquivos rasterizados não permitem comprovar a família utilizada, a fonte oficial permanece pendente. O Design System deverá confirmar a licença e definir:

- família primária;
- família de fallback;
- pesos disponíveis;
- escala;
- altura de linha;
- espaçamento;
- comportamento responsivo.

## 9.2 Características obrigatórias

A família selecionada deverá:

- possuir excelente legibilidade em telas;
- suportar integralmente português brasileiro;
- disponibilizar pesos regular, medium, semibold e bold;
- diferenciar claramente letras e números em documentos, placas, valores e horários;
- funcionar em Windows, Linux, macOS, Android e iOS por fonte web ou fallback;
- possuir licença compatível com o produto;
- manter leitura adequada em tamanhos operacionais compactos.

## 9.3 Hierarquia provisória

| Nível | Uso | Característica |
|---|---|---|
| Display | materiais institucionais | forte presença, uso restrito |
| Título 1 | título principal da página | semibold ou bold |
| Título 2 | seção ou cartão principal | semibold |
| Título 3 | subseção | medium ou semibold |
| Corpo | dados e textos operacionais | regular |
| Rótulo | campos, filtros e metadados | medium |
| Auxiliar | ajuda, data, versão e observação | regular, menor ênfase |
| Dado crítico | placa, valor, status e protocolo | medium ou semibold |

## 9.4 Uso de caixa

- nomes de marca podem usar caixa alta na assinatura;
- títulos de telas devem preferir caixa normal;
- rótulos e botões devem seguir capitalização consistente;
- textos longos em caixa alta são proibidos;
- siglas como CPF, RG, LPR, OCR e API permanecem em caixa alta;
- nomes próprios devem respeitar a grafia cadastrada.

---

# 10. Iconografia

## 10.1 Estilo

As referências adotam ícones:

- lineares;
- geométricos;
- de espessura uniforme;
- com cantos levemente arredondados;
- acompanhados por texto quando a ação não for universal;
- inseridos em áreas claras ou sobre o menu escuro com contraste adequado.

## 10.2 Princípios

- um mesmo conceito deverá usar o mesmo ícone em todo o sistema;
- ícones não deverão substituir rótulos em operações críticas;
- o estado ativo poderá combinar ícone, fundo e texto;
- tamanhos e espessuras deverão ser definidos como tokens;
- ícones de terceiros deverão pertencer a uma única família compatível;
- ícones de fabricante não deverão ser confundidos com módulos do núcleo.

## 10.3 Ações críticas

Os ícones de:

- exclusão;
- remoção;
- bloqueio;
- negativa;
- liberação;
- fechamento de caixa;
- alteração de permissão

deverão possuir rótulo, confirmação e significado coerente com as regras de histórico.

O ícone de lixeira observado nas referências não autoriza exclusão destrutiva. No MVP, deverá representar inativação, encerramento, desvinculação rastreável ou descarte permitido pela política aplicável.

---

# 11. Fotografia, retratos e imagens operacionais

## 11.1 Retratos de pessoas

Fotos faciais deverão:

- priorizar enquadramento frontal;
- apresentar iluminação suficiente;
- evitar fundos visualmente carregados;
- manter proporção consistente;
- preservar a aparência natural;
- evitar filtros estéticos;
- possuir qualidade adequada ao uso operacional autorizado;
- ser exibidas somente a perfis com necessidade.

## 11.2 Documentos

Imagens de documentos deverão:

- ser legíveis;
- manter proporção sem deformação;
- indicar frente e verso quando aplicável;
- evitar exposição fora do contexto de validação;
- utilizar miniaturas protegidas;
- ocultar ou reduzir dados quando a visualização completa não for necessária.

## 11.3 Veículos e placas

Imagens de veículos deverão:

- favorecer a identificação da placa e do veículo;
- exibir marcação de leitura sem encobrir caracteres;
- distinguir imagem capturada de ilustração;
- informar data, hora, câmera e confiança quando operacionais;
- manter vínculo evidente com os dados reconhecidos.

## 11.4 Imagens institucionais

A prancha institucional utiliza elementos de:

- tecnologia;
- malha digital;
- rosto humano estilizado;
- veículo;
- portaria;
- fundo azul-marinho.

Esse repertório poderá ser utilizado em materiais institucionais, desde que não substitua dados reais por imagens decorativas em telas operacionais.

---

# 12. Linguagem visual da interface

## 12.1 Estrutura recorrente

As referências estabelecem:

- menu lateral azul-marinho;
- marca no topo ou na base da navegação;
- fundo geral branco ou cinza muito claro;
- conteúdo em cartões brancos;
- bordas claras e cantos suavemente arredondados;
- cabeçalhos com contexto, data, hora, usuário e situação operacional;
- azul para seleção e ações primárias;
- indicadores semânticos com texto e cor;
- ações principais agrupadas no final da jornada.

## 12.2 Densidade

A interface deve ser informativa sem parecer congestionada.

Para isso:

- dados relacionados devem ser agrupados;
- títulos devem separar etapas;
- informações críticas devem permanecer visíveis;
- detalhes secundários podem usar expansão controlada;
- espaços em branco devem organizar, não desperdiçar área operacional;
- tabelas devem manter alinhamento e leitura rápida;
- ações de maior risco devem ficar visualmente distintas.

## 12.3 Navegação

O menu lateral deverá:

- exibir somente itens permitidos;
- destacar o item atual por fundo, cor, ícone e texto;
- organizar módulos em grupos;
- manter nomenclatura estável;
- permitir adaptação responsiva sem alterar a arquitetura da informação;
- evitar menus excessivamente profundos.

## 12.4 Cartões e painéis

Cartões deverão:

- reunir uma única finalidade principal;
- utilizar título claro;
- evitar sombras excessivas;
- adotar borda sutil;
- manter alinhamento com a grade;
- destacar estados críticos sem dominar toda a tela;
- permitir comparação rápida entre dado capturado, cadastrado e validado.

## 12.5 Botões

| Tipo | Cor predominante | Uso |
|---|---|---|
| Primário | azul ou verde conforme contexto | ação principal segura ou confirmação |
| Secundário | branco, borda neutra ou azul | voltar, visualizar, ação alternativa |
| Atenção | âmbar | salvar pendente ou continuar depois |
| Negativo | vermelho | negar ou operação de alto impacto |
| Desabilitado | neutro claro | ação temporariamente indisponível |

Uma tela não deverá apresentar múltiplas ações com o mesmo peso visual quando apenas uma for principal.

## 12.6 Estados

Os estados observados incluem:

- ativo;
- aprovado;
- liberado;
- sincronizado;
- aguardando;
- pendente;
- divergente;
- negado;
- rejeitado;
- expirado;
- inativo;
- falha.

Todo estado deverá combinar:

```text
cor semântica + rótulo textual + ícone quando útil
```

---

# 13. Acessibilidade e inclusão

O sistema deverá buscar, no mínimo:

- contraste compatível com WCAG 2.1 nível AA para textos e controles aplicáveis;
- foco de teclado visível;
- navegação sem dependência exclusiva de mouse;
- áreas de toque adequadas no pré-cadastro móvel;
- rótulos persistentes em campos;
- mensagens de erro próximas ao problema e compreensíveis;
- informação de estado não dependente apenas de cor;
- ordem de leitura lógica;
- alternativas textuais para imagens funcionais;
- zoom sem perda de informação essencial;
- respeito ao nome social;
- linguagem sem termos discriminatórios;
- não exposição desnecessária de deficiência, condição de saúde ou dado sensível.

A marca tecnológica não deverá justificar animações, contrastes extremos ou efeitos que prejudiquem a operação contínua.

---

# 14. Voz e tom

## 14.1 Voz da marca

A voz do SDV Access é:

- clara;
- objetiva;
- respeitosa;
- segura;
- orientada à ação;
- transparente sobre resultados e falhas.

## 14.2 Tom por contexto

| Contexto | Tom |
|---|---|
| Operação normal | direto e informativo |
| Sucesso | confirmativo, sem celebração excessiva |
| Pendência | orientador e específico |
| Negativa | firme, respeitoso e sem julgamento |
| Falha técnica | transparente, acionável e sem simular sucesso |
| Segurança | preciso, sem alarmismo |
| Pré-cadastro público | acolhedor, simples e explicativo |
| Administração | técnico na medida necessária |

## 14.3 Padrões de escrita

- preferir voz ativa;
- nomear a ação e o resultado;
- evitar códigos técnicos para usuários finais;
- informar como corrigir quando possível;
- não atribuir culpa ao usuário;
- usar frases curtas em telas operacionais;
- preservar termos definidos no domínio;
- diferenciar claramente “salvo”, “aprovado”, “autorizado”, “enviado” e “liberado”.

## 14.4 Exemplos

| Evitar | Preferir |
|---|---|
| Erro 500 | Não foi possível concluir a operação. Tente novamente ou acione o suporte. |
| Usuário inválido | Usuário ou senha não conferem. |
| Cadastro realizado e acesso liberado | Cadastro salvo. A autorização de acesso ainda será validada. |
| Equipamento acionado | Comando enviado. Aguardando confirmação do equipamento. |
| Deletar morador | Encerrar vínculo |
| Placa errada | A placa capturada diverge da placa cadastrada. Confira os dados. |
| Acesso recusado | Entrada negada. Informe o motivo para concluir. |

---

# 15. Aplicações da marca

## 15.1 Aplicação web

Na plataforma:

- a marca do produto deve ser prioritária;
- a marca institucional pode aparecer como endosso;
- a implantação deve aparecer como contexto configurável;
- o logotipo não deve competir com a tarefa;
- o menu lateral deve preservar a identidade escura observada;
- a tela pública pode usar composição mais acolhedora, mantendo a mesma família visual.

## 15.2 Login

O login deverá conter:

- assinatura do SDV Access;
- identificação da implantação quando aplicável;
- campos e ações com contraste adequado;
- recuperação segura de acesso;
- informações institucionais discretas;
- nenhum dado operacional antes da autenticação.

## 15.3 Pré-cadastro público

O pré-cadastro poderá apresentar imagem institucional ou da implantação, desde que:

- não prejudique o carregamento móvel;
- possua autorização de uso;
- mantenha leitura e contraste;
- não exponha detalhes de segurança física;
- preserve a marca SDV Access e a identificação do destino.

## 15.4 Documentos e relatórios

Relatórios deverão conter, conforme finalidade:

- marca do produto;
- implantação;
- título;
- período;
- data e hora de emissão;
- usuário emissor quando aplicável;
- classificação ou aviso de confidencialidade;
- paginação;
- marca institucional em posição secundária.

## 15.5 E-mail e notificações

Comunicações futuras deverão:

- identificar claramente o SDV Access e a implantação;
- evitar solicitar senha;
- utilizar links seguros e temporários;
- manter linguagem conforme a seção 14;
- preservar remetente e canais oficiais;
- possuir versão textual quando aplicável.

## 15.6 Favicon e ícone

O favicon deverá utilizar versão oficial simplificada do símbolo. Não deverá ser produzido recortando arbitrariamente uma imagem rasterizada das referências.

---

# 16. Personalização por implantação

## 16.1 Elementos configuráveis

Poderão ser configuráveis:

- nome da implantação;
- logotipo institucional do cliente em área definida;
- fotografia de recepção ou local;
- contatos;
- textos legais;
- canais de suporte;
- rodapé;
- informações operacionais;
- parâmetros de acesso.

## 16.2 Elementos protegidos

Não deverão ser alterados sem decisão de produto:

- nome e assinatura principal do SDV Access;
- arquitetura visual central;
- significado das cores semânticas;
- ícones de ações críticas;
- hierarquia de estados;
- padrões de acessibilidade;
- nomenclatura das entidades do domínio;
- identificação de propriedade da Soluções do Vale;
- comportamento das jornadas aprovadas.

## 16.3 Coexistência de marcas

Quando a marca do cliente for exibida:

- não deverá ser fundida ao logotipo do SDV Access;
- deverá possuir área própria;
- deverá manter proporções originais;
- não deverá ultrapassar visualmente a marca principal sem regra comercial aprovada;
- deverá respeitar fundo, contraste e área de proteção;
- não poderá alterar cores de estado do sistema.

---

# 17. Governança da marca

## 17.1 Responsabilidades

| Papel | Responsabilidade |
|---|---|
| Product Owner | aprovar posicionamento, nomenclatura e mudanças de identidade |
| Soluções do Vale | custodiar arquivos mestres e usos institucionais |
| UX/UI | aplicar Brand Book e transformar decisões em especificações |
| Desenvolvimento | utilizar tokens e ativos aprovados, sem recriação |
| Qualidade | verificar aderência visual, textual e acessível |
| Implantação | aplicar somente personalizações autorizadas |

## 17.2 Controle de ativos

Ativos oficiais deverão:

- permanecer versionados;
- possuir origem identificada;
- incluir formatos vetoriais e rasterizados adequados;
- evitar múltiplas cópias sem identificação;
- registrar substituições;
- possuir nomes de arquivo estáveis;
- indicar versões para fundo claro, escuro, monocromático e símbolo.

## 17.3 Mudanças

Alterações estruturais de marca deverão:

1. indicar motivação;
2. identificar referências afetadas;
3. atualizar este documento;
4. atualizar Design System e UX/UI;
5. fornecer novos ativos;
6. revisar critérios de homologação;
7. preservar histórico.

---

# 18. Critérios de aceite do Brand Book

Este documento poderá ser aprovado quando:

1. a arquitetura entre Soluções do Vale, SDV Access e Santa Rita estiver confirmada;
2. a forma oficial do nome estiver aceita;
3. propósito, posicionamento e atributos representarem o produto;
4. regras de assinatura e coexistência de marcas estiverem claras;
5. a direção cromática estiver aderente às referências;
6. as cores aproximadas estiverem reconhecidas como provisórias;
7. a direção tipográfica estiver aceita;
8. iconografia, fotografia e linguagem visual refletirem as telas;
9. voz, tom e exemplos forem adequados à operação;
10. acessibilidade fizer parte da identidade;
11. personalizações não descaracterizarem o produto;
12. pendências estiverem atribuídas ao próximo documento ou decisão.

A homologação de interfaces deverá ocorrer posteriormente contra os ativos mestres, os tokens do Design System e as especificações UX/UI.

## 18.1 Registro de aprovação

| Papel | Nome | Decisão | Data | Observações |
|---|---|---|---|---|
| Product Owner | Vinicius Velasco de Azevedo | Aprovado | 28/07/2026 | Brand Book aprovado como referência para o Design System |
| Soluções do Vale | Representante designado | Ciente | 28/07/2026 | Aprovação registrada no repositório oficial |

---

# 19. Pendências abertas

| PEN-BR | Pendência | Impacto | Encaminhamento |
|---|---|---|---|
| PEN-BR-001 | Obter logotipos oficiais em SVG, PDF vetorial ou formato mestre equivalente | Impede especificar construção e reprodução exata | Soluções do Vale fornecer ativos |
| PEN-BR-002 | Confirmar versões oficiais das assinaturas institucional e do produto | Afeta login, navegação, documentos e materiais | Aprovação do Product Owner |
| PEN-BR-003 | Definir malha, proporções, área de proteção e tamanhos mínimos exatos | Afeta consistência de aplicação | Especificação com base no vetor |
| PEN-BR-004 | Confirmar códigos oficiais de cor em RGB, HEX, CMYK e Pantone, quando aplicável | Valores atuais são aproximados | Extrair dos arquivos mestres |
| PEN-BR-005 | Identificar e licenciar a família tipográfica oficial | Afeta Design System e renderização | Decisão de UX/UI e validação jurídica |
| PEN-BR-006 | Definir família oficial de ícones | Afeta consistência dos módulos e ações | Design System |
| PEN-BR-007 | Aprovar o descritor oficial do produto | Há variação entre “plataforma” e “sistema inteligente” | Decisão do Product Owner |
| PEN-BR-008 | Confirmar o uso de “Tecnologia” e “Soluções do Vale” nas assinaturas reduzidas | Há variações nas referências | Normalizar ativos oficiais |
| PEN-BR-009 | Definir regras comerciais para co-branding e eventual white-label | Afeta futuras implantações | Decisão comercial e de produto |
| PEN-BR-010 | Definir níveis e limites de personalização da Santa Rita | Afeta login, pré-cadastro e relatórios | Especificação UX/UI da implantação |
| PEN-BR-011 | Substituir nomes genéricos das imagens por nomes documentais estáveis | Afeta rastreabilidade dos ativos | Curadoria do repositório |
| PEN-BR-012 | Decidir se a imagem institucional da portaria pode ser usada publicamente | Afeta direito de uso e segurança física | Validação de propriedade e privacidade |
| PEN-BR-013 | Confirmar se o símbolo do SDV Access é idêntico ao institucional ou uma variação própria | Afeta arquitetura de marca | Inspeção dos arquivos mestres |
| PEN-BR-014 | Definir requisitos de impressão e materiais físicos, se necessários | Não demonstrado nas referências | Levantamento de aplicações |

---

# 20. Decisões consolidadas

Ficam consolidadas para o próximo documento:

- Soluções do Vale Tecnologia é a marca proprietária;
- SDV Access é a marca do produto;
- Santa Rita é uma implantação do produto;
- azul-marinho, azul de ação, branco e neutros claros formam a base visual;
- verde, âmbar e vermelho possuem função semântica;
- a linguagem é segura, tecnológica, clara e profissional;
- a interface utiliza navegação lateral escura e superfícies de conteúdo claras;
- estados não dependem exclusivamente de cor;
- o ícone de remoção não autoriza exclusão destrutiva;
- a marca do cliente deverá coexistir sem ser fundida à marca do produto;
- os valores cromáticos permanecem provisórios até obtenção dos arquivos mestres;
- tipografia, ícones e especificações exatas serão formalizados no Design System;
- as referências aprovadas não poderão ser substituídas por um tema genérico.

---

# 21. Próximo documento

Após a aprovação deste Brand Book, deverá ser produzido:

**`docs/003_DESIGN_SYSTEM.md`**

O Design System deverá transformar estas diretrizes em:

- tokens oficiais de cor;
- tipografia e escala;
- espaçamento e grade;
- raios, bordas e elevação;
- iconografia;
- botões e campos;
- tabelas, cartões e navegação;
- estados, alertas e feedback;
- padrões responsivos;
- acessibilidade;
- critérios de implementação em Blade e Livewire;
- catálogo de componentes e exemplos de uso.

Nenhum token provisório deste documento deverá ser convertido em valor definitivo sem a revisão das pendências aplicáveis.

---

## Situação do documento

Este Brand Book consolida a identidade observável nas referências oficiais e encontra-se **aprovado**. As lacunas que exigem arquivos mestres ou decisão de marca permanecem registradas e deverão ser tratadas no Design System ou pela governança responsável, sem invalidar esta aprovação documental.
