<?php

namespace App\Support;

use App\Models\Post;

// schema.org for a question page. A poll is a Q&A page whose answers are its two options: each needs text, none is "accepted".
class QuestionSchema
{
    public static function for(Post $post, string $url, string $locale): array
    {
        $username = $post->user->username;
        $profile = route('profile.show', $username);

        $answer = fn (int $n, ?string $title, ?string $image, int $votes) => array_filter([
            '@type' => 'Answer',
            'text' => $title ?: "Option {$n}",
            'upvoteCount' => $votes,
            'url' => "{$url}#option{$n}",
            'image' => $image ? asset('storage/'.$image) : null,
        ], fn ($v) => $v !== null);

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => route('home')],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => '@'.$username, 'item' => $profile],
                        ['@type' => 'ListItem', 'position' => 3, 'name' => mb_strimwidth($post->question, 0, 50, '…'), 'item' => $url],
                    ],
                ],
                [
                    '@type' => 'QAPage',
                    '@id' => "{$url}#qa",
                    'url' => $url,
                    'inLanguage' => $locale,
                    'mainEntity' => array_filter([
                        '@type' => 'Question',
                        'name' => $post->question,
                        'text' => $post->ai_generated_context ?: $post->question,
                        'answerCount' => 2,
                        'datePublished' => $post->created_at->toIso8601String(),
                        'dateModified' => $post->updated_at->toIso8601String(),
                        'author' => ['@type' => 'Person', 'name' => '@'.$username, 'url' => $profile],
                        'commentCount' => (int) ($post->comments_count ?? 0),
                        'suggestedAnswer' => [
                            $answer(1, $post->option_one_title, $post->option_one_image, (int) $post->option_one_votes),
                            $answer(2, $post->option_two_title, $post->option_two_image, (int) $post->option_two_votes),
                        ],
                    ], fn ($v) => $v !== null),
                ],
            ],
        ];
    }

    // safe inside <script>: < > & are escaped, so a hostile question cannot close the tag
    public static function json(array $schema): string
    {
        return json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
    }
}
