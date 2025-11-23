#!/bin/bash

# =====================================================
# Script para Recompilar Frontend para Produção
# EasyBot Telegram - Frontend Production Rebuild
# =====================================================

echo "=========================================="
echo "Recompilando Frontend para Produção"
echo "=========================================="
echo ""

# Diretório do frontend
cd "$(dirname "$0")" || exit 1

echo "📦 Limpando build anterior..."
rm -rf build

echo ""
echo "📦 Instalando dependências (se necessário)..."
npm install

echo ""
echo "🔨 Compilando frontend para produção..."
echo "📋 Usando REACT_APP_API_URL do arquivo .env..."
if [ ! -f ".env" ]; then
    echo "⚠️  Arquivo .env não encontrado! Criando com valor padrão..."
    echo "REACT_APP_API_URL=http://0.0.0.0:8000/api" > .env
fi
npm run build

if [ $? -eq 0 ]; then
    echo ""
    echo "📋 Copiando arquivo .htaccess para build..."
    if [ -f "public/.htaccess" ]; then
        cp public/.htaccess build/.htaccess
        echo "✅ Arquivo .htaccess copiado com sucesso!"
    else
        echo "⚠️  Arquivo .htaccess não encontrado em public/"
    fi
    
    echo ""
    echo "✅ Build concluído com sucesso!"
    echo ""
    echo "📁 Arquivos compilados estão em: ./build/"
    echo ""
    echo "⚠️  IMPORTANTE: Faça o deploy dos arquivos da pasta 'build/' para o servidor de produção"
    echo "⚠️  Certifique-se de que o arquivo .htaccess está incluído no deploy"
    echo ""
else
    echo ""
    echo "❌ Erro ao compilar o frontend!"
    exit 1
fi

