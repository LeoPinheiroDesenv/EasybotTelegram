<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserGroup extends Model
{
    protected $fillable = [
        'name',
        'description',
        'active',
        'created_by',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    /**
     * Get the users in this group
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'user_group_id');
    }

    /**
     * Get the permissions for this group
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(UserGroupPermission::class);
    }

    /**
     * Get bots that this group has access to
     */
    public function bots(): BelongsToMany
    {
        return $this->belongsToMany(Bot::class, 'user_group_permissions', 'user_group_id', 'resource_id')
            ->where('resource_type', 'bot')
            ->withPivot('permission')
            ->withTimestamps();
    }

    /**
     * Get the user that created this group
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

