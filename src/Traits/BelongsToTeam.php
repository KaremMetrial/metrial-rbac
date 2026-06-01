<?php

namespace Metrial\RBAC\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Metrial\RBAC\Models\Team;

trait BelongsToTeam
{
    /**
     * Bootstrap the trait — auto-scope queries to current team when set.
     */
    public static function bootBelongsToTeam(): void
    {
        static::addGlobalScope('team', function (Builder $builder) {
            if ($teamId = static::getCurrentTeamId()) {
                $builder->where((new static)->getTable() . '.team_id', $teamId);
            }
        });

        static::creating(function (Model $model) {
            if (! $model->team_id && $teamId = static::getCurrentTeamId()) {
                $model->team_id = $teamId;
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function scopeTeam(Builder $query, Team|string $team): Builder
    {
        $teamId = $team instanceof Team ? $team->id : $team;

        return $query->where($this->getTable() . '.team_id', $teamId);
    }

    /**
     * Get the current team ID from the authenticated user's context.
     */
    protected static function getCurrentTeamId(): ?string
    {
        if (auth()->check() && method_exists(auth()->user(), 'getActiveTeamId')) {
            return auth()->user()->getActiveTeamId();
        }

        return null;
    }
}
