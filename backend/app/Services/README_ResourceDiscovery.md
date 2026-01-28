# ResourceDiscoveryService - Documentação

## Visão Geral

O `ResourceDiscoveryService` é responsável por descobrir automaticamente todos os recursos disponíveis no sistema (menus, rotas, etc.) para serem disponibilizados para grupos de usuários.

## Como Funciona

### Descoberta Automática de Menus

O serviço analisa:
1. **Mapeamento de Rotas**: Um mapeamento centralizado (`ROUTE_TO_MENU_MAP`) que define quais rotas pertencem a quais menus
2. **Rotas Reais**: Analisa as rotas registradas no Laravel para descobrir novos recursos
3. **Inferência por Prefixo**: Se uma rota não estiver no mapeamento, tenta inferir o menu baseado no prefixo da rota

### Adicionando Novos Menus/Recursos

Quando uma nova tela ou recurso é criado:

1. **Adicione ao Mapeamento de Rotas** (`ROUTE_TO_MENU_MAP`):
   ```php
   '/nova-rota' => 'novo_menu',
   '/nova-rota/*' => 'novo_menu', // Para capturar todas as sub-rotas
   ```

2. **Adicione o Label** (`getMenuLabels()`):
   ```php
   'novo_menu' => 'Novo Menu',
   ```

3. **Sincronize com o Frontend**: Atualize o `routeToMenuMap` em `frontend/src/components/ProtectedRoute.js`

## Estrutura de Menus

Os menus disponíveis são:
- `dashboard` - Dashboard principal
- `billing` - Faturamento
- `bot` - Gerenciamento de bots
- `results` - Resultados e contatos
- `marketing` - Marketing e alertas
- `settings` - Configurações do sistema

## Uso

```php
$service = app(ResourceDiscoveryService::class);

// Descobrir todos os menus disponíveis
$menus = $service->discoverAvailableMenus();

// Obter label de um menu
$label = $service->getMenuLabel('dashboard'); // Retorna "Dashboard"

// Descobrir todos os recursos
$resources = $service->discoverAllResources();
```

## Manutenção

- O mapeamento `ROUTE_TO_MENU_MAP` deve ser mantido sincronizado com as rotas reais do sistema
- Sempre que uma nova rota for criada, verifique se ela precisa ser adicionada ao mapeamento
- Os labels devem ser mantidos atualizados em `getMenuLabels()`
