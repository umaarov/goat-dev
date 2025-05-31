<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ClearLog extends Command
{
    protected $signature = 'app:clear-log';

    protected $description = 'Command description';

    public function handle()
    {
        file_put_contents(storage_path('logs/laravel.log'), '');
        $this->info('laravel.log has been cleared.');
    }

}
