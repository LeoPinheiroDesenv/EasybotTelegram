# Configuração de Email - Hostgator

## Configurações do Servidor

- **Servidor SMTP:** `mail.easypagamentos.com`
- **Porta SMTP:** `465`
- **Criptografia:** `ssl` (porta 465 requer SSL, não TLS)
- **Autenticação:** Sim (obrigatória)
- **Usuário:** `recuperacao@easypagamentos.com`
- **Senha:** A senha da conta de email

## Configuração no .env

Adicione as seguintes variáveis no arquivo `.env` do backend:

```env
# Configuração de Email - Hostgator
MAIL_MAILER=smtp
MAIL_HOST=mail.easypagamentos.com
MAIL_PORT=465
MAIL_USERNAME=recuperacao@easypagamentos.com
MAIL_PASSWORD=sua-senha-aqui
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=recuperacao@easypagamentos.com
MAIL_FROM_NAME="EasyBot Telegram"

# URL do Frontend (para o link de recuperação)
FRONTEND_URL=https://seu-dominio-frontend.com
```

## Importante

1. **Porta 465 requer SSL**, não TLS. Certifique-se de usar `MAIL_ENCRYPTION=ssl`
2. **Porta 587 requer TLS**. Se a porta 465 não funcionar, tente:
   ```env
   MAIL_PORT=587
   MAIL_ENCRYPTION=tls
   ```
3. Use a senha real da conta de email `recuperacao@easypagamentos.com`
4. O endereço `MAIL_FROM_ADDRESS` deve ser o mesmo que `MAIL_USERNAME` ou um alias válido

## Teste de Configuração

Após configurar o `.env`, execute:

```bash
cd /var/www/html/EasybotTelegram/backend
php artisan config:clear
php artisan cache:clear
```

Depois, acesse no navegador (ou via curl):
```
http://seu-dominio.com/test-email?email=seu-email@exemplo.com
```

Isso irá:
- Mostrar a configuração atual
- Tentar enviar um email de teste
- Exibir erros detalhados se houver problemas

## Verificação de Logs

Os logs detalhados estão em:
```bash
tail -f /var/www/html/EasybotTelegram/backend/storage/logs/laravel.log
```

## Solução de Problemas

### Erro: "Connection could not be established"
- Verifique se o servidor `mail.easypagamentos.com` está acessível
- Verifique se a porta 465 não está bloqueada pelo firewall
- Tente usar a porta 587 com TLS

### Erro: "Authentication failed"
- Verifique se o usuário e senha estão corretos
- Certifique-se de usar a senha da conta de email, não a senha do cPanel

### Erro: "SSL certificate problem"
- Adicione `'verify_peer' => false` na configuração (já adicionado)
- Ou configure certificados SSL adequados

### Email não chega
- Verifique a pasta de spam
- Verifique se o email de destino está correto
- Verifique os logs do servidor de email na Hostgator

