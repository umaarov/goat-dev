<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'username' => $this->faker->unique()->userName(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'profile_picture' => null,
            'google_id' => null,
            'x_id' => null,
            'telegram_id' => null,
            'github_id' => null,
            'is_developer' => false,
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn(array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function withPassword(string $password): static
    {
        return $this->state(fn(array $attributes) => [
            'password' => bcrypt($password),
        ]);
    }

    public function developer(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_developer' => true,
        ]);
    }
}
