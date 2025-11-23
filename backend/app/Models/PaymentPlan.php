<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentPlan extends Model
{
    protected $fillable = [
        'bot_id',
        'payment_cycle_id',
        'title',
        'price',
        'charge_period',
        'cycle',
        'message',
        'pix_message',
        'active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'active' => 'boolean',
    ];

    /**
     * Get the bot that owns the payment plan.
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    /**
     * Get the payment cycle for this plan.
     */
    public function paymentCycle(): BelongsTo
    {
        return $this->belongsTo(PaymentCycle::class);
    }

    /**
     * Get the transactions for this payment plan.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
