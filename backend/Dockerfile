FROM php:8.3-cli

# Instalar dependências do sistema e limpar cache
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    default-mysql-client \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mysqli mbstring exif pcntl bcmath gd opcache zip ftp \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configurar diretório de trabalho
WORKDIR /var/www

# Copiar apenas arquivos necessários primeiro (para cache do Docker)
COPY composer.json composer.lock ./

# Instalar dependências do Composer (sem dev para produção)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Copiar o resto dos arquivos
COPY . .

# Executar scripts do Composer e configurar permissões
RUN composer dump-autoload --optimize \
    && chmod -R 755 storage bootstrap/cache

# Expor porta
EXPOSE 8000

# Comando para iniciar o servidor
CMD php artisan serve --host=0.0.0.0 --port=8000

