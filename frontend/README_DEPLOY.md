# Guia de Deploy - Frontend

## Problema: Erro 404 em Rotas Diretas

Quando você acessa uma rota diretamente no navegador (ex: `/bot/update/1`) ou recarrega a página em uma rota específica, o servidor web tenta encontrar esse arquivo/pasta fisicamente no servidor. Como o React é uma SPA (Single Page Application), todas as rotas devem ser redirecionadas para o `index.html`.

## Solução

Foi criado um arquivo `.htaccess` que faz o rewrite de todas as rotas para o `index.html`, permitindo que o React Router funcione corretamente.

### Arquivo `.htaccess`

O arquivo `.htaccess` está localizado em:
- `frontend/public/.htaccess` (será copiado automaticamente no build)
- `frontend/build/.htaccess` (criado após o build)

### Conteúdo do `.htaccess`

```apache
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteBase /
  RewriteRule ^index\.html$ - [L]
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteRule . /index.html [L]
</IfModule>
```

## Como Fazer o Deploy

### 1. Compilar o Frontend

Execute o script de rebuild:

```bash
cd frontend
bash rebuild_production.sh
```

Ou manualmente:

```bash
cd frontend
npm install
# Certifique-se de que o arquivo .env existe com REACT_APP_API_URL configurada
npm run build
cp public/.htaccess build/.htaccess
```

**IMPORTANTE:** Certifique-se de que o arquivo `.env` na raiz do frontend contém:
```
REACT_APP_API_URL=http://0.0.0.0:8000/api
```

### 2. Fazer Upload para o Servidor

Faça upload de **todos os arquivos** da pasta `build/` para o diretório público do servidor web (geralmente `public_html/` ou `www/`).

**IMPORTANTE:** Certifique-se de incluir o arquivo `.htaccess` no upload!

### 3. Verificar Permissões

No servidor, certifique-se de que o arquivo `.htaccess` tem as permissões corretas:

```bash
chmod 644 .htaccess
```

### 4. Verificar Configuração do Apache

Certifique-se de que o módulo `mod_rewrite` está habilitado no Apache:

```bash
# Verificar se está habilitado
apache2ctl -M | grep rewrite

# Se não estiver habilitado, habilitar:
sudo a2enmod rewrite
sudo systemctl restart apache2
```

### 5. Verificar Configuração do Virtual Host

Se estiver usando Apache, certifique-se de que o `AllowOverride` está configurado para permitir `.htaccess`:

```apache
<Directory /caminho/para/public_html>
    AllowOverride All
    Require all granted
</Directory>
```

## Testando

Após o deploy, teste acessando diretamente uma rota:

1. Acesse: `https://seudominio.com/bot/update/1`
2. Deve carregar a página corretamente (não deve dar 404)
3. Recarregue a página (F5) - deve continuar funcionando

## Troubleshooting

### Erro 404 Persiste

1. Verifique se o arquivo `.htaccess` está na pasta correta do servidor
2. Verifique se o `mod_rewrite` está habilitado
3. Verifique se o `AllowOverride` está configurado como `All`
4. Verifique os logs do Apache para erros

### Erro 500 (Internal Server Error)

1. Verifique a sintaxe do `.htaccess`
2. Verifique os logs do Apache: `tail -f /var/log/apache2/error.log`
3. Certifique-se de que o módulo `mod_rewrite` está instalado e habilitado

### Rotas Funcionam mas Recarregar Dá Erro

Isso indica que o `.htaccess` não está funcionando. Verifique:
1. Se o arquivo está no diretório correto
2. Se as permissões estão corretas
3. Se o `AllowOverride` está configurado

## Para Nginx

Se estiver usando Nginx em vez de Apache, você precisa configurar o `try_files` no arquivo de configuração:

```nginx
server {
    listen 80;
    server_name seudominio.com;
    root /caminho/para/build;
    index index.html;

    location / {
        try_files $uri $uri/ /index.html;
    }
}
```

