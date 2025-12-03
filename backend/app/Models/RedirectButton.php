<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RedirectButton extends Model
{
    protected $fillable = [
        'bot_id',
        'title',
        'link',
        'order',
    ];

    protected $casts = [
        'order' => 'integer',
    ];

    /**
     * Get the bot that owns the redirect button.
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }
}
