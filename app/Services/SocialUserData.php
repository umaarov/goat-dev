<?php

namespace App\Services;

/**
 * Normalised representation of a verified social-provider identity.
 */
class SocialUserData
{
    public function __construct(
        public string $id,
        public ?string $email = null,
        public ?string $name = null,
        public ?string $nickname = null,
        public ?string $avatar = null,
    ) {}
}
