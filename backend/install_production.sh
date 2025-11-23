#!/bin/bash

# =====================================================
# Script de Instalação para Produção
# EasyBot Telegram - Production Installation
# =====================================================

echo "=========================================="
echo "Instalação em Produção - EasyBot Telegram"
echo "=========================================="
echo ""

# Diretório do projeto (ajuste conforme necessário)
PROJECT_DIR="/home1/hg291905/public_html/api"

if [ ! -d "$PROJECT_DIR" ]; then
    echo "❌ Erro: Diretório do projeto não encontrado: $PROJECT_DIR"
    echo "Por favor, ajuste a variável PROJECT_DIR no script"
    exit 1
fi

cd "$PROJECT_DIR" || exit 1

echo "📦 Instalando dependências do Composer..."
composer install --no-dev --optimize-autoloader --no-interaction

if [ $? -ne 0 ]; then
    echo "❌ Erro ao instalar dependências!"
    exit 1
fi

echo ""
echo "📦 Verificando pacote Google2FA..."
if ! composer show | grep -q "pragmarx/google2fa"; then
    echo "📦 Instalando pacote Google2FA..."
    composer require pragmarx/google2fa:^9.0 --no-interaction
    
    if [ $? -ne 0 ]; then
        echo "❌ Erro ao instalar Google2FA!"
        exit 1
    fi
else
    echo "✅ Pacote Google2FA já está instalado"
fi

echo ""
echo "🔄 Atualizando autoload..."
composer dump-autoload -o

if [ $? -ne 0 ]; then
    echo "❌ Erro ao atualizar autoload!"
    exit 1
fi

echo ""
echo "🧹 Limpando cache do Laravel..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

echo ""
echo "✅ Instalação concluída com sucesso!"
echo ""
echo "Próximos passos:"
echo "1. Verifique as permissões: chmod -R 755 storage bootstrap/cache"
echo "2. Execute as migrations: php artisan migrate --force"
echo "3. Crie o usuário admin: php fix_admin_password.php"

