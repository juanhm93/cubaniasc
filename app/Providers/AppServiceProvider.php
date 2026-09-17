<?php

namespace App\Providers;

use App\Models\DanceType;
use App\Models\Level;
use App\Models\Student;
use App\Policies\DanceTypePolicy;
use App\Policies\LevelPolicy;
use App\Policies\StudentPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Gate::policy(Level::class, LevelPolicy::class);
        Gate::policy(DanceType::class, DanceTypePolicy::class);
        Gate::policy(Student::class, StudentPolicy::class);

        Date::use(CarbonImmutable::class);

        if (! $this->app->environment(['local', 'testing'])) {
            URL::forceHttps();
        }

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
