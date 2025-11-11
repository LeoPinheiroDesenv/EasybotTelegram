# Guia de Uso - Autenticação de Dois Fatores (2FA)

Este guia explica como configurar e usar a autenticação de dois fatores (2FA) no sistema.

## 📱 Pré-requisitos

Antes de começar, você precisa ter um aplicativo autenticador instalado no seu celular:

- **Google Authenticator** (iOS/Android)
- **Microsoft Authenticator** (iOS/Android)
- **Authy** (iOS/Android)
- Qualquer outro aplicativo compatível com TOTP (Time-based One-Time Password)

## 🔧 Configuração Inicial do 2FA

### Passo 1: Fazer Login

Primeiro, faça login normalmente com seu email e senha:

```bash
POST /api/auth/login
{
  "email": "admin@admin.com",
  "password": "admin123"
}
```

### Passo 2: Configurar o 2FA

Após fazer login e obter o token de autenticação, configure o 2FA:

```bash
GET /api/auth/2fa/setup
Headers:
  Authorization: Bearer {seu_token}
```

**Resposta:**
```json
{
  "secret": "JBSWY3DPEHPK3PXP",
  "qrCode": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA...",
  "manualEntryKey": "JBSWY3DPEHPK3PXP"
}
```

### Passo 3: Escanear o QR Code

Você tem duas opções:

**Opção A - Escanear QR Code (Recomendado):**
1. Abra o aplicativo autenticador no seu celular
2. Toque em "Adicionar conta" ou "+"
3. Escolha "Escanear código QR"
4. Escaneie o QR code retornado na resposta (use a string `qrCode` como imagem base64)

**Opção B - Inserir Manualmente:**
1. Abra o aplicativo autenticador
2. Toque em "Adicionar conta" ou "+"
3. Escolha "Inserir chave manualmente"
4. Digite o `manualEntryKey` fornecido na resposta
5. Nomeie como "Easy Bot Telegram" ou similar

### Passo 4: Verificar e Ativar o 2FA

Após escanear o QR code, o aplicativo gerará um código de 6 dígitos que muda a cada 30 segundos.

1. Copie o código atual do aplicativo autenticador
2. Envie uma requisição para verificar e ativar:

```bash
POST /api/auth/2fa/verify
Headers:
  Authorization: Bearer {seu_token}
Body:
{
  "token": "123456"
}
```

**Resposta de sucesso:**
```json
{
  "success": true
}
```

✅ **Pronto!** O 2FA está agora ativado para sua conta.

## 🔐 Como Fazer Login com 2FA Ativado

Quando o 2FA está ativado, o processo de login tem duas etapas:

### Etapa 1: Login com Email e Senha

```bash
POST /api/auth/login
{
  "email": "admin@admin.com",
  "password": "admin123"
}
```

**Resposta (quando 2FA está ativado):**
```json
{
  "requiresTwoFactor": true,
  "userId": 1,
  "message": "Two-factor authentication required"
}
```

⚠️ **Importante:** Não receberá o token JWT ainda. Precisa verificar o código 2FA primeiro.

### Etapa 2: Verificar Código 2FA

1. Abra o aplicativo autenticador no seu celular
2. Copie o código de 6 dígitos atual
3. Envie a requisição de verificação:

```bash
POST /api/auth/verify-2fa
Body:
{
  "userId": 1,
  "token": "123456"
}
```

**Resposta de sucesso:**
```json
{
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "user": {
    "id": 1,
    "name": "Admin",
    "email": "admin@admin.com",
    "role": "admin"
  }
}
```

✅ **Agora você está autenticado!** Use o token retornado nas requisições subsequentes.

## 🚫 Desativar o 2FA

Se você quiser desativar o 2FA:

```bash
POST /api/auth/2fa/disable
Headers:
  Authorization: Bearer {seu_token}
```

**Resposta:**
```json
{
  "success": true
}
```

Após desativar, você poderá fazer login normalmente apenas com email e senha.

## 📝 Exemplos Práticos

### Exemplo Completo - Configuração Inicial

```bash
# 1. Login inicial
curl -X POST http://localhost:5000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@admin.com",
    "password": "admin123"
  }'

# Resposta: { "token": "eyJhbGc...", "user": {...} }

# 2. Configurar 2FA
curl -X GET http://localhost:5000/api/auth/2fa/setup \
  -H "Authorization: Bearer eyJhbGc..."

# Resposta: { "secret": "...", "qrCode": "data:image/png;base64,...", ... }

# 3. Verificar e ativar (use o código do app autenticador)
curl -X POST http://localhost:5000/api/auth/2fa/verify \
  -H "Authorization: Bearer eyJhbGc..." \
  -H "Content-Type: application/json" \
  -d '{
    "token": "123456"
  }'

# Resposta: { "success": true }
```

### Exemplo Completo - Login com 2FA

```bash
# 1. Primeira etapa - Email e senha
curl -X POST http://localhost:5000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@admin.com",
    "password": "admin123"
  }'

# Resposta: { "requiresTwoFactor": true, "userId": 1, ... }

# 2. Segunda etapa - Código 2FA
curl -X POST http://localhost:5000/api/auth/verify-2fa \
  -H "Content-Type: application/json" \
  -d '{
    "userId": 1,
    "token": "654321"
  }'

# Resposta: { "token": "eyJhbGc...", "user": {...} }
```

## 🎨 No Frontend (Interface Web)

O frontend já está configurado para suportar 2FA automaticamente:

1. **Login Normal:**
   - Digite email e senha
   - Clique em "Entrar"

2. **Se 2FA estiver ativado:**
   - Após clicar em "Entrar", aparecerá um campo para o código 2FA
   - Digite o código de 6 dígitos do seu aplicativo autenticador
   - Clique em "Verificar"

3. **Botão Voltar:**
   - Se precisar voltar, clique em "Voltar" para tentar novamente

## ⚠️ Dicas Importantes

1. **Códigos Temporários:** Os códigos 2FA mudam a cada 30 segundos. Se um código não funcionar, espere o próximo.

2. **Janela de Tolerância:** O sistema aceita códigos de até 2 períodos antes e depois (60 segundos de tolerância).

3. **Backup do Secret:** Anote o `manualEntryKey` em local seguro. Se perder acesso ao celular, você pode reconfigurar o 2FA em outro dispositivo.

4. **Múltiplos Dispositivos:** Você pode escanear o mesmo QR code em múltiplos dispositivos para ter backup.

5. **Problemas Comuns:**
   - **Código inválido:** Verifique se o relógio do celular está sincronizado
   - **QR code não funciona:** Use a opção de inserção manual com o `manualEntryKey`
   - **Perdeu o celular:** Entre em contato com o administrador para desativar o 2FA

## 🔒 Segurança

- O secret 2FA é armazenado de forma criptografada no banco de dados
- Apenas o hash do secret é armazenado, nunca o código completo
- Os códigos são válidos apenas por 30 segundos
- O sistema usa o padrão TOTP (RFC 6238), amplamente utilizado e seguro

## 📚 Referências

- [TOTP Specification (RFC 6238)](https://tools.ietf.org/html/rfc6238)
- [Google Authenticator](https://support.google.com/accounts/answer/1066447)
- [Microsoft Authenticator](https://www.microsoft.com/en-us/security/mobile-authenticator-app)

