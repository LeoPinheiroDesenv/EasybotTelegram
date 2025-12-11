<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Downsell extends Model
{
    protected $fillable = [
        'bot_id',
        'plan_id',
        'title',
        'initial_media_url',
        'message',
        'promotional_value',
        'quantity_uses',
        'max_uses',
        'trigger_after_minutes',
        'trigger_event',
        'active',
    ];

    protected $casts = [
        'promotional_value' => 'decimal:2',
        'quantity_uses' => 'integer',
        'max_uses' => 'integer',
        'trigger_after_minutes' => 'integer',
        'active' => 'boolean',
    ];

    /**
     * Get the bot that owns the downsell.
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    /**
     * Get the payment plan associated with the downsell.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(PaymentPlan::class, 'plan_id');
    }

    /**
     * Check if downsell can be used (hasn't reached max uses)
     */
    public function canBeUsed(): bool
    {
        if (!$this->active) {
            return false;
        }

        if ($this->max_uses === null) {
            return true; // Ilimitado
        }

        return $this->quantity_uses < $this->max_uses;
    }

    /**
     * Increment usage counter
     */
    public function incrementUsage(): void
    {
        $this->increment('quantity_uses');
    }
}
