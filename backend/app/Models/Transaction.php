<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'bot_id',
        'contact_id',
        'payment_plan_id',
        'payment_cycle_id',
        'gateway',
        'gateway_transaction_id',
        'amount',
        'currency',
        'status',
        'payment_method',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
    ];

    /**
     * Get the bot that owns the transaction.
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    /**
     * Get the contact associated with the transaction.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Get the payment plan associated with the transaction.
     */
    public function paymentPlan(): BelongsTo
    {
        return $this->belongsTo(PaymentPlan::class, 'payment_plan_id');
    }

    /**
     * Get the payment cycle associated with the transaction.
     */
    public function paymentCycle(): BelongsTo
    {
        return $this->belongsTo(PaymentCycle::class, 'payment_cycle_id');
    }
}
