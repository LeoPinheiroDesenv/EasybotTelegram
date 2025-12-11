<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bot extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'token',
        'telegram_group_id',
        'active',
        'initial_message',
        'top_message',
        'button_message',
        'activate_cta',
        'media_1_url',
        'media_2_url',
        'media_3_url',
        'request_email',
        'request_phone',
        'request_language',
        'payment_method',
        'activated',
    ];

    protected $casts = [
        'active' => 'boolean',
        'activate_cta' => 'boolean',
        'request_email' => 'boolean',
        'request_phone' => 'boolean',
        'request_language' => 'boolean',
        'payment_method' => 'array',
        'activated' => 'boolean',
    ];

    /**
     * Get the user that owns the bot.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the commands for the bot.
     */
    public function commands()
    {
        return $this->hasMany(BotCommand::class);
    }

    /**
     * Get the administrators for the bot.
     */
    public function administrators()
    {
        return $this->hasMany(BotAdministrator::class);
    }

    /**
     * Get the redirect buttons for the bot.
     */
    public function redirectButtons()
    {
        return $this->hasMany(RedirectButton::class);
    }
}
