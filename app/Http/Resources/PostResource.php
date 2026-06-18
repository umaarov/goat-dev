<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesMediaUrls;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    use ResolvesMediaUrls;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'question' => $this->question,
            'ai_generated_context' => $this->ai_generated_context,
            'ai_generated_tags' => $this->ai_generated_tags,

            'option_one' => [
                'title' => $this->option_one_title,
                'image' => $this->mediaUrl($this->option_one_image),
                'image_lqip' => $this->option_one_image_lqip,
                'votes' => (int) $this->option_one_votes,
                'percentage' => $this->option_one_percentage,
            ],
            'option_two' => [
                'title' => $this->option_two_title,
                'image' => $this->mediaUrl($this->option_two_image),
                'image_lqip' => $this->option_two_image_lqip,
                'votes' => (int) $this->option_two_votes,
                'percentage' => $this->option_two_percentage,
            ],

            'total_votes' => (int) $this->total_votes,
            'view_count' => (int) $this->view_count,
            'shares_count' => (int) ($this->shares_count ?? 0),
            'comments_count' => $this->whenNotNull($this->comments_count ?? null),

            // Current user's vote on this post: 'option_one' | 'option_two' | null.
            // Populated by the controller (mirrors web attachUserVoteStatus()).
            'user_vote' => $this->user_vote ?? null,

            'user' => new UserMiniResource($this->whenLoaded('user')),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
