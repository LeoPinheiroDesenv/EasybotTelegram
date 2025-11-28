#!/bin/bash

# =====================================================
# Script para Copiar Vendor do Container Docker
# Para fazer upload via FTP para o servidor de produção
# =====================================================

echo "=========================================="
echo "Copiando pasta vendor do container Docker"
echo "=========================================="
echo ""

CONTAINER_NAME="bottelegram_backend"
SOURCE_PATH="/var/www/vendor"
DEST_PATH="./vendor_from_docker"

# Verifica se o container está rodando
if ! docker ps | grep -q "$CONTAINER_NAME"; then
    echo "❌ Erro: Container $CONTAINER_NAME não está rodando!"
    echo "   Execute: docker-compose up -d"
    exit 1
fi

echo "✅ Container $CONTAINER_NAME encontrado"
echo ""

# Verifica se a pasta vendor existe no container
if ! docker exec $CONTAINER_NAME test -d "$SOURCE_PATH"; then
    echo "❌ Erro: Pasta $SOURCE_PATH não existe no container!"
    exit 1
fi

echo "✅ Pasta vendor encontrada no container"
echo ""

# Remove pasta de destino se existir
if [ -d "$DEST_PATH" ]; then
    echo "🗑️  Removendo pasta de destino existente..."
    rm -rf "$DEST_PATH"
fi

# Cria pasta de destino
mkdir -p "$DEST_PATH"

echo "📦 Copiando pasta vendor do container..."
echo "   Isso pode levar alguns minutos (pasta tem ~83MB)..."
echo ""

# Copia a pasta vendor do container
docker cp "${CONTAINER_NAME}:${SOURCE_PATH}" "$DEST_PATH"

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ Pasta vendor copiada com sucesso!"
    echo ""
    echo "📁 Localização: $(pwd)/$DEST_PATH/vendor"
    echo ""
    
    # Verifica se os pacotes necessários estão presentes
    echo "🔍 Verificando pacotes necessários..."
    echo ""
    
    if [ -d "$DEST_PATH/vendor/pragmarx/google2fa" ]; then
        echo "✅ pragmarx/google2fa encontrado"
    else
        echo "❌ pragmarx/google2fa NÃO encontrado"
    fi
    
    if [ -d "$DEST_PATH/vendor/simplesoftwareio/simple-qrcode" ]; then
        echo "✅ simplesoftwareio/simple-qrcode encontrado"
    else
        echo "❌ simplesoftwareio/simple-qrcode NÃO encontrado"
    fi
    
    echo ""
    echo "=========================================="
    echo "✅ Pronto para upload via FTP!"
    echo "=========================================="
    echo ""
    echo "📋 Próximos passos:"
    echo "   1. Conecte-se ao servidor via FTP"
    echo "   2. Navegue até: /home1/hg291905/public_html/api/"
    echo "   3. Faça backup da pasta vendor existente (se houver)"
    echo "   4. Faça upload da pasta: $DEST_PATH/vendor/"
    echo "   5. Certifique-se de que TODAS as subpastas foram transferidas"
    echo ""
    echo "📊 Tamanho da pasta:"
    du -sh "$DEST_PATH/vendor" 2>/dev/null || echo "Não foi possível calcular"
    echo ""
else
    echo ""
    echo "❌ Erro ao copiar pasta vendor do container"
    exit 1
fi

