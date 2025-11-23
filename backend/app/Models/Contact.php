<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contact extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'bot_id',
        'telegram_id',
        'username',
        'first_name',
        'last_name',
        'is_bot',
        'is_blocked',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_bot' => 'boolean',
            'is_blocked' => 'boolean',
        ];
    }

    /**
     * Get the bot that owns the contact.
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }
}

