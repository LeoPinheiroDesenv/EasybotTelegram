<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserGroupPermission extends Model
{
    protected $fillable = [
        'user_group_id',
        'resource_type',
        'resource_id',
        'permission',
    ];

    /**
     * Get the user group that owns this permission
     */
    public function userGroup(): BelongsTo
    {
        return $this->belongsTo(UserGroup::class);
    }

    /**
     * Get the bot resource if resource_type is 'bot'
     */
    public function bot()
    {
        return $this->belongsTo(Bot::class, 'resource_id');
    }
}

