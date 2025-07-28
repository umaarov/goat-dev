<?php

namespace Tests\Unit;

use App\Services\AvatarService;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AvatarServiceTest extends TestCase
{
    #[Test]
    public function it_generates_an_initials_avatar_and_saves_it()
    {
        // Fake the storage to prevent actual file writes
        Storage::fake('public');
        $service = new AvatarService();
        $path = $service->generateInitialsAvatar('John', 'Doe', 'user123');

        // Assert the file was "saved" to the fake storage disk
        Storage::disk('public')->assertExists($path);

        // Assert the path is what we expect
        $this->assertEquals('profile_pictures/initial_user123.png', $path);
    }

    #[Test]
    public function it_generates_an_avatar_with_only_a_first_name()
    {
        Storage::fake('public');
        $service = new AvatarService();
        $path = $service->generateInitialsAvatar('Jane', '', 'user456');

        Storage::disk('public')->assertExists($path);
        $this->assertEquals('profile_pictures/initial_user456.png', $path);
    }
}
