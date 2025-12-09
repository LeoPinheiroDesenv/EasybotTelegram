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
    ];

    /**
     * Executa um comando do artisan
     */
    public function execute(Request $request): JsonResponse
    {
        try {
            $command = $request->input('command');

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

            // Executa o comando
            Artisan::call($command);
            $output = Artisan::output();

            Log::info('Artisan command executed', [
                'command' => $command,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => $this->allowedCommands[$command] . ' executado com sucesso',
                'output' => $output ?: 'Comando executado sem saída',
                'command' => $command,
            ]);

        } catch (\Exception $e) {
            Log::error('Error executing artisan command', [
                'command' => $request->input('command'),
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
        return response()->json([
            'commands' => $this->allowedCommands,
        ]);
    }
}

