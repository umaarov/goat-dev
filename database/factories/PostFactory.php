<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'question' => $this->faker->sentence().'?',
            'option_one_title' => $this->faker->word(),
            'option_one_image' => 'post_images/'.$this->faker->uuid().'.webp',
            'option_one_image_lqip' => null,
            'option_two_title' => $this->faker->word(),
            'option_two_image' => 'post_images/'.$this->faker->uuid().'.webp',
            'option_two_image_lqip' => null,
            'option_one_votes' => 0,
            'option_two_votes' => 0,
            'total_votes' => 0,
            'view_count' => 0,
        ];
    }
}
