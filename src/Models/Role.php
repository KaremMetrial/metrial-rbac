<?php

namespace Metrial\RBAC\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Role extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'slug', 'description', 'level', 'guard_name', 'is_system', 'team_id'];

    protected $keyType = 'string';

    public $incrementing = false;

    protected $casts = [
        'is_system' => 'boolean',
        'level'     => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Model $model) {
            if (empty($model->id)) {
                $model->id = Str::uuid()->toString();
            }
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->name);
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    public function users(): MorphToMany
    {
        $userModel = config('rbac.user_model', config('auth.providers.users.model'));

        return $this->morphedByMany($userModel, 'model', 'model_roles')
            ->withPivot(['team_id', 'guard_name', 'starts_at', 'expires_at', 'assigned_by'])
            ->withTimestamps();
    }

    public function parentRoles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'role_hierarchy',
            'descendant_id',
            'ancestor_id'
        )->withPivot('depth');
    }

    public function childRoles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'role_hierarchy',
            'ancestor_id',
            'descendant_id'
        )->withPivot('depth');
    }
}
