#!/usr/bin/env bash
set -e

LOCAL_ROOT="$(cd "$(dirname "$0")" && pwd)"
REMOTE_USER="hg291905"
REMOTE_HOST="69.6.212.196"
REMOTE_PORT="22"
REMOTE_PATH="public_html"

# 0) Build frontend
echo "Building frontend..."
cd "${LOCAL_ROOT}/frontend"
npm run build

# 1) build/ -> public_html/
# Nota: Os arquivos .env do frontend não são copiados para a pasta build/ pelo processo de build do React,
# portanto não precisam ser explicitamente excluídos aqui.
if [ -d "${LOCAL_ROOT}/frontend/build" ]; then
  echo "Deploying build/ to ${REMOTE_PATH}/..."
rsync -avz \
  -e "ssh -p ${REMOTE_PORT}" \
  "${LOCAL_ROOT}/frontend/build/" \
    "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_PATH}/"
else
  echo "Warning: 'build' directory not found. Skipping frontend deploy."
fi

# 2) backend/ -> public_html/api/
echo "Deploying api/ to ${REMOTE_PATH}/api/..."
rsync -avz \
  --exclude 'vendor/' \
  --exclude '.composer/' \
  --exclude '.env.local' \
  --exclude '.env.production' \
  --exclude '.env.development' \
  --exclude '.env' \
  -e "ssh -p ${REMOTE_PORT}" \
  "${LOCAL_ROOT}/backend/" \
  "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_PATH}/api/"
