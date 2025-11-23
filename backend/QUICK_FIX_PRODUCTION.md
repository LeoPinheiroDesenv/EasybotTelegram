# Correção Rápida - Erro Google2FA em Produção

## Erro
```
Class "PragmaRX\Google2FA\Google2FA" not found
```

## Solução Rápida

Execute os seguintes comandos no servidor de produção:

```bash
# 1. Acessar o diretório do projeto
cd /home1/hg291905/public_html/api

# 2. Instalar o pacote Google2FA
composer require pragmarx/google2fa:^9.0 --no-interaction

# 3. Atualizar autoload
composer dump-autoload -o

# 4. Limpar cache do Laravel
php artisan config:clear
php artisan cache:clear
```

## Solução Completa (Recomendada)

```bash
# Executar script de instalação
cd /home1/hg291905/public_html/api
bash install_production.sh
```

Ou manualmente:

```bash
cd /home1/hg291905/public_html/api

# Instalar todas as dependências
composer install --no-dev --optimize-autoloader --no-interaction

# Instalar Google2FA se não estiver instalado
composer require pragmarx/google2fa:^9.0 --no-interaction

# Atualizar autoload
composer dump-autoload -o

# Limpar todos os caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

## Verificação

Após instalar, verifique se está funcionando:

```bash
php -r "require 'vendor/autoload.php'; echo class_exists('PragmaRX\Google2FA\Google2FA') ? 'OK' : 'ERRO';"
```

Deve retornar: `OK`

## Se ainda não funcionar

1. **Verifique se o Composer está atualizado:**
   ```bash
   composer self-update
   ```

2. **Verifique permissões:**
   ```bash
   chmod -R 755 storage bootstrap/cache
   chown -R www-data:www-data storage bootstrap/cache
   ```

3. **Verifique se o pacote está no composer.json:**
   ```bash
   grep "google2fa" composer.json
   ```

4. **Reinstale todas as dependências:**
   ```bash
   rm -rf vendor
   composer install --no-dev --optimize-autoloader
   ```

