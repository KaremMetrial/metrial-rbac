<?php

namespace Metrial\RBAC\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoleHierarchy extends Model
{
    public $timestamps = true;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['ancestor_id', 'descendant_id', 'depth'];

    protected $casts = [
        'depth' => 'integer',
    ];

    public function ancestor(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'ancestor_id');
    }

    public function descendant(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'descendant_id');
    }
}
