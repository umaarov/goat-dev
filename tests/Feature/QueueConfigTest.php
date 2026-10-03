<?php

namespace Tests\Feature;

use Tests\TestCase;

class QueueConfigTest extends TestCase
{
    // a serializer on the queue connection leaves finished jobs in :reserved, so they run again after retry_after
    public function test_the_redis_queue_uses_a_connection_without_a_serializer(): void
    {
        $name = config('queue.connections.redis.connection');

        $this->assertSame('queue', $name);
        $this->assertSame(\Redis::SERIALIZER_NONE, config("database.redis.{$name}.options.serializer"));
    }

    public function test_the_worker_consumes_every_queue_jobs_are_sent_to(): void
    {
        if (!is_file(base_path('docker-compose.yml'))) {
            $this->markTestSkipped('docker-compose.yml is not part of the image');
        }

        $compose = file_get_contents(base_path('docker-compose.yml'));
        preg_match('/queue:work\s+--queue=(\S+)/', $compose, $m);

        $this->assertNotEmpty($m, 'worker must list its queues');
        foreach (config('monitoring.queues') as $queue) {
            $this->assertContains($queue, explode(',', $m[1]));
        }
    }
}
