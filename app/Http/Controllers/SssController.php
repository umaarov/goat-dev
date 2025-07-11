<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SssController extends Controller
{
    public function __invoke(Request $request): View
    {
        $badges = $this->getDummyBadgesData();
        $spotlightUsers = $this->getDummySpotlightUsers();
        $recentAiCreations = $this->getDummyAiCreations();
        $trendingWords = $this->getTrendingWords();

        return view('sss', compact('badges', 'spotlightUsers', 'recentAiCreations', 'trendingWords'));
    }

    private function getDummyBadgesData(): Collection
    {
        $allUsers = $this->getDummySpotlightUsers();

        return collect([
            [
                'key' => 'votes',
                'title' => 'The Gilded Horn',
                'image_url' => '/images/badges/votes_badge_placeholder.png',
                'context' => "Awarded for exceptional community acclaim.",
                'description' => "This emblem is granted to members whose posts have garnered the highest esteem, representing a voice that resonates powerfully within the community.",
                'stats' => ['rarity' => 'Legendary', 'origin' => 'Community Vote', 'type' => 'Recognition'],
                'rarity_percentage' => 0.8,
                'holders' => $allUsers->take(3),
            ],
            [
                'key' => 'posters',
                'title' => 'The Creator\'s Quill',
                'image_url' => '/images/badges/posters_badge_placeholder.png',
                'context' => "Awarded for prolific and insightful contribution.",
                'description' => "A symbol of dedicated creation, this badge recognizes the platform's most active and influential posters, whose contributions form the backbone of the community.",
                'stats' => ['rarity' => 'Epic', 'origin' => 'Activity Metric', 'type' => 'Contribution'],
                'rarity_percentage' => 2.5,
                'holders' => $allUsers->slice(2, 3),
            ],
            [
                'key' => 'likes',
                'title' => 'Heart of the Community',
                'image_url' => '/images/badges/likes_badge_placeholder.png',
                'context' => "Awarded for positive and impactful engagement.",
                'description' => "Forged in the spirit of connection, this badge is granted to those whose comments consistently receive widespread appreciation and foster a positive environment.",
                'stats' => ['rarity' => 'Epic', 'origin' => 'Peer Appreciation', 'type' => 'Engagement'],
                'rarity_percentage' => 4.1,
                'holders' => $allUsers->slice(4, 3),
            ],
            [
                'key' => 'commentators',
                'title' => 'The Dialogue Weaver',
                'image_url' => '/images/badges/commentators_badge_placeholder.png',
                'context' => "Awarded for mastery of conversation.",
                'description' => "This badge signifies a member's vital role in sparking and sustaining the most engaging discussions, skillfully weaving threads of dialogue throughout the platform.",
                'stats' => ['rarity' => 'Rare', 'origin' => 'Discourse Analysis', 'type' => 'Communication'],
                'rarity_percentage' => 7.3,
                'holders' => $allUsers->slice(1, 4),
            ]
        ])->map(fn($badge) => (object)$badge);
    }

    private function getDummySpotlightUsers(): Collection
    {
        $users = [];
        $names = ['AstroNomad', 'PixelProphet', 'SynthWave', 'GlitchArt', 'NeuralNet', 'VectorVibe', 'Cyborg', 'Dreamer'];
        foreach ($names as $name) {
            $users[] = (object)['username' => $name, 'profile_picture' => "https://placehold.co/100x100/1f2937/d1d5db?text=" . substr($name, 0, 1),];
        }
        return collect($users);
    }

    private function getDummyAiCreations(): Collection
    {
        $creations = [];
        $titles = ['Neon Alleyway', 'Crystal Desert', 'Floating Islands', 'Cybernetic Forest', 'Sunken Metropolis', 'Mechanical Heart', 'Galactic Library', 'Solar Flare Dragon'];
        $users = $this->getDummySpotlightUsers();

        foreach ($titles as $index => $title) {
            $creations[] = (object)['id' => $index + 1, 'slug' => Str::slug($title), 'title' => $title, 'image_path' => "https://placehold.co/400x400/111827/4f46e5?text=" . urlencode($title), 'user' => $users->random(),];
        }
        return collect($creations);
    }

    private function getTrendingWords(): Collection
    {
        return collect([['word' => 'Community', 'size' => 10], ['word' => 'Creative', 'size' => 8], ['word' => 'Future', 'size' => 9], ['word' => 'AI', 'size' => 12], ['word' => 'Tashkent', 'size' => 8], ['word' => 'GOAT', 'size' => 11], ['word' => 'Art', 'size' => 9], ['word' => 'Vision', 'size' => 7],]);
    }
}
