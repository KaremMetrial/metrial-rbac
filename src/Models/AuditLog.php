<?php

namespace Metrial\RBAC\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $table = 'rbac_audit_log';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'actor_id', 'action', 'entity_type', 'entity_id',
        'old_value', 'new_value', 'ip_address', 'user_agent', 'context', 'created_at',
    ];

    protected $casts = [
        'old_value' => 'array',
        'new_value' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Model $model) {
            if (empty($model->id)) {
                $model->id = Str::uuid()->toString();
            }
            if (empty($model->created_at)) {
                $model->created_at = now();
            }
        });
    }

    public function actor(): BelongsTo
    {
        $userModel = config('rbac.user_model', config('auth.providers.users.model'));

        return $this->belongsTo($userModel, 'actor_id');
    }
}
