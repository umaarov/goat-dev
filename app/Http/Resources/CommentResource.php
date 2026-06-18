<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'content' => $this->content,
            'post_id' => $this->post_id,
            'parent_id' => $this->parent_id,
            'root_comment_id' => $this->root_comment_id,
            'likes_count' => (int) ($this->likes_count ?? 0),
            'replies_count' => $this->whenNotNull($this->replies_count ?? null),

            // Set explicitly by the controller for the authenticated user
            // (avoids the model accessor which depends on the web guard).
            'is_liked_by_current_user' => (bool) ($this->is_liked ?? false),

            'user' => new UserMiniResource($this->whenLoaded('user')),

            // The comment this one replies to (for "replying to @user" UI).
            'parent' => $this->when(
                $this->relationLoaded('parent') && $this->parent,
                fn () => [
                    'id' => $this->parent->id,
                    'user' => new UserMiniResource($this->whenLoaded('parent')?->user ?? null),
                ]
            ),

            // Preview / paginated replies, when loaded as the flatReplies relation.
            'replies' => CommentResource::collection($this->whenLoaded('flatReplies')),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
