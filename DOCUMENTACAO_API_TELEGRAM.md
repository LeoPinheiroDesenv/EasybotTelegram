# Documentação Detalhada - APIs do Telegram

## Índice
1. [Visão Geral](#visão-geral)
2. [Bot API](#bot-api)
3. [Telegram API (MTProto)](#telegram-api-mtproto)
4. [TDLib](#tdlib)
5. [Gateway API](#gateway-api)

---

## Visão Geral

O Telegram oferece três tipos principais de APIs para desenvolvedores:

1. **Bot API** - Para criar bots que usam mensagens do Telegram como interface
2. **Telegram API (MTProto)** - Para construir clientes Telegram personalizados
3. **Gateway API** - Para enviar códigos de verificação via Telegram

Todas as APIs são **gratuitas** para uso.

---

## Bot API

### Introdução

A Bot API é uma interface baseada em HTTP criada para desenvolvedores que desejam criar bots para o Telegram. Bots são contas especiais que não requerem número de telefone adicional para configuração.

### Autenticação

Cada bot recebe um token de autenticação único quando é criado. O token tem o formato:
```
123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11
```

### Fazendo Requisições

Todas as consultas à Bot API devem ser feitas via HTTPS no formato:
```
https://api.telegram.org/bot<token>/METHOD_NAME
```

**Exemplo:**
```
https://api.telegram.org/bot123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11/getMe
```

### Métodos HTTP Suportados

- **GET**
- **POST**

### Formas de Passar Parâmetros

1. **URL query string**
2. **application/x-www-form-urlencoded**
3. **application/json** (exceto para upload de arquivos)
4. **multipart/form-data** (para upload de arquivos)

### Formato de Resposta

A resposta contém um objeto JSON com:
- `ok` (Boolean) - Indica se a requisição foi bem-sucedida
- `description` (String, opcional) - Descrição legível do resultado
- `result` - Contém o resultado se `ok` for `true`
- `error_code` (Integer) - Código de erro (se houver)
- `parameters` (opcional) - Parâmetros adicionais para tratamento de erros

### Características Importantes

- Todos os métodos são **case-insensitive**
- Todas as consultas devem usar **UTF-8**
- A API é **case-insensitive**

### Servidor Local Bot API

O código-fonte do servidor Bot API está disponível em [telegram-bot-api](https://github.com/tdlib/telegram-bot-api). Você pode executá-lo localmente e enviar requisições para seu próprio servidor.

**Vantagens de usar um servidor local:**
- Download de arquivos sem limite de tamanho
- Upload de arquivos até 2000 MB
- Upload usando caminho local e esquema de URI de arquivo
- Usar URL HTTP para webhook
- Usar qualquer endereço IP local para webhook
- Usar qualquer porta para webhook

### Recursos Principais da Bot API

#### Versão Atual: Bot API 9.2 (Agosto 2025)

**Novos Recursos:**
- **Checklists** - Bots podem enviar e gerenciar listas de tarefas
- **Gifts** - Sistema de presentes com informações sobre chats publicadores
- **Direct Messages in Channels** - Mensagens diretas em canais
- **Suggested Posts** - Sistema de posts sugeridos para monetização

#### Funcionalidades Disponíveis

1. **Envio de Mensagens**
   - Texto, fotos, vídeos, áudios, documentos
   - Stickers, GIFs, localização
   - Contatos, dados de contato
   - Dados de localização ao vivo

2. **Comandos de Bot**
   - Comandos personalizados
   - Botões inline
   - Menu de botões

3. **Webhooks e Updates**
   - Recebimento de atualizações via webhook
   - Polling de atualizações

4. **Pagamentos**
   - Integração com sistemas de pagamento
   - Invoices e pagamentos

5. **Mini Apps**
   - Aplicativos HTML5 interativos
   - Integração com Telegram Web Apps

6. **Business Accounts**
   - Gerenciamento de contas comerciais
   - Horários de funcionamento
   - Respostas rápidas
   - Mensagens automatizadas

---

## Telegram API (MTProto)

### Introdução

A Telegram API permite construir clientes Telegram personalizados. É 100% aberta para todos os desenvolvedores que desejam criar aplicações Telegram na plataforma.

### Autorização de Usuário

#### Enviando Código de Verificação

Para autorizar um usuário, você precisa:

1. **Obter lista de países** usando `help.getCountriesList`
2. **Enviar código de verificação** usando `auth.sendCode`

**Método principal:**
```tl
auth.sendCode#a677244f phone_number:string api_id:int api_hash:string settings:CodeSettings = auth.SentCode;
```

#### Tipos de Código

O sistema escolhe automaticamente como enviar o código de autorização:

1. **auth.sentCodeTypeApp** - Código enviado como notificação de serviço Telegram para outras sessões logadas
2. **auth.sentCodeTypeSms** - Código enviado via SMS
3. **auth.sentCodeTypeCall** - Usuário recebe uma chamada telefônica com código sintetizado
4. **auth.sentCodeTypeFlashCall** - Chamada flash (fechada imediatamente)
5. **auth.sentCodeTypeMissedCall** - Chamada perdida com código nos últimos dígitos
6. **auth.sentCodeTypeEmailCode** - Código enviado para email configurado
7. **auth.sentCodeTypeFragmentSms** - Código enviado via Fragment.com
8. **auth.sentCodeTypeFirebaseSms** - Fluxo Firebase (apenas apps oficiais)
9. **auth.sentCodeTypeSetUpEmailRequired** - Requer configuração de email

#### Verificação de Email

Telegram pode solicitar verificação de email. O processo inclui:

1. Verificação com Google/Apple ID (se permitido)
2. Verificação via código de email
3. Reset de email de login se necessário

**Métodos relacionados:**
- `account.sendVerifyEmailCode` - Enviar código de verificação
- `account.verifyEmail` - Verificar email
- `auth.resetLoginEmail` - Resetar email de login

#### Login/Registro

**Métodos:**
- `auth.signIn` - Fazer login com código de verificação
- `auth.signUp` - Registrar nova conta

Se o código estiver correto mas retornar `auth.authorizationSignUpRequired`, significa que a conta não existe e o usuário precisa se registrar.

#### Autenticação de Dois Fatores (2FA)

Se o usuário tiver 2FA habilitado, `auth.signIn` retornará erro `SESSION_PASSWORD_NEEDED`. Neste caso, siga as instruções de [autenticação SRP 2FA](/api/srp).

#### Confirmando Login

Quando um novo login ocorre, outras sessões recebem `updateNewAuthorization`. Se a flag `unconfirmed` estiver definida, o cliente deve exibir notificação pedindo confirmação do usuário.

**Métodos relacionados:**
- `account.getAuthorizations` - Listar autorizações
- `account.changeAuthorizationSettings` - Confirmar sessão
- `account.resetAuthorization` - Revogar sessão

#### Invalidando Códigos de Login

Códigos de login são automaticamente invalidados se:
- Enviados para outro chat Telegram
- Screenshot de mensagem do serviço de login (ID 777000)
- Mensagem encaminhada

Use `account.invalidateSignInCodes` para invalidar manualmente.

### Métodos Disponíveis

A API MTProto oferece centenas de métodos organizados por categoria:

#### Configuração
- `help.getConfig` - Obter configuração atual
- `help.getAppConfig` - Obter configuração específica do app
- `help.getNearestDc` - Obter DC mais próximo
- `help.getCountriesList` - Lista de países

#### Mensagens
- `messages.sendMessage` - Enviar mensagem
- `messages.getHistory` - Obter histórico
- `messages.search` - Buscar mensagens
- `messages.forwardMessages` - Encaminhar mensagens
- `messages.deleteMessages` - Deletar mensagens

#### Chats e Canais
- `messages.createChat` - Criar chat
- `channels.createChannel` - Criar canal
- `channels.editAdmin` - Editar admin
- `channels.getFullChannel` - Obter canal completo

#### Arquivos
- `upload.saveFilePart` - Salvar parte do arquivo
- `upload.getFile` - Obter arquivo
- `messages.uploadMedia` - Upload de mídia

#### E muito mais...

### Segurança

#### Chats Secretos (E2E Encryption)
- Criptografia ponta a ponta
- Chaves temporárias
- Perfect Forward Secrecy

#### Diretrizes de Segurança
- Verificações importantes no cliente
- Validação de chaves
- Proteção contra ataques

### Otimização

- Otimização de cliente
- Gerenciamento de conexões
- Cache local
- Compressão de dados

---

## TDLib

### Introdução

**TDLib** (Telegram Database Library) é uma biblioteca cross-platform e totalmente funcional para criar clientes Telegram. Foi projetada para ajudar desenvolvedores terceiros a criar aplicativos personalizados usando a plataforma Telegram.

### Vantagens do TDLib

1. **Cross-platform**
   - Android, iOS, Windows, macOS, Linux
   - WebAssembly, FreeBSD, Windows Phone
   - watchOS, tvOS, Tizen, Cygwin
   - E outros sistemas *nix

2. **Multi-idioma**
   - Pode ser usado com qualquer linguagem que execute funções C
   - Bindings nativos para Java (JNI) e C# (C++/CLI)

3. **Fácil de usar**
   - Cuida de todos os detalhes de implementação de rede
   - Gerencia criptografia
   - Gerencia armazenamento local de dados

4. **Alta performance**
   - Cada instância TDLib lida com mais de **24.000 bots ativos** simultaneamente

5. **Bem documentado**
   - Todos os métodos e interfaces públicas são totalmente documentados
   - Documentação disponível em: https://core.telegram.org/tdlib/docs/

6. **Consistente**
   - Garante que todas as atualizações sejam entregues na ordem correta

7. **Confiável**
   - Permanece estável em conexões lentas e não confiáveis

8. **Seguro**
   - Todos os dados locais são criptografados usando chave fornecida pelo usuário

9. **Totalmente assíncrono**
   - Requisições não bloqueiam umas às outras
   - Respostas são enviadas quando disponíveis

### Recursos

- **Código-fonte aberto**: Disponível no [GitHub](https://github.com/tdlib/td)
- **Documentação completa**: https://core.telegram.org/tdlib/docs/
- **Lista de opções**: https://core.telegram.org/tdlib/options
- **Notification API**: https://core.telegram.org/tdlib/notification-api/

### Começando

Para começar com TDLib, consulte: https://core.telegram.org/tdlib/getting-started

---

## Gateway API

### Introdução

O Telegram Gateway é uma solução **econômica** e **focada em privacidade** para autenticação de usuários, permitindo que empresas verifiquem números de telefone de clientes através do Telegram a uma fração do custo de SMS tradicional.

### Benefícios Principais

#### Economia de Custos
- **$0.01 por código de verificação** - até **50x mais barato** que SMS
- **Reembolsos automáticos** para códigos não entregues
- Você paga apenas por verificações bem-sucedidas

#### Seguro e Confiável
- Usa infraestrutura confiável do Telegram
- Entrega rápida e segura de códigos
- Evita métodos de verificação desatualizados e caros

#### Amigável para Desenvolvedores
- Integração fácil com API clara
- **Ambiente de teste gratuito** - teste antes de comprometer

### O Que Você Pode Fazer

#### 1. Autenticar Usuários em Qualquer Lugar
Usuários registrados do Telegram precisam apenas de conexão com internet. Não precisam de plano SMS ativo do provedor.

#### 2. Reduzir Custos de Aquisição
- Verificação via Telegram: **$0.01** por código entregue
- SMS pode custar até **50 vezes mais**
- Pagamento apenas por códigos entregues no tempo especificado

#### 3. Acessar Estatísticas Detalhadas
- Estatísticas detalhadas para gerenciar orçamento
- Rastrear volume de mensagens
- Analisar crescimento de usuários e taxas de conversão

#### 4. Entregar Mensagens com Segurança
- Protocolo de criptografia comprovado
- Apps open-source verificáveis
- SMS tradicional não é criptografado e suscetível a vulnerabilidades

#### 5. Construir uma Audiência
- Telegram está entre os **5 apps mais baixados** do mundo
- Mais de **950 milhões de usuários**
- Mensagens podem ser assinadas por canal verificado

### FAQ

#### Q: Quem fornece o número de contato para usuários?
R: Serviços usando a Gateway API devem fornecer o número de telefone relevante. Todos os números devem ser de usuários que **compartilharam voluntariamente** seu número com o serviço.

> Telegram **não divulga números de telefone** de usuários para serviços que utilizam a Plataforma de Verificação.

#### Q: Meus usuários precisam optar por receber minhas mensagens?
R: Sim. Números de telefone registrados são **sempre privados** - usuários devem **optar voluntariamente** compartilhando seu número e concordando em receber mensagens.

#### Q: Qual formato de número de telefone devo usar?
R: Todos os números devem estar no formato [E.164](https://en.wikipedia.org/wiki/E.164).

#### Q: Como posso testar meu fluxo de autorização?
R: Você pode simplesmente enviar códigos para seu próprio número no Telegram - todas as mensagens para você são **gratuitas**.

#### Q: Posso transferir fundos entre contas Gateway?
R: Não, transferência de fundos entre contas não é suportada atualmente. Todos os fundos transferidos constituem créditos pré-pagos e não podem ser transferidos ou sacados.

#### Q: Como verifico se um usuário pode receber minhas mensagens?
R: Use o método `checkSendAbility` para verificar. Se não puderem receber, sua requisição será gratuita.

### Documentação Adicional

- **Guia Rápido**: https://core.telegram.org/gateway/verification-tutorial
- **Referência Completa da API**: https://core.telegram.org/gateway/api
- **Termos de Serviço**: https://telegram.org/tos/gateway

---

## Recursos Adicionais

### Widgets do Telegram
Adicione widgets do Telegram ao seu website: https://core.telegram.org/widgets

### Stickers Animados e Emoji
Designers podem criar stickers animados e emoji: https://core.telegram.org/stickers#animated-stickers-and-emoji

### Temas Personalizados
Crie temas personalizados para Telegram: https://core.telegram.org/themes

### Schema TL
Documentação completa do schema TL (Type Language): https://core.telegram.org/schema

### Protocolo MTProto
Documentação do protocolo de criptografia: https://core.telegram.org/mtproto

---

## Links Úteis

- **Página Principal da API**: https://core.telegram.org/api
- **Bot API**: https://core.telegram.org/bots/api
- **TDLib**: https://core.telegram.org/tdlib
- **Gateway API**: https://core.telegram.org/gateway
- **Métodos Disponíveis**: https://core.telegram.org/methods
- **Schema JSON**: https://core.telegram.org/schema/json

---

## Notas Finais

- Todas as APIs são **gratuitas** para uso
- Documentação completa disponível online
- Código-fonte aberto para TDLib e Bot API Server
- Suporte ativo da comunidade
- Atualizações regulares com novos recursos

---

*Documentação gerada com base nas informações oficiais do Telegram em https://core.telegram.org*
*Última atualização: Agosto 2025*
