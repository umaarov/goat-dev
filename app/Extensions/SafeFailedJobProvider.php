<?php

namespace App\Extensions;

use Illuminate\Queue\Failed\DatabaseUuidFailedJobProvider;
use Illuminate\Support\Str;

class SafeFailedJobProvider extends DatabaseUuidFailedJobProvider
{
    public function log($connection, $queue, $payload, $exception)
    {
        try {
            return parent::log($connection, $queue, $payload, $exception);
        } catch (\PDOException $e) {
            $data = json_decode($payload, true);
            $data['uuid'] = Str::uuid()->toString();

            return parent::log($connection, $queue, json_encode($data), $exception);
        }
    }
}
