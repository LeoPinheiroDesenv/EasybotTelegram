<?php

namespace App\Http\Controllers;

use App\Models\CronJob;
use App\Services\CpanelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CronJobController extends Controller
{
    /**
     * Lista todos os cron jobs (incluindo os padrão do sistema)
     */
    public function index(): JsonResponse
    {
        try {
            // Busca cron jobs do banco de dados
            $customCronJobs = [];
            try {
                $customCronJobs = CronJob::orderBy('created_at', 'desc')->get();
            } catch (\Exception $e) {
                // Se a tabela não existir ainda, retorna array vazio
                Log::warning('Tabela cron_jobs pode não existir ainda: ' . $e->getMessage());
                $customCronJobs = collect([]);
            }
            
            // Busca cron jobs padrão do sistema
            $defaultCronJobs = [];
            $defaultCronJobsInDb = [];
            
            try {
                $defaultCronJobs = CronJob::getDefaultCronJobs();
                
                // Verifica quais cron jobs padrão já existem no banco
                foreach ($defaultCronJobs as $default) {
                    $exists = false;
                    
                    // Verifica se já existe no banco
                    if ($customCronJobs->isNotEmpty()) {
                        $exists = $customCronJobs->first(function ($job) use ($default) {
                            if (!$job->is_system) {
                                return false;
                            }
                            
                            // Compara endpoints (pode ter URL base diferente)
                            $defaultPath = parse_url($default['endpoint'], PHP_URL_PATH);
                            $jobPath = parse_url($job->endpoint, PHP_URL_PATH);
                            
                            // Se não conseguir extrair o path, compara o endpoint completo
                            if ($defaultPath === null) {
                                $defaultPath = $default['endpoint'];
                            }
                            if ($jobPath === null) {
                                $jobPath = $job->endpoint;
                            }
                            
                            // Normaliza os paths (remove barras duplicadas)
                            $defaultPath = rtrim($defaultPath, '/');
                            $jobPath = rtrim($jobPath, '/');
                            
                            return $jobPath === $defaultPath;
                        }) !== null;
                    }
                    
                    if (!$exists) {
                        $defaultCronJobsInDb[] = [
                            'id' => null,
                            'name' => $default['name'],
                            'description' => $default['description'],
                            'endpoint' => $default['endpoint'],
                            'method' => $default['method'],
                            'frequency' => $default['frequency'],
                            'headers' => $default['headers'],
                            'body' => $default['body'],
                            'is_active' => true,
                            'is_system' => true,
                            'last_run_at' => null,
                            'last_response' => null,
                            'last_success' => null,
                            'run_count' => 0,
                            'success_count' => 0,
                            'error_count' => 0,
                            'created_at' => null,
                            'updated_at' => null,
                        ];
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Erro ao obter cron jobs padrão: ' . $e->getMessage());
                // Continua mesmo se houver erro nos padrões
            }
            
            $responseData = [
                'success' => true,
                'cron_jobs' => $customCronJobs->toArray(),
                'default_cron_jobs' => $defaultCronJobsInDb,
            ];
            
            Log::debug('CronJobs index response', [
                'cron_jobs_count' => count($responseData['cron_jobs']),
                'default_cron_jobs_count' => count($responseData['default_cron_jobs'])
            ]);
            
            return response()->json($responseData);
        } catch (\Exception $e) {
            Log::error('Erro ao listar cron jobs: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            // Tenta retornar pelo menos os cron jobs padrão mesmo em caso de erro
            try {
                $defaultCronJobs = CronJob::getDefaultCronJobs();
                $defaultCronJobsInDb = array_map(function ($default) {
                    return [
                        'id' => null,
                        'name' => $default['name'],
                        'description' => $default['description'],
                        'endpoint' => $default['endpoint'],
                        'method' => $default['method'],
                        'frequency' => $default['frequency'],
                        'headers' => $default['headers'],
                        'body' => $default['body'],
                        'is_active' => true,
                        'is_system' => true,
                        'last_run_at' => null,
                        'last_response' => null,
                        'last_success' => null,
                        'run_count' => 0,
                        'success_count' => 0,
                        'error_count' => 0,
                        'created_at' => null,
                        'updated_at' => null,
                    ];
                }, $defaultCronJobs);
                
                return response()->json([
                    'success' => true,
                    'cron_jobs' => [],
                    'default_cron_jobs' => $defaultCronJobsInDb,
                    'warning' => 'Erro ao carregar cron jobs do banco, mas cron jobs padrão estão disponíveis'
                ]);
            } catch (\Exception $e2) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro ao listar cron jobs: ' . $e->getMessage(),
                    'cron_jobs' => [],
                    'default_cron_jobs' => []
                ], 500);
            }
        }
    }

    /**
     * Cria um novo cron job
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'endpoint' => 'required|url',
                'method' => 'required|in:GET,POST,PUT,DELETE',
                'frequency' => 'required|string|regex:/^[\*\d\/\s,]+$/',
                'headers' => 'nullable|array',
                'body' => 'nullable|array',
                'is_active' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $cronJob = CronJob::create([
                'name' => $request->name,
                'description' => $request->description,
                'endpoint' => $request->endpoint,
                'method' => $request->method,
                'frequency' => $request->frequency,
                'headers' => $request->headers ?? [],
                'body' => $request->body,
                'is_active' => $request->input('is_active', true),
                'is_system' => false,
            ]);

            // Tenta criar no cPanel se estiver configurado e ativo
            $cpanelMessage = null;
            if ($cronJob->is_active) {
                try {
                    $cpanelService = app(CpanelService::class);
                    if ($cpanelService->isConfigured()) {
                        $command = $cpanelService->buildCronCommand(
                            $cronJob->endpoint,
                            $cronJob->method,
                            $cronJob->headers ?? [],
                            $cronJob->body
                        );
                        
                        $result = $cpanelService->createCronJob(
                            $command,
                            $cronJob->frequency,
                            $cronJob->name . ' - ' . ($cronJob->description ?? 'Cron job automático')
                        );
                        
                        if ($result['success'] && isset($result['cron_id'])) {
                            $cronJob->cpanel_cron_id = $result['cron_id'];
                            $cronJob->save();
                            $cpanelMessage = 'Cron job criado no cPanel com sucesso';
                            
                            Log::info('Cron job criado no cPanel', [
                                'cron_job_id' => $cronJob->id,
                                'cpanel_cron_id' => $result['cron_id']
                            ]);
                        }
                    } else {
                        $cpanelMessage = 'cPanel não configurado - cron job criado apenas no banco de dados';
                        Log::info('Cron job criado sem cPanel (não configurado)', [
                            'cron_job_id' => $cronJob->id
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::warning('Erro ao criar cron job no cPanel (continuando sem cPanel)', [
                        'cron_job_id' => $cronJob->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    $cpanelMessage = 'Aviso: Não foi possível criar no cPanel: ' . $e->getMessage();
                }
            }

            Log::info('Cron job criado', [
                'id' => $cronJob->id,
                'name' => $cronJob->name,
                'cpanel_cron_id' => $cronJob->cpanel_cron_id
            ]);

            $response = [
                'success' => true,
                'message' => 'Cron job criado com sucesso' . ($cpanelMessage ? '. ' . $cpanelMessage : ''),
                'cron_job' => $cronJob->fresh()
            ];
            
            if ($cpanelMessage) {
                $response['cpanel_message'] = $cpanelMessage;
            }

            return response()->json($response, 201);
        } catch (\Exception $e) {
            Log::error('Erro ao criar cron job: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao criar cron job: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cria um cron job padrão do sistema
     */
    public function createDefault(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $defaultCronJobs = CronJob::getDefaultCronJobs();
            $defaultCronJob = collect($defaultCronJobs)->firstWhere('name', $request->name);

            if (!$defaultCronJob) {
                return response()->json([
                    'success' => false,
                    'error' => 'Cron job padrão não encontrado'
                ], 404);
            }

            // Verifica se já existe
            $exists = CronJob::where('endpoint', $defaultCronJob['endpoint'])
                ->where('is_system', true)
                ->first();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'error' => 'Este cron job padrão já existe',
                    'cron_job' => $exists
                ], 409);
            }

            $cronJob = CronJob::create([
                'name' => $defaultCronJob['name'],
                'description' => $defaultCronJob['description'],
                'endpoint' => $defaultCronJob['endpoint'],
                'method' => $defaultCronJob['method'],
                'frequency' => $defaultCronJob['frequency'],
                'headers' => $defaultCronJob['headers'],
                'body' => $defaultCronJob['body'],
                'is_active' => true,
                'is_system' => true,
            ]);

            // Tenta criar no cPanel se estiver configurado e ativo
            $cpanelMessage = null;
            if ($cronJob->is_active) {
                try {
                    $cpanelService = app(CpanelService::class);
                    if ($cpanelService->isConfigured()) {
                        $command = $cpanelService->buildCronCommand(
                            $cronJob->endpoint,
                            $cronJob->method,
                            $cronJob->headers ?? [],
                            $cronJob->body
                        );
                        
                        Log::info('Tentando criar cron job padrão no cPanel', [
                            'cron_job_id' => $cronJob->id,
                            'name' => $cronJob->name,
                            'frequency' => $cronJob->frequency,
                            'command_preview' => substr($command, 0, 100) . '...'
                        ]);
                        
                        $result = $cpanelService->createCronJob(
                            $command,
                            $cronJob->frequency,
                            $cronJob->name . ' - ' . ($cronJob->description ?? 'Cron job automático')
                        );
                        
                        if ($result['success'] && isset($result['cron_id'])) {
                            $cronJob->cpanel_cron_id = $result['cron_id'];
                            $cronJob->save();
                            $cpanelMessage = 'Cron job criado no cPanel com sucesso (ID: ' . $result['cron_id'] . ')';
                            
                            Log::info('Cron job padrão criado no cPanel com sucesso', [
                                'cron_job_id' => $cronJob->id,
                                'cpanel_cron_id' => $result['cron_id'],
                                'endpoint_used' => $result['endpoint_used'] ?? 'unknown'
                            ]);
                        } else {
                            $cpanelMessage = 'Erro: cPanel retornou sucesso mas sem cron_id';
                            Log::warning('cPanel retornou sucesso mas sem cron_id', [
                                'cron_job_id' => $cronJob->id,
                                'result' => $result
                            ]);
                        }
                    } else {
                        $cpanelMessage = 'cPanel não configurado - cron job criado apenas no banco de dados';
                        Log::info('Cron job padrão criado sem cPanel (não configurado)', [
                            'cron_job_id' => $cronJob->id
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Erro ao criar cron job padrão no cPanel', [
                        'cron_job_id' => $cronJob->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    $cpanelMessage = 'Aviso: Não foi possível criar no cPanel: ' . $e->getMessage();
                }
            }

            Log::info('Cron job padrão criado', [
                'id' => $cronJob->id,
                'name' => $cronJob->name,
                'cpanel_cron_id' => $cronJob->cpanel_cron_id
            ]);

            $response = [
                'success' => true,
                'message' => 'Cron job padrão criado com sucesso' . ($cpanelMessage ? '. ' . $cpanelMessage : ''),
                'cron_job' => $cronJob
            ];
            
            if ($cpanelMessage) {
                $response['cpanel_message'] = $cpanelMessage;
            }

            return response()->json($response, 201);
        } catch (\Exception $e) {
            Log::error('Erro ao criar cron job padrão: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao criar cron job padrão: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exibe um cron job específico
     */
    public function show($id): JsonResponse
    {
        try {
            $cronJob = CronJob::findOrFail($id);
            
            return response()->json([
                'success' => true,
                'cron_job' => $cronJob,
                'curl_command' => $cronJob->getCurlCommand(),
                'wget_command' => $cronJob->getWgetCommand(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Cron job não encontrado'
            ], 404);
        }
    }

    /**
     * Atualiza um cron job
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $cronJob = CronJob::findOrFail($id);

            // Não permite editar cron jobs do sistema (exceto is_active)
            if ($cronJob->is_system && $request->has('is_system')) {
                return response()->json([
                    'success' => false,
                    'error' => 'Não é possível alterar o status de sistema de um cron job padrão'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'endpoint' => 'sometimes|required|url',
                'method' => 'sometimes|required|in:GET,POST,PUT,DELETE',
                'frequency' => 'sometimes|required|string|regex:/^[\*\d\/\s,]+$/',
                'headers' => 'nullable|array',
                'body' => 'nullable|array',
                'is_active' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $updateData = $request->only([
                'name', 'description', 'endpoint', 'method', 'frequency', 
                'headers', 'body', 'is_active'
            ]);

            // Se for cron job do sistema, só permite alterar is_active
            if ($cronJob->is_system) {
                $updateData = ['is_active' => $request->input('is_active', $cronJob->is_active)];
            }

            $oldIsActive = $cronJob->is_active;
            $oldFrequency = $cronJob->frequency;
            $oldEndpoint = $cronJob->endpoint;
            $oldMethod = $cronJob->method;
            
            $cronJob->update($updateData);
            $cronJob->refresh();

            // Atualiza no cPanel se necessário
            $cpanelMessage = null;
            try {
                $cpanelService = app(CpanelService::class);
                
                // Se cPanel está configurado
                if ($cpanelService->isConfigured()) {
                    // Se foi desativado, remove do cPanel
                    if (!$cronJob->is_active && $oldIsActive && $cronJob->cpanel_cron_id) {
                        try {
                            $cpanelService->deleteCronJob($cronJob->cpanel_cron_id);
                            $cronJob->cpanel_cron_id = null;
                            $cronJob->save();
                            $cpanelMessage = 'Cron job removido do cPanel (desativado)';
                        } catch (\Exception $e) {
                            Log::warning('Erro ao remover cron job do cPanel ao desativar', [
                                'cron_job_id' => $cronJob->id,
                                'cpanel_cron_id' => $cronJob->cpanel_cron_id,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                    // Se foi ativado, cria no cPanel (mesmo que já tenha cpanel_cron_id, recria para garantir)
                    elseif ($cronJob->is_active) {
                        // Se não tinha no cPanel ou se algo mudou, cria/atualiza
                        if (!$cronJob->cpanel_cron_id || 
                            $cronJob->frequency !== $oldFrequency ||
                            $cronJob->endpoint !== $oldEndpoint ||
                            $cronJob->method !== $oldMethod) {
                            
                            // Se tinha um cron_id antigo, remove primeiro
                            if ($cronJob->cpanel_cron_id && (
                                $cronJob->frequency !== $oldFrequency ||
                                $cronJob->endpoint !== $oldEndpoint ||
                                $cronJob->method !== $oldMethod
                            )) {
                                try {
                                    $cpanelService->deleteCronJob($cronJob->cpanel_cron_id);
                                } catch (\Exception $e) {
                                    Log::warning('Erro ao remover cron job antigo do cPanel antes de atualizar', [
                                        'cron_job_id' => $cronJob->id,
                                        'cpanel_cron_id' => $cronJob->cpanel_cron_id,
                                        'error' => $e->getMessage()
                                    ]);
                                }
                            }
                            
                            // Cria novo cron job no cPanel
                            $command = $cpanelService->buildCronCommand(
                                $cronJob->endpoint,
                                $cronJob->method,
                                $cronJob->headers ?? [],
                                $cronJob->body
                            );
                            
                            $result = $cpanelService->createCronJob(
                                $command,
                                $cronJob->frequency,
                                $cronJob->name . ' - ' . ($cronJob->description ?? 'Cron job automático')
                            );
                            
                            if ($result['success'] && isset($result['cron_id'])) {
                                $cronJob->cpanel_cron_id = $result['cron_id'];
                                $cronJob->save();
                                $cpanelMessage = $cronJob->cpanel_cron_id ? 'Cron job atualizado no cPanel' : 'Cron job criado no cPanel';
                                
                                Log::info('Cron job sincronizado com cPanel', [
                                    'cron_job_id' => $cronJob->id,
                                    'cpanel_cron_id' => $result['cron_id'],
                                    'action' => $cronJob->cpanel_cron_id ? 'updated' : 'created'
                                ]);
                            }
                        }
                    }
                } else {
                    $cpanelMessage = 'cPanel não configurado - cron job atualizado apenas no banco de dados';
                }
            } catch (\Exception $e) {
                Log::error('Erro ao atualizar cron job no cPanel', [
                    'cron_job_id' => $cronJob->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $cpanelMessage = 'Aviso: Não foi possível atualizar no cPanel: ' . $e->getMessage();
            }

            Log::info('Cron job atualizado', [
                'id' => $cronJob->id,
                'name' => $cronJob->name,
                'cpanel_cron_id' => $cronJob->cpanel_cron_id
            ]);

            $response = [
                'success' => true,
                'message' => 'Cron job atualizado com sucesso' . ($cpanelMessage ? '. ' . $cpanelMessage : ''),
                'cron_job' => $cronJob->fresh()
            ];
            
            if ($cpanelMessage) {
                $response['cpanel_message'] = $cpanelMessage;
            }

            return response()->json($response);
        } catch (\Exception $e) {
            Log::error('Erro ao atualizar cron job: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao atualizar cron job: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove um cron job
     */
    public function destroy($id): JsonResponse
    {
        try {
            $cronJob = CronJob::findOrFail($id);

            // Não permite deletar cron jobs do sistema
            if ($cronJob->is_system) {
                return response()->json([
                    'success' => false,
                    'error' => 'Não é possível deletar cron jobs do sistema'
                ], 403);
            }

            // Remove do cPanel se existir
            $cpanelMessage = null;
            if ($cronJob->cpanel_cron_id) {
                try {
                    $cpanelService = app(CpanelService::class);
                    if ($cpanelService->isConfigured()) {
                        $cpanelService->deleteCronJob($cronJob->cpanel_cron_id);
                        $cpanelMessage = 'Cron job removido do cPanel';
                    }
                } catch (\Exception $e) {
                    Log::warning('Erro ao remover cron job do cPanel (continuando)', [
                        'cron_job_id' => $id,
                        'cpanel_cron_id' => $cronJob->cpanel_cron_id,
                        'error' => $e->getMessage()
                    ]);
                    $cpanelMessage = 'Aviso: Não foi possível remover do cPanel: ' . $e->getMessage();
                }
            }

            $cronJob->delete();

            Log::info('Cron job deletado', [
                'id' => $id,
                'name' => $cronJob->name,
                'cpanel_cron_id' => $cronJob->cpanel_cron_id
            ]);

            $response = [
                'success' => true,
                'message' => 'Cron job deletado com sucesso' . ($cpanelMessage ? '. ' . $cpanelMessage : '')
            ];
            
            if ($cpanelMessage) {
                $response['cpanel_message'] = $cpanelMessage;
            }

            return response()->json($response);
        } catch (\Exception $e) {
            Log::error('Erro ao deletar cron job: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao deletar cron job: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Testa um cron job (executa manualmente)
     */
    public function test($id): JsonResponse
    {
        try {
            $cronJob = CronJob::findOrFail($id);

            $startTime = microtime(true);
            
            try {
                $request = Http::timeout(30);
                
                // Adiciona headers
                if ($cronJob->headers) {
                    foreach ($cronJob->headers as $key => $value) {
                        if (!empty($value)) {
                            $request->withHeaders([$key => $value]);
                        }
                    }
                }

                // Faz a requisição
                $response = match($cronJob->method) {
                    'GET' => $request->get($cronJob->endpoint),
                    'POST' => $request->post($cronJob->endpoint, $cronJob->body),
                    'PUT' => $request->put($cronJob->endpoint, $cronJob->body),
                    'DELETE' => $request->delete($cronJob->endpoint),
                };

                $duration = round((microtime(true) - $startTime) * 1000, 2);
                $success = $response->successful();
                $responseBody = $response->json() ?? $response->body();

                // Atualiza estatísticas
                $cronJob->last_run_at = now();
                $cronJob->last_response = json_encode($responseBody);
                $cronJob->last_success = $success;
                $cronJob->run_count++;
                if ($success) {
                    $cronJob->success_count++;
                } else {
                    $cronJob->error_count++;
                }
                $cronJob->save();

                Log::info('Cron job testado', [
                    'id' => $cronJob->id,
                    'name' => $cronJob->name,
                    'success' => $success,
                    'duration_ms' => $duration
                ]);

                return response()->json([
                    'success' => true,
                    'message' => $success ? 'Cron job executado com sucesso' : 'Cron job executado com erro',
                    'test_result' => [
                        'success' => $success,
                        'status_code' => $response->status(),
                        'response' => $responseBody,
                        'duration_ms' => $duration,
                    ],
                    'cron_job' => $cronJob->fresh()
                ]);
            } catch (\Exception $e) {
                $duration = round((microtime(true) - $startTime) * 1000, 2);
                
                // Atualiza estatísticas com erro
                $cronJob->last_run_at = now();
                $cronJob->last_response = json_encode(['error' => $e->getMessage()]);
                $cronJob->last_success = false;
                $cronJob->run_count++;
                $cronJob->error_count++;
                $cronJob->save();

                Log::error('Erro ao testar cron job: ' . $e->getMessage(), [
                    'id' => $cronJob->id,
                    'name' => $cronJob->name
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Erro ao executar cron job: ' . $e->getMessage(),
                    'test_result' => [
                        'success' => false,
                        'error' => $e->getMessage(),
                        'duration_ms' => $duration,
                    ],
                    'cron_job' => $cronJob->fresh()
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Cron job não encontrado'
            ], 404);
        }
    }

    /**
     * Executa automaticamente todos os cron jobs ativos que estão atrasados
     * Este endpoint deve ser chamado periodicamente (ex: a cada minuto) pelo cPanel
     */
    public function executeAll(Request $request): JsonResponse
    {
        try {
            $secretToken = env('CRON_JOBS_EXECUTOR_SECRET_TOKEN');
            
            // Se houver token configurado, verifica o token fornecido
            if ($secretToken) {
                $authHeader = $request->header('Authorization');
                $bearerToken = null;
                if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
                    $bearerToken = substr($authHeader, 7);
                }
                
                $providedToken = $request->header('X-Cron-Executor-Token') 
                    ?? $bearerToken 
                    ?? $request->input('token');
                
                if (!$providedToken || $providedToken !== $secretToken) {
                    return response()->json([
                        'error' => 'Token inválido ou não fornecido',
                        'message' => 'Para executar cron jobs, forneça o token no header X-Cron-Executor-Token, Authorization Bearer, ou no parâmetro token'
                    ], 403);
                }
            }
            
            // Busca todos os cron jobs ativos
            $cronJobs = CronJob::where('is_active', true)->get();
            
            if ($cronJobs->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Nenhum cron job ativo encontrado',
                    'executed' => 0,
                    'skipped' => 0,
                    'errors' => 0
                ]);
            }
            
            $executed = 0;
            $skipped = 0;
            $errors = 0;
            $results = [];
            
            foreach ($cronJobs as $cronJob) {
                try {
                    // Verifica se o cron job deve ser executado baseado na frequência
                    if (!$this->shouldExecute($cronJob)) {
                        $skipped++;
                        $results[] = [
                            'id' => $cronJob->id,
                            'name' => $cronJob->name,
                            'status' => 'skipped',
                            'reason' => 'Ainda não é hora de executar (baseado na frequência)'
                        ];
                        continue;
                    }
                    
                    // Executa o cron job
                    $startTime = microtime(true);
                    
                    try {
                        $httpRequest = Http::timeout(30);
                        
                        // Adiciona headers
                        if ($cronJob->headers) {
                            foreach ($cronJob->headers as $key => $value) {
                                if (!empty($value)) {
                                    $httpRequest->withHeaders([$key => $value]);
                                }
                            }
                        }

                        // Faz a requisição
                        $response = match($cronJob->method) {
                            'GET' => $httpRequest->get($cronJob->endpoint),
                            'POST' => $httpRequest->post($cronJob->endpoint, $cronJob->body),
                            'PUT' => $httpRequest->put($cronJob->endpoint, $cronJob->body),
                            'DELETE' => $httpRequest->delete($cronJob->endpoint),
                        };

                        $duration = round((microtime(true) - $startTime) * 1000, 2);
                        $success = $response->successful();
                        $responseBody = $response->json() ?? $response->body();

                        // Atualiza estatísticas
                        $cronJob->last_run_at = now();
                        $cronJob->last_response = json_encode($responseBody);
                        $cronJob->last_success = $success;
                        $cronJob->run_count++;
                        if ($success) {
                            $cronJob->success_count++;
                        } else {
                            $cronJob->error_count++;
                            $errors++;
                        }
                        $cronJob->save();
                        
                        $executed++;
                        $results[] = [
                            'id' => $cronJob->id,
                            'name' => $cronJob->name,
                            'status' => $success ? 'success' : 'error',
                            'status_code' => $response->status(),
                            'duration_ms' => $duration
                        ];
                        
                        Log::info('Cron job executado automaticamente', [
                            'id' => $cronJob->id,
                            'name' => $cronJob->name,
                            'success' => $success,
                            'duration_ms' => $duration
                        ]);
                    } catch (\Exception $e) {
                        $duration = round((microtime(true) - $startTime) * 1000, 2);
                        
                        // Atualiza estatísticas com erro
                        $cronJob->last_run_at = now();
                        $cronJob->last_response = json_encode(['error' => $e->getMessage()]);
                        $cronJob->last_success = false;
                        $cronJob->run_count++;
                        $cronJob->error_count++;
                        $cronJob->save();
                        
                        $errors++;
                        $results[] = [
                            'id' => $cronJob->id,
                            'name' => $cronJob->name,
                            'status' => 'error',
                            'error' => $e->getMessage(),
                            'duration_ms' => $duration
                        ];
                        
                        Log::error('Erro ao executar cron job automaticamente: ' . $e->getMessage(), [
                            'id' => $cronJob->id,
                            'name' => $cronJob->name
                        ]);
                    }
                } catch (\Exception $e) {
                    $errors++;
                    $results[] = [
                        'id' => $cronJob->id ?? null,
                        'name' => $cronJob->name ?? 'Unknown',
                        'status' => 'error',
                        'error' => $e->getMessage()
                    ];
                    
                    Log::error('Erro ao processar cron job: ' . $e->getMessage());
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => "Processados {$executed} cron job(s), {$skipped} pulado(s), {$errors} erro(s)",
                'executed' => $executed,
                'skipped' => $skipped,
                'errors' => $errors,
                'results' => $results
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao executar todos os cron jobs: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao executar cron jobs: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Verifica se um cron job deve ser executado baseado na frequência e última execução
     */
    private function shouldExecute(CronJob $cronJob): bool
    {
        // Se nunca foi executado, deve executar
        if (!$cronJob->last_run_at) {
            return true;
        }
        
        // Calcula quantos minutos se passaram desde a última execução
        $minutesSinceLastRun = $cronJob->last_run_at->diffInMinutes(now());
        
        // Parse da frequência cron para determinar intervalo mínimo
        $frequency = $cronJob->frequency;
        
        // Extrai o intervalo de minutos da expressão cron
        // Exemplos:
        // "* * * * *" = a cada minuto (1 minuto)
        // "*/5 * * * *" = a cada 5 minutos
        // "*/1 * * * *" = a cada 1 minuto
        $minInterval = $this->parseCronInterval($frequency);
        
        // Se passou o intervalo mínimo, deve executar
        return $minutesSinceLastRun >= $minInterval;
    }
    
    /**
     * Extrai o intervalo mínimo em minutos de uma expressão cron
     */
    private function parseCronInterval(string $frequency): int
    {
        $parts = explode(' ', trim($frequency));
        
        if (count($parts) !== 5) {
            // Se não conseguir parsear, assume 1 minuto por segurança
            return 1;
        }
        
        $minutePart = $parts[0];
        
        // Se for "*", é a cada minuto
        if ($minutePart === '*') {
            return 1;
        }
        
        // Se for "*/N", é a cada N minutos
        if (str_starts_with($minutePart, '*/')) {
            $interval = (int) substr($minutePart, 2);
            return $interval > 0 ? $interval : 1;
        }
        
        // Se for um número específico, assume 1 minuto (executa quando chegar naquele minuto)
        if (is_numeric($minutePart)) {
            return 1;
        }
        
        // Default: 1 minuto
        return 1;
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
     * Sincroniza todos os cron jobs ativos com o cPanel
     * Cria no cPanel os cron jobs que não têm cpanel_cron_id
     */
    public function syncWithCpanel(): JsonResponse
    {
        try {
            $cpanelService = app(CpanelService::class);
            
            if (!$cpanelService->isConfigured()) {
                return response()->json([
                    'success' => false,
                    'error' => 'cPanel não está configurado. Configure CPANEL_HOST, CPANEL_USERNAME e CPANEL_API_TOKEN no .env'
                ], 400);
            }

            // Busca todos os cron jobs ativos que não têm cpanel_cron_id
            $cronJobsToSync = CronJob::where('is_active', true)
                ->whereNull('cpanel_cron_id')
                ->get();

            $synced = 0;
            $errors = 0;
            $results = [];

            foreach ($cronJobsToSync as $cronJob) {
                try {
                    $command = $cpanelService->buildCronCommand(
                        $cronJob->endpoint,
                        $cronJob->method,
                        $cronJob->headers ?? [],
                        $cronJob->body
                    );
                    
                    $result = $cpanelService->createCronJob(
                        $command,
                        $cronJob->frequency,
                        $cronJob->name . ' - ' . ($cronJob->description ?? 'Cron job automático')
                    );
                    
                    if ($result['success'] && isset($result['cron_id'])) {
                        $cronJob->cpanel_cron_id = $result['cron_id'];
                        $cronJob->save();
                        $synced++;
                        
                        $results[] = [
                            'id' => $cronJob->id,
                            'name' => $cronJob->name,
                            'status' => 'success',
                            'cpanel_cron_id' => $result['cron_id']
                        ];
                        
                        Log::info('Cron job sincronizado com cPanel', [
                            'cron_job_id' => $cronJob->id,
                            'cpanel_cron_id' => $result['cron_id']
                        ]);
                    } else {
                        $errors++;
                        $results[] = [
                            'id' => $cronJob->id,
                            'name' => $cronJob->name,
                            'status' => 'error',
                            'error' => 'cPanel retornou sucesso mas sem cron_id'
                        ];
                    }
                } catch (\Exception $e) {
                    $errors++;
                    $results[] = [
                        'id' => $cronJob->id,
                        'name' => $cronJob->name,
                        'status' => 'error',
                        'error' => $e->getMessage()
                    ];
                    
                    Log::error('Erro ao sincronizar cron job com cPanel', [
                        'cron_job_id' => $cronJob->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Sincronização concluída: {$synced} criado(s), {$errors} erro(s)",
                'synced' => $synced,
                'errors' => $errors,
                'results' => $results
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao sincronizar cron jobs com cPanel: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao sincronizar: ' . $e->getMessage()
            ], 500);
        }
    }
}
