# 📋 Descrição das Funcionalidades - EasyBot Telegram

## 🎯 Visão Geral

O **EasyBot Telegram** é uma plataforma completa de gerenciamento de bots do Telegram com sistema de pagamentos integrado, autenticação de dois fatores, marketing automatizado e análise de dados. A aplicação permite criar, configurar e gerenciar múltiplos bots do Telegram através de uma interface web intuitiva.

---

## 🔐 Autenticação e Segurança

### Login e Autenticação
- **Login seguro** com email e senha
- **Autenticação JWT** (JSON Web Tokens) para sessões
- **Autenticação de Dois Fatores (2FA)** opcional
  - Geração de QR Code para aplicativos autenticadores
  - Suporte a Google Authenticator, Microsoft Authenticator, Authy
  - Códigos TOTP (Time-based One-Time Password)
  - Ativação/desativação de 2FA por usuário

### Controle de Acesso
- **Níveis de permissão**: Administrador e Usuário
- **Proteção de rotas** baseada em roles
- **Middleware de autenticação** em todas as rotas protegidas

---

## 🤖 Gerenciamento de Bots

### Criação e Configuração
- **Criar novos bots** com nome e token do Telegram
- **Validar tokens** em tempo real com a API do Telegram
- **Configurar grupo do Telegram** associado ao bot
- **Ativar/desativar bots** individualmente
- **Gerenciar múltiplos bots** simultaneamente

### Integração com Telegram
- **Inicialização automática** de bots ao criar/atualizar
- **Processamento de mensagens** em tempo real via polling
- **Comandos personalizados**:
  - `/start` - Mensagem de boas-vindas configurável
  - `/comandos` - Lista de comandos disponíveis
- **Respostas automáticas** configuráveis
- **Envio de mídias** (imagens, vídeos, documentos)

### Controle de Bots
- **Inicializar/parar bots** manualmente
- **Verificar status** de cada bot (ativo/inativo)
- **Logs de operações** dos bots
- **Múltiplos bots** rodando simultaneamente

---

## 👥 Gerenciamento de Contatos

### Captura Automática
- **Salvamento automático** de contatos ao interagir com o bot
- **Atualização de informações** quando usuários interagem novamente
- **Rastreamento de interações** com cada bot

### Gerenciamento Manual
- **Listar todos os contatos** com filtros e busca
- **Visualizar detalhes** de cada contato
- **Editar informações** de contatos
- **Bloquear/desbloquear** contatos
- **Estatísticas de contatos** por bot

### Informações Capturadas
- Nome do usuário
- Email (quando fornecido)
- Telefone (quando fornecido)
- Idioma preferido
- Data de cadastro
- Última interação
- Status (ativo/bloqueado)

---

## 💰 Sistema de Pagamentos

### Gateways Integrados
- **Mercado Pago** (PIX)
- **Stripe** (Cartão de Crédito)
- **Configuração por bot** ou global
- **Ambiente de teste e produção**

### Planos de Pagamento
- **Criar planos** personalizados
- **Definir valores** e periodicidade
- **Configurar métodos de pagamento** aceitos
- **Associar planos a bots** específicos
- **Gerenciar múltiplos planos**

### Processamento de Pagamentos

#### PIX (Mercado Pago)
- **Geração de QR Code** PIX
- **QR Code em imagem** (base64)
- **Link para pagamento**
- **Data de expiração** configurável
- **Webhook para confirmação** automática

#### Cartão de Crédito (Stripe)
- **Checkout seguro** via Stripe
- **Suporte a múltiplos cartões**
- **Processamento assíncrono**
- **Webhook para confirmação** automática

### Transações
- **Rastreamento completo** de todas as transações
- **Status em tempo real** (pendente, aprovado, recusado, cancelado)
- **Histórico de pagamentos** por contato
- **Relatórios financeiros**
- **Ciclos de pagamento** configuráveis

---

## 📊 Dashboard e Analytics

### Visão Geral
- **Estatísticas gerais** do sistema
- **Número de bots ativos**
- **Total de contatos**
- **Receita total**
- **Gráficos interativos** (Chart.js)

### Gráficos e Relatórios
- **Gráfico de assinantes** ao longo do tempo
- **Gráfico de faturamento** por período
- **Análise de crescimento** de contatos
- **Performance de bots** individuais

### Métricas
- **Contatos por bot**
- **Taxa de conversão**
- **Receita por período**
- **Atividade dos bots**

---

## 📢 Marketing e Comunicação

### Mensagens de Boas-Vindas
- **Configurar mensagem inicial** personalizada
- **Suporte a múltiplas mídias** (até 3 arquivos)
- **Upload de imagens/vídeos**
- **Botões de redirecionamento** configuráveis
- **Mensagens por bot**

### Alertas
- **Criar alertas** personalizados
- **Agendar envio** de mensagens
- **Filtrar por plano de pagamento**
- **Filtrar por idioma do usuário**
- **Filtrar por categoria** de usuário
- **Anexar arquivos** aos alertas

### Downsell
- **Criar ofertas especiais**
- **Configurar condições** de downsell
- **Associar a planos** de pagamento
- **Mensagens personalizadas**

### Botões de Redirecionamento
- **Criar botões** personalizados (até 3)
- **Configurar links** de destino
- **Títulos personalizados**
- **Gerenciar botões** por bot

---

## 👨‍💼 Administração

### Gerenciamento de Usuários
- **Listar todos os usuários**
- **Criar novos usuários**
- **Editar informações** de usuários
- **Ativar/desativar** usuários
- **Definir níveis de acesso** (admin/user)
- **Excluir usuários**

### Administradores de Bots
- **Definir administradores** para cada bot
- **Controle de acesso** por bot
- **Múltiplos administradores** por bot

### Grupos e Canais
- **Gerenciar grupos** do Telegram
- **Associar grupos a bots**
- **Configurar permissões**
- **IDs de grupos** e canais

### Logs do Sistema
- **Registro de todas as ações**
- **Filtros por tipo** de ação
- **Filtros por usuário**
- **Filtros por data**
- **Busca em logs**
- **Exportação de logs**

---

## ⚙️ Configurações

### Ciclos de Pagamento
- **Configurar ciclos** personalizados
- **Definir períodos** (mensal, trimestral, anual)
- **Gerenciar múltiplos ciclos**

### Gateways de Pagamento
- **Configurar credenciais** do Mercado Pago
- **Configurar credenciais** do Stripe
- **Ambiente de teste/produção**
- **Configuração por bot** ou global
- **Webhooks** configuráveis

### Configurações de Bot
- **Solicitar email** ao usuário
- **Solicitar telefone** ao usuário
- **Solicitar idioma** preferido
- **Método de pagamento padrão**
- **Ativação/desativação** de funcionalidades

---

## 🔄 Integrações

### Telegram Bot API
- **Integração completa** com Telegram
- **Polling de mensagens** em tempo real
- **Envio de mensagens** programadas
- **Processamento de comandos**
- **Webhooks** (futuro)

### APIs de Pagamento
- **Mercado Pago API** para PIX
- **Stripe API** para cartões
- **Webhooks** para confirmação automática
- **Tratamento de erros** e retentativas

---

## 📱 Interface do Usuário

### Design Moderno
- **Interface responsiva** (mobile-first)
- **Tema claro/escuro** (preparado)
- **Componentes reutilizáveis**
- **Ícones Font Awesome**
- **Tipografia Inter** (Google Fonts)

### Navegação
- **Sidebar** com menu lateral
- **Header** com informações do usuário
- **Breadcrumbs** para navegação
- **Menu mobile** responsivo

### Componentes UI
- **Botões** estilizados e reutilizáveis
- **Cards** para organização de conteúdo
- **Formulários** com validação
- **Modais** para ações importantes
- **Tabelas** com ordenação e filtros
- **Gráficos** interativos

---

## 🛠️ Tecnologias Utilizadas

### Frontend
- **React 18** - Biblioteca JavaScript
- **React Router** - Roteamento
- **Axios** - Cliente HTTP
- **Chart.js** - Gráficos
- **Font Awesome** - Ícones
- **CSS3** - Estilização

### Backend
- **Node.js** - Runtime JavaScript
- **Express** - Framework web
- **PostgreSQL** - Banco de dados
- **JWT** - Autenticação
- **bcrypt** - Criptografia de senhas
- **node-telegram-bot-api** - Integração Telegram
- **Mercado Pago SDK** - Pagamentos PIX
- **Stripe SDK** - Pagamentos cartão

### Infraestrutura
- **Docker** - Containerização
- **Docker Compose** - Orquestração
- **PostgreSQL** - Banco de dados relacional

---

## 📈 Funcionalidades Futuras

- [ ] Webhooks do Telegram (em vez de polling)
- [ ] Chat em tempo real no painel
- [ ] Exportação de relatórios (PDF/Excel)
- [ ] API pública para integrações
- [ ] Notificações push
- [ ] Sistema de templates de mensagens
- [ ] Automações avançadas
- [ ] Integração com mais gateways de pagamento
- [ ] App mobile

---

## 🔒 Segurança

- **Senhas criptografadas** com bcrypt
- **Tokens JWT** com expiração
- **Validação de entrada** em todas as rotas
- **Proteção CSRF** (preparado)
- **Rate limiting** (preparado)
- **Logs de auditoria** completos
- **Autenticação de dois fatores** opcional

---

## 📞 Suporte

Para mais informações sobre funcionalidades específicas, consulte:
- `GUIA_2FA.md` - Guia de autenticação de dois fatores
- `GUIA_PAGAMENTOS.md` - Guia de integração de pagamentos
- `INTEGRACAO_TELEGRAM.md` - Guia de integração com Telegram

---

**Versão:** 1.0.0  
**Última atualização:** 2024

