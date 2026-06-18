<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Jenssegers\Agent\Agent;

/**
 * An active refresh-token "session" / signed-in device.
 */
class SessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $agent = new Agent;
        if ($this->user_agent) {
            $agent->setUserAgent($this->user_agent);
        }

        return [
            'id' => $this->id,
            'ip_address' => $this->ip_address,
            'browser' => $this->user_agent ? ($agent->browser() ?: null) : null,
            'platform' => $this->user_agent ? ($agent->platform() ?: null) : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
        ];
    }
}
