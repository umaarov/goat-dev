<?php

namespace App\Notifications\Messages;

/**
 * A push message destined for Firebase Cloud Messaging.
 *
 * `data` values are coerced to strings when sent (FCM requires string values
 * in the data payload).
 */
class FcmMessage
{
    public function __construct(
        public string $title,
        public string $body,
        public array $data = [],
    ) {}

    public static function create(string $title, string $body, array $data = []): self
    {
        return new self($title, $body, $data);
    }
}
