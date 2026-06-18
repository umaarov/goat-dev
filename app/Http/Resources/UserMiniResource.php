<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesMediaUrls;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Compact user representation embedded inside posts, comments, etc.
 */
class UserMiniResource extends JsonResource
{
    use ResolvesMediaUrls;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'profile_picture' => $this->mediaUrl($this->profile_picture),
        ];
    }
}
