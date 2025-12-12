# Configuração Automática de Cron Jobs no cPanel

## Visão Geral

O sistema agora suporta criação automática de cron jobs no cPanel quando você cria, atualiza ou remove cron jobs na aplicação. Isso elimina a necessidade de configurar manualmente os cron jobs no cPanel.

## Configuração Inicial

### 1. Obter Token de API do cPanel

1. Acesse seu cPanel
2. Vá em **"Preferências"** > **"Gerenciar API Tokens"** ou **"API Tokens"**
3. Clique em **"Criar Token"** ou **"Create Token"**
4. Dê um nome descritivo (ex: "EasybotTelegram Cron Jobs")
5. Selecione as permissões necessárias:
   - **Cron** (acesso completo)
6. Clique em **"Criar"** ou **"Create"**
7. **Copie o token** (você só verá ele uma vez!)

### 2. Configurar Variáveis de Ambiente

Adicione as seguintes variáveis no arquivo `.env` do backend:

```env
# Configuração do cPanel para criação automática de cron jobs
CPANEL_HOST=seu-dominio.com
CPANEL_USERNAME=seu_usuario_cpanel
CPANEL_API_TOKEN=seu_token_api_aqui
CPANEL_PORT=2083
CPANEL_USE_SSL=true
```

**Explicação das variáveis:**

- `CPANEL_HOST`: O domínio do seu servidor cPanel (sem http:// ou https://)
- `CPANEL_USERNAME`: Seu nome de usuário do cPanel
- `CPANEL_API_TOKEN`: O token de API que você criou no passo anterior
- `CPANEL_PORT`: Porta do cPanel (2083 para HTTP, 2087 para HTTPS - geralmente 2083)
- `CPANEL_USE_SSL`: `true` para usar HTTPS, `false` para HTTP (recomendado `true`)

### 3. Executar Migration

Execute a migration para adicionar o campo `cpanel_cron_id` na tabela:

```bash
php artisan migrate
```

## Como Funciona

### Criação Automática

Quando você cria um cron job na aplicação:

1. O cron job é salvo no banco de dados
2. Se o cPanel estiver configurado e o cron job estiver ativo:
   - O sistema gera o comando curl/wget automaticamente
   - Cria o cron job no cPanel via API
   - Salva o ID do cron job do cPanel (`cpanel_cron_id`) no banco de dados

### Atualização Automática

Quando você atualiza um cron job:

- **Se desativar**: Remove automaticamente do cPanel
- **Se ativar**: Cria automaticamente no cPanel
- **Se mudar frequência/endpoint/método**: Atualiza automaticamente no cPanel

### Remoção Automática

Quando você remove um cron job:

- Remove automaticamente do cPanel antes de deletar do banco de dados

## Testando a Conexão

Na tela de Cron Jobs, você pode testar a conexão com o cPanel usando o endpoint:

```
POST /api/cron-jobs/test-cpanel
```

Ou via interface, adicione um botão "Testar Conexão cPanel" na tela de Cron Jobs.

## Tratamento de Erros

O sistema foi projetado para ser resiliente:

- **Se o cPanel não estiver configurado**: Os cron jobs são criados apenas no banco de dados (comportamento normal)
- **Se houver erro ao criar no cPanel**: O cron job é criado no banco de dados mesmo assim, mas com aviso
- **Se o cPanel estiver offline**: O sistema continua funcionando normalmente, apenas sem sincronização

## Logs

Todos os eventos relacionados ao cPanel são registrados nos logs do Laravel:

```bash
tail -f storage/logs/laravel.log | grep -i cpanel
```

## Exemplo de Uso

1. **Configure o cPanel** (variáveis no .env)
2. **Execute a migration**: `php artisan migrate`
3. **Acesse a tela de Cron Jobs** no admin
4. **Crie um cron job** ou clique em "Criar Padrão" para os cron jobs do sistema
5. **Verifique no cPanel**: Vá em "Cron Jobs" no cPanel e veja os cron jobs criados automaticamente

## Troubleshooting

### Erro: "cPanel não está configurado"

- Verifique se todas as variáveis estão no `.env`
- Verifique se o `.env` foi recarregado (execute `php artisan config:clear`)

### Erro: "Token inválido"

- Verifique se o token está correto
- Verifique se o token não expirou
- Crie um novo token se necessário

### Erro: "Erro HTTP ao criar cron job"

- Verifique se o `CPANEL_HOST` está correto
- Verifique se a porta está correta (2083 para HTTP, 2087 para HTTPS)
- Verifique se `CPANEL_USE_SSL` está correto
- Verifique se o usuário tem permissão para criar cron jobs

### Cron jobs não aparecem no cPanel

- Verifique os logs: `storage/logs/laravel.log`
- Teste a conexão usando o endpoint de teste
- Verifique se o `cpanel_cron_id` está sendo salvo no banco de dados

### Sincronização Manual

Se precisar sincronizar manualmente cron jobs existentes:

1. Edite o cron job na aplicação
2. Salve (mesmo sem mudanças)
3. O sistema tentará criar/atualizar no cPanel

## Segurança

- **Nunca compartilhe seu token de API**
- **Use HTTPS** (`CPANEL_USE_SSL=true`)
- **Mantenha o token seguro** no `.env` (não commite no Git)
- **Revogue tokens antigos** se suspeitar de comprometimento

## Limitações

- Requer acesso à API do cPanel (disponível na maioria dos hosts)
- Requer permissões de API Token com acesso a Cron
- Alguns hosts podem ter limitações na API do cPanel

## Suporte

Se encontrar problemas:

1. Verifique os logs do Laravel
2. Teste a conexão com o cPanel
3. Verifique as configurações no `.env`
4. Consulte a documentação da API do cPanel do seu host
