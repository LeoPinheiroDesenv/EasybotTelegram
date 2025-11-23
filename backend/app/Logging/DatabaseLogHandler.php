<?php

namespace App\Logging;

use App\Models\Log;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;

class DatabaseLogHandler extends AbstractProcessingHandler
{
    /**
     * Write log record to database
     *
     * @param LogRecord $record
     * @return void
     */
    protected function write(LogRecord $record): void
    {
        try {
            $context = $record->context ?? [];
            
            // Extrai bot_id do contexto se existir
            $botId = $context['bot_id'] ?? null;
            if (isset($context['bot_id'])) {
                unset($context['bot_id']);
            }
            
            // Extrai user_email do contexto se existir
            $userEmail = $context['user_email'] ?? null;
            if (isset($context['user_email'])) {
                unset($context['user_email']);
            }
            
            // Extrai ip_address do contexto se existir
            $ipAddress = $context['ip_address'] ?? null;
            if (isset($context['ip_address'])) {
                unset($context['ip_address']);
            }
            
            // Se não houver user_email no contexto, tenta pegar do auth
            if (!$userEmail && function_exists('auth') && auth()->check()) {
                $userEmail = auth()->user()->email ?? null;
            }
            
            // Se não houver ip_address no contexto, tenta pegar da request
            if (!$ipAddress && function_exists('request') && request()) {
                $ipAddress = request()->ip();
            }
            
            // Converte o nível do log para string
            $level = strtolower($record->level->getName());
            
            // Prepara os dados para salvar
            $logData = [
                'bot_id' => $botId,
                'level' => $level,
                'message' => $record->message ?? '',
                'context' => !empty($context) ? $context : null,
                'details' => method_exists($record, 'formatted') ? ($record->formatted ?? null) : null,
                'user_email' => $userEmail,
                'ip_address' => $ipAddress,
            ];
            
            // Salva no banco de dados
            Log::create($logData);
            
        } catch (\Exception $e) {
            // Se falhar ao salvar no banco, não quebra a aplicação
            // Apenas registra no log padrão do Laravel (sem usar o handler de database para evitar loop)
            try {
                error_log('Erro ao salvar log no banco de dados: ' . $e->getMessage());
            } catch (\Exception $ignored) {
                // Ignora se também falhar
            }
        }
    }
}

