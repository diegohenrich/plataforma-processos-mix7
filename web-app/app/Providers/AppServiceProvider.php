<?php

namespace App\Providers;

use App\Models\KnowledgeItem;
use App\Models\User;
use App\Policies\KnowledgePolicy;
use App\Policies\PersonalApiTokenPolicy;
use App\Policies\TeamMemberPolicy;
use App\View\Composers\TaskTrayComposer;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(User::class, TeamMemberPolicy::class);
        Gate::policy(KnowledgeItem::class, KnowledgePolicy::class);
        Gate::define('managePersonalApiTokens', [PersonalApiTokenPolicy::class, 'managePersonalApiTokens']);
        View::composer('layouts.task-tray', TaskTrayComposer::class);
    }
}
