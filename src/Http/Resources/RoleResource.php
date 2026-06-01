<?php

namespace Metrial\RBAC\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'slug'        => $this->slug,
            'description' => $this->description,
            'guard_name'  => $this->guard_name,
            'level'       => $this->level,
            'is_system'   => $this->is_system,
            'permissions' => $this->whenLoaded('permissions', fn () =>
                $this->permissions->pluck('name')
            ),
            'parent_roles' => $this->whenLoaded('parentRoles', fn () =>
                $this->parentRoles->pluck('slug')
            ),
            'child_roles' => $this->whenLoaded('childRoles', fn () =>
                $this->childRoles->pluck('slug')
            ),
            'team_id'     => $this->team_id,
            'created_at'  => $this->created_at?->toISOString(),
            'updated_at'  => $this->updated_at?->toISOString(),
        ];
    }
}
