#!/usr/bin/env bash
set -euo pipefail

if [ ! -f .env ] || [ ! -f vendor/autoload.php ]; then
  echo "[SDV One] Rode primeiro: bash .devcontainer/setup-preview.sh"
  exit 0
fi

if pgrep -f '[p]hp artisan serve --host=0.0.0.0 --port=8000' > /dev/null; then
  echo "[SDV One] Servidor ja esta na porta 8000."
else
  nohup php artisan serve --host=0.0.0.0 --port=8000 > /tmp/sdv-one-preview.log 2>&1 < /dev/null &
  echo "[SDV One] Servidor iniciado. Abra a aba PORTS e toque no link da porta 8000."
fi
