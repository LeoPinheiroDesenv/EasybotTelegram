# Correção de Timeout na API do Telegram

## ✅ Problema Resolvido

**Problema Original**: Erro de timeout ao inicializar bot - `cURL error 28: Operation timed out after 10000 milliseconds`

**Causa**: Timeout muito curto (10 segundos) para requisições à API do Telegram, especialmente em conexões mais lentas ou quando a API do Telegram está com latência alta.

## 🔧 Correções Implementadas

### 1. **Timeout Configurável**
- Timeout padrão aumentado de **10 segundos** para **30 segundos**
- Configurável via variável de ambiente `TELEGRAM_API_TIMEOUT`
- Aplicado em todas as requisições à API do Telegram

### 2. **Sistema de Retry Automático**
- Implementado retry automático com 3 tentativas
- Delay de 2 segundos entre tentativas
- Tratamento específico para erros de timeout e conexão

### 3. **Métodos Helper**
- Criado método `http()` em `TelegramService` e `TelegramWebhookController`
- Centraliza configuração de timeout e retry
- Facilita manutenção futura

### 4. **Melhor Tratamento de Erros**
- Mensagens de erro mais descritivas
- Logs detalhados de tentativas
- Diferenciação entre erros de timeout e outros erros

## 📝 Configuração

### Variável de Ambiente

Adicione ao arquivo `.env`:

```env
# Timeout para requisições à API do Telegram (em segundos)
# Padrão: 30 segundos
TELEGRAM_API_TIMEOUT=30
```

### Valores Recomendados

- **Desenvolvimento**: 30 segundos (padrão)
- **Produção com conexão estável**: 30-45 segundos
- **Produção com conexão instável**: 60 segundos

## 🔍 Arquivos Modificados

### `backend/app/Services/TelegramService.php`
- ✅ Método `getTimeout()` - obtém timeout configurável
- ✅ Método `http()` - cria instância HTTP com timeout e retry
- ✅ Método `validateToken()` - implementado retry com 3 tentativas
- ✅ Todos os métodos HTTP atualizados para usar `$this->http()`

### `backend/app/Http/Controllers/TelegramWebhookController.php`
- ✅ Método `getTimeout()` - obtém timeout configurável
- ✅ Método `http()` - cria instância HTTP com timeout e retry
- ✅ Todos os métodos HTTP atualizados para usar `$this->http()`

## 📊 Melhorias Implementadas

### Antes
```php
Http::timeout(10)->get("https://api.telegram.org/bot{$token}/getMe");
```

### Depois
```php
$this->http()->get("https://api.telegram.org/bot{$token}/getMe");
// Timeout: 30 segundos (configurável)
// Retry: 2 tentativas automáticas
```

## 🧪 Comportamento do Retry

1. **Tentativa 1**: Requisição inicial
2. **Se falhar com timeout**: Aguarda 2 segundos
3. **Tentativa 2**: Segunda tentativa
4. **Se falhar novamente**: Aguarda 2 segundos
5. **Tentativa 3**: Terceira tentativa
6. **Se falhar**: Retorna erro com mensagem descritiva

## ⚙️ Exemplo de Uso

### Configurar Timeout Personalizado

```env
# .env
TELEGRAM_API_TIMEOUT=60
```

### Verificar Timeout Atual

```php
$timeout = env('TELEGRAM_API_TIMEOUT', 30);
echo "Timeout configurado: {$timeout} segundos";
```

## ✅ Resultado

- ✅ Timeout aumentado de 10 para 30 segundos (configurável)
- ✅ Retry automático implementado (3 tentativas)
- ✅ Melhor tratamento de erros de conexão
- ✅ Mensagens de erro mais descritivas
- ✅ Logs detalhados de tentativas
- ✅ Código mais limpo e manutenível

## 🔍 Troubleshooting

### Ainda recebendo timeout?

1. **Aumente o timeout**:
   ```env
   TELEGRAM_API_TIMEOUT=60
   ```

2. **Verifique conectividade**:
   ```bash
   curl -I https://api.telegram.org
   ```

3. **Verifique firewall/proxy**:
   - Certifique-se de que o servidor pode acessar `api.telegram.org`
   - Verifique se há proxy configurado

4. **Verifique logs**:
   ```bash
   tail -f storage/logs/laravel.log
   ```

## 📚 Referências

- [Laravel HTTP Client Documentation](https://laravel.com/docs/http-client)
- [Telegram Bot API](https://core.telegram.org/bots/api)

