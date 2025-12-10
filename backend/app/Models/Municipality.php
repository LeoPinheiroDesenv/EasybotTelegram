<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Municipality extends Model
{
    protected $fillable = [
        'codigo',
        'nome',
        'uf',
    ];

    /**
     * Get the state for this municipality
     */
    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class, 'uf', 'uf');
    }
}
