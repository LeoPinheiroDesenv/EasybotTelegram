<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class CpanelService
{
    protected $host;
    protected $username;
    protected $token;
    protected $port;
    protected $useSsl;

    public function __construct()
    {
        $this->host = env('CPANEL_HOST');
        $this->username = env('CPANEL_USERNAME');
        $this->token = env('CPANEL_API_TOKEN');
        $this->port = env('CPANEL_PORT', 2083);
        $this->useSsl = env('CPANEL_USE_SSL', true);
    }

    /**
     * Verifica se o cPanel está configurado
     */
    public function isConfigured(): bool
    {
        return !empty($this->host) && !empty($this->username) && !empty($this->token);
    }

    /**
     * Cria um cron job no cPanel
     * Tenta múltiplos endpoints da API do cPanel (UAPI e API 2)
     */
    public function createCronJob(string $command, string $frequency, string $description = null): array
    {
        if (!$this->isConfigured()) {
            throw new Exception('cPanel não está configurado. Configure CPANEL_HOST, CPANEL_USERNAME e CPANEL_API_TOKEN no .env');
        }

        $protocol = $this->useSsl ? 'https' : 'http';
        $minute = $this->parseCronMinute($frequency);
        $hour = $this->parseCronHour($frequency);
        $day = $this->parseCronDay($frequency);
        $month = $this->parseCronMonth($frequency);
        $weekday = $this->parseCronWeekday($frequency);
        $comment = $description ?? 'Cron job criado automaticamente';

        // Parâmetros para a API
        $params = [
            'command' => $command,
            'minute' => $minute,
            'hour' => $hour,
            'day' => $day,
            'month' => $month,
            'weekday' => $weekday,
            'comment' => $comment,
        ];

        // Tenta múltiplos endpoints, priorizando API 2 (execute) que geralmente tem menos restrições
        // UAPI pode retornar 403 se o token não tiver permissões específicas
        $endpoints = [
            "/execute/Cron/add_line",  // API 2 - geralmente mais compatível
            "/execute/Cron/set_cron",  // API 2 alternativa
            "/uapi/Cron/add_line",     // UAPI - pode retornar 403 se sem permissões
        ];

        $lastError = null;
        $moduleUnavailableErrors = []; // Armazena erros de módulo não disponível (autenticação OK)
        $authErrors = []; // Armazena erros de autenticação/permissão
        
        foreach ($endpoints as $endpoint) {
            try {
                $url = "{$protocol}://{$this->host}:{$this->port}{$endpoint}";
                
                Log::debug('Tentando criar cron job no cPanel', [
                    'endpoint' => $endpoint,
                    'url' => $url,
                    'params' => $params
                ]);

                // Formato correto: Authorization: cpanel username:TOKEN
                $authHeader = "cpanel {$this->username}:{$this->token}";
                
                Log::debug('Fazendo requisição ao cPanel', [
                    'url' => $url,
                    'auth_header_preview' => substr($authHeader, 0, 20) . '...',
                    'username' => $this->username,
                    'token_length' => strlen($this->token),
                    'params' => $params
                ]);

                $response = Http::timeout(30)
                    ->withHeaders([
                        'Authorization' => $authHeader,
                        'Content-Type' => 'application/x-www-form-urlencoded',
                    ])
                    ->asForm()
                    ->post($url, $params);

                $statusCode = $response->status();
                $responseBody = $response->body();
                
                // Tenta parsear JSON, se falhar usa o body como string
                $responseData = null;
                try {
                    $responseData = $response->json();
                } catch (\Exception $e) {
                    $responseData = ['raw_body' => $responseBody];
                }

                Log::info('Resposta do cPanel ao criar cron job', [
                    'endpoint' => $endpoint,
                    'status_code' => $statusCode,
                    'response_body_preview' => substr($responseBody, 0, 500),
                    'response_data' => $responseData,
                    'command' => substr($command, 0, 100) . '...',
                    'frequency' => $frequency
                ]);

                // Trata erro 403 (Forbidden) - token pode não ter permissão para este endpoint
                if ($statusCode === 403) {
                    $errorMsg = "Acesso negado (403) para o endpoint {$endpoint}. O token pode não ter permissões para este endpoint ou a URL está incorreta.";
                    
                    // Verifica se a mensagem indica problema de autenticação
                    if (str_contains($responseBody, 'Token authentication') || 
                        str_contains($responseBody, 'Forbidden') ||
                        str_contains($responseBody, 'access denied')) {
                        $errorMsg = "Acesso negado (403) para o endpoint {$endpoint}. O token de API pode não ter permissões para UAPI ou este endpoint específico.";
                    }
                    
                    $authErrors[] = $errorMsg;
                    $lastError = $errorMsg;
                    Log::warning('Erro 403 ao criar cron job no cPanel', [
                        'endpoint' => $endpoint,
                        'error' => $errorMsg,
                        'response_preview' => substr($responseBody, 0, 300)
                    ]);
                    continue; // Tenta próximo endpoint
                }

                if ($response->successful()) {
                    // UAPI retorna estrutura diferente
                    if (isset($responseData['status']) && $responseData['status'] === 1) {
                        $cronId = $responseData['data']['id'] ?? $responseData['data']['line'] ?? null;
                        
                        Log::info('Cron job criado no cPanel com sucesso', [
                            'endpoint' => $endpoint,
                            'cron_id' => $cronId,
                            'command' => $command,
                            'frequency' => $frequency,
                            'minute' => $minute,
                            'hour' => $hour,
                            'day' => $day,
                            'month' => $month,
                            'weekday' => $weekday
                        ]);
                        
                        return [
                            'success' => true,
                            'cron_id' => $cronId,
                            'message' => 'Cron job criado no cPanel com sucesso',
                            'endpoint_used' => $endpoint
                        ];
                    }
                    // UAPI pode retornar success: true sem status
                    elseif (isset($responseData['status']) && $responseData['status'] === true) {
                        $cronId = $responseData['data']['id'] ?? $responseData['data']['line'] ?? null;
                        
                        Log::info('Cron job criado no cPanel com sucesso (UAPI)', [
                            'endpoint' => $endpoint,
                            'cron_id' => $cronId,
                            'command' => $command
                        ]);
                        
                        return [
                            'success' => true,
                            'cron_id' => $cronId,
                            'message' => 'Cron job criado no cPanel com sucesso',
                            'endpoint_used' => $endpoint
                        ];
                    }
                    // Verifica se há erros na resposta
                    elseif (isset($responseData['errors']) || isset($responseData['error'])) {
                        $error = $responseData['errors'] ?? $responseData['error'] ?? 'Erro desconhecido';
                        $errorStr = is_array($error) ? json_encode($error) : $error;
                        
                        // Verifica se o erro é sobre módulo não disponível
                        if (str_contains($errorStr, 'Can\'t locate Cpanel') || 
                            str_contains($errorStr, 'Failed to load module') ||
                            (str_contains($errorStr, 'module') && str_contains($errorStr, 'not available'))) {
                            // Autenticação funcionou (HTTP 200), mas o módulo não está disponível
                            $moduleError = "Módulo Cron não está disponível neste servidor cPanel. O módulo Perl 'Cpanel::API::Cron' não está instalado.";
                            $moduleUnavailableErrors[] = $moduleError;
                            $lastError = $moduleError;
                            
                            Log::warning('Módulo Cron não disponível no cPanel', [
                                'endpoint' => $endpoint,
                                'error' => $error,
                                'note' => 'Autenticação OK (HTTP 200), mas módulo não disponível'
                            ]);
                            continue; // Tenta próximo endpoint
                        }
                        
                        $lastError = "Erro na resposta do cPanel ({$endpoint}): " . $errorStr;
                        Log::warning('Erro na resposta do cPanel', [
                            'endpoint' => $endpoint,
                            'error' => $error,
                            'response' => $responseData
                        ]);
                        continue; // Tenta próximo endpoint
                    } else {
                        $lastError = "Resposta inesperada do cPanel ({$endpoint}): " . json_encode($responseData);
                        Log::warning('Resposta inesperada do cPanel', [
                            'endpoint' => $endpoint,
                            'response' => $responseData
                        ]);
                        continue; // Tenta próximo endpoint
                    }
                } else {
                    $lastError = "Erro HTTP {$statusCode} ao criar cron job no cPanel ({$endpoint}): {$responseBody}";
                    Log::warning('Erro HTTP ao criar cron job no cPanel', [
                        'endpoint' => $endpoint,
                        'status_code' => $statusCode,
                        'response_body' => $responseBody
                    ]);
                    // Continua tentando outros endpoints
                }
            } catch (Exception $e) {
                $lastError = "Exceção ao tentar criar cron job no cPanel ({$endpoint}): " . $e->getMessage();
                Log::warning('Exceção ao criar cron job no cPanel', [
                    'endpoint' => $endpoint,
                    'error' => $e->getMessage()
                ]);
                // Continua tentando outros endpoints
            }
        }

        // Se chegou aqui, nenhum endpoint funcionou
        // Prioriza mensagem sobre módulo não disponível (autenticação OK) sobre erros de permissão
        if (!empty($moduleUnavailableErrors)) {
            // Todos os erros foram sobre módulo não disponível - autenticação funcionou!
            $errorMessage = "O módulo Cron não está disponível neste servidor cPanel. O módulo Perl 'Cpanel::API::Cron' não está instalado. " .
                           "A autenticação com o cPanel funcionou corretamente, mas as APIs de Cron não estão disponíveis. " .
                           "Você precisa criar os cron jobs manualmente no cPanel usando os comandos gerados pela aplicação.";
            
            Log::warning('Falha ao criar cron job no cPanel - módulo não disponível', [
                'command' => $command,
                'frequency' => $frequency,
                'module_errors' => $moduleUnavailableErrors,
                'endpoints_tried' => $endpoints,
                'note' => 'Autenticação OK, mas módulo Cron não disponível'
            ]);
            
            throw new Exception($errorMessage);
        }
        
        // Se houver erros de autenticação, prioriza esses
        if (!empty($authErrors)) {
            $errorMessage = "Não foi possível criar cron job no cPanel. Erros de autenticação/permissão: " . implode('; ', array_unique($authErrors)) . 
                           ". Verifique se o token de API tem permissões para gerenciar cron jobs.";
            
            Log::error('Falha ao criar cron job no cPanel - erros de autenticação', [
                'command' => $command,
                'frequency' => $frequency,
                'auth_errors' => $authErrors,
                'endpoints_tried' => $endpoints
            ]);
            
            throw new Exception($errorMessage);
        }
        
        // Verifica se o erro é sobre módulo não disponível (fallback)
        $isModuleError = str_contains($lastError ?? '', 'Can\'t locate Cpanel') || 
                        str_contains($lastError ?? '', 'Failed to load module') ||
                        str_contains($lastError ?? '', 'Módulo Cron não está disponível');
        
        if ($isModuleError) {
            Log::warning('Falha ao criar cron job no cPanel - módulo não disponível (fallback)', [
                'command' => $command,
                'frequency' => $frequency,
                'last_error' => $lastError,
                'endpoints_tried' => $endpoints
            ]);
            
            throw new Exception("O módulo Cron não está disponível neste servidor cPanel. O módulo Perl 'Cpanel::API::Cron' não está instalado. Você precisa criar os cron jobs manualmente no cPanel usando os comandos curl/wget gerados pela aplicação. Acesse a tela de Cron Jobs na aplicação para ver os comandos prontos para copiar.");
        }
        
        Log::error('Falha ao criar cron job no cPanel - todos os endpoints falharam', [
            'command' => $command,
            'frequency' => $frequency,
            'last_error' => $lastError,
            'endpoints_tried' => $endpoints
        ]);
        
        throw new Exception("Não foi possível criar cron job no cPanel. Último erro: {$lastError}. Verifique se a API do cPanel está habilitada e se o token tem permissões de Cron. Se o módulo Cron não estiver disponível, você pode criar os cron jobs manualmente no cPanel usando os comandos gerados pela aplicação.");
    }

    /**
     * Atualiza um cron job no cPanel
     */
    public function updateCronJob(int $cronId, string $command, string $frequency, string $description = null): array
    {
        if (!$this->isConfigured()) {
            throw new Exception('cPanel não está configurado');
        }

        try {
            // Primeiro remove o cron job antigo
            $this->deleteCronJob($cronId);
            
            // Depois cria um novo com os novos parâmetros
            return $this->createCronJob($command, $frequency, $description);
        } catch (Exception $e) {
            Log::error('Erro ao atualizar cron job no cPanel', [
                'cron_id' => $cronId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Remove um cron job do cPanel
     * Tenta múltiplos endpoints da API do cPanel
     */
    public function deleteCronJob(int $cronId): array
    {
        if (!$this->isConfigured()) {
            throw new Exception('cPanel não está configurado');
        }

        $protocol = $this->useSsl ? 'https' : 'http';
        $endpoints = [
            "/uapi/Cron/remove_line",
            "/execute/Cron/remove_line",
        ];

        $lastError = null;

        foreach ($endpoints as $endpoint) {
            try {
                $url = "{$protocol}://{$this->host}:{$this->port}{$endpoint}";

                $authHeader = "cpanel {$this->username}:{$this->token}";
                
                $response = Http::timeout(30)
                    ->withHeaders([
                        'Authorization' => $authHeader,
                        'Content-Type' => 'application/x-www-form-urlencoded',
                    ])
                    ->asForm()
                    ->post($url, [
                        'line' => $cronId,
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    
                    if ((isset($data['status']) && ($data['status'] === 1 || $data['status'] === true))) {
                        Log::info('Cron job removido do cPanel com sucesso', [
                            'endpoint' => $endpoint,
                            'cron_id' => $cronId
                        ]);
                        
                        return [
                            'success' => true,
                            'message' => 'Cron job removido do cPanel com sucesso'
                        ];
                    } else {
                        // Se não encontrar, considera sucesso (já foi removido)
                        if (isset($data['errors']) && is_array($data['errors'])) {
                            $errorMsg = json_encode($data['errors']);
                            if (str_contains($errorMsg, 'not found') || str_contains($errorMsg, 'does not exist')) {
                                Log::info('Cron job não encontrado no cPanel (já foi removido)', [
                                    'endpoint' => $endpoint,
                                    'cron_id' => $cronId
                                ]);
                                return [
                                    'success' => true,
                                    'message' => 'Cron job não encontrado (já foi removido)'
                                ];
                            }
                        }
                        
                        $error = $data['errors'] ?? $data['message'] ?? 'Erro desconhecido';
                        $lastError = "Erro ao remover cron job do cPanel ({$endpoint}): " . json_encode($error);
                        continue; // Tenta próximo endpoint
                    }
                } else {
                    $lastError = "Erro HTTP {$response->status()} ao remover cron job do cPanel ({$endpoint}): {$response->body()}";
                    continue; // Tenta próximo endpoint
                }
            } catch (Exception $e) {
                $lastError = "Exceção ao remover cron job do cPanel ({$endpoint}): " . $e->getMessage();
                continue; // Tenta próximo endpoint
            }
        }

        // Se chegou aqui, nenhum endpoint funcionou
        Log::error('Falha ao remover cron job do cPanel - todos os endpoints falharam', [
            'cron_id' => $cronId,
            'last_error' => $lastError
        ]);
        
        throw new Exception("Não foi possível remover cron job do cPanel. Último erro: {$lastError}");
    }

    /**
     * Lista todos os cron jobs do cPanel
     * Tenta múltiplos endpoints da API do cPanel
     * Nota: Alguns servidores podem não ter o módulo Cron disponível
     */
    public function listCronJobs(): array
    {
        if (!$this->isConfigured()) {
            throw new Exception('cPanel não está configurado');
        }

        $protocol = $this->useSsl ? 'https' : 'http';
        $endpoints = [
            "/uapi/Cron/list_crons",
            "/execute/Cron/list_crons",
        ];

        $lastError = null;

        foreach ($endpoints as $endpoint) {
            try {
                $url = "{$protocol}://{$this->host}:{$this->port}{$endpoint}";

                $authHeader = "cpanel {$this->username}:{$this->token}";
                
                $response = Http::timeout(30)
                    ->withHeaders([
                        'Authorization' => $authHeader,
                    ])
                    ->get($url);

                if ($response->successful()) {
                    $data = $response->json();
                    
                    if (isset($data['status']) && ($data['status'] === 1 || $data['status'] === true)) {
                        return [
                            'success' => true,
                            'cron_jobs' => $data['data'] ?? []
                        ];
                    } else {
                        $error = $data['errors'] ?? $data['message'] ?? 'Erro desconhecido';
                        $errorStr = is_array($error) ? json_encode($error) : $error;
                        
                        // Verifica se o erro é sobre módulo não disponível
                        if (str_contains($errorStr, 'Can\'t locate Cpanel') || 
                            str_contains($errorStr, 'Failed to load module') ||
                            str_contains($errorStr, 'module') && str_contains($errorStr, 'not available')) {
                            $lastError = "Módulo Cron não está disponível neste servidor cPanel. O módulo Perl 'Cpanel::API::Cron' não está instalado. Você ainda pode criar cron jobs manualmente no cPanel usando os comandos gerados pela aplicação.";
                            continue; // Tenta próximo endpoint
                        }
                        
                        $lastError = "Erro ao listar cron jobs do cPanel ({$endpoint}): " . $errorStr;
                        continue; // Tenta próximo endpoint
                    }
                } else {
                    $lastError = "Erro HTTP {$response->status()} ao listar cron jobs do cPanel ({$endpoint}): {$response->body()}";
                    continue; // Tenta próximo endpoint
                }
            } catch (Exception $e) {
                $lastError = "Exceção ao listar cron jobs do cPanel ({$endpoint}): " . $e->getMessage();
                continue; // Tenta próximo endpoint
            }
        }

        // Se chegou aqui, nenhum endpoint funcionou
        Log::warning('Falha ao listar cron jobs do cPanel - todos os endpoints falharam', [
            'last_error' => $lastError
        ]);
        
        throw new Exception("Não foi possível listar cron jobs do cPanel. {$lastError}");
    }

    /**
     * Testa a conexão com o cPanel
     * Usa uma API genérica que sempre está disponível
     */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'cPanel não está configurado. Configure CPANEL_HOST, CPANEL_USERNAME e CPANEL_API_TOKEN no .env'
            ];
        }

        $protocol = $this->useSsl ? 'https' : 'http';
        
        // Tenta múltiplas APIs genéricas para testar a conexão
        // Prioriza UAPI (API v3) que é mais moderna e sempre disponível
        // Se UAPI não funcionar, tenta APIs mais básicas
        $testEndpoints = [
            '/uapi/Misc/version',
            '/uapi/Misc/account_info',
            '/uapi/Email/list_pops', // API simples que sempre funciona
            '/uapi/StatsBar/get_stats', // API de estatísticas
            '/uapi/API2/get_theme', // API básica de tema
            '/execute/API2/get_theme', // API 2 como fallback
        ];

        $lastError = null;
        $cronJobsCount = null;
        $authWorked = false; // Flag para indicar se a autenticação funcionou (HTTP 200 recebido)

        foreach ($testEndpoints as $endpoint) {
            try {
                $url = "{$protocol}://{$this->host}:{$this->port}{$endpoint}";
                $authHeader = "cpanel {$this->username}:{$this->token}";
                
                Log::debug('Testando conexão com cPanel', [
                    'endpoint' => $endpoint,
                    'url' => $url,
                    'host' => $this->host,
                    'port' => $this->port,
                    'username' => $this->username,
                    'token_length' => strlen($this->token)
                ]);
                
                $response = Http::timeout(30)
                    ->withHeaders([
                        'Authorization' => $authHeader,
                    ])
                    ->get($url);

                $statusCode = $response->status();
                $responseBody = $response->body();
                
                // Tenta parsear JSON
                $data = null;
                try {
                    $data = $response->json();
                } catch (\Exception $e) {
                    // Se não for JSON, verifica se é HTML (pode ser página de erro)
                    if (str_contains($responseBody, '<html') || str_contains($responseBody, '<!DOCTYPE')) {
                        $lastError = "Resposta HTML recebida ({$endpoint}) - pode ser página de erro ou autenticação falhou";
                        Log::warning('Resposta HTML recebida do cPanel', [
                            'endpoint' => $endpoint,
                            'status_code' => $statusCode,
                            'response_preview' => substr($responseBody, 0, 200)
                        ]);
                        continue;
                    }
                    $data = ['raw_response' => $responseBody];
                }
                
                if ($statusCode === 200 || $statusCode === 201) {
                    // Verifica se há erros na resposta (mesmo com HTTP 200)
                    $hasErrors = isset($data['errors']) && !empty($data['errors']);
                    $errorStr = null;
                    
                    if ($hasErrors) {
                        $errorStr = is_array($data['errors']) ? json_encode($data['errors']) : $data['errors'];
                        
                        // Verifica se o erro é sobre módulo não disponível
                        // Se for apenas erro de módulo, a autenticação funcionou!
                        if (str_contains($errorStr, 'Can\'t locate Cpanel') || 
                            str_contains($errorStr, 'Failed to load module') ||
                            (str_contains($errorStr, 'module') && str_contains($errorStr, 'not available'))) {
                            
                            // Autenticação funcionou, mas o módulo não está disponível
                            // Isso é considerado sucesso parcial - a conexão está OK
                            $authWorked = true; // HTTP 200 recebido = autenticação OK
                            
                            Log::info('Autenticação cPanel OK, mas módulo não disponível', [
                                'endpoint' => $endpoint,
                                'error' => substr($errorStr, 0, 200),
                                'note' => 'A autenticação funcionou (HTTP 200), mas este módulo específico não está disponível'
                            ]);
                            
                            // Continua tentando outros endpoints, mas marca que a autenticação funciona
                            $lastError = "Módulo não disponível ({$endpoint}), mas autenticação OK";
                            continue; // Tenta próximo endpoint
                        }
                        
                        // Se o erro não for sobre módulo, pode ser autenticação
                        if (str_contains($errorStr, 'authentication') || 
                            str_contains($errorStr, 'unauthorized') ||
                            str_contains($errorStr, 'invalid') && str_contains($errorStr, 'token') ||
                            str_contains($errorStr, 'access denied')) {
                            $lastError = "Erro de autenticação ({$endpoint}): Verifique se o usuário e token estão corretos";
                            Log::warning('Erro de autenticação no cPanel', [
                                'endpoint' => $endpoint,
                                'error' => substr($errorStr, 0, 200)
                            ]);
                            continue; // Tenta próximo endpoint
                        }
                        
                        $lastError = "Erro na resposta do cPanel ({$endpoint}): " . substr($errorStr, 0, 200);
                        continue; // Tenta próximo endpoint
                    }
                    
                    // Aceita múltiplos formatos de resposta
                    $isSuccess = (isset($data['status']) && ($data['status'] === 1 || $data['status'] === true)) ||
                                 (isset($data['data']) && !isset($data['errors'])) ||
                                 (isset($data['version']) || isset($data['account'])) ||
                                 (isset($data['status']) && $data['status'] === 0 && empty($data['errors'])) || // UAPI pode retornar status 0 sem erros
                                 (isset($data['cpanelresult']) && !isset($data['error'])); // API 2 pode retornar cpanelresult
                    
                    if ($isSuccess) {
                        // Conexão funcionou! Agora tenta listar cron jobs (opcional)
                        $cronModuleAvailable = false;
                        $cronJobsCount = null;
                        $cronModuleError = null;
                        
                        try {
                            $cronResult = $this->listCronJobs();
                            $cronJobsCount = count($cronResult['cron_jobs'] ?? []);
                            $cronModuleAvailable = true;
                            
                            Log::info('Módulo Cron disponível - cron jobs listados com sucesso', [
                                'cron_jobs_count' => $cronJobsCount
                            ]);
                        } catch (Exception $e) {
                            // Se não conseguir listar cron jobs, não é crítico para o teste de conexão
                            $cronModuleError = $e->getMessage();
                            $cronModuleAvailable = false;
                            
                            Log::info('Conexão com cPanel OK, mas não foi possível listar cron jobs', [
                                'error' => $cronModuleError,
                                'note' => 'O módulo Cron pode não estar disponível neste servidor'
                            ]);
                        }
                        
                        $response = [
                            'success' => true,
                            'message' => 'Conexão com cPanel estabelecida com sucesso',
                            'cron_module_available' => $cronModuleAvailable,
                            'endpoint_used' => $endpoint,
                        ];
                        
                        if ($cronModuleAvailable && $cronJobsCount !== null) {
                            $response['cron_jobs_count'] = $cronJobsCount;
                            $response['message'] = 'Conexão com cPanel estabelecida com sucesso. Módulo Cron disponível.';
                        } else {
                            $response['cron_module_note'] = 'O módulo Cron não está disponível neste servidor cPanel. O módulo Perl "Cpanel::API::Cron" não está instalado. Você pode criar os cron jobs manualmente no cPanel usando os comandos curl/wget gerados pela aplicação.';
                            $response['message'] = 'Conexão com cPanel estabelecida com sucesso. Nota: O módulo Cron não está disponível, mas você pode criar cron jobs manualmente.';
                            if ($cronModuleError) {
                                $response['cron_module_error'] = $cronModuleError;
                            }
                        }
                        
                        return $response;
                    }
                }
                
                $lastError = "Erro HTTP {$statusCode} ao testar conexão ({$endpoint}): " . substr($responseBody, 0, 200);
            } catch (Exception $e) {
                $lastError = "Exceção ao testar conexão ({$endpoint}): " . $e->getMessage();
            }
        }

        // Se nenhum endpoint funcionou, verifica se pelo menos a autenticação funcionou
        // (recebeu HTTP 200 mas com erro de módulo não disponível)
        if ($authWorked || ($lastError && str_contains($lastError, 'autenticação OK'))) {
            // Autenticação funcionou (recebemos HTTP 200), mas nenhum módulo está disponível
            // Isso ainda é considerado sucesso parcial - a conexão e autenticação estão OK
            return [
                'success' => true,
                'message' => 'Conexão e autenticação com cPanel OK. Nota: Os módulos da API não estão disponíveis neste servidor.',
                'cron_module_available' => false,
                'cron_module_note' => 'Os módulos da API do cPanel não estão disponíveis neste servidor. O módulo Perl "Cpanel::API::*" não está instalado. Você pode criar os cron jobs manualmente no cPanel usando os comandos curl/wget gerados pela aplicação.',
                'note' => 'A autenticação funcionou (HTTP 200 recebido), mas as APIs específicas não estão disponíveis. Isso pode indicar uma versão antiga do cPanel ou APIs desabilitadas.',
                'last_error' => $lastError
            ];
        }
        
        // Verifica se todos os erros foram sobre módulos não disponíveis
        // Se sim, a autenticação provavelmente funcionou
        if ($lastError && (
            str_contains($lastError, 'Módulo não disponível') ||
            str_contains($lastError, 'Can\'t locate Cpanel') ||
            str_contains($lastError, 'Failed to load module')
        )) {
            return [
                'success' => true,
                'message' => 'Conexão e autenticação com cPanel OK. Nota: Os módulos da API não estão disponíveis neste servidor.',
                'cron_module_available' => false,
                'cron_module_note' => 'Os módulos da API do cPanel não estão disponíveis neste servidor. O módulo Perl "Cpanel::API::*" não está instalado. Você pode criar os cron jobs manualmente no cPanel usando os comandos curl/wget gerados pela aplicação.',
                'note' => 'A autenticação funcionou (HTTP 200 recebido), mas as APIs específicas não estão disponíveis. Isso pode indicar uma versão antiga do cPanel ou APIs desabilitadas.',
                'last_error' => $lastError
            ];
        }
        
        // Se nenhum endpoint funcionou, retorna erro
        return [
            'success' => false,
            'error' => 'Erro ao conectar com cPanel. Verifique se o host, usuário e token estão corretos. Último erro: ' . ($lastError ?? 'Erro desconhecido'),
            'troubleshooting' => [
                '1. Verifique se CPANEL_HOST está correto (ex: seu-dominio.com ou IP)',
                '2. Verifique se CPANEL_USERNAME está correto',
                '3. Verifique se CPANEL_API_TOKEN está correto e não expirou',
                '4. Verifique se CPANEL_PORT está correto (geralmente 2083 para HTTP ou 2087 para HTTPS)',
                '5. Verifique se o token tem permissões de API habilitadas no cPanel'
            ]
        ];
    }

    /**
     * Converte frequência cron para comando curl/wget
     */
    public function buildCronCommand(string $endpoint, string $method, array $headers = [], array $body = null): string
    {
        $command = 'curl -X ' . escapeshellarg($method);
        
        // Adiciona headers
        if ($headers) {
            foreach ($headers as $key => $value) {
                if (!empty($value)) {
                    $command .= ' -H ' . escapeshellarg($key . ': ' . $value);
                }
            }
        }
        
        // Adiciona body se for POST/PUT
        if (in_array($method, ['POST', 'PUT']) && $body) {
            $bodyJson = json_encode($body);
            $command .= ' -d ' . escapeshellarg($bodyJson);
        }
        
        $command .= ' --silent --output /dev/null ' . escapeshellarg($endpoint);
        
        return $command;
    }

    /**
     * Parse da parte de minutos da expressão cron
     * Converte formatos como *\/1 para * (a cada minuto)
     */
    private function parseCronMinute(string $frequency): string
    {
        $parts = explode(' ', trim($frequency));
        $minute = $parts[0] ?? '*';
        
        // Converte */1 para * (a cada minuto)
        if ($minute === '*/1' || $minute === '*/1 * * * *') {
            return '*';
        }
        
        return $minute;
    }

    /**
     * Parse da parte de horas da expressão cron
     */
    private function parseCronHour(string $frequency): string
    {
        $parts = explode(' ', trim($frequency));
        return $parts[1] ?? '*';
    }

    /**
     * Parse da parte de dia do mês da expressão cron
     */
    private function parseCronDay(string $frequency): string
    {
        $parts = explode(' ', trim($frequency));
        return $parts[2] ?? '*';
    }

    /**
     * Parse da parte de mês da expressão cron
     */
    private function parseCronMonth(string $frequency): string
    {
        $parts = explode(' ', trim($frequency));
        return $parts[3] ?? '*';
    }

    /**
     * Parse da parte de dia da semana da expressão cron
     */
    private function parseCronWeekday(string $frequency): string
    {
        $parts = explode(' ', trim($frequency));
        return $parts[4] ?? '*';
    }
}
