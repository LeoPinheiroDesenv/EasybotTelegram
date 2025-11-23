<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotCommand extends Model
{
    protected $fillable = [
        'bot_id',
        'command',
        'response',
        'description',
        'active',
        'usage_count',
    ];

    protected $casts = [
        'active' => 'boolean',
        'usage_count' => 'integer',
    ];

    /**
     * Get the bot that owns the command.
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    /**
     * Increment usage count
     */
    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }
}

