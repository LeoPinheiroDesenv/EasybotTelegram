# Correção: Lazy Loading para TwoFactorService

## Problema

O erro `Google2FA package is not installed` estava ocorrendo em produção porque o `TwoFactorService` estava sendo injetado no construtor do `AuthService` e `AuthController`, causando a tentativa de instanciação mesmo quando o pacote não estava instalado.

## Solução Implementada

Foi implementado **lazy loading** para o `TwoFactorService`:

1. **Removida a injeção de dependência do construtor** - O `TwoFactorService` não é mais injetado automaticamente
2. **Criado método `getTwoFactorService()`** - Instancia o serviço apenas quando necessário
3. **Verificação de disponibilidade** - Verifica se a classe `Google2FA` existe antes de tentar instanciar

## Benefícios

- ✅ **Funciona sem o pacote instalado** - O login funciona normalmente se o usuário não tiver 2FA ativado
- ✅ **Erro claro quando necessário** - Se o usuário tiver 2FA ativado e o pacote não estiver instalado, retorna erro 503 com mensagem clara
- ✅ **Melhor performance** - O serviço só é instanciado quando realmente necessário

## Arquivos Modificados

1. `app/Services/AuthService.php`
   - Removida injeção de `TwoFactorService` do construtor
   - Adicionado método `getTwoFactorService()` com lazy loading

2. `app/Http/Controllers/AuthController.php`
   - Removida injeção de `TwoFactorService` do construtor
   - Adicionado método `getTwoFactorService()` com lazy loading
   - Todos os métodos que usam 2FA agora verificam se o serviço está disponível

## Comportamento

### Sem pacote Google2FA instalado:
- ✅ Login funciona normalmente para usuários sem 2FA
- ❌ Login falha com erro claro para usuários com 2FA ativado
- ❌ Endpoints de 2FA retornam erro 503

### Com pacote Google2FA instalado:
- ✅ Tudo funciona normalmente
- ✅ Login com 2FA funciona
- ✅ Endpoints de 2FA funcionam

## Próximos Passos

Para habilitar 2FA completamente em produção, instale o pacote:

```bash
cd /home1/hg291905/public_html/api
composer require pragmarx/google2fa:^9.0 --no-interaction
composer dump-autoload -o
php artisan config:clear
php artisan cache:clear
```

