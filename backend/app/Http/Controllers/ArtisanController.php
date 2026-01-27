<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class ArtisanController extends Controller
{
    /**
     * Lista de comandos permitidos (por segurança)
     */
    private $allowedCommands = [
        'cache:clear' => 'Limpar cache da aplicação',
        'config:clear' => 'Limpar cache de configuração',
        'route:clear' => 'Limpar cache de rotas',
        'view:clear' => 'Limpar cache de views',
        'pix:crc-diagnostic-report' => 'Gerar relatório de diagnóstico de CRC PIX',
        'pix:check-expiration' => 'Verificar expiração de PIX pendentes e notificar usuários',
        'check:group-link-expiration' => 'Verificar expiração de links de grupo e notificar usuários',
        'payments:check-pending' => 'Verificar pagamentos pendentes e processar aprovações automaticamente',
    ];

    /**
     * Executa um comando do artisan
     */
    public function execute(Request $request): JsonResponse
    {
        try {
            $command = $request->input('command');
            $parameters = $request->input('parameters', []);

            if (!$command) {
                return response()->json([
                    'success' => false,
                    'error' => 'Comando não especificado'
                ], 400);
            }

            // Verifica se o comando é permitido
            if (!array_key_exists($command, $this->allowedCommands)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Comando não permitido: ' . $command
                ], 403);
            }

            // Prepara parâmetros para comandos específicos
            $commandParameters = [];
            
            // Comando pix:crc-diagnostic-report com parâmetros padrão
            if ($command === 'pix:crc-diagnostic-report') {
                $days = $parameters['days'] ?? 7;
                $output = $parameters['output'] ?? 'console';
                
                $commandParameters = [
                    '--days' => (int) $days,
                    '--output' => $output,
                ];
            }
            
            // Comando pix:check-expiration com parâmetros
            if ($command === 'pix:check-expiration') {
                if (isset($parameters['bot_id']) && $parameters['bot_id'] !== '') {
                    $commandParameters['--bot-id'] = (int) $parameters['bot_id'];
                }
                
                if (isset($parameters['dry_run']) && $parameters['dry_run'] === true) {
                    $commandParameters['--dry-run'] = true;
                }
            }
            
            // Comando check:group-link-expiration com parâmetros
            if ($command === 'check:group-link-expiration') {
                if (isset($parameters['dry_run']) && $parameters['dry_run'] === true) {
                    $commandParameters['--dry-run'] = true;
                }
            }
            
            // Comando payments:check-pending com parâmetros
            if ($command === 'payments:check-pending') {
                if (isset($parameters['bot_id']) && $parameters['bot_id'] !== '') {
                    $commandParameters['--bot-id'] = (int) $parameters['bot_id'];
                }
                
                if (isset($parameters['interval']) && $parameters['interval'] !== '') {
                    $commandParameters['--interval'] = (int) $parameters['interval'];
                }
            }

            // Executa o comando com parâmetros
            Artisan::call($command, $commandParameters);
            $output = Artisan::output();

            Log::info('Artisan command executed', [
                'command' => $command,
                'parameters' => $commandParameters,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => $this->allowedCommands[$command] . ' executado com sucesso',
                'output' => $output ?: 'Comando executado sem saída',
                'command' => $command,
                'parameters' => $commandParameters,
            ]);

        } catch (\Exception $e) {
            Log::error('Error executing artisan command', [
                'command' => $request->input('command'),
                'parameters' => $request->input('parameters', []),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erro ao executar comando: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Limpa todos os caches
     */
    public function clearAllCaches(): JsonResponse
    {
        try {
            $commands = [
                'cache:clear',
                'config:clear',
                'route:clear',
                'view:clear',
            ];

            $results = [];
            $hasError = false;

            foreach ($commands as $command) {
                try {
                    Artisan::call($command);
                    $output = Artisan::output();
                    $results[] = [
                        'command' => $command,
                        'description' => $this->allowedCommands[$command],
                        'success' => true,
                        'output' => $output ?: 'Executado com sucesso',
                    ];
                } catch (\Exception $e) {
                    $hasError = true;
                    $results[] = [
                        'command' => $command,
                        'description' => $this->allowedCommands[$command],
                        'success' => false,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            Log::info('All caches cleared', [
                'user_id' => auth()->id(),
                'results' => $results,
            ]);

            return response()->json([
                'success' => !$hasError,
                'message' => $hasError 
                    ? 'Alguns caches foram limpos, mas houve erros' 
                    : 'Todos os caches foram limpos com sucesso',
                'results' => $results,
            ]);

        } catch (\Exception $e) {
            Log::error('Error clearing all caches', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erro ao limpar caches: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lista comandos disponíveis
     */
    public function availableCommands(): JsonResponse
    {
        $commands = [];
        
        foreach ($this->allowedCommands as $command => $description) {
            $commandInfo = [
                'command' => $command,
                'description' => $description,
                'parameters' => [],
            ];
            
            // Adiciona parâmetros específicos para cada comando
            if ($command === 'pix:crc-diagnostic-report') {
                $commandInfo['parameters'] = [
                    'days' => [
                        'type' => 'integer',
                        'default' => 7,
                        'description' => 'Número de dias para analisar',
                        'required' => false,
                    ],
                    'output' => [
                        'type' => 'string',
                        'default' => 'console',
                        'description' => 'Formato de saída (console, json, file)',
                        'required' => false,
                        'options' => ['console', 'json', 'file'],
                    ],
                ];
            }
            
            if ($command === 'pix:check-expiration') {
                $commandInfo['parameters'] = [
                    'bot_id' => [
                        'type' => 'integer',
                        'default' => null,
                        'description' => 'ID do bot específico (opcional - deixe vazio para verificar todos)',
                        'required' => false,
                    ],
                    'dry_run' => [
                        'type' => 'boolean',
                        'default' => false,
                        'description' => 'Simular sem enviar notificações',
                        'required' => false,
                    ],
                ];
            }
            
            if ($command === 'check:group-link-expiration') {
                $commandInfo['parameters'] = [
                    'dry_run' => [
                        'type' => 'boolean',
                        'default' => false,
                        'description' => 'Simular sem enviar notificações (modo dry-run)',
                        'required' => false,
                    ],
                ];
            }
            
            if ($command === 'payments:check-pending') {
                $commandInfo['parameters'] = [
                    'bot_id' => [
                        'type' => 'integer',
                        'default' => null,
                        'description' => 'ID do bot específico (opcional - deixe vazio para verificar todos)',
                        'required' => false,
                    ],
                    'interval' => [
                        'type' => 'integer',
                        'default' => 30,
                        'description' => 'Intervalo em segundos desde a última verificação (padrão: 30)',
                        'required' => false,
                    ],
                ];
            }
            
            $commands[$command] = $commandInfo;
        }
        
        return response()->json([
            'commands' => $commands,
        ]);
    }
}

