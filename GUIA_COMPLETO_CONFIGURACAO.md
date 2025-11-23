# Guia Completo de Configuração - EasyBot Telegram

Este guia explica passo a passo como configurar completamente um bot do Telegram, desde a criação até a configuração de administradores e grupos.

---

## 📋 Índice

1. [Criar o Bot no Telegram](#1-criar-o-bot-no-telegram)
2. [Criar e Configurar o Grupo](#2-criar-e-configurar-o-grupo)
3. [Relacionar o Bot com o Grupo](#3-relacionar-o-bot-com-o-grupo)
4. [Configurar o Bot no Sistema](#4-configurar-o-bot-no-sistema)
5. [Criar Administradores do Bot](#5-criar-administradores-do-bot)
6. [Validar Configuração](#6-validar-configuração)
7. [Troubleshooting](#7-troubleshooting)

---

## 1. Criar o Bot no Telegram

### Passo 1.1: Abrir o BotFather

1. Abra o aplicativo Telegram no seu celular ou computador
2. Na barra de pesquisa, digite: `@BotFather`
3. Clique no resultado oficial do BotFather (verificado com ✓)

### Passo 1.2: Iniciar Conversa

1. Clique em **"Iniciar"** ou **"Start"** para começar a conversa
2. O BotFather enviará uma mensagem de boas-vindas com os comandos disponíveis

### Passo 1.3: Criar Novo Bot

1. Digite o comando: `/newbot`
2. O BotFather perguntará: **"Alright, a new bot. How are we going to call it? Please choose a name for your bot."**
3. Digite um nome para o bot (exemplo: "Meu Bot de Vendas")
4. O BotFather perguntará: **"Good. Now let's choose a username for your bot. It must end in `bot`. Like this, for example: TetrisBot or tetris_bot."**
5. Digite um username único que termine com `bot` (exemplo: `meu_bot_vendas_bot`)
   - ⚠️ **Importante**: O username deve ser único e terminar com `bot`
   - Se o username já existir, o BotFather pedirá outro

### Passo 1.4: Obter o Token

1. Após criar o bot com sucesso, o BotFather enviará uma mensagem como:
   ```
   Done! Congratulations on your new bot. You will find it at t.me/meu_bot_vendas_bot.
   
   Use this token to access the HTTP API:
   123456789:ABCdefGHIjklMNOpqrsTUVwxyz
   
   Keep your token secure and store it safely, it can be used by anyone to control your bot.
   ```

2. **Copie o token** (exemplo: `123456789:ABCdefGHIjklMNOpqrsTUVwxyz`)
   - ⚠️ **IMPORTANTE**: Guarde este token com segurança! Ele será necessário para configurar o bot no sistema

### Passo 1.5: Configurações Adicionais (Opcional)

Você pode personalizar seu bot com os seguintes comandos:

- `/setdescription` - Define uma descrição para o bot
- `/setabouttext` - Define um texto "Sobre" para o bot
- `/setuserpic` - Define uma foto de perfil para o bot
- `/setcommands` - Define comandos que aparecem quando o usuário digita `/`

**Exemplo de comandos personalizados:**
```
start - Iniciar conversa com o bot
help - Ver ajuda
comandos - Listar comandos disponíveis
```

---

## 2. Criar e Configurar o Grupo

### Passo 2.1: Criar o Grupo

1. No Telegram, clique no ícone de **"Nova conversa"** (lápis ou +)
2. Selecione **"Novo grupo"** ou **"New Group"**
3. Escolha os participantes iniciais (você pode adicionar apenas você mesmo)
4. Digite um nome para o grupo (exemplo: "Grupo VIP - Meu Bot")
5. Clique em **"Criar"** ou **"Create"**

### Passo 2.2: Configurar o Grupo

1. Abra as configurações do grupo (clique no nome do grupo no topo)
2. Vá em **"Administradores"** ou **"Administrators"**
3. Adicione o bot como administrador:
   - Clique em **"Adicionar administrador"** ou **"Add Administrator"**
   - Procure pelo seu bot (exemplo: `@meu_bot_vendas_bot`)
   - Selecione o bot
   - **IMPORTANTE**: Ative as seguintes permissões:
     - ✅ **"Banir usuários"** (Ban users)
     - ✅ **"Adicionar usuários"** (Add users)
     - ✅ **"Excluir mensagens"** (Delete messages) - Opcional mas recomendado
     - ✅ **"Fixar mensagens"** (Pin messages) - Opcional

### Passo 2.3: Obter o ID do Grupo

O ID do grupo é necessário para relacionar o bot com o grupo no sistema. Existem duas formas de obter:

#### Método 1: Usando o Bot

1. Adicione o bot `@userinfobot` ao grupo
2. O bot automaticamente mostrará o ID do grupo
3. O ID será um número negativo (exemplo: `-1001234567890`)
4. Anote este número

#### Método 2: Usando o Bot Criado

1. Envie uma mensagem qualquer no grupo
2. Acesse: `https://api.telegram.org/bot{SEU_TOKEN}/getUpdates`
   - Substitua `{SEU_TOKEN}` pelo token do seu bot
3. Procure por `"chat":{"id":-1001234567890}`
4. O número após `"id":` é o ID do grupo

**⚠️ IMPORTANTE**: 
- IDs de grupos começam com `-` (negativo)
- IDs de supergrupos começam com `-100`
- Guarde este ID, você precisará dele para configurar no sistema

---

## 3. Relacionar o Bot com o Grupo

### Passo 3.1: Adicionar o Bot ao Grupo

1. No grupo criado, clique em **"Adicionar membros"** ou **"Add Members"**
2. Procure pelo seu bot pelo username (exemplo: `@meu_bot_vendas_bot`)
3. Selecione o bot e adicione ao grupo
4. Certifique-se de que o bot aparece na lista de membros

### Passo 3.2: Verificar Permissões do Bot

1. Vá em **"Administradores"** no grupo
2. Verifique se o bot está listado como administrador
3. Confirme que as permissões necessárias estão ativadas:
   - ✅ Banir usuários
   - ✅ Adicionar usuários

### Passo 3.3: Testar o Bot no Grupo

1. No grupo, mencione o bot: `@meu_bot_vendas_bot`
2. Ou envie um comando: `/start@meu_bot_vendas_bot`
3. O bot deve responder (se já estiver configurado no sistema)

---

## 4. Configurar o Bot no Sistema

### Passo 4.1: Acessar o Sistema

1. Abra o navegador e acesse o sistema EasyBot Telegram
2. Faça login com suas credenciais de administrador

### Passo 4.2: Criar o Bot no Sistema

1. No menu, vá em **"Bots"** ou **"Meus Bots"**
2. Clique em **"Criar novo bot"** ou **"Create Bot"**
3. Preencha o formulário:
   - **Nome do bot**: Digite o nome do bot (exemplo: "Meu Bot de Vendas")
   - **Token**: Cole o token obtido do BotFather
   - **ID do grupo**: Cole o ID do grupo (exemplo: `-1001234567890`)
   - **Configurações de privacidade**: Configure conforme necessário
   - **Método de pagamento**: Selecione (Cartão de Crédito ou PIX)
4. Clique em **"Salvar"** ou **"Save"**

### Passo 4.3: Validar Token e Grupo

1. Na página de edição do bot, você verá um botão **"Validar Token e Grupo"**
2. Clique no botão
3. O sistema verificará:
   - ✅ Se o token é válido
   - ✅ Se o grupo existe
   - ✅ Se o bot é membro do grupo
   - ✅ Se o bot tem as permissões necessárias
4. Se tudo estiver correto, você verá:
   - ✅ Token: Válido
   - ✅ Grupo: Válido
   - Informações do bot (nome, username, ID)
   - Informações do grupo (título, tipo, número de membros, permissões)

### Passo 4.4: Ativar o Bot

1. Após validar, clique em **"Ativar bot"** ou **"Activate Bot"**
2. O bot ficará ativo e pronto para receber comandos

---

## 5. Criar Administradores do Bot

### Passo 5.1: Criar Usuário Administrador no Sistema

1. No menu, vá em **"Usuários"** ou **"Users"**
2. Clique em **"Criar novo usuário"** ou **"Create User"**
3. Preencha o formulário:
   - **Nome**: Nome completo do administrador
   - **Email**: Email único do administrador
   - **Senha**: Senha segura para o administrador
   - **Role**: Selecione **"admin"**
   - **Ativo**: Marque como ativo (✅)
4. Clique em **"Salvar"** ou **"Create"**

### Passo 5.2: Configurar Autenticação de Dois Fatores (2FA) - Recomendado

1. Após criar o usuário, faça login com as credenciais criadas
2. Vá em **"Configurações"** ou **"Settings"**
3. Procure por **"Autenticação de Dois Fatores"** ou **"Two-Factor Authentication"**
4. Clique em **"Configurar 2FA"**
5. Escaneie o QR Code com um aplicativo autenticador (Google Authenticator, Authy, etc.)
6. Digite o código de 6 dígitos para confirmar
7. Guarde os códigos de backup em local seguro

### Passo 5.3: Associar Administrador ao Bot

**Opção 1: Criar Bot como Administrador**

Quando você cria um bot no sistema, ele automaticamente é associado ao usuário que está logado. Portanto:

1. Faça login com a conta de administrador
2. Crie o bot normalmente
3. O bot já estará associado a esse administrador

**Opção 2: Transferir Bot para Outro Administrador**

1. Vá em **"Bots"** → **"Editar Bot"**
2. O bot só pode ser editado pelo seu dono atual
3. Para transferir, o administrador atual deve deletar o bot e o novo administrador deve criá-lo novamente

**Opção 3: Múltiplos Administradores**

Atualmente, cada bot pertence a um único usuário. Para ter múltiplos administradores:

1. Todos os administradores devem ter acesso ao sistema
2. Cada um pode criar seus próprios bots
3. Ou compartilhar as credenciais de acesso (não recomendado)

---

## 6. Validar Configuração

### Passo 6.1: Validar no Sistema

1. Acesse a página de edição do bot
2. Clique em **"Validar Token e Grupo"**
3. Verifique se todas as validações passaram:
   - ✅ Token válido
   - ✅ Grupo válido
   - ✅ Bot é membro do grupo
   - ✅ Bot tem permissões necessárias

### Passo 6.2: Testar no Telegram

1. No grupo do Telegram, envie: `/start@meu_bot_vendas_bot`
2. O bot deve responder com a mensagem de boas-vindas configurada
3. Teste outros comandos configurados

### Passo 6.3: Testar Gerenciamento de Membros

1. No sistema, vá em **"Contatos"** ou **"Contacts"**
2. Adicione um contato manualmente ou aguarde um pagamento
3. Teste adicionar/remover membros do grupo:
   - Clique em **"+ Grupo"** para adicionar
   - Clique em **"- Grupo"** para remover
4. Verifique no Telegram se o membro foi adicionado/removido corretamente

---

## 7. Troubleshooting

### Problema: Token Inválido

**Sintomas**: Validação retorna "Token inválido"

**Soluções**:
1. Verifique se copiou o token completo do BotFather
2. Certifique-se de que não há espaços antes ou depois do token
3. Gere um novo token no BotFather com `/token` e tente novamente

### Problema: Grupo Inválido ou Bot Não é Membro

**Sintomas**: Validação retorna "Grupo inválido" ou "Bot não é membro"

**Soluções**:
1. Verifique se o ID do grupo está correto (deve começar com `-`)
2. Certifique-se de que o bot foi adicionado ao grupo
3. Verifique se o bot não foi removido do grupo
4. Adicione o bot novamente ao grupo se necessário

### Problema: Bot Não Tem Permissões

**Sintomas**: Validação retorna "Bot não tem permissões necessárias"

**Soluções**:
1. Vá nas configurações do grupo → Administradores
2. Verifique se o bot está como administrador
3. Ative as permissões:
   - ✅ Banir usuários
   - ✅ Adicionar usuários
4. Tente validar novamente

### Problema: Bot Não Responde no Grupo

**Sintomas**: Bot não responde a comandos no grupo

**Soluções**:
1. Verifique se o bot está ativo no sistema
2. Verifique se o webhook está configurado ou se o polling está rodando
3. Teste enviando mensagem privada para o bot primeiro
4. Verifique os logs do sistema para erros

### Problema: Não Consigo Adicionar Membros ao Grupo

**Sintomas**: Erro ao tentar adicionar membro via sistema

**Soluções**:
1. Verifique se o bot tem permissão de "Adicionar usuários"
2. Certifique-se de que o usuário não está bloqueado no Telegram
3. Verifique se o ID do grupo está correto
4. Verifique os logs do sistema para detalhes do erro

### Problema: Notificações Não São Enviadas

**Sintomas**: Usuários não recebem notificações quando são adicionados/removidos

**Soluções**:
1. Verifique se o usuário iniciou conversa com o bot antes (`/start`)
2. Verifique se o usuário não bloqueou o bot
3. Verifique os logs do sistema para erros de envio
4. Teste enviando mensagem manual para o usuário

---

## 📝 Checklist Final

Antes de considerar a configuração completa, verifique:

- [ ] Bot criado no Telegram via BotFather
- [ ] Token do bot copiado e guardado com segurança
- [ ] Grupo criado no Telegram
- [ ] Bot adicionado ao grupo como administrador
- [ ] Permissões do bot configuradas (banir e adicionar usuários)
- [ ] ID do grupo obtido e anotado
- [ ] Bot criado no sistema com token e ID do grupo
- [ ] Validação de token e grupo bem-sucedida
- [ ] Bot ativado no sistema
- [ ] Administradores criados no sistema
- [ ] Teste de comandos funcionando
- [ ] Teste de adicionar/remover membros funcionando

---

## 🔒 Segurança

### Boas Práticas:

1. **Nunca compartilhe o token do bot** publicamente
2. **Use autenticação de dois fatores (2FA)** para administradores
3. **Mantenha senhas fortes** para contas de administrador
4. **Revise permissões regularmente** no grupo do Telegram
5. **Monitore os logs** do sistema regularmente
6. **Faça backup** das configurações importantes

### Em Caso de Token Comprometido:

1. Acesse o BotFather
2. Use o comando `/revoke` para revogar o token atual
3. Um novo token será gerado
4. Atualize o token no sistema imediatamente

---

## 📚 Recursos Adicionais

- [Documentação da API do Telegram](https://core.telegram.org/bots/api)
- [Guia do BotFather](https://core.telegram.org/bots)
- [Documentação do Sistema](README.md)

---

## 💡 Dicas Úteis

1. **Nome do Bot**: Escolha um nome descritivo e profissional
2. **Username**: Mantenha o username simples e fácil de lembrar
3. **Descrição**: Configure uma descrição clara do que o bot faz
4. **Comandos**: Configure comandos úteis para facilitar o uso
5. **Foto**: Adicione uma foto de perfil para tornar o bot mais reconhecível
6. **Testes**: Sempre teste em um grupo de teste antes de usar em produção

---

## 🆘 Suporte

Se encontrar problemas não listados neste guia:

1. Verifique os logs do sistema em **"Logs"** no menu
2. Consulte a documentação técnica do sistema
3. Entre em contato com o suporte técnico

---

**Última atualização**: Novembro 2025

