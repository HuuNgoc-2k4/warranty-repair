<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
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
        // Vai trò không hợp lệ (dữ liệu lạ trong DB) thì không có quyền nào
        foreach (UserRole::abilities() as $ability) {
            Gate::define($ability, fn (User $user) => UserRole::tryFrom((string) $user->role)?->allows($ability) ?? false);
        }
    }
}
