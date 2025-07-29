<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Generate the sitemap for the application.';

    public function handle()
    {
        $this->info('Starting sitemap generation...');
        $sitemapPath = public_path('sitemap.xml');

        $sitemap = Sitemap::create();

        // 1. Add Static Pages
        $this->info('Adding static pages...');
        $sitemap->add(Url::create(route('home'))
            ->setLastModificationDate(Carbon::now())
            ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
            ->setPriority(1.0));

        $sitemap->add(Url::create(route('about'))
            ->setLastModificationDate(Carbon::parse('2024-01-01'))
            ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
            ->setPriority(0.5));

        $sitemap->add(Url::create(route('terms'))
            ->setLastModificationDate(Carbon::parse('2024-01-01'))
            ->setChangeFrequency(Url::CHANGE_FREQUENCY_YEARLY)
            ->setPriority(0.3));

        $sitemap->add(Url::create(route('sponsorship'))
            ->setLastModificationDate(Carbon::parse('2024-01-01'))
            ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
            ->setPriority(0.4));

        $sitemap->add(Url::create(route('ads'))
            ->setLastModificationDate(Carbon::parse('2024-01-01'))
            ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
            ->setPriority(0.4));

        // 2. Add Posts
        $this->info('Adding posts...');

        Post::with('user')
            // ->where('is_published', true)
            // ->where('moderation_status', 'approved')
            // ->whereNotNull('slug')
            ->orderBy('created_at', 'desc')
            ->chunk(200, function ($posts) use ($sitemap) {
                foreach ($posts as $post) {
                    try {
                        $url = route('posts.show.user-scoped', [
                            'username' => $post->user->username,
                            'post' => $post->id,
                        ]);

                        $sitemap->add(
                            Url::create($url)
                                ->setLastModificationDate($post->updated_at)
                                ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                                ->setPriority(0.9)
                        );

                    } catch (Exception $e) {
                        $this->error("Could not generate URL for Post ID {$post->id} ('{$post->question}'): " . $e->getMessage());
                    }
                }
            });

        $this->info('Posts added.');

        // 3. Add User Profiles
        $this->info('Adding user profiles...');
        User::query()
            // ->where('is_active', true)
            // ->where('profile_is_public', true)
            // ->whereHas('posts')
            ->orderBy('created_at', 'desc')
            ->chunk(200, function ($users) use ($sitemap) {
                foreach ($users as $user) {
                    try {
                        $url = route('profile.show', ['username' => $user->username]);
                        $sitemap->add(Url::create($url)
                            ->setLastModificationDate($user->updated_at)
                            ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                            ->setPriority(0.7));
                    } catch (Exception $e) {
                        $this->error("Could not generate URL for User ID {$user->id} ('{$user->username}'): " . $e->getMessage());
                    }
                }
            });
        $this->info('User profiles added.');

        try {
            $sitemap->writeToFile($sitemapPath);
            $this->info("Sitemap generated successfully at: {$sitemapPath}");
        } catch (Exception $e) {
            $this->error("Failed to write sitemap file: " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
