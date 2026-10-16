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
```

## Limites

- o resultado é sugestão, não validação do documento;
- o serviço não reconhece nem compara rostos;
- o recorte da fotografia do documento permanece na próxima entrega;
- o fluxo manual continua disponível quando o serviço estiver desligado ou falhar;
- nenhuma imagem é enviada a fornecedor externo.

RapidOCR é um projeto com licença Apache 2.0: <https://github.com/RapidAI/RapidOCR>.
