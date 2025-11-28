#!/bin/bash

# =====================================================
# Script para Instalar Google2FA no Servidor de Produção
# EasyBot Telegram - 2FA Installation Script
# =====================================================

echo "=========================================="
echo "Instalando Google2FA no Servidor"
echo "=========================================="
echo ""

# Verifica se está no diretório correto
if [ ! -f "composer.json" ]; then
    echo "❌ Erro: composer.json não encontrado!"
    echo "   Execute este script no diretório raiz do backend (onde está o composer.json)"
    exit 1
fi

echo "📦 Verificando dependências necessárias..."
echo ""

# Verifica se o Composer está instalado
if ! command -v composer &> /dev/null; then
    echo "❌ Composer não está instalado!"
    echo "   Instale o Composer primeiro:"
    echo "   curl -sS https://getcomposer.org/installer | php"
    echo "   mv composer.phar /usr/local/bin/composer"
    exit 1
fi

echo "✅ Composer encontrado: $(composer --version)"
echo ""

# Verifica se o PHP está instalado
if ! command -v php &> /dev/null; then
    echo "❌ PHP não está instalado!"
    exit 1
fi

echo "✅ PHP encontrado: $(php -v | head -n 1)"
echo ""

# Verifica a versão do PHP
PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
REQUIRED_VERSION="8.2"

if [ "$(printf '%s\n' "$REQUIRED_VERSION" "$PHP_VERSION" | sort -V | head -n1)" != "$REQUIRED_VERSION" ]; then
    echo "⚠️  Aviso: PHP $PHP_VERSION detectado. Recomendado PHP ^8.2"
    echo "   Continuando mesmo assim..."
    echo ""
fi

echo "📦 Instalando pacotes necessários para 2FA..."
echo ""

# Instala o Google2FA
echo "1. Instalando pragmarx/google2fa..."
composer require pragmarx/google2fa:^9.0 --no-interaction

if [ $? -ne 0 ]; then
    echo "❌ Erro ao instalar pragmarx/google2fa"
    exit 1
fi

echo "✅ pragmarx/google2fa instalado com sucesso!"
echo ""

# Verifica se o SimpleSoftwareIO QR Code está instalado
if ! composer show | grep -q "simplesoftwareio/simple-qrcode"; then
    echo "2. Instalando simplesoftwareio/simple-qrcode..."
    composer require simplesoftwareio/simple-qrcode:^4.2 --no-interaction
    
    if [ $? -ne 0 ]; then
        echo "❌ Erro ao instalar simplesoftwareio/simple-qrcode"
        exit 1
    fi
    
    echo "✅ simplesoftwareio/simple-qrcode instalado com sucesso!"
    echo ""
else
    echo "✅ simplesoftwareio/simple-qrcode já está instalado"
    echo ""
fi

# Otimiza o autoload
echo "🔄 Otimizando autoload..."
composer dump-autoload --optimize --no-interaction

if [ $? -ne 0 ]; then
    echo "⚠️  Aviso: Erro ao otimizar autoload, mas continuando..."
fi

echo ""

# Limpa cache do Laravel (se artisan estiver disponível)
if [ -f "artisan" ]; then
    echo "🧹 Limpando cache do Laravel..."
    php artisan config:clear 2>/dev/null || true
    php artisan cache:clear 2>/dev/null || true
    php artisan route:clear 2>/dev/null || true
    echo "✅ Cache limpo!"
    echo ""
fi

# Verifica se os pacotes foram instalados corretamente
echo "🔍 Verificando instalação..."
echo ""

if composer show | grep -q "pragmarx/google2fa"; then
    echo "✅ pragmarx/google2fa: INSTALADO"
    composer show | grep "pragmarx/google2fa"
else
    echo "❌ pragmarx/google2fa: NÃO ENCONTRADO"
fi

echo ""

if composer show | grep -q "simplesoftwareio/simple-qrcode"; then
    echo "✅ simplesoftwareio/simple-qrcode: INSTALADO"
    composer show | grep "simplesoftwareio/simple-qrcode"
else
    echo "❌ simplesoftwareio/simple-qrcode: NÃO ENCONTRADO"
fi

echo ""
echo "=========================================="
echo "✅ Instalação concluída!"
echo "=========================================="
echo ""
echo "📝 Próximos passos:"
echo "   1. Teste o endpoint de 2FA: GET /api/auth/2fa/setup"
echo "   2. Verifique os logs se houver algum problema"
echo "   3. Certifique-se de que o servidor tem permissões corretas"
echo ""

