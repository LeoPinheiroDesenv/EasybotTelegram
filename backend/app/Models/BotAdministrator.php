<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotAdministrator extends Model
{
    protected $fillable = [
        'bot_id',
        'telegram_user_id',
    ];

    /**
     * Get the bot that owns this administrator.
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }
}
