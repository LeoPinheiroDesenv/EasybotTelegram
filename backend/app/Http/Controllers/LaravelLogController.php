<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use App\Services\CpanelService;

class LaravelLogController extends Controller
{
    /**
     * Lista os arquivos de log disponíveis
     */
    public function index(): JsonResponse
    {
        try {
            $logsPath = storage_path('logs');
            $logFiles = [];
            
            if (File::exists($logsPath)) {
                $files = File::files($logsPath);
                
                foreach ($files as $file) {
                    if ($file->getExtension() === 'log') {
                        $logFiles[] = [
                            'name' => $file->getFilename(),
                            'path' => $file->getPathname(),
                            'size' => $file->getSize(),
                            'size_human' => $this->formatBytes($file->getSize()),
                            'modified' => $file->getMTime(),
                            'modified_formatted' => date('Y-m-d H:i:s', $file->getMTime()),
                        ];
                    }
                }
                
                // Ordena por data de modificação (mais recente primeiro)
                usort($logFiles, function($a, $b) {
                    return $b['modified'] - $a['modified'];
                });
            }
            
            return response()->json([
                'success' => true,
                'log_files' => $logFiles
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao listar arquivos de log: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao listar arquivos de log: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lê o conteúdo de um arquivo de log
     */
    public function show(Request $request, string $filename): JsonResponse
    {
        try {
            $logsPath = storage_path('logs');
            $filePath = $logsPath . '/' . basename($filename);
            
            // Validação de segurança
            if (!File::exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Arquivo de log não encontrado'
                ], 404);
            }
            
            // Verifica se está dentro do diretório de logs
            $realPath = realpath($filePath);
            $realLogsPath = realpath($logsPath);
            if (!$realPath || strpos($realPath, $realLogsPath) !== 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'Acesso negado ao arquivo'
                ], 403);
            }
            
            $lines = $request->input('lines', 1000); // Padrão: últimas 1000 linhas
            $lines = min(max((int)$lines, 1), 10000); // Limita entre 1 e 10000
            
            $level = $request->input('level'); // Filtrar por nível (debug, info, warning, error, critical)
            $search = $request->input('search'); // Buscar texto
            $tail = $request->input('tail', true); // Se true, pega as últimas linhas
            
            $content = File::get($filePath);
            $allLines = explode("\n", $content);
            
            // Filtra por nível se especificado
            if ($level) {
                $allLines = array_filter($allLines, function($line) use ($level) {
                    return stripos($line, '[' . strtoupper($level) . ']') !== false ||
                           stripos($line, '.'.strtoupper($level).':') !== false;
                });
            }
            
            // Busca texto se especificado
            if ($search) {
                $allLines = array_filter($allLines, function($line) use ($search) {
                    return stripos($line, $search) !== false;
                });
            }
            
            // Reindexa o array após filtros
            $allLines = array_values($allLines);
            
            // Pega as últimas N linhas se tail=true, senão pega as primeiras N
            if ($tail) {
                $allLines = array_slice($allLines, -$lines);
            } else {
                $allLines = array_slice($allLines, 0, $lines);
            }
            
            $fileSize = File::size($filePath);
            $totalLines = count(explode("\n", $content));
            
            return response()->json([
                'success' => true,
                'filename' => $filename,
                'content' => implode("\n", $allLines),
                'lines_count' => count($allLines),
                'total_lines' => $totalLines,
                'file_size' => $fileSize,
                'file_size_human' => $this->formatBytes($fileSize),
                'filters' => [
                    'level' => $level,
                    'search' => $search,
                    'lines' => $lines,
                    'tail' => $tail
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao ler arquivo de log: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao ler arquivo de log: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Deleta um arquivo de log
     */
    public function destroy(string $filename): JsonResponse
    {
        try {
            $logsPath = storage_path('logs');
            $filePath = $logsPath . '/' . basename($filename);
            
            // Validação de segurança
            if (!File::exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Arquivo de log não encontrado'
                ], 404);
            }
            
            // Verifica se está dentro do diretório de logs
            $realPath = realpath($filePath);
            $realLogsPath = realpath($logsPath);
            if (!$realPath || strpos($realPath, $realLogsPath) !== 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'Acesso negado ao arquivo'
                ], 403);
            }
            
            File::delete($filePath);
            
            Log::info('Arquivo de log deletado', [
                'filename' => $filename,
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Arquivo de log deletado com sucesso'
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao deletar arquivo de log: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao deletar arquivo de log: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Limpa o conteúdo de um arquivo de log (sem deletar o arquivo)
     */
    public function clear(string $filename): JsonResponse
    {
        try {
            $logsPath = storage_path('logs');
            $filePath = $logsPath . '/' . basename($filename);
            
            // Validação de segurança
            if (!File::exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Arquivo de log não encontrado'
                ], 404);
            }
            
            // Verifica se está dentro do diretório de logs
            $realPath = realpath($filePath);
            $realLogsPath = realpath($logsPath);
            if (!$realPath || strpos($realPath, $realLogsPath) !== 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'Acesso negado ao arquivo'
                ], 403);
            }
            
            File::put($filePath, '');
            
            Log::info('Arquivo de log limpo', [
                'filename' => $filename,
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Arquivo de log limpo com sucesso'
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao limpar arquivo de log: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao limpar arquivo de log: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Testa a conexão com o cPanel
     */
    public function testCpanelConnection(): JsonResponse
    {
        try {
            $cpanelService = app(CpanelService::class);
            $result = $cpanelService->testConnection();
            
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao testar conexão com cPanel: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Formata bytes para formato legível
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
