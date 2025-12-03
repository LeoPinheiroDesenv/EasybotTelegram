<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class StorageController extends Controller
{
    /**
     * Cria o link simbólico do storage
     */
    public function createStorageLink(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            // Apenas administradores podem criar o link
            if (!$user || !$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Acesso negado. Apenas administradores podem executar esta ação.'
                ], 403);
            }

            $publicPath = public_path('storage');
            $storagePath = storage_path('app/public');

            // Verifica se o diretório storage/app/public existe
            if (!File::exists($storagePath)) {
                File::makeDirectory($storagePath, 0755, true);
            }

            // Remove link existente se houver
            if (File::exists($publicPath) || is_link($publicPath)) {
                if (is_link($publicPath)) {
                    unlink($publicPath);
                } else {
                    // Se não é um link, pode ser um diretório - move para backup
                    $backupPath = $publicPath . '_backup_' . time();
                    File::moveDirectory($publicPath, $backupPath);
                }
            }

            // Cria o link simbólico
            if (PHP_OS_FAMILY === 'Windows') {
                // Windows usa junction
                $command = "mklink /J \"$publicPath\" \"$storagePath\"";
                exec($command, $output, $returnCode);
                if ($returnCode !== 0) {
                    $error = implode("\n", $output);
                    throw new \Exception('Erro ao criar link simbólico no Windows: ' . $error);
                }
            } else {
                // Linux/Unix usa symlink
                // Tenta criar o diretório public se não existir
                $publicDir = dirname($publicPath);
                if (!File::exists($publicDir)) {
                    File::makeDirectory($publicDir, 0755, true);
                }
                
                // Cria o link simbólico
                if (!@symlink($storagePath, $publicPath)) {
                    $error = error_get_last();
                    $errorMsg = $error ? $error['message'] : 'Erro desconhecido';
                    
                    // Verifica se é problema de permissão
                    if (strpos($errorMsg, 'Permission denied') !== false || strpos($errorMsg, 'permission') !== false) {
                        throw new \Exception('Permissão negada. Verifique as permissões do diretório public/. O servidor web precisa ter permissão para criar links simbólicos.');
                    }
                    
                    throw new \Exception('Erro ao criar link simbólico: ' . $errorMsg);
                }
            }

            // Verifica se o link foi criado corretamente
            if (!File::exists($publicPath) || !is_link($publicPath)) {
                throw new \Exception('Link simbólico não foi criado corretamente');
            }

            return response()->json([
                'success' => true,
                'message' => 'Link simbólico criado com sucesso!',
                'public_path' => $publicPath,
                'storage_path' => $storagePath,
                'link_target' => readlink($publicPath)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao criar link simbólico: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verifica o status do link simbólico
     */
    public function checkStorageLink(): JsonResponse
    {
        try {
            $publicPath = public_path('storage');
            $storagePath = storage_path('app/public');

            $exists = File::exists($publicPath);
            $isLink = is_link($publicPath);
            $isDirectory = $exists && !$isLink && is_dir($publicPath);
            $storageExists = File::exists($storagePath);

            $status = 'not_created';
            $message = 'Link simbólico não existe';

            if ($isLink) {
                $linkTarget = readlink($publicPath);
                if ($linkTarget === $storagePath || realpath($linkTarget) === realpath($storagePath)) {
                    $status = 'ok';
                    $message = 'Link simbólico está configurado corretamente';
                } else {
                    $status = 'broken';
                    $message = 'Link simbólico aponta para local incorreto: ' . $linkTarget;
                }
            } elseif ($isDirectory) {
                $status = 'directory_exists';
                $message = 'Existe um diretório em public/storage ao invés de um link simbólico';
            }

            return response()->json([
                'success' => true,
                'status' => $status,
                'message' => $message,
                'public_path' => $publicPath,
                'storage_path' => $storagePath,
                'storage_exists' => $storageExists,
                'public_exists' => $exists,
                'is_link' => $isLink,
                'is_directory' => $isDirectory,
                'link_target' => $isLink ? readlink($publicPath) : null
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao verificar link simbólico: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Testa se o storage está acessível
     */
    public function testStorageAccess(): JsonResponse
    {
        try {
            $testFile = 'test_' . time() . '.txt';
            $testContent = 'Test file created at ' . now()->toDateTimeString();

            // Tenta criar um arquivo de teste
            Storage::disk('public')->put($testFile, $testContent);

            // Verifica se o arquivo foi criado
            $exists = Storage::disk('public')->exists($testFile);

            if (!$exists) {
                throw new \Exception('Arquivo de teste não foi criado');
            }

            // Tenta ler o arquivo
            $content = Storage::disk('public')->get($testFile);

            // Tenta obter a URL
            $url = Storage::disk('public')->url($testFile);

            // Remove o arquivo de teste
            Storage::disk('public')->delete($testFile);

            // Verifica se a URL é acessível
            $urlAccessible = false;
            try {
                $publicPath = public_path('storage/' . $testFile);
                $urlAccessible = File::exists($publicPath);
            } catch (\Exception $e) {
                // Ignora erro
            }

            return response()->json([
                'success' => true,
                'message' => 'Storage está funcionando corretamente',
                'file_created' => $exists,
                'file_readable' => $content === $testContent,
                'url_generated' => !empty($url),
                'url_accessible' => $urlAccessible,
                'test_url' => $url,
                'storage_path' => storage_path('app/public'),
                'public_path' => public_path('storage')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao testar storage: ' . $e->getMessage()
            ], 500);
        }
    }
}

