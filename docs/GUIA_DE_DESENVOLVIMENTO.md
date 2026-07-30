# Guia de desenvolvimento

## Objetivo

Este guia descreve a execução local da fundação do SDV Access. As decisões
funcionais, visuais e arquiteturais permanecem nos documentos e ADRs
aprovados do repositório.

## Pré-requisitos

- Git;
- Docker Desktop com Docker Compose;
- pelo menos 8 GB de memória disponível para os containers.

PHP, Composer, Node.js, PostgreSQL, Redis e o serviço S3 compatível não
precisam ser instalados diretamente no computador.

## Serviços locais

| Serviço | Finalidade | Porta |
|---|---|---:|
| `web` | entrada HTTP por Nginx | 8080 |
| `app` | aplicação Laravel/PHP-FPM | interna |
| `queue` | processamento assíncrono | interna |
| `scheduler` | tarefas agendadas | interna |
| `postgres` | banco PostgreSQL | 5432 |
| `redis` | cache, sessão e filas | 6379 |
| `minio` | API compatível com S3 | 9000 |
| `minio` | console administrativo local | 9001 |
| `frontend` | servidor Vite | 5173 |

## Primeira execução

1. Copiar `.env.example` para `.env`.
2. Trocar todas as credenciais marcadas como locais quando o ambiente deixar
   de ser exclusivamente local.
3. Construir os containers:

   ```bash
   docker compose build
   ```

4. Gerar a chave da aplicação:

   ```bash
   docker compose run --rm app php artisan key:generate
   ```

5. Iniciar os serviços:

   ```bash
   docker compose up -d
   ```

6. Executar as migrations:

   ```bash
   docker compose exec app php artisan migrate
   ```

7. Acessar `http://localhost:8080`.

## Verificações

```bash
docker compose exec app composer test
docker compose exec app ./vendor/bin/pint --test
docker compose config
```

O endpoint `GET /health` oferece somente a informação mínima de vitalidade.
Dependências e detalhes internos não devem ser expostos publicamente.

## Restrições

- `.env` não será versionado;
- dados reais não serão copiados para o ambiente local;
- o MinIO local não substitui testes de contrato com o S3 de homologação;
- simuladores não substituem homologação com equipamentos reais;
- nenhuma tela genérica substitui as referências visuais aprovadas;
- migrations de domínio devem respeitar UUIDv7, histórico, auditoria e
  `implantacao_id`, conforme os ADRs.
