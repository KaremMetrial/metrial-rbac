<?php

namespace Metrial\RBAC\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use Metrial\RBAC\Models\Team;
use Metrial\RBAC\Services\TeamService;

trait HasTeams
{
    use HasPermissions;

    // ── Team Management ──────────────────────────────────────────────

    public function addToTeam(Team $team, bool $asOwner = false): void
    {
        app(TeamService::class)->addMember($team, $this, $asOwner);
    }

    public function removeFromTeam(Team $team): void
    {
        app(TeamService::class)->removeMember($team, $this);
    }

    public function isMemberOf(Team $team): bool
    {
        return app(TeamService::class)->isMember($team, $this);
    }

    public function isOwnerOf(Team $team): bool
    {
        return app(TeamService::class)->isOwner($team, $this);
    }

    // ── Team Relationship ────────────────────────────────────────────

    public function teamMemberships(): MorphToMany
    {
        return $this->morphToMany(Team::class, 'model', 'model_teams')
            ->withPivot('is_owner')
            ->withTimestamps();
    }
}
