<?php

namespace App\Services;

use App\Models\Downsell;
use App\Models\Transaction;
use App\Models\Contact;
use App\Models\Bot;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DownsellService
{
    protected $telegramService;

    public function __construct(TelegramService $telegramService)
    {
        $this->telegramService = $telegramService;
    }

    /**
     * Processa downsell para uma transação que falhou ou foi cancelada
     *
     * @param Transaction $transaction
     * @param string $event 'payment_failed' ou 'payment_canceled'
     * @return bool
     */
    public function processDownsell(Transaction $transaction, string $event = 'payment_failed'): bool
    {
        try {
            $bot = $transaction->bot;
            $contact = $transaction->contact;
            $plan = $transaction->paymentPlan;

            if (!$bot || !$contact || !$plan) {
                Log::warning('Dados incompletos para processar downsell', [
                    'transaction_id' => $transaction->id
                ]);
                return false;
            }

            // Busca downsells ativos para o plano e evento
            $downsells = Downsell::where('bot_id', $bot->id)
                ->where('plan_id', $plan->id)
                ->where('trigger_event', $event)
                ->where('active', true)
                ->get();

            if ($downsells->isEmpty()) {
                Log::info('Nenhum downsell encontrado para processar', [
                    'transaction_id' => $transaction->id,
                    'plan_id' => $plan->id,
                    'event' => $event
                ]);
                return false;
            }

            // Seleciona o primeiro downsell disponível (pode ser melhorado com lógica de prioridade)
            $downsell = $downsells->first();

            // Verifica se pode ser usado
            if (!$downsell->canBeUsed()) {
                Log::info('Downsell atingiu limite de usos', [
                    'downsell_id' => $downsell->id,
                    'quantity_uses' => $downsell->quantity_uses,
                    'max_uses' => $downsell->max_uses
                ]);
                return false;
            }

            // Agenda o envio do downsell após X minutos
            if ($downsell->trigger_after_minutes > 0) {
                // Usa um job agendado para enviar após X minutos
                \App\Jobs\SendDownsell::dispatch($downsell, $contact, $transaction)
                    ->delay(now()->addMinutes($downsell->trigger_after_minutes));
            } else {
                // Envia imediatamente
                $this->sendDownsell($downsell, $contact, $transaction);
            }

            // Incrementa contador de usos
            $downsell->incrementUsage();

            Log::info('Downsell processado com sucesso', [
                'downsell_id' => $downsell->id,
                'transaction_id' => $transaction->id,
                'contact_id' => $contact->id,
                'delay_minutes' => $downsell->trigger_after_minutes
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Erro ao processar downsell', [
                'transaction_id' => $transaction->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Envia mensagem de downsell para o contato
     *
     * @param Downsell $downsell
     * @param Contact $contact
     * @param Transaction $transaction
     * @return void
     */
    public function sendDownsell(Downsell $downsell, Contact $contact, Transaction $transaction): void
    {
        try {
            $bot = $downsell->bot;

            // Verifica se o bot está ativo
            if (!$bot->active || !$bot->activated) {
                Log::warning('Bot inativo, não enviando downsell', [
                    'bot_id' => $bot->id,
                    'downsell_id' => $downsell->id
                ]);
                return;
            }

            // Verifica se o contato está bloqueado
            if ($contact->is_blocked) {
                Log::info('Contato bloqueado, não enviando downsell', [
                    'contact_id' => $contact->id,
                    'downsell_id' => $downsell->id
                ]);
                return;
            }

            $chatId = $contact->telegram_id;

            // Envia mídia inicial se houver
            if ($downsell->initial_media_url) {
                $this->sendMedia($bot, $chatId, $downsell->initial_media_url);
            }

            // Prepara mensagem com valor promocional
            $message = $downsell->message;
            
            // Substitui variáveis na mensagem
            $message = str_replace('{valor_promocional}', number_format($downsell->promotional_value, 2, ',', '.'), $message);
            $message = str_replace('{valor_original}', number_format($transaction->amount, 2, ',', '.'), $message);
            $message = str_replace('{desconto}', number_format($transaction->amount - $downsell->promotional_value, 2, ',', '.'), $message);
            $message = str_replace('{nome_plano}', $downsell->plan->title ?? '', $message);

            // Envia mensagem
            $this->telegramService->sendMessage($bot, $chatId, $message);

            // Cria botão para aceitar a oferta
            $keyboard = [
                'inline_keyboard' => [[
                    [
                        'text' => '✅ Aceitar Oferta Especial',
                        'callback_data' => "downsell_accept_{$downsell->id}_{$transaction->id}"
                    ]
                ]]
            ];

            $acceptMessage = "💎 <b>Oferta Especial!</b>\n\n";
            $acceptMessage .= "💰 Valor promocional: R$ " . number_format($downsell->promotional_value, 2, ',', '.') . "\n";
            $acceptMessage .= "📦 Plano: {$downsell->plan->title}\n\n";
            $acceptMessage .= "Clique no botão abaixo para aceitar esta oferta exclusiva!";

            $this->telegramService->sendMessage($bot, $chatId, $acceptMessage, $keyboard);

            Log::info('Downsell enviado com sucesso', [
                'downsell_id' => $downsell->id,
                'contact_id' => $contact->id,
                'transaction_id' => $transaction->id
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao enviar downsell', [
                'downsell_id' => $downsell->id ?? null,
                'contact_id' => $contact->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Envia mídia (imagem ou vídeo)
     */
    protected function sendMedia(Bot $bot, int $chatId, string $mediaUrl): void
    {
        try {
            $extension = strtolower(pathinfo(parse_url($mediaUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
            
            $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $videoExtensions = ['mp4', 'mov', 'avi'];

            // Usa Http facade diretamente
            $http = \Illuminate\Support\Facades\Http::timeout(30);

            if (in_array($extension, $imageExtensions)) {
                $http->post("https://api.telegram.org/bot{$bot->token}/sendPhoto", [
                    'chat_id' => $chatId,
                    'photo' => $mediaUrl
                ]);
            } elseif (in_array($extension, $videoExtensions)) {
                $http->post("https://api.telegram.org/bot{$bot->token}/sendVideo", [
                    'chat_id' => $chatId,
                    'video' => $mediaUrl
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Erro ao enviar mídia do downsell', [
                'media_url' => $mediaUrl,
                'error' => $e->getMessage()
            ]);
        }
    }
}
