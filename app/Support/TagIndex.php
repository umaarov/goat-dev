<?php

namespace App\Support;

use App\Models\Post;
use App\Models\Tag;
use Illuminate\Support\Str;

class TagIndex
{
    public static function slug(string $name): string
    {
        return Str::limit(Str::slug($name), 100, '');
    }

    // "AI risk, ai risk , Marvel" -> ['ai-risk' => 'AI risk', 'marvel' => 'Marvel']; a tag without letters or digits is dropped
    public static function parse(?string $raw): array
    {
        $tags = [];

        foreach (preg_split('/[,\n]+/', (string) $raw) as $name) {
            $name = Str::limit(Str::squish(trim($name, " \t#")), 100, '');
            $slug = $name === '' ? '' : self::slug($name);

            if ($slug !== '' && !isset($tags[$slug])) {
                $tags[$slug] = $name;
            }
        }

        return array_slice($tags, 0, 12, true);
    }

    public static function sync(Post $post): void
    {
        $ids = [];

        foreach (self::parse($post->ai_generated_tags) as $slug => $name) {
            $ids[] = Tag::firstOrCreate(['slug' => $slug], ['name' => $name])->id;
        }

        $post->tags()->sync($ids);
    }
}
