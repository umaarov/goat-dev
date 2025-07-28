<?php

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class RegistrationTest extends DuskTestCase
{
    use DatabaseMigrations;

    /** @test */
    public function a_user_can_register_successfully_through_the_form()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/register')
                ->type('first_name', 'Dusk')
                ->type('username', 'duskuser')
                ->type('email', 'dusk@laravel.com')
                ->type('password', 'password')
                ->type('password_confirmation', 'password')
                ->check('terms')
                ->press(__('messages.register'))
                ->assertPathIs('/login')
                ->assertSee(__('messages.registration_successful_verify_email'));
        });
    }

    /** @test */
    public function live_username_validation_shows_availability()
    {
        User::factory()->create(['username' => 'existinguser']);

        $this->browse(function (Browser $browser) {
            $browser->visit('/register')
                // Test for a username that is too short
                ->type('username', 'abc')
                ->pause(700)
                ->assertSeeIn('#username-status', __('messages.username_min_length'))

                // Test for a taken username
                ->type('username', 'existinguser')
                ->pause(700)
                ->assertSeeIn('#username-status', __('messages.username_taken'))

                // Test for an available username
                ->type('username', 'newuniqueuser')
                ->pause(700)
                ->assertSeeIn('#username-status', __('messages.username_available'));
        });
    }
}
