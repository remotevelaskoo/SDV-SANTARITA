# Leitor interno de documentos

Este serviço executa a prova técnica de OCR do AJ-005. Ele recebe imagens de RG/CIN ou CNH pela rede interna e devolve sugestões estruturadas para conferência no pré-cadastro.

## Execução local

```bash
python -m venv .venv
. .venv/bin/activate
pip install -r requirements.txt
DOCUMENT_OCR_TOKEN=troque-este-segredo uvicorn app.main:app --host 127.0.0.1 --port 8001
```

No Laravel, configure:

```dotenv
DOCUMENT_OCR_ENABLED=true
DOCUMENT_OCR_URL=http://127.0.0.1:8001
DOCUMENT_OCR_TOKEN=troque-este-segredo
DOCUMENT_OCR_TIMEOUT=15
```

O token precisa ser igual nos dois processos. Em produção, ele deverá vir do gerenciador de segredos e o serviço ficará acessível somente pela rede interna.

## Testes

```bash
pip install -r requirements-dev.txt
PYTHONPATH=. python -m unittest discover -s tests -v
# leitura real com o motor sobre documento SINTÉTICO desenhado em memória
OCR_ENGINE_TESTS=1 PYTHONPATH=. python -m unittest tests.test_engine_synthetic -v
```

Todos os testes usam imagens geradas em memória. Não coloque documento real em `tests/`.

## Diagnóstico da leitura (AJ-006)

Antes do OCR, cada imagem é orientada pelo EXIF e medida (resolução, brilho, reflexo, foco, contraste e enquadramento). Se a leitura vier pobre ou com o texto na vertical, ela é repetida com a imagem girada. A resposta traz `diagnostics`, sem texto lido nem valores:

| Campo | Conteúdo |
| --- | --- |
| `stage` | `ok`, `partial`, `capture` (arquivo inválido), `quality` (imagem ruim e nada lido), `ocr` (nenhum texto), `interpretation` (texto sem campos) |
| `issues` | `sem_texto`, `campos_nao_localizados`, `tipo_divergente`, `verso_recomendado`, `cpf_digito_invalido`, `confianca_baixa` |
| `quality_issues` | `imagem_invalida`, `resolucao_baixa`, `escura`, `clara_demais`, `reflexo`, `desfocada`, `baixo_contraste`, `possivelmente_cortado` |
| `detected_type` | `cnh`, `rg_cin` ou `null`, pelas palavras-chave do documento |
| `rotation_applied`, `exif_rotated` | lados que precisaram ser girados |
| `recapture_recommended` | se vale pedir nova foto |

Cada campo traz `value`, `confidence`, `source` e `validated` (CPF com dígito verificador correto ou data válida). O SDV converte os códigos em orientações em português e decide entre nova captura e preenchimento manual. Os limiares de qualidade em `app/quality.py` são provisórios: eles explicam um resultado ruim, mas não impedem a leitura.

Na subida, o serviço faz uma leitura de aquecimento para carregar os modelos e evitar que o primeiro documento passe do tempo limite do SDV. Desligue com `DOCUMENT_OCR_WARMUP=0`.

## Limites

- **o leitor não está homologado**: a taxa de acerto com documentos reais ainda precisa ser medida com amostras autorizadas (ver abaixo);
- o resultado é sugestão, não validação do documento;
- o serviço localiza a região de uma foto no documento, mas não reconhece nem compara rostos;
- o recorte somente é devolvido quando `include_portrait=true` e deve ser armazenado em área privada;
- o fluxo manual continua disponível quando o serviço estiver desligado ou falhar;
- nenhuma imagem é enviada a fornecedor externo.

RapidOCR é um projeto com licença Apache 2.0: <https://github.com/RapidAI/RapidOCR>.

## Preparação da homologação

As imagens reais autorizadas devem ser colocadas somente em `evaluation/samples/`. Essa pasta, o manifesto privado e os relatórios estão bloqueados pelo Git para impedir publicação acidental.

1. copie `evaluation/manifest.example.jsonl` para `evaluation/manifest.private.jsonl`;
2. substitua os dados fictícios pelas respostas esperadas das amostras autorizadas;
3. coloque as imagens na pasta privada usando nomes sem dados pessoais;
4. execute:

```bash
PYTHONPATH=. python -m evaluation.evaluate evaluation/manifest.private.jsonl
```

O relatório contém somente quantidades, acerto por campo, detecção da foto, falhas técnicas e tempo de resposta. Valores pessoais e texto extraído não são impressos nem gravados no relatório.
