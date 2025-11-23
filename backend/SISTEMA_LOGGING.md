# Sistema de Logging - EasyBot Telegram

## 📋 Visão Geral

O sistema de logging foi implementado para salvar automaticamente todos os logs da aplicação no banco de dados, permitindo rastreamento completo de ações, erros e requisições.

## ✅ Funcionalidades Implementadas

### 1. **Log Handler Customizado**
- **Arquivo**: `app/Logging/DatabaseLogHandler.php`
- **Função**: Intercepta todos os logs do Laravel e salva no banco de dados
- **Níveis suportados**: info, warning, error, critical, debug

### 2. **LogService**
- **Arquivo**: `app/Services/LogService.php`
- **Função**: Helper para facilitar o uso de logs no código
- **Métodos disponíveis**:
  - `LogService::log($message, $level, $context, $botId)`
  - `LogService::info($message, $context, $botId)`
  - `LogService::warning($message, $context, $botId)`
  - `LogService::error($message, $context, $botId)`
  - `LogService::critical($message, $context, $botId)`

### 3. **Middleware de Requisições HTTP**
- **Arquivo**: `app/Http/Middleware/LogHttpRequests.php`
- **Função**: Captura automaticamente todas as requisições HTTP e salva no banco
- **Configurável via**: `LOG_HTTP_REQUESTS` no `.env`

### 4. **Exception Handler**
- **Arquivo**: `bootstrap/app.php`
- **Função**: Captura exceções não tratadas e salva no banco de dados

## 🔧 Configuração

### Variáveis de Ambiente

Adicione ao arquivo `.env`:

```env
# Nível mínimo de log (debug, info, warning, error, critical)
LOG_LEVEL=info

# Canais de log (single,database = arquivo + banco)
LOG_STACK=single,database

# Habilitar logging de requisições HTTP (true/false)
LOG_HTTP_REQUESTS=true
```

### Configuração do Logging

O canal `database` foi adicionado ao arquivo `config/logging.php` e está incluído no stack padrão.

## 📝 Como Usar

### 1. Usando o LogService (Recomendado)

```php
use App\Services\LogService;

// Log simples
LogService::info('Usuário fez login', ['user_id' => 1]);

// Log com bot_id
LogService::error('Erro ao processar mensagem', ['error' => $e->getMessage()], $botId);

// Log de aviso
LogService::warning('Tentativa de acesso não autorizado', ['ip' => request()->ip()]);
```

### 2. Usando o Facade do Laravel

```php
use Illuminate\Support\Facades\Log;

// Log simples (será salvo automaticamente no banco)
Log::info('Mensagem de log');

// Log com contexto (incluindo bot_id)
Log::error('Erro no bot', [
    'bot_id' => 1,
    'error' => $e->getMessage(),
    'user_email' => auth()->user()->email ?? null,
    'ip_address' => request()->ip()
]);
```

### 3. Logs Automáticos

Os seguintes logs são salvos automaticamente:

- ✅ **Requisições HTTP**: Todas as requisições são logadas (exceto health checks e webhooks)
- ✅ **Exceções**: Todas as exceções não tratadas são logadas
- ✅ **Logs do TelegramService**: Ações dos bots são logadas automaticamente
- ✅ **Logs do Laravel**: Todos os logs do Laravel são salvos no banco

## 🗄️ Estrutura da Tabela `logs`

```sql
- id: BIGINT (Primary Key)
- bot_id: BIGINT (Foreign Key para bots, nullable)
- level: VARCHAR(50) (info, warning, error, critical, debug)
- message: TEXT (Mensagem do log)
- context: JSON (Contexto adicional)
- details: TEXT (Detalhes adicionais)
- user_email: VARCHAR (Email do usuário que gerou o log)
- ip_address: VARCHAR (IP de origem)
- created_at: TIMESTAMP
- updated_at: TIMESTAMP
```

## 📊 Consultando Logs

### Via API

```bash
# Listar todos os logs
GET /api/logs

# Filtrar por nível
GET /api/logs?level=error

# Filtrar por data
GET /api/logs?startDate=2025-01-01&endDate=2025-01-31

# Paginação
GET /api/logs?limit=50&offset=0
```

### Via Código

```php
use App\Models\Log;

// Buscar logs de erro
$errorLogs = Log::where('level', 'error')->get();

// Buscar logs de um bot específico
$botLogs = Log::where('bot_id', 1)->get();

// Buscar logs recentes
$recentLogs = Log::orderBy('created_at', 'desc')->limit(100)->get();
```

## 🔍 Exemplos de Uso

### Exemplo 1: Log de Ação do Usuário

```php
use App\Services\LogService;

public function updateBot(Request $request, $id)
{
    try {
        $bot = Bot::findOrFail($id);
        $bot->update($request->all());
        
        LogService::info('Bot atualizado com sucesso', [
            'bot_id' => $bot->id,
            'changes' => $request->all()
        ], $bot->id);
        
        return response()->json(['message' => 'Bot atualizado']);
    } catch (\Exception $e) {
        LogService::error('Erro ao atualizar bot', [
            'bot_id' => $id,
            'error' => $e->getMessage()
        ], $id);
        
        return response()->json(['error' => 'Erro ao atualizar'], 500);
    }
}
```

### Exemplo 2: Log de Erro com Contexto

```php
use Illuminate\Support\Facades\Log;

try {
    // código que pode gerar erro
} catch (\Exception $e) {
    Log::error('Erro ao processar requisição', [
        'bot_id' => $botId,
        'exception' => get_class($e),
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'user_email' => auth()->user()->email ?? null,
        'ip_address' => request()->ip()
    ]);
}
```

## ⚙️ Desabilitar Logging de Requisições HTTP

Se você quiser desabilitar o logging automático de requisições HTTP (por exemplo, em produção com muito tráfego), adicione ao `.env`:

```env
LOG_HTTP_REQUESTS=false
```

## 🐛 Troubleshooting

### Logs não estão sendo salvos

1. **Verifique se a tabela `logs` existe**:
   ```bash
   php artisan migrate:status
   ```

2. **Verifique se o canal `database` está configurado**:
   ```bash
   php artisan config:show logging.channels.database
   ```

3. **Limpe o cache de configuração**:
   ```bash
   php artisan config:clear
   ```

4. **Verifique os logs do Laravel**:
   ```bash
   tail -f storage/logs/laravel.log
   ```

### Performance

Se você estiver tendo problemas de performance devido ao volume de logs:

1. Desabilite o logging de requisições HTTP:
   ```env
   LOG_HTTP_REQUESTS=false
   ```

2. Aumente o nível mínimo de log:
   ```env
   LOG_LEVEL=warning
   ```

3. Use apenas o canal `single` (arquivo) em vez de `database`:
   ```env
   LOG_STACK=single
   ```

## 📚 Referências

- [Laravel Logging Documentation](https://laravel.com/docs/logging)
- [Monolog Documentation](https://github.com/Seldaek/monolog)

