<?php

namespace Metrial\RBAC\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'actor_id'    => $this->actor_id,
            'action'      => $this->action,
            'entity_type' => $this->entity_type,
            'entity_id'   => $this->entity_id,
            'old_value'   => $this->old_value,
            'new_value'   => $this->new_value,
            'ip_address'  => $this->ip_address,
            'user_agent'  => $this->user_agent,
            'context'     => $this->context,
            'created_at'  => $this->created_at?->toISOString(),
        ];
    }
}
