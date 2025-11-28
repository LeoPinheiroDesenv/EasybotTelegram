<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    protected $fillable = [
        'bot_id',
        'plan_id',
        'alert_type',
        'message',
        'scheduled_date',
        'scheduled_time',
        'user_language',
        'user_category',
        'file_url',
        'status',
        'sent_at',
        'sent_count',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'scheduled_time' => 'datetime',
        'sent_at' => 'datetime',
        'sent_count' => 'integer',
    ];

    /**
     * Get the bot that owns the alert.
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    /**
     * Get the payment plan associated with the alert.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(PaymentPlan::class, 'plan_id');
    }

    /**
     * Check if alert is scheduled and ready to be sent
     */
    public function isReadyToSend(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->alert_type === 'scheduled') {
            $scheduledDateTime = $this->scheduled_date . ' ' . $this->scheduled_time;
            return now() >= $scheduledDateTime;
        }

        return $this->alert_type === 'common';
    }
}
