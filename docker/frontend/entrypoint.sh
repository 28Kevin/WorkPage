#!/bin/bash
set -e

cd /app

if [ ! -f .env ]; then
  echo "==> Creando .env a partir de .env.example"
  cp .env.example .env
fi

if [ ! -d node_modules/vite ]; then
  echo "==> Instalando dependencias de npm (puede tardar la primera vez)…"
  npm install --no-audit --no-fund
fi

echo "==> Frontend listo en http://localhost:${FRONTEND_PORT:-5173}"

exec "$@"
