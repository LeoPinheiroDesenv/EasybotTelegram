# Estrutura Laravel 12 - Replicação do Backend2

## ✅ O que foi criado

### 1. Projeto Laravel 12
- Laravel 12.38.1 instalado com sucesso
- Estrutura base criada

### 2. Dependências Instaladas
- `tymon/jwt-auth` - Autenticação JWT
- `mercadopago/dx-php` - Integração Mercado Pago
- `stripe/stripe-php` - Integração Stripe
- `longman/telegram-bot` - Integração Telegram Bot API
- `simplesoftwareio/simple-qrcode` - Geração de QR Codes

### 3. Models Criados
- ✅ `User.php` - Adaptado com JWT e campos do backend2
- ✅ `Bot.php` - Criado
- ✅ `Contact.php` - Criado
- ✅ `PaymentPlan.php` - Criado
- ✅ `PaymentCycle.php` - Criado
- ✅ `PaymentGatewayConfig.php` - Criado
- ✅ `Transaction.php` - Criado
- ✅ `Log.php` - Criado

### 4. Controllers Criados
- ✅ `AuthController.php`
- ✅ `BotController.php`
- ✅ `UserController.php`
- ✅ `ContactController.php`
- ✅ `PaymentController.php`
- ✅ `PaymentPlanController.php`
- ✅ `PaymentCycleController.php`
- ✅ `PaymentGatewayConfigController.php`
- ✅ `LogController.php`

### 5. Migrations Criadas
- ✅ Migrations base criadas para todos os models
- ⚠️ **Pendente**: Preencher as migrations com os campos corretos baseados no backend2/

### 6. Estrutura de Diretórios
- ✅ `app/Services/` - Criado
- ✅ `app/Http/Middleware/` - Criado

## 📋 Próximos Passos

### 1. Completar as Migrations

Baseado nas migrations do `backend2/migrations/`, você precisa preencher as migrations do Laravel:

#### Exemplo: `create_bots_table.php`
```php
Schema::create('bots', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->string('name');
    $table->string('token', 500);
    $table->string('telegram_group_id')->nullable();
    $table->boolean('active')->default(true);
    $table->timestamps();
    
    $table->index('user_id');
    $table->index('token');
});
```

#### Referências das migrations do backend2:
- `createTables.sql` - Tabela users
- `createBotsTable.sql` - Tabela bots
- `createContactsTable.sql` - Tabela contacts
- `createPaymentPlansTable.sql` - Tabela payment_plans
- `createPaymentCyclesTable.sql` - Tabela payment_cycles
- `createPaymentGatewayConfigsTable.sql` - Tabela payment_gateway_configs
- `createTransactionsTable.sql` - Tabela transactions
- `createLogsTable.sql` - Tabela logs
- `addTwoFactorAuth.sql` - Campos 2FA na tabela users
- `addBotSettings.sql` - Configurações adicionais dos bots
- `addWelcomeMessage.sql` - Mensagens de boas-vindas
- `addLogDetails.sql` - Detalhes dos logs
- `addPaymentCycleId.sql` - Payment cycle ID nas transações

### 2. Completar os Models

Adicionar relacionamentos e métodos baseados nos models do `backend2/src/models/`:

#### Exemplo: `Bot.php`
```php
protected $fillable = [
    'user_id',
    'name',
    'token',
    'telegram_group_id',
    'active',
    // ... outros campos
];

public function user()
{
    return $this->belongsTo(User::class);
}

public function contacts()
{
    return $this->hasMany(Contact::class);
}
```

### 3. Criar os Services

Criar os services em `app/Services/` baseados em `backend2/src/services/`:

- `AuthService.php`
- `BotService.php`
- `ContactService.php`
- `PaymentService.php`
- `PaymentPlanService.php`
- `PaymentCycleService.php`
- `PaymentGatewayConfigService.php`
- `MercadoPagoService.php`
- `StripeService.php`
- `TelegramService.php`
- `TwoFactorService.php`
- `UserService.php`
- `LogService.php`

### 4. Completar os Controllers

Adaptar a lógica dos controllers do `backend2/src/controllers/` para Laravel:

- Usar `Request` classes para validação
- Usar `Resource` classes para formatação de resposta
- Usar `try-catch` com exceptions do Laravel
- Retornar `JsonResponse`

### 5. Criar as Routes

Criar as routes em `routes/api.php` baseadas em `backend2/src/routes/`:

```php
Route::prefix('api')->group(function () {
    // Auth routes
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/verify-2fa', [AuthController::class, 'verifyTwoFactor']);
    Route::get('/auth/me', [AuthController::class, 'getCurrentUser'])->middleware('auth:api');
    
    // Bot routes
    Route::middleware('auth:api')->group(function () {
        Route::apiResource('bots', BotController::class);
        Route::post('/bots/{id}/initialize', [BotController::class, 'initialize']);
        Route::post('/bots/{id}/stop', [BotController::class, 'stop']);
        Route::get('/bots/{id}/status', [BotController::class, 'status']);
    });
    
    // ... outras routes
});
```

### 6. Criar Middleware

- `AuthenticateToken.php` - Baseado em `backend2/src/middlewares/auth.js`
- `RequestLogger.php` - Baseado em `backend2/src/middlewares/logger.js`

### 7. Configurar .env

Adicionar as variáveis de ambiente necessárias:

```env
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=bottelegram_db
DB_USERNAME=postgres
DB_PASSWORD=postgres123

JWT_SECRET=seu_jwt_secret_aqui

MERCADOPAGO_ACCESS_TOKEN=
MERCADOPAGO_WEBHOOK_URL=

STRIPE_SECRET_KEY=
STRIPE_PUBLIC_KEY=
STRIPE_WEBHOOK_SECRET=
```

### 8. Criar Dockerfile

Criar um Dockerfile para Laravel baseado no padrão Laravel:

```dockerfile
FROM php:8.3-fpm

# Instalar dependências
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    postgresql-client

# Instalar extensões PHP
RUN docker-php-ext-install pdo_pgsql mbstring exif pcntl bcmath gd

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .

RUN composer install --no-dev --optimize-autoloader

EXPOSE 8000

CMD php artisan serve --host=0.0.0.0 --port=8000
```

### 9. Atualizar docker-compose.yml

Atualizar o serviço backend no `docker-compose.yml` para usar o novo Dockerfile do Laravel.

## 📚 Recursos Úteis

- [Laravel 12 Documentation](https://laravel.com/docs/12.x)
- [JWT Auth for Laravel](https://jwt-auth.readthedocs.io/)
- [Laravel Migrations](https://laravel.com/docs/12.x/migrations)
- [Laravel Eloquent](https://laravel.com/docs/12.x/eloquent)

## 🔄 Mapeamento de Estrutura

| Backend2 (Node.js) | Backend (Laravel) |
|-------------------|-------------------|
| `src/models/` | `app/Models/` |
| `src/controllers/` | `app/Http/Controllers/` |
| `src/services/` | `app/Services/` |
| `src/routes/` | `routes/api.php` |
| `src/middlewares/` | `app/Http/Middleware/` |
| `src/config/` | `config/` |
| `migrations/*.sql` | `database/migrations/*.php` |

## ⚠️ Notas Importantes

1. **Eloquent vs Query Builder**: O Laravel usa Eloquent ORM, que é diferente das queries SQL diretas do Node.js
2. **Validação**: Use Form Requests do Laravel em vez de express-validator
3. **Respostas**: Use Resources do Laravel para formatar respostas JSON
4. **Exceptions**: Use as exceptions do Laravel (ModelNotFoundException, ValidationException, etc.)
5. **Autenticação**: JWT Auth já está configurado, use `auth:api` middleware

