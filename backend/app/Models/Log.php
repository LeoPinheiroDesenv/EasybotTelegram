<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Log extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'bot_id',
        'level',
        'message',
        'context',
        'details',
        'user_email',
        'ip_address',
        'request',
        'response',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'context' => 'array',
            'request' => 'array',
            'response' => 'array',
        ];
    }

    /**
     * Get the bot that owns the log.
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }
}
