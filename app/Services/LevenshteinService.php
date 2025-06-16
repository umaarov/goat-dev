<?php

namespace App\Services;

use Illuminate\Support\Collection;

class LevenshteinService
{
    public function findBestMatches(string $searchTerm, Collection $items, array $searchableKeys, int $relevanceThreshold = 25): Collection
    {
        $searchTermLower = strtolower(trim($searchTerm));

        if (empty($searchTermLower)) {
            return new Collection();
        }

        $scoredItems = $items->map(function ($item) use ($searchTermLower, $searchableKeys) {
            $bestItemScore = 0;

            foreach ($searchableKeys as $key) {
                $content = data_get($item, $key);

                if (!$content || !is_string($content)) {
                    continue;
                }

                $contentLower = strtolower($content);
                if (empty($contentLower)) {
                    continue;
                }

                $distance = levenshtein($searchTermLower, $contentLower);

                $words = explode(' ', $contentLower);
                foreach ($words as $word) {
                    $wordDistance = levenshtein($searchTermLower, $word);
                    if ($wordDistance < $distance) {
                        $distance = $wordDistance;
                    }
                }

                $maxLength = max(strlen($searchTermLower), strlen($contentLower));
                if ($maxLength > 0) {
                    $score = (1 - ($distance / $maxLength)) * 100;

                    if ($score > $bestItemScore) {
                        $bestItemScore = $score;
                    }
                }
            }

            $item->relevance_score = $bestItemScore;
            return $item;
        });

        return $scoredItems
            ->filter(fn($item) => $item->relevance_score >= $relevanceThreshold)
            ->sortByDesc('relevance_score')
            ->values();
    }
}
