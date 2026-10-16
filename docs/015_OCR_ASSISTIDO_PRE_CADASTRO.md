# OCR assistido no pré-cadastro

**Documento:** SDV-UXP-015  
**Situação:** Em desenvolvimento — entrega 1  
**Responsáveis:** Lucas Pastorelli + Codex  
**Data:** 15 de outubro de 2026

## 1. Objetivo

Permitir que a pessoa fotografe RG/CIN ou CNH durante o pré-cadastro e receba sugestões de preenchimento a partir do conteúdo do documento.

O sistema deverá tentar extrair:

- nome completo;
- CPF;
- número do documento;
- data de nascimento;
- fotografia existente no documento.

O resultado será sempre uma sugestão. A pessoa deverá conferir e poderá corrigir cada campo antes do envio.

## 2. Limites desta função

- OCR significa leitura automática do texto presente na imagem.
- OCR não comprova autenticidade do documento.
- localizar e recortar a fotografia não cria uma credencial biométrica.
- a fotografia do documento não substitui uma selfie recente.
- comparação facial automática não faz parte desta entrega.
- nenhum resultado autoriza ou libera entrada.
- falha ou indisponibilidade do leitor não impede o preenchimento manual.

## 3. Fluxo aprovado para desenvolvimento

1. escolher RG/CIN ou CNH;
2. enviar a frente do documento;
3. enviar o verso quando houver informações adicionais;
4. proteger as imagens fora da área pública;
5. solicitar a leitura ao processador autorizado;
6. mostrar o estado do processamento;
7. apresentar valor sugerido e grau de confiança por campo;
8. permitir correção humana;
9. exigir selfie separada;
10. preservar valor extraído e valor confirmado para auditoria;
11. encaminhar o pré-cadastro para análise da portaria.

## 4. Divisão das entregas

| Entrega | Conteúdo | Situação |
|---|---|---|
| 1 — Captura estruturada | Tipo do documento, frente obrigatória, verso opcional e armazenamento privado separado | 🟡 Em desenvolvimento |
| 2 — Leitura de texto | Integração com processador autorizado e extração de nome, CPF, número e nascimento | ⚪ Planejada |
| 3 — Conferência | Sugestões, confiança, correção e confirmação explícita | ⚪ Planejada |
| 4 — Foto do documento | Localização e recorte privado da fotografia para comparação visual | ⚪ Planejada |
| 5 — Homologação | Testes com amostras autorizadas de RG/CIN e CNH, erros, desempenho e acessibilidade | ⚪ Planejada |

## 5. Dados e segurança

- arquivos continuam usando o armazenamento privado criado no AJ-001;
- frente, verso, selfie e eventual recorte facial são objetos diferentes;
- nomes, CPF e números de documento não aparecem no endereço interno do arquivo;
- logs não armazenam imagem nem documento integral;
- processamento registra horário, versão, resultado e confiança, sem expor o conteúdo sensível;
- serviço externo não será usado sem decisão documentada sobre finalidade, retenção, contrato e localização dos dados;
- imagens não poderão ser usadas para treinamento sem autorização própria;
- o caminho manual permanecerá disponível.

## 6. Qualidade mínima

A tecnologia somente poderá ser tratada como pronta depois de um teste autorizado que meça separadamente:

- acerto por tipo e versão de documento;
- acerto de nome, CPF, número e nascimento;
- documentos inclinados, desfocados, com reflexo ou cortados;
- tempo de resposta;
- falhas técnicas;
- correções realizadas pelas pessoas;
- detecção da região da fotografia sem comparação biométrica.

Não será divulgada uma porcentagem de precisão antes dessa prova.

## 7. Decisões ainda necessárias

| Decisão | Situação |
|---|---|
| Tecnologia de OCR: biblioteca interna ou fornecedor contratado | Pendente de prova técnica |
| Metas mínimas de acerto por campo | Pendente da amostra de homologação |
| Prazo de retenção das imagens e resultados | Pendente de política de privacidade |
| Uso da fotografia apenas para conferência visual | Adotado nesta entrega |
| Comparação facial automática | Fora do escopo; depende de ADR-013 e AJ-003 |
| Equipamento BRAVAS | Fora do escopo; depende de P25 |

## 8. Critérios de aceite da entrega 1

- RG/CIN e CNH podem ser selecionados;
- a frente é obrigatória;
- o verso é opcional e armazenado separadamente;
- os formatos continuam limitados a JPG, PNG e WebP de até 8 MB;
- o formulário explica que a leitura será assistida e confirmada;
- a selfie continua sendo uma etapa separada;
- nenhuma integração externa ou reconhecimento facial é acionado;
- os testes existentes continuam válidos e há teste específico para frente e verso.

## 9. Relações

- `docs/006_UX_UI_PRE_CADASTRO.md`;
- `docs/ADR/ADR-006_ARMAZENAMENTO_S3_E_CICLO_DE_VIDA_DE_ARQUIVOS.md`;
- `docs/ADR/ADR-011_PYTHON_FASTAPI_PARA_OCR_OU_IA.md`;
- `docs/ADR/ADR-013_BIOMETRIA_E_REFERENCIAS_EXTERNAS.md`;
- AJ-001, AJ-003 e AJ-005 do documento 013.

