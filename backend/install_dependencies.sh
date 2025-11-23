#!/bin/bash

# =====================================================
# Script para instalar dependências do Composer
# EasyBot Telegram - Install Dependencies
# =====================================================

echo "=========================================="
echo "Instalando dependências do Composer..."
echo "=========================================="
echo ""

# Verificar se o container está rodando
if ! docker-compose ps backend | grep -q "Up"; then
    echo "❌ Erro: Container do backend não está rodando!"
    echo "Execute: docker-compose up -d"
    exit 1
fi

echo "📦 Instalando pacotes do Composer..."
docker-compose exec backend composer install --no-interaction

if [ $? -eq 0 ]; then
    echo ""
    echo "📦 Instalando pacote Google2FA..."
    docker-compose exec backend composer require pragmarx/google2fa:^9.0 --no-interaction
    
    if [ $? -eq 0 ]; then
        echo ""
        echo "🔄 Atualizando autoload..."
        docker-compose exec backend composer dump-autoload -o
        
        echo ""
        echo "✅ Dependências instaladas com sucesso!"
    else
        echo ""
        echo "❌ Erro ao instalar Google2FA!"
        exit 1
    fi
else
    echo ""
    echo "❌ Erro ao instalar dependências!"
    exit 1
fi

