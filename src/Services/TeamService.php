<?php

namespace Metrial\RBAC\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Metrial\RBAC\Models\Team;

class TeamService
{
    public function __construct(
        protected AuditService $audit,
    ) {}

    public function create(array $data): Team
    {
        /** @var Team $team */
        $team = Team::create($data);

        $this->audit->log('team.created', 'team', $team->id, [], $data);

        return $team;
    }

    public function findById(string $id): ?Team
    {
        return Team::find($id);
    }

    public function findBySlug(string $slug): ?Team
    {
        return Team::where('slug', $slug)->first();
    }

    public function getAllTeams(): Collection
    {
        return Team::all();
    }

    public function addMember(Team $team, Model $model, bool $asOwner = false): void
    {
        DB::table('model_teams')->upsert(
            [[
                'id'         => \Illuminate\Support\Str::uuid()->toString(),
                'team_id'    => $team->id,
                'model_type' => $model->getMorphClass(),
                'model_id'   => $model->getKey(),
                'is_owner'   => $asOwner,
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['team_id', 'model_type', 'model_id'],
            ['is_owner', 'updated_at']
        );

        $this->audit->log('team.member.added', 'team', $team->id, [], [
            'model_type' => $model->getMorphClass(),
            'model_id' => $model->getKey(),
            'is_owner' => $asOwner,
        ]);
    }

    public function removeMember(Team $team, Model $model): void
    {
        DB::table('model_teams')
            ->where('team_id', $team->id)
            ->where('model_type', $model->getMorphClass())
            ->where('model_id', $model->getKey())
            ->delete();

        $this->audit->log('team.member.removed', 'team', $team->id, [], [
            'model_type' => $model->getMorphClass(),
            'model_id' => $model->getKey(),
        ]);
    }

    public function getMembers(Team $team): Collection
    {
        $userModel = config('rbac.user_model', config('auth.providers.users.model'));

        return $userModel::whereHas('teams', function ($q) use ($team) {
            $q->where('teams.id', $team->id);
        })->get();
    }

    public function isMember(Team $team, Model $model): bool
    {
        return DB::table('model_teams')
            ->where('team_id', $team->id)
            ->where('model_type', $model->getMorphClass())
            ->where('model_id', $model->getKey())
            ->exists();
    }

    public function isOwner(Team $team, Model $model): bool
    {
        return DB::table('model_teams')
            ->where('team_id', $team->id)
            ->where('model_type', $model->getMorphClass())
            ->where('model_id', $model->getKey())
            ->where('is_owner', true)
            ->exists();
    }
}
