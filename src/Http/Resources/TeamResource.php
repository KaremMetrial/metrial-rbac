<?php

namespace Metrial\RBAC\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'slug'        => $this->slug,
            'description' => $this->description,
            'members'     => $this->whenLoaded('members', fn () =>
                $this->members->map(fn ($m) => [
                    'id'       => $m->getKey(),
                    'name'     => $m->name ?? null,
                    'email'    => $m->email ?? null,
                    'is_owner' => (bool) $m->pivot->is_owner,
                ])
            ),
            'roles'       => $this->whenLoaded('roles', fn () =>
                RoleResource::collection($this->roles)
            ),
            'created_at'  => $this->created_at?->toISOString(),
            'updated_at'  => $this->updated_at?->toISOString(),
        ];
    }
}
