<?php

namespace App\Jobs;

use App\Models\Alert;
use App\Models\Bot;
use App\Models\Contact;
use App\Services\TelegramService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendAlert implements ShouldQueue
{
    use Queueable, SerializesModels;

    protected $alert;
    protected $contact;

    /**
     * Create a new job instance.
     */
    public function __construct(Alert $alert, Contact $contact)
    {
        $this->alert = $alert;
        $this->contact = $contact;
    }

    /**
     * Execute the job.
     */
    public function handle(TelegramService $telegramService): void
    {
        try {
            $bot = $this->alert->bot;
            
            // Verifica se o bot está ativo
            if (!$bot->active || !$bot->activated) {
                Log::warning('Tentativa de enviar alerta para bot inativo', [
                    'alert_id' => $this->alert->id,
                    'bot_id' => $bot->id
                ]);
                return;
            }

            // Verifica se o contato está bloqueado
            if ($this->contact->is_blocked) {
                Log::info('Contato bloqueado, pulando envio de alerta', [
                    'alert_id' => $this->alert->id,
                    'contact_id' => $this->contact->id
                ]);
                return;
            }

            // Envia a mensagem
            $telegramService->sendMessage($bot, $this->contact->telegram_id, $this->alert->message);

            // Se houver arquivo, envia também
            if ($this->alert->file_url) {
                $this->sendMedia($telegramService, $bot, $this->contact->telegram_id);
            }

            // Atualiza contador de envios
            $this->alert->increment('sent_count');
            $this->alert->update(['sent_at' => now()]);

            Log::info('Alerta enviado com sucesso', [
                'alert_id' => $this->alert->id,
                'contact_id' => $this->contact->id,
                'bot_id' => $bot->id
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao enviar alerta', [
                'alert_id' => $this->alert->id,
                'contact_id' => $this->contact->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    /**
     * Envia mídia se houver
     */
    protected function sendMedia(TelegramService $telegramService, Bot $bot, int $chatId): void
    {
        try {
            $fileUrl = $this->alert->file_url;
            
            // Determina o tipo de mídia pela extensão
            $extension = strtolower(pathinfo($fileUrl, PATHINFO_EXTENSION));
            
            $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $videoExtensions = ['mp4', 'avi', 'mov', 'mkv'];
            
            $message = '';
            if (in_array($extension, $imageExtensions)) {
                $message = "📷 Imagem: {$fileUrl}";
            } elseif (in_array($extension, $videoExtensions)) {
                $message = "🎥 Vídeo: {$fileUrl}";
            } else {
                $message = "📎 Arquivo: {$fileUrl}";
            }
            
            // Por enquanto, envia a URL como mensagem
            // Para enviar mídia real, seria necessário fazer upload ou usar sendPhoto/sendVideo
            $telegramService->sendMessage($bot, $chatId, $message);
        } catch (\Exception $e) {
            Log::warning('Erro ao enviar mídia do alerta', [
                'alert_id' => $this->alert->id,
                'file_url' => $this->alert->file_url,
                'error' => $e->getMessage()
            ]);
        }
    }
}
