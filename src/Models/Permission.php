<?php

namespace Metrial\RBAC\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;

class Permission extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'guard_name', 'group', 'description'];

    protected $keyType = 'string';

    public $incrementing = false;

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Model $model) {
            if (empty($model->id)) {
                $model->id = Str::uuid()->toString();
            }
        });
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permission');
    }

    public function users(): MorphToMany
    {
        $userModel = config('rbac.user_model', config('auth.providers.users.model'));

        return $this->morphedByMany($userModel, 'model', 'model_permissions')
            ->withPivot(['team_id', 'guard_name', 'starts_at', 'expires_at', 'assigned_by'])
            ->withTimestamps();
    }
}
