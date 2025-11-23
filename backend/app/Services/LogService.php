<?php

namespace App\Services;

use App\Models\Log;
use Illuminate\Support\Facades\Auth;

class LogService
{
    /**
     * Registra um log no banco de dados
     *
     * @param string $message
     * @param string $level
     * @param array $context
     * @param int|null $botId
     * @return Log|null
     */
    public static function log(
        string $message,
        string $level = 'info',
        array $context = [],
        ?int $botId = null
    ): ?Log {
        try {
            $userEmail = null;
            $ipAddress = null;
            
            // Tenta obter email do usuário autenticado
            if (Auth::check()) {
                $userEmail = Auth::user()->email ?? null;
            }
            
            // Tenta obter IP da requisição atual
            if (request()) {
                $ipAddress = request()->ip();
            }
            
            // Extrai bot_id do contexto se existir
            if (!$botId && isset($context['bot_id'])) {
                $botId = $context['bot_id'];
                unset($context['bot_id']);
            }
            
            // Valida se o bot_id existe antes de salvar (evita erro de foreign key)
            if ($botId !== null) {
                $botExists = \App\Models\Bot::where('id', $botId)->exists();
                if (!$botExists) {
                    $botId = null; // Remove bot_id inválido
                }
            }
            
            return Log::create([
                'bot_id' => $botId,
                'level' => $level,
                'message' => $message,
                'context' => !empty($context) ? $context : null,
                'user_email' => $userEmail,
                'ip_address' => $ipAddress,
            ]);
        } catch (\Exception $e) {
            // Se falhar, registra no log padrão do Laravel
            \Illuminate\Support\Facades\Log::error('Erro ao salvar log no banco de dados', [
                'error' => $e->getMessage(),
                'original_message' => $message
            ]);
            return null;
        }
    }
    
    /**
     * Registra um log de informação
     *
     * @param string $message
     * @param array $context
     * @param int|null $botId
     * @return Log|null
     */
    public static function info(string $message, array $context = [], ?int $botId = null): ?Log
    {
        return self::log($message, 'info', $context, $botId);
    }
    
    /**
     * Registra um log de aviso
     *
     * @param string $message
     * @param array $context
     * @param int|null $botId
     * @return Log|null
     */
    public static function warning(string $message, array $context = [], ?int $botId = null): ?Log
    {
        return self::log($message, 'warning', $context, $botId);
    }
    
    /**
     * Registra um log de erro
     *
     * @param string $message
     * @param array $context
     * @param int|null $botId
     * @return Log|null
     */
    public static function error(string $message, array $context = [], ?int $botId = null): ?Log
    {
        return self::log($message, 'error', $context, $botId);
    }
    
    /**
     * Registra um log crítico
     *
     * @param string $message
     * @param array $context
     * @param int|null $botId
     * @return Log|null
     */
    public static function critical(string $message, array $context = [], ?int $botId = null): ?Log
    {
        return self::log($message, 'critical', $context, $botId);
    }
}

