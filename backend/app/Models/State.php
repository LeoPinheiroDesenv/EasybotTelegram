<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class State extends Model
{
    protected $fillable = [
        'codigo_uf',
        'nome',
        'uf',
        'regiao',
    ];

    /**
     * Get municipalities for this state
     */
    public function municipalities(): HasMany
    {
        return $this->hasMany(Municipality::class, 'uf', 'uf');
    }
}
