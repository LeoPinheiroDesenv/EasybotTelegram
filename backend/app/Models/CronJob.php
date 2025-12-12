<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CronJob extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'endpoint',
        'method',
        'frequency',
        'headers',
        'body',
        'is_active',
        'is_system',
        'cpanel_cron_id',
        'last_run_at',
        'last_response',
        'last_success',
        'run_count',
        'success_count',
        'error_count',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_system' => 'boolean',
        'last_success' => 'boolean',
        'last_run_at' => 'datetime',
        'headers' => 'array',
        'body' => 'array',
        'run_count' => 'integer',
        'success_count' => 'integer',
        'error_count' => 'integer',
    ];

    /**
     * Accessor para headers - garante que sempre retorne array
     */
    public function getHeadersAttribute($value)
    {
        if (empty($value)) {
            return [];
        }
        
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        
        return is_array($value) ? $value : [];
    }

    /**
     * Accessor para body - retorna null se vazio
     */
    public function getBodyAttribute($value)
    {
        if (empty($value) || $value === 'null') {
            return null;
        }
        
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return $decoded;
        }
        
        return $value;
    }

    /**
     * Retorna os cron jobs padrão do sistema
     */
    public static function getDefaultCronJobs(): array
    {
        $baseUrl = config('app.url', env('APP_URL', 'https://seu-dominio.com'));
        
        return [
            [
                'name' => 'Verificar Pagamentos Pendentes',
                'description' => 'Verifica pagamentos PIX pendentes e processa aprovações automaticamente. Deve ser executado a cada 1-2 minutos.',
                'endpoint' => $baseUrl . '/api/payments/check-pending',
                'method' => 'POST',
                'frequency' => '*/1 * * * *',
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Payments-Check-Token' => env('PAYMENTS_CHECK_PENDING_SECRET_TOKEN', ''),
                ],
                'body' => [
                    'bot_id' => null,
                    'interval' => 30,
                ],
                'is_system' => true,
            ],
            [
                'name' => 'Verificar Expiração de PIX',
                'description' => 'Verifica PIX que expiraram e notifica os usuários. Deve ser executado a cada 5 minutos.',
                'endpoint' => $baseUrl . '/api/pix/check-expiration',
                'method' => 'POST',
                'frequency' => '*/5 * * * *',
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Pix-Check-Token' => env('PIX_CHECK_EXPIRATION_SECRET_TOKEN', ''),
                ],
                'body' => null,
                'is_system' => true,
            ],
        ];
    }

    /**
     * Gera o comando curl para o cron job
     */
    public function getCurlCommand(): string
    {
        $command = 'curl -X ' . $this->method;
        
        // Adiciona headers
        if ($this->headers) {
            foreach ($this->headers as $key => $value) {
                if (!empty($value)) {
                    $command .= ' -H "' . $key . ': ' . $value . '"';
                }
            }
        }
        
        // Adiciona body se for POST/PUT
        if (in_array($this->method, ['POST', 'PUT']) && $this->body) {
            $bodyJson = json_encode($this->body);
            $command .= ' -d \'' . addslashes($bodyJson) . '\'';
        }
        
        $command .= ' --silent --output /dev/null "' . $this->endpoint . '"';
        
        return $command;
    }

    /**
     * Gera o comando wget para o cron job
     */
    public function getWgetCommand(): string
    {
        $command = 'wget --quiet --method=' . $this->method;
        
        // Adiciona headers
        if ($this->headers) {
            foreach ($this->headers as $key => $value) {
                if (!empty($value)) {
                    $command .= ' --header="' . $key . ': ' . $value . '"';
                }
            }
        }
        
        // Adiciona body se for POST/PUT
        if (in_array($this->method, ['POST', 'PUT']) && $this->body) {
            $bodyJson = json_encode($this->body);
            $command .= ' --body-data=\'' . addslashes($bodyJson) . '\'';
        }
        
        $command .= ' --output-document=/dev/null "' . $this->endpoint . '"';
        
        return $command;
    }
}
