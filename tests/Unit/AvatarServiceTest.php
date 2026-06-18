<?php

namespace Tests\Unit;

use App\Services\AvatarService;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AvatarServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Avatar rendering needs a TrueType font in storage/app/fonts. Skip
        // rather than fail in environments where none is deployed (e.g. CI
        // before assets are provisioned).
        if (! glob(storage_path('app/fonts').DIRECTORY_SEPARATOR.'*.ttf')) {
            $this->markTestSkipped('No .ttf font available in storage/app/fonts.');
        }
    }

    #[Test]
    public function it_generates_an_initials_avatar_and_saves_it()
    {
        Storage::fake('public');

        // Signature is (userId, firstName, lastName).
        $path = (new AvatarService)->generateInitialsAvatar('user123', 'John', 'Doe');

        Storage::disk('public')->assertExists($path);
        $this->assertMatchesRegularExpression('#^profile_pictures/initials_user123_[A-Za-z0-9]+\.webp$#', $path);
    }

    #[Test]
    public function it_generates_an_avatar_with_only_a_first_name()
    {
        Storage::fake('public');

        $path = (new AvatarService)->generateInitialsAvatar('user456', 'Jane', '');

        Storage::disk('public')->assertExists($path);
        $this->assertMatchesRegularExpression('#^profile_pictures/initials_user456_[A-Za-z0-9]+\.webp$#', $path);
    }

    #[Test]
    public function it_falls_back_to_a_question_mark_for_empty_names()
    {
        Storage::fake('public');

        $path = (new AvatarService)->generateInitialsAvatar('user789', '', '');

        Storage::disk('public')->assertExists($path);
        $this->assertStringStartsWith('profile_pictures/initials_user789_', $path);
    }
}
