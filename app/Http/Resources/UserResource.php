<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesMediaUrls;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full user representation.
 *
 * Private fields (email, preferences, auth-method flags) are only exposed when
 * the resource represents the currently authenticated user.
 */
class UserResource extends JsonResource
{
    use ResolvesMediaUrls;

    public function toArray(Request $request): array
    {
        $isSelf = $request->user() && $request->user()->id === $this->id;

        $data = [
            'id' => $this->id,
            'username' => $this->username,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'profile_picture' => $this->mediaUrl($this->profile_picture),
            'header_background' => $this->mediaUrl($this->header_background),
            'external_links' => $this->external_links ?? [],
            'is_developer' => (bool) $this->is_developer,
            'show_voted_posts_publicly' => (bool) $this->show_voted_posts_publicly,
            'created_at' => $this->created_at?->toIso8601String(),
            // Aggregates are exposed only when they were eager-loaded.
            'posts_count' => $this->whenNotNull($this->posts_count ?? null),
            'total_votes_received' => $this->whenNotNull($this->posts_sum_total_votes ?? null),
        ];

        if ($isSelf) {
            $data = array_merge($data, [
                'email' => $this->email,
                'email_verified' => ! is_null($this->email_verified_at),
                'locale' => $this->locale,
                'receives_notifications' => (bool) $this->receives_notifications,
                'ai_insight_preference' => $this->ai_insight_preference,
                'has_password' => ! is_null($this->password),
                'linked_providers' => [
                    'google' => ! is_null($this->google_id),
                    'x' => ! is_null($this->x_id),
                    'telegram' => ! is_null($this->telegram_id),
                    'github' => ! is_null($this->github_id),
                ],
            ]);
        }

        return $data;
    }
}
