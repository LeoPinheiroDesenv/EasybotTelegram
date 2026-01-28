# Changelog - ResourceDiscoveryService

## ⚠️ IMPORTANTE: Checklist ao Criar Nova Tela/Recurso

Sempre que uma **nova tela ou recurso** for criado, você DEVE atualizar os seguintes arquivos:

### 1. Backend - ResourceDiscoveryService.php
- [ ] Adicionar a rota ao `ROUTE_TO_MENU_MAP` (se necessário)
- [ ] Adicionar o label do menu em `getMenuLabels()` (se for um novo menu)
- [ ] Atualizar este CHANGELOG com a data e o recurso adicionado

### 2. Frontend - ProtectedRoute.js
- [ ] Adicionar a rota ao `routeToMenuMap` (se necessário)
- [ ] Garantir que a rota está mapeada para o menu correto

### 3. Frontend - App.js
- [ ] Adicionar a rota no Router (se necessário)
- [ ] Proteger com `<ProtectedRoute>` se necessário

---

## Histórico de Atualizações

### 2025-12-10 - Atualização Completa Inicial
**Recursos mapeados até esta data:**

#### Dashboard
- ✅ `/` - Dashboard principal
- ✅ `/dashboard` - Dashboard

#### Billing (Faturamento)
- ✅ `/billing` - Faturamento
- ✅ Todas as sub-rotas de billing

#### Bot (Gerenciamento de Bots)
- ✅ `/bot/list` - Lista de bots
- ✅ `/bot/create` - Criar bot
- ✅ `/bot/update` - Atualizar bot
- ✅ `/bot/manage` - Gerenciar bot
- ✅ `/bot/welcome` - Mensagem de boas-vindas
- ✅ `/bot/payment-plans` - Planos de pagamento
- ✅ `/bot/redirect` - Botões de redirecionamento
- ✅ `/bot/administrators` - Administradores do bot
- ✅ `/bot/groups` - Grupos do bot
- ✅ `/bot/telegram-groups` - Grupos do Telegram
- ✅ `/bot/commands` - Comandos do bot
- ✅ `/bot/botfather` - Configurações do BotFather
- ✅ `/bot/group-management` - Gerenciamento de grupos
- ✅ Todas as sub-rotas de bot (`/bot/*`)

#### Results (Resultados)
- ✅ `/results/contacts` - Contatos
- ✅ `/results/contacts/:id` - Detalhes do contato
- ✅ Todas as sub-rotas de results (`/results/*`)

#### Marketing
- ✅ `/marketing` - Marketing
- ✅ `/marketing/alerts` - Alertas
- ✅ `/marketing/downsell` - Downsell
- ✅ Todas as sub-rotas de marketing (`/marketing/*`)

#### Settings (Configurações)
- ✅ `/settings/payment-cycles` - Ciclos de pagamento
- ✅ `/settings/payment-gateways` - Gateways de pagamento
- ✅ `/settings/profile` - Meu perfil
- ✅ `/settings/security` - Segurança (2FA)
- ✅ `/settings/storage` - Storage (apenas super admin)
- ✅ `/settings/artisan` - Comandos Artisan (apenas super admin)
- ✅ `/users` - Usuários
- ✅ `/user-groups` - Grupos de usuários
- ✅ `/logs` - Logs (apenas super admin)
- ✅ `/ftp` - Gerenciador FTP
- ✅ Todas as sub-rotas de settings (`/settings/*`)

**Total de menus disponíveis:** 6
- Dashboard
- Billing (Faturamento)
- Bot
- Results (Resultados)
- Marketing
- Settings (Configurações)

**Total de rotas mapeadas:** 30+

---

## Como Adicionar um Novo Recurso

### Exemplo: Adicionar um novo menu "Relatórios"

1. **Backend - ResourceDiscoveryService.php:**
```php
// Adicionar ao ROUTE_TO_MENU_MAP:
'/reports' => 'reports',
'/reports/*' => 'reports',

// Adicionar ao getMenuLabels():
'reports' => 'Relatórios',
```

2. **Frontend - ProtectedRoute.js:**
```javascript
// Adicionar ao routeToMenuMap:
'/reports': 'reports',
'/reports/*': 'reports',
```

3. **Atualizar este CHANGELOG:**
```markdown
### YYYY-MM-DD - Novo Menu: Relatórios
- Adicionado menu "reports"
- Rotas: /reports, /reports/*
- Label: "Relatórios"
```

---

## Notas

- O sistema usa wildcards (`/*`) para capturar todas as sub-rotas automaticamente
- Menus são descobertos automaticamente baseado no mapeamento e nas rotas reais
- Sempre sincronize backend e frontend quando adicionar novos recursos
- Consulte `ATUALIZAR_RECURSOS.md` na raiz do projeto para mais detalhes
