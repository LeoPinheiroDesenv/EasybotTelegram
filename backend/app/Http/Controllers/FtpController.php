<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Exception;

class FtpController extends Controller
{
    /**
     * Lista arquivos e diretórios no FTP
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function listFiles(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'path' => 'nullable|string',
                'disk' => 'nullable|string|in:ftp,sftp'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => $validator->errors()->first()
                ], 400);
            }

            $disk = $request->input('disk', 'ftp');
            $path = $request->input('path', '/');

            // Verifica se o disco está configurado
            if (!config("filesystems.disks.{$disk}")) {
                return response()->json([
                    'success' => false,
                    'error' => "Disco {$disk} não está configurado. Verifique as variáveis de ambiente."
                ], 400);
            }

            try {
                $files = Storage::disk($disk)->files($path);
                $directories = Storage::disk($disk)->directories($path);

                $fileList = [];
                
                // Processa arquivos
                foreach ($files as $file) {
                    try {
                        $size = Storage::disk($disk)->size($file);
                        $lastModified = Storage::disk($disk)->lastModified($file);
                        
                        $fileList[] = [
                            'name' => basename($file),
                            'path' => $file,
                            'type' => 'file',
                            'size' => $size,
                            'size_formatted' => $this->formatBytes($size),
                            'last_modified' => date('Y-m-d H:i:s', $lastModified),
                            'extension' => pathinfo($file, PATHINFO_EXTENSION)
                        ];
                    } catch (Exception $e) {
                        Log::warning("Erro ao obter informações do arquivo: {$file}", [
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                // Processa diretórios
                foreach ($directories as $directory) {
                    $fileList[] = [
                        'name' => basename($directory),
                        'path' => $directory,
                        'type' => 'directory',
                        'size' => null,
                        'size_formatted' => '-',
                        'last_modified' => null,
                        'extension' => null
                    ];
                }

                // Ordena: diretórios primeiro, depois arquivos
                usort($fileList, function ($a, $b) {
                    if ($a['type'] === $b['type']) {
                        return strcmp($a['name'], $b['name']);
                    }
                    return $a['type'] === 'directory' ? -1 : 1;
                });

                return response()->json([
                    'success' => true,
                    'path' => $path,
                    'files' => $fileList
                ]);
            } catch (Exception $e) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro ao listar arquivos: ' . $e->getMessage()
                ], 500);
            }
        } catch (Exception $e) {
            Log::error('Erro ao listar arquivos FTP', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erro ao listar arquivos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Faz upload de arquivo para o FTP
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function uploadFile(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'file' => 'required|file|max:10240', // 10MB máximo
                'path' => 'nullable|string',
                'disk' => 'nullable|string|in:ftp,sftp'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => $validator->errors()->first()
                ], 400);
            }

            $disk = $request->input('disk', 'ftp');
            $path = $request->input('path', '/');
            $file = $request->file('file');
            $fileName = $file->getClientOriginalName();

            // Verifica se o disco está configurado
            if (!config("filesystems.disks.{$disk}")) {
                return response()->json([
                    'success' => false,
                    'error' => "Disco {$disk} não está configurado. Verifique as variáveis de ambiente."
                ], 400);
            }

            try {
                // Normaliza o path
                $path = rtrim($path, '/') . '/';
                $fullPath = $path . $fileName;

                // Faz upload do arquivo
                Storage::disk($disk)->putFileAs($path, $file, $fileName);

                return response()->json([
                    'success' => true,
                    'message' => 'Arquivo enviado com sucesso',
                    'path' => $fullPath,
                    'file' => [
                        'name' => $fileName,
                        'path' => $fullPath,
                        'size' => $file->getSize(),
                        'size_formatted' => $this->formatBytes($file->getSize())
                    ]
                ]);
            } catch (Exception $e) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro ao fazer upload: ' . $e->getMessage()
                ], 500);
            }
        } catch (Exception $e) {
            Log::error('Erro ao fazer upload FTP', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erro ao fazer upload: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Faz download de arquivo do FTP
     *
     * @param Request $request
     * @return \Illuminate\Http\Response|JsonResponse
     */
    public function downloadFile(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'path' => 'required|string',
                'disk' => 'nullable|string|in:ftp,sftp'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => $validator->errors()->first()
                ], 400);
            }

            $disk = $request->input('disk', 'ftp');
            $path = $request->input('path');

            // Verifica se o disco está configurado
            if (!config("filesystems.disks.{$disk}")) {
                return response()->json([
                    'success' => false,
                    'error' => "Disco {$disk} não está configurado. Verifique as variáveis de ambiente."
                ], 400);
            }

            try {
                // Verifica se o arquivo existe
                if (!Storage::disk($disk)->exists($path)) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Arquivo não encontrado'
                    ], 404);
                }

                // Obtém o conteúdo do arquivo
                $content = Storage::disk($disk)->get($path);
                $fileName = basename($path);

                return response($content)
                    ->header('Content-Type', Storage::disk($disk)->mimeType($path))
                    ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"');
            } catch (Exception $e) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro ao fazer download: ' . $e->getMessage()
                ], 500);
            }
        } catch (Exception $e) {
            Log::error('Erro ao fazer download FTP', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erro ao fazer download: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Deleta arquivo ou diretório do FTP
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function deleteFile(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'path' => 'required|string',
                'disk' => 'nullable|string|in:ftp,sftp'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => $validator->errors()->first()
                ], 400);
            }

            $disk = $request->input('disk', 'ftp');
            $path = $request->input('path');

            // Verifica se o disco está configurado
            if (!config("filesystems.disks.{$disk}")) {
                return response()->json([
                    'success' => false,
                    'error' => "Disco {$disk} não está configurado. Verifique as variáveis de ambiente."
                ], 400);
            }

            try {
                // Verifica se existe
                if (!Storage::disk($disk)->exists($path)) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Arquivo ou diretório não encontrado'
                    ], 404);
                }

                // Verifica se é diretório
                if (Storage::disk($disk)->exists($path) && count(Storage::disk($disk)->files($path)) > 0) {
                    // Tenta deletar diretório (deve estar vazio)
                    Storage::disk($disk)->deleteDirectory($path);
                } else {
                    // Deleta arquivo
                    Storage::disk($disk)->delete($path);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Arquivo ou diretório deletado com sucesso'
                ]);
            } catch (Exception $e) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro ao deletar: ' . $e->getMessage()
                ], 500);
            }
        } catch (Exception $e) {
            Log::error('Erro ao deletar arquivo FTP', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erro ao deletar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cria um diretório no FTP
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createDirectory(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'path' => 'required|string',
                'name' => 'required|string',
                'disk' => 'nullable|string|in:ftp,sftp'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => $validator->errors()->first()
                ], 400);
            }

            $disk = $request->input('disk', 'ftp');
            $path = $request->input('path', '/');
            $name = $request->input('name');

            // Verifica se o disco está configurado
            if (!config("filesystems.disks.{$disk}")) {
                return response()->json([
                    'success' => false,
                    'error' => "Disco {$disk} não está configurado. Verifique as variáveis de ambiente."
                ], 400);
            }

            try {
                // Normaliza o path
                $path = rtrim($path, '/') . '/';
                $fullPath = $path . $name;

                // Cria o diretório
                Storage::disk($disk)->makeDirectory($fullPath);

                return response()->json([
                    'success' => true,
                    'message' => 'Diretório criado com sucesso',
                    'path' => $fullPath
                ]);
            } catch (Exception $e) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro ao criar diretório: ' . $e->getMessage()
                ], 500);
            }
        } catch (Exception $e) {
            Log::error('Erro ao criar diretório FTP', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erro ao criar diretório: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verifica conexão com o FTP
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function testConnection(Request $request): JsonResponse
    {
        try {
            $disk = $request->input('disk', 'ftp');

            // Verifica se o disco está configurado
            if (!config("filesystems.disks.{$disk}")) {
                return response()->json([
                    'success' => false,
                    'error' => "Disco {$disk} não está configurado. Verifique as variáveis de ambiente."
                ], 400);
            }

            try {
                // Tenta listar o diretório raiz
                Storage::disk($disk)->files('/');

                return response()->json([
                    'success' => true,
                    'message' => 'Conexão com FTP estabelecida com sucesso'
                ]);
            } catch (Exception $e) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro ao conectar: ' . $e->getMessage()
                ], 500);
            }
        } catch (Exception $e) {
            Log::error('Erro ao testar conexão FTP', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erro ao testar conexão: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Formata bytes para formato legível
     *
     * @param int $bytes
     * @return string
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}

