<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ModerationStatus extends Command
{
    protected $signature = 'moderation:status';

    protected $description = 'Show content-moderation provider configuration (DeepSeek text + Groq vision)';

    public function handle(): int
    {
        $deepseekKey = config('services.deepseek.api_key');
        $groqKey = config('services.groq.api_key');
        $visionModel = config('services.groq.vision_model');

        $textOk = ! empty($deepseekKey);
        $imageOk = ! empty($groqKey) && ! empty($visionModel);

        $this->components->twoColumnDetail('<options=bold>TEXT moderation (DeepSeek)</>', $textOk ? '<fg=green>configured</>' : '<fg=yellow>off (text allowed)</>');
        $this->components->twoColumnDetail('  model', (string) config('services.deepseek.model'));
        $this->components->twoColumnDetail('  base_url', (string) config('services.deepseek.base_url'));

        $this->newLine();

        $this->components->twoColumnDetail('<options=bold>IMAGE moderation (Groq vision)</>', $imageOk ? '<fg=green>configured</>' : '<fg=yellow>off (images allowed)</>');
        $this->components->twoColumnDetail('  vision_model', (string) ($visionModel ?: '(not set)'));

        $this->newLine();
        $this->components->info('Live check: php artisan moderation:test "some text"  (add --url= or --image=path)');

        return self::SUCCESS;
    }
}
