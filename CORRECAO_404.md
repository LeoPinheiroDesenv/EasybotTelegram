# Correção do Erro 404 em Rotas Diretas

## Problema Identificado

Ao acessar rotas diretamente no navegador (ex: `/bot/update/1`) ou recarregar a página em uma rota específica, ocorre erro 404 porque o servidor web tenta encontrar o arquivo/pasta fisicamente, mas em uma SPA (Single Page Application) React, todas as rotas devem ser redirecionadas para o `index.html`.

## Solução Implementada

Foi criado um arquivo `.htaccess` que faz o rewrite de todas as rotas para o `index.html`, permitindo que o React Router funcione corretamente.

### Arquivos Criados/Atualizados

1. ✅ `frontend/public/.htaccess` - Arquivo de configuração do Apache
2. ✅ `frontend/build/.htaccess` - Cópia do arquivo na pasta de build
3. ✅ `frontend/rebuild_production.sh` - Script atualizado para copiar automaticamente o `.htaccess`
4. ✅ `frontend/README_DEPLOY.md` - Documentação completa do deploy

## Ações Necessárias em Produção

### 1. Fazer Upload do Arquivo `.htaccess`

**IMPORTANTE:** Certifique-se de que o arquivo `.htaccess` está no diretório público do servidor web (onde estão os arquivos do `build/`).

O arquivo já está criado em:
- `frontend/build/.htaccess`

Você precisa fazer upload deste arquivo junto com os outros arquivos do build para o servidor de produção.

### 2. Verificar Permissões do Arquivo

No servidor de produção, execute:

```bash
chmod 644 .htaccess
```

### 3. Verificar Configuração do Apache

Certifique-se de que:

#### a) O módulo `mod_rewrite` está habilitado:

```bash
# Verificar
apache2ctl -M | grep rewrite

# Se não estiver habilitado, habilitar:
sudo a2enmod rewrite
sudo systemctl restart apache2
```

#### b) O `AllowOverride` está configurado no Virtual Host:

No arquivo de configuração do Apache (geralmente em `/etc/apache2/sites-available/`), certifique-se de que há:

```apache
<Directory /caminho/para/public_html>
    AllowOverride All
    Require all granted
</Directory>
```

Depois, reinicie o Apache:

```bash
sudo systemctl restart apache2
```

### 4. Testar

Após fazer as alterações:

1. Acesse diretamente: `https://seudominio.com/bot/update/1`
2. Deve carregar a página corretamente (não deve dar 404)
3. Recarregue a página (F5) - deve continuar funcionando

## Se Estiver Usando Nginx

Se o servidor usar Nginx em vez de Apache, você precisa configurar o `try_files` no arquivo de configuração do Nginx:

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

Depois, recarregue o Nginx:

```bash
sudo nginx -t  # Verificar configuração
sudo systemctl reload nginx
```

## Troubleshooting

### Erro 404 Persiste

1. ✅ Verifique se o arquivo `.htaccess` está na pasta correta do servidor
2. ✅ Verifique se o `mod_rewrite` está habilitado: `apache2ctl -M | grep rewrite`
3. ✅ Verifique se o `AllowOverride` está configurado como `All`
4. ✅ Verifique os logs do Apache: `tail -f /var/log/apache2/error.log`

### Erro 500 (Internal Server Error)

1. ✅ Verifique a sintaxe do `.htaccess`
2. ✅ Verifique os logs do Apache: `tail -f /var/log/apache2/error.log`
3. ✅ Certifique-se de que o módulo `mod_rewrite` está instalado e habilitado

### Rotas Funcionam mas Recarregar Dá Erro

Isso indica que o `.htaccess` não está funcionando. Verifique:
1. ✅ Se o arquivo está no diretório correto
2. ✅ Se as permissões estão corretas (`chmod 644 .htaccess`)
3. ✅ Se o `AllowOverride` está configurado

## Conteúdo do Arquivo .htaccess

O arquivo `.htaccess` criado contém:

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

Este arquivo:
- Ativa o módulo de rewrite
- Redireciona todas as requisições que não são arquivos ou diretórios existentes para o `index.html`
- Permite que o React Router gerencie as rotas no lado do cliente

## Próximos Passos

1. ✅ Fazer upload do arquivo `.htaccess` para o servidor de produção
2. ✅ Verificar permissões do arquivo
3. ✅ Verificar configuração do Apache/Nginx
4. ✅ Testar acessando diretamente uma rota
5. ✅ Testar recarregando a página em uma rota específica

