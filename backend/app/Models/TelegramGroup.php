<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramGroup extends Model
{
    protected $fillable = [
        'bot_id',
        'title',
        'telegram_group_id',
        'payment_plan_id',
        'invite_link',
        'type',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    /**
     * Get the bot that owns this group/channel
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    /**
     * Get the payment plan associated with this group/channel
     */
    public function paymentPlan(): BelongsTo
    {
        return $this->belongsTo(PaymentPlan::class);
    }

    /**
     * Generate invite link based on telegram_group_id
     */
    public function generateInviteLink(): ?string
    {
        if (!$this->telegram_group_id) {
            return null;
        }

        // Se já tem invite_link, retorna ele
        if ($this->invite_link) {
            return $this->invite_link;
        }

        // Tenta gerar link baseado no ID
        // Para grupos: https://t.me/joinchat/{invite_hash} ou https://t.me/c/{chat_id}
        // Para canais: https://t.me/{username} ou https://t.me/c/{chat_id}
        
        // Se o ID começa com @, é um username
        if (str_starts_with($this->telegram_group_id, '@')) {
            return 'https://t.me/' . ltrim($this->telegram_group_id, '@');
        }

        // Se é um ID numérico, tenta gerar link (mas precisa do invite_hash da API)
        // Por enquanto retorna null e será preenchido pela API do Telegram
        return null;
    }
}
