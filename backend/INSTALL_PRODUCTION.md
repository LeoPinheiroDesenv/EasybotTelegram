# Instalação em Produção

Este guia explica como instalar as dependências do projeto em um ambiente de produção.

## Dependências Necessárias

O projeto requer os seguintes pacotes PHP via Composer:

- `pragmarx/google2fa:^9.0` - Autenticação de dois fatores
- `simplesoftwareio/simple-qrcode:^4.2` - Geração de QR codes
- `tymon/jwt-auth:^2.2` - Autenticação JWT
- `mercadopago/dx-php:^3.7` - Integração Mercado Pago
- `stripe/stripe-php:^18.2` - Integração Stripe
- `longman/telegram-bot:^0.83.1` - Integração Telegram Bot API

## Instalação

### 1. Acessar o diretório do projeto

```bash
cd /home1/hg291905/public_html/api
```

### 2. Instalar dependências do Composer

```bash
composer install --no-dev --optimize-autoloader
```

### 3. Instalar pacote Google2FA (se não estiver instalado)

```bash
composer require pragmarx/google2fa:^9.0
```

### 4. Atualizar autoload

```bash
composer dump-autoload -o
```

### 5. Limpar cache do Laravel

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

## Verificação

Para verificar se o pacote Google2FA está instalado:

```bash
php -r "require 'vendor/autoload.php'; echo class_exists('PragmaRX\Google2FA\Google2FA') ? 'Installed' : 'Not installed';"
```

## Solução Rápida

Execute este comando para instalar todas as dependências:

```bash
cd /home1/hg291905/public_html/api && \
composer require pragmarx/google2fa:^9.0 --no-interaction && \
composer dump-autoload -o && \
php artisan config:clear && \
php artisan cache:clear
```

## Problemas Comuns

### Erro: "Class not found"
- Execute: `composer install --no-dev --optimize-autoloader`
- Execute: `composer dump-autoload -o`

### Erro: "Memory limit exceeded"
- Aumente o limite de memória: `php -d memory_limit=512M composer install`

### Erro: "Permission denied"
- Verifique permissões: `chmod -R 755 storage bootstrap/cache`
- Verifique propriedade: `chown -R www-data:www-data storage bootstrap/cache`

