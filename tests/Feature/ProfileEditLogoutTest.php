<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileEditLogoutTest extends TestCase
{
    use RefreshDatabase;

    public static function accounts(): array
    {
        return [
            'with a password' => [['password' => 'a-long-password-123']],
            'google only' => [['password' => null, 'google_id' => 'g-1']],
            'x only' => [['password' => null, 'x_id' => 'x-1']],
            'telegram only' => [['password' => null, 'telegram_id' => 111111]],
            'github only' => [['password' => null, 'github_id' => 'gh-1']],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('accounts')]
    public function test_every_kind_of_account_can_log_out_from_the_edit_page(array $attributes): void
    {
        $user = User::factory()->create($attributes);

        $html = $this->actingAs($user)->get(route('profile.edit'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<form action="[^"]*\/logout" method="POST"/', $html);
    }

    public function test_changing_the_password_is_offered_only_to_accounts_that_have_one(): void
    {
        $withPassword = User::factory()->create(['password' => 'a-long-password-123']);
        $without = User::factory()->create(['password' => null, 'x_id' => 'x-2']);

        $this->assertStringContainsString(route('password.change.form'), $this->actingAs($withPassword)->get(route('profile.edit'))->getContent());
        $this->assertStringNotContainsString(route('password.change.form'), $this->actingAs($without)->get(route('profile.edit'))->getContent());
    }

    public function test_the_logout_button_really_logs_a_passwordless_account_out(): void
    {
        $user = User::factory()->create(['password' => null, 'telegram_id' => 999999]);

        $this->actingAs($user)->post(route('logout'))->assertRedirect();

        $this->assertGuest();
    }
}
