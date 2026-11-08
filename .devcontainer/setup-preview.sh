#!/usr/bin/env bash
set -euo pipefail

echo "[SDV One] Preparando ambiente demonstrativo isolado..."
if ! php -m | grep -iq 'pdo_sqlite'; then
  echo "ERRO: extensao PHP pdo_sqlite ausente no container."
  exit 1
fi

composer install --no-interaction --prefer-dist --no-progress

if [ ! -f .env ]; then
  cp .env.example .env
  php artisan key:generate --force --no-interaction
fi

mkdir -p database
if [ ! -s database/database.sqlite ]; then
  touch database/database.sqlite
  php artisan migrate:fresh --seed --force
else
  php artisan migrate --force
fi

# Usa a versao de pnpm fixada no projeto, sem exigir instalacao global.
npx --yes pnpm@11.9.0 install --frozen-lockfile
npx --yes pnpm@11.9.0 run build

echo "[SDV One] Ambiente pronto. Acesse a porta 8000 (privada) na aba PORTS."
echo "[SDV One] Login demo: administrador / senha: sdv2026"
