<?php

namespace App\Services;

use Illuminate\Support\Facades\Route;

/**
 * Serviço para descobrir automaticamente recursos disponíveis no sistema
 * (menus, rotas, etc.) para serem disponibilizados para grupos de usuários
 * 
 * ⚠️⚠️⚠️ ATENÇÃO: SEMPRE QUE UMA NOVA TELA OU RECURSO FOR CRIADO, ATUALIZE ESTE ARQUIVO! ⚠️⚠️⚠️
 * 
 * 📋 CHECKLIST OBRIGATÓRIO:
 * 1. Adicione a rota ao ROUTE_TO_MENU_MAP abaixo
 * 2. Se for um novo menu, adicione o label em getMenuLabels()
 * 3. Sincronize com frontend/src/components/ProtectedRoute.js
 * 4. Atualize o CHANGELOG_ResourceDiscovery.md
 * 
 * 📚 Consulte ATUALIZAR_RECURSOS.md na raiz do projeto para instruções detalhadas
 * 
 * Última atualização completa: 2025-12-10
 * Total de recursos mapeados: 30+ rotas em 6 menus
 */
class ResourceDiscoveryService
{
    /**
     * Mapeamento de rotas para menus
     * 
     * ⚠️ ATENÇÃO: Este mapeamento deve estar sincronizado com:
     * - frontend/src/components/ProtectedRoute.js (routeToMenuMap)
     * - frontend/src/App.js (rotas definidas)
     * 
     * IMPORTANTE: Sempre que uma nova tela/rota for criada, adicione aqui!
     * 
     * Estrutura: 'rota' => 'menu'
     * Use '/*' no final para capturar todas as sub-rotas (ex: '/bot/*')
     */
    private const ROUTE_TO_MENU_MAP = [
        // ============================================
        // DASHBOARD
        // ============================================
        '/' => 'dashboard',
        '/dashboard' => 'dashboard',
        
        // ============================================
        // BILLING (Faturamento)
        // ============================================
        '/billing' => 'billing',
        '/billing/*' => 'billing',
        
        // ============================================
        // BOT (Gerenciamento de Bots)
        // ============================================
        '/bot' => 'bot',
        '/bot/*' => 'bot', // Wildcard para capturar TODAS as rotas /bot/*
        '/bot/list' => 'bot',
        '/bot/create' => 'bot',
        '/bot/update' => 'bot',
        '/bot/manage' => 'bot',
        '/bot/welcome' => 'bot',
        '/bot/payment-plans' => 'bot',
        '/bot/redirect' => 'bot',
        '/bot/administrators' => 'bot',
        '/bot/groups' => 'bot',
        '/bot/telegram-groups' => 'bot',
        '/bot/commands' => 'bot',
        '/bot/botfather' => 'bot',
        '/bot/group-management' => 'bot',
        
        // ============================================
        // RESULTS (Resultados e Contatos)
        // ============================================
        '/results' => 'results',
        '/results/*' => 'results', // Wildcard para capturar todas as rotas /results/*
        '/results/contacts' => 'results',
        
        // ============================================
        // MARKETING (Marketing e Alertas)
        // ============================================
        '/marketing' => 'marketing',
        '/marketing/*' => 'marketing', // Wildcard para capturar todas as rotas /marketing/*
        '/marketing/alerts' => 'marketing',
        '/marketing/downsell' => 'marketing',
        
        // ============================================
        // SETTINGS (Configurações)
        // ============================================
        '/settings' => 'settings',
        '/settings/*' => 'settings', // Wildcard para capturar todas as rotas /settings/*
        '/settings/payment-cycles' => 'settings',
        '/settings/payment-gateways' => 'settings',
        '/settings/profile' => 'settings',
        '/settings/security' => 'settings',
        '/settings/storage' => 'settings',
        '/settings/artisan' => 'settings',
        '/users' => 'settings',
        '/user-groups' => 'settings',
        '/logs' => 'settings',
        '/ftp' => 'settings',
    ];

    /**
     * Descobre todos os menus disponíveis no sistema
     * baseado nas rotas definidas e no mapeamento de rotas
     *
     * @return array Lista de menus únicos disponíveis
     */
    public function discoverAvailableMenus(): array
    {
        $menus = [];
        
        // Primeiro, adiciona todos os menus únicos do mapeamento
        // Itera pelo mapeamento e extrai apenas os valores únicos de menu
        foreach (self::ROUTE_TO_MENU_MAP as $pattern => $menu) {
            if (!in_array($menu, $menus)) {
                $menus[] = $menu;
            }
        }
        
        // Depois, analisa as rotas reais para descobrir novos menus
        try {
            $routes = Route::getRoutes();
            
            foreach ($routes as $route) {
                $uri = '/' . ltrim($route->uri(), '/');
                $menu = $this->getMenuFromRoute($uri);
                
                if ($menu && !in_array($menu, $menus)) {
                    $menus[] = $menu;
                }
            }
        } catch (\Exception $e) {
            // Se houver erro ao obter rotas, continua com os menus do mapeamento
            // Log::warning('Erro ao descobrir menus das rotas: ' . $e->getMessage());
            // Comentado para evitar dependência - o sistema funciona sem logs neste ponto
        }
        
        // Garante que menus padrão estejam presentes (fallback de segurança)
        $defaultMenus = ['dashboard', 'billing', 'bot', 'results', 'marketing', 'settings'];
        foreach ($defaultMenus as $menu) {
            if (!in_array($menu, $menus)) {
                $menus[] = $menu;
            }
        }
        
        // Remove duplicatas e ordena
        $menus = array_unique($menus);
        sort($menus);
        
        return $menus;
    }

    /**
     * Obtém o menu correspondente a uma rota
     *
     * @param string $routeUri URI da rota
     * @return string|null Nome do menu ou null se não encontrado
     */
    private function getMenuFromRoute(string $routeUri): ?string
    {
        // Remove query strings
        $routeUri = strtok($routeUri, '?');
        
        // Remove parâmetros dinâmicos (ex: /bot/123 -> /bot)
        $routeUri = preg_replace('/\/\d+/', '', $routeUri);
        $routeUri = preg_replace('/\{[^}]+\}/', '', $routeUri);
        
        // Verifica correspondência exata primeiro
        if (isset(self::ROUTE_TO_MENU_MAP[$routeUri])) {
            return self::ROUTE_TO_MENU_MAP[$routeUri];
        }
        
        // Verifica padrões com wildcard
        foreach (self::ROUTE_TO_MENU_MAP as $pattern => $menu) {
            if (substr($pattern, -2) === '/*') {
                $prefix = rtrim($pattern, '/*');
                if (strpos($routeUri, $prefix) === 0) {
                    return $menu;
                }
            }
        }
        
        // Tenta inferir o menu baseado no prefixo da rota
        $segments = explode('/', trim($routeUri, '/'));
        if (!empty($segments[0])) {
            $firstSegment = $segments[0];
            
            // Mapeamento de prefixos comuns
            $prefixMap = [
                'bot' => 'bot',
                'results' => 'results',
                'marketing' => 'marketing',
                'settings' => 'settings',
                'billing' => 'billing',
                'dashboard' => 'dashboard',
                'users' => 'settings',
                'user-groups' => 'settings',
                'logs' => 'settings',
                'ftp' => 'settings',
            ];
            
            if (isset($prefixMap[$firstSegment])) {
                return $prefixMap[$firstSegment];
            }
        }
        
        return null;
    }

    /**
     * Obtém todos os recursos disponíveis (menus e outros tipos)
     *
     * @return array Estrutura com todos os recursos disponíveis
     */
    public function discoverAllResources(): array
    {
        return [
            'menus' => $this->discoverAvailableMenus(),
            // Futuramente pode incluir outros tipos de recursos
            // 'features' => $this->discoverFeatures(),
            // 'modules' => $this->discoverModules(),
        ];
    }

    /**
     * Registra um novo recurso no sistema
     * Útil para quando novos recursos são criados programaticamente
     *
     * @param string $type Tipo do recurso (menu, feature, etc.)
     * @param string $identifier Identificador único do recurso
     * @param array $metadata Metadados adicionais do recurso
     * @return void
     */
    public function registerResource(string $type, string $identifier, array $metadata = []): void
    {
        // Futuramente pode implementar um sistema de registro de recursos
        // Por enquanto, os recursos são descobertos automaticamente
    }

    /**
     * Obtém labels amigáveis para os menus
     * 
     * ⚠️ IMPORTANTE: Sempre que um novo menu for criado, adicione o label aqui!
     *
     * @return array Mapeamento de menu => label
     */
    public function getMenuLabels(): array
    {
        return [
            'dashboard' => 'Dashboard',
            'billing' => 'Faturamento',
            'bot' => 'Bot',
            'results' => 'Resultados',
            'marketing' => 'Marketing',
            'settings' => 'Configurações',
        ];
    }

    /**
     * Obtém label amigável para um menu específico
     *
     * @param string $menu Nome do menu
     * @return string Label amigável
     */
    public function getMenuLabel(string $menu): string
    {
        $labels = $this->getMenuLabels();
        return $labels[$menu] ?? ucfirst($menu);
    }

    /**
     * Adiciona um novo menu ao sistema
     * Útil para quando novos menus são criados e precisam ser disponibilizados
     * 
     * ⚠️ NOTA: Este método é para referência futura. Para adicionar um menu permanentemente,
     * edite diretamente ROUTE_TO_MENU_MAP e getMenuLabels()
     *
     * @param string $menuId Identificador do menu (ex: 'reports')
     * @param string $label Label amigável (ex: 'Relatórios')
     * @param array $routePatterns Padrões de rotas que pertencem a este menu (ex: ['/reports/*'])
     * @return void
     */
    public function registerNewMenu(string $menuId, string $label, array $routePatterns = []): void
    {
        // Este método pode ser expandido no futuro para registrar menus dinamicamente
        // Por enquanto, os menus são descobertos automaticamente via discoverAvailableMenus()
        // e os labels são mantidos em getMenuLabels()
        
        // Para adicionar um novo menu permanentemente, edite:
        // 1. ROUTE_TO_MENU_MAP para incluir os padrões de rotas
        // 2. getMenuLabels() para incluir o label do menu
        // 3. Sincronize com frontend/src/components/ProtectedRoute.js
    }
}
