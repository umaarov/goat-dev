<?php

namespace App\Providers;

use App\Models\Post;
use App\Policies\PostPolicy;
use App\Policies\SessionPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Post::class => PostPolicy::class,
    ];

    public function boot(): void
    {
        Gate::define('delete-session', [SessionPolicy::class, 'delete']);
        $this->registerPolicies();
    }
}
