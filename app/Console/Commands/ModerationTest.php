<?php

namespace App\Console\Commands;

use App\Services\ModerationService;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;

/**
 * Live connectivity/sanity check for content moderation. Makes a real call to
 * DeepSeek (text/url) and/or Groq (image) so you can confirm keys, prompts and
 * JSON-mode responses work before trusting moderation in production.
 */
class ModerationTest extends Command
{
    protected $signature = 'moderation:test
        {text? : Text to moderate (defaults to a benign sample)}
        {--url= : A URL to moderate}
        {--image= : Path to an image file to moderate}
        {--lang=en : Language code for the response}';

    protected $description = 'Run a live moderation check against DeepSeek (text/url) / Groq (image)';

    public function handle(ModerationService $moderation): int
    {
        $lang = (string) $this->option('lang');
        $ran = false;

        $text = $this->argument('text') ?? ($this->option('url') || $this->option('image') ? null : 'Hello, this is a friendly test message.');

        if ($text !== null) {
            $ran = true;
            $this->report('TEXT (DeepSeek)', $moderation->moderateText($text, $lang));
        }

        if ($url = $this->option('url')) {
            $ran = true;
            $this->report('URL (DeepSeek)', $moderation->moderateUrl((string) $url, $lang));
        }

        if ($imagePath = $this->option('image')) {
            $ran = true;
            if (! is_file($imagePath)) {
                $this->components->error("Image not found: {$imagePath}");

                return self::FAILURE;
            }
            $file = new UploadedFile($imagePath, basename($imagePath), null, null, true);
            $this->report('IMAGE (Groq vision)', $moderation->moderateImage($file, $lang));
        }

        if (! $ran) {
            $this->components->warn('Nothing to check.');
        }

        return self::SUCCESS;
    }

    private function report(string $label, array $result): void
    {
        $appropriate = $result['is_appropriate'] ?? true;
        $category = $result['category'] ?? 'UNKNOWN';

        $this->newLine();
        $this->components->twoColumnDetail("<options=bold>{$label}</>", $appropriate ? '<fg=green>appropriate</>' : '<fg=red>flagged</>');
        $this->components->twoColumnDetail('  category', (string) $category);
        if (! empty($result['reason'])) {
            $this->components->twoColumnDetail('  reason', (string) $result['reason']);
        }

        // A NONE/skip category with no key means the provider was not called.
        if (in_array($category, ['NONE'], true)) {
            $this->components->twoColumnDetail('  note', '<fg=yellow>provider may be unconfigured — see moderation:status</>');
        }
    }
}
