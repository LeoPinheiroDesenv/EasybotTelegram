# Instalação do Pacote Google2FA

O pacote `pragmarx/google2fa` é necessário para a funcionalidade de autenticação de dois fatores (2FA).

## Problema

Se você receber o erro:
```
Class "PragmaRX\Google2FA\Google2FA" not found
```

Isso significa que o pacote não está instalado ou o autoload não foi atualizado.

## Solução

### Opção 1: Instalar via Composer (Recomendado)

```bash
# 1. Instalar o pacote
docker-compose exec backend composer require pragmarx/google2fa:^9.0

# 2. Atualizar autoload
docker-compose exec backend composer dump-autoload -o

# 3. Verificar se está funcionando
docker-compose exec backend php fix_google2fa.php
```

### Opção 2: Usar o script de instalação

```bash
bash backend/install_dependencies.sh
```

### Opção 3: Reinstalar todas as dependências

```bash
# Reinstalar todas as dependências
docker-compose exec backend composer install --no-interaction

# Atualizar autoload
docker-compose exec backend composer dump-autoload -o
```

## Verificação

Após instalar, verifique se está funcionando:

```bash
docker-compose exec backend php fix_google2fa.php
```

Você deve ver:
```
✅ Classe Google2FA encontrada!
✅ Instância criada com sucesso!
✅ Método generateSecretKey() funcionando!
✅ Pacote Google2FA está funcionando corretamente!
```

## Se ainda não funcionar

1. **Verifique se o pacote está no composer.json:**
   ```bash
   grep -i "google2fa" backend/composer.json
   ```

2. **Verifique se está instalado:**
   ```bash
   docker-compose exec backend composer show | grep google2fa
   ```

3. **Limpe o cache do Composer:**
   ```bash
   docker-compose exec backend composer clear-cache
   docker-compose exec backend composer install --no-interaction
   docker-compose exec backend composer dump-autoload -o
   ```

4. **Reconstrua o container:**
   ```bash
   docker-compose build backend
   docker-compose up -d backend
   ```

## Versão do Pacote

O projeto usa a versão `^9.0` do `pragmarx/google2fa`, que é compatível com PHP 8.2+ e Laravel 12.

