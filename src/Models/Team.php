<?php

namespace Metrial\RBAC\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;

class Team extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'slug', 'description'];

    protected $keyType = 'string';

    public $incrementing = false;

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

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function members(): MorphToMany
    {
        $userModel = config('rbac.user_model', config('auth.providers.users.model'));

        return $this->morphedByMany($userModel, 'model', 'model_teams')
            ->withPivot('is_owner')
            ->withTimestamps();
    }
}
