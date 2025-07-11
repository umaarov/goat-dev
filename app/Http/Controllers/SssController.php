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
        $spotlightUsers = $this->getDummySpotlightUsers();
        $recentAiCreations = $this->getDummyAiCreations();
        $trendingWords = $this->getTrendingWords();

        return view('sss', compact('spotlightUsers', 'recentAiCreations', 'trendingWords'));
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
