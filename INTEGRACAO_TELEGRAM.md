# Integração com Telegram - Guia de Configuração

## Problema Identificado

O sistema estava apenas armazenando os tokens dos bots no banco de dados, mas não havia integração real com a API do Telegram para gerenciar os bots. Isso significa que mesmo configurando corretamente o bot, o sistema não estava processando mensagens ou interagindo com os usuários.

## Solução Implementada

Foi criada uma integração completa com o Telegram Bot API usando a biblioteca `node-telegram-bot-api`. Agora o sistema:

1. **Inicializa automaticamente os bots** quando são criados ou atualizados
2. **Processa mensagens** recebidas do Telegram
3. **Responde a comandos** como `/start` e `/comandos`
4. **Salva contatos** automaticamente quando usuários interagem com o bot
5. **Envia mensagens de boas-vindas** configuradas no painel

## Instalação

### 1. Instalar Dependências

Execute no diretório `backend`:

```bash
cd backend
npm install
```

Isso instalará as novas dependências:
- `node-telegram-bot-api`: Biblioteca para interagir com a API do Telegram
- `axios`: Para validação de tokens com a API do Telegram

### 2. Reiniciar o Servidor

Após instalar as dependências, reinicie o servidor backend:

```bash
# Se estiver usando Docker
docker-compose restart backend

# Ou se estiver rodando diretamente
npm start
```

## Como Funciona

### Inicialização Automática

Quando você cria ou atualiza um bot no painel:

1. O sistema valida o token com a API do Telegram
2. Se o bot estiver ativo (`active = true`), ele é automaticamente inicializado
3. O bot começa a receber e processar mensagens via polling

### Processamento de Mensagens

O sistema processa os seguintes eventos:

#### Comando `/start`
- Salva ou atualiza o contato do usuário
- Envia a mensagem de boas-vindas configurada
- Envia as mídias configuradas (se houver)

#### Comando `/comandos`
- Lista os comandos disponíveis (apenas para administradores)

#### Mensagens de Texto
- Salva ou atualiza o contato automaticamente

### Gerenciamento de Bots

#### Inicializar um Bot Manualmente

```bash
POST /api/bots/:id/initialize
Authorization: Bearer <token>
```

#### Parar um Bot

```bash
POST /api/bots/:id/stop
Authorization: Bearer <token>
```

#### Verificar Status de um Bot

```bash
GET /api/bots/:id/status
Authorization: Bearer <token>
```

Resposta:
```json
{
  "isActive": true,
  "botId": 1
}
```

## Validação de Token

A validação de token agora verifica:

1. **Formato**: Deve seguir o padrão `número:alfanumérico`
2. **Validade**: Faz uma chamada real à API do Telegram para verificar se o token é válido
3. **Informações do Bot**: Retorna informações do bot (nome, username, etc.)

## Estrutura de Arquivos

```
backend/
  src/
    services/
      telegramService.js    # Serviço principal de integração com Telegram
    controllers/
      botController.js      # Atualizado para inicializar/parar bots
    routes/
      botRoutes.js          # Novas rotas para gerenciar bots
```

## Troubleshooting

### Bot não está respondendo

1. Verifique se o bot está ativo no banco de dados:
   ```sql
   SELECT id, name, active, token FROM bots WHERE id = <bot_id>;
   ```

2. Verifique os logs do servidor:
   ```bash
   docker-compose logs backend
   ```

3. Tente inicializar o bot manualmente via API:
   ```bash
   POST /api/bots/:id/initialize
   ```

### Erro ao inicializar bot

- Verifique se o token está correto e válido
- Verifique se o bot não está bloqueado no Telegram
- Verifique se há conexão com a internet

### Bot para de funcionar após reiniciar o servidor

O sistema inicializa automaticamente todos os bots ativos ao iniciar o servidor. Se algum bot não inicializar:

1. Verifique se está marcado como `active = true` no banco
2. Verifique os logs para erros específicos
3. Tente inicializar manualmente via API

## Próximos Passos

Para melhorar ainda mais a integração, você pode:

1. **Implementar webhooks** em vez de polling (mais eficiente para produção)
2. **Adicionar mais comandos** personalizados
3. **Implementar envio de alertas** e downsells
4. **Adicionar processamento de grupos** e canais
5. **Implementar sistema de administradores** para controle de acesso

## Notas Importantes

- O sistema usa **polling** para receber mensagens, o que é adequado para desenvolvimento e pequena escala
- Para produção com muitos bots, considere implementar **webhooks**
- Os bots são inicializados automaticamente ao criar/atualizar, mas você pode controlá-los manualmente via API
- Todos os contatos são salvos automaticamente quando interagem com o bot

