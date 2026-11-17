<?php

namespace App\Providers;

use App\Models\DanceType;
use App\Models\Level;
use App\Models\Student;
use App\Models\User;
use App\Policies\DanceTypePolicy;
use App\Policies\LevelPolicy;
use App\Policies\StudentPolicy;
use App\Policies\UserPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
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
        $this->configureRateLimiting();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Gate::policy(Level::class, LevelPolicy::class);
        Gate::policy(DanceType::class, DanceTypePolicy::class);
        Gate::policy(Student::class, StudentPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

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

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('app-login-request', function (Request $request): array {
            $account = $this->loginIdentifier($request);

            return [
                Limit::perMinute(3)->by('app-request:'.$account.'|'.$request->ip()),
                Limit::perHour(6)->by('app-request-account:'.$account),
                Limit::perMinute(10)->by('app-request-ip:'.$request->ip()),
                Limit::perHour(60)->by('app-request-ip-hour:'.$request->ip()),
            ];
        });

        RateLimiter::for('app-login-verify', function (Request $request): array {
            $account = $this->loginIdentifier($request);

            return [
                Limit::perMinute(5)->by('app-verify:'.$account.'|'.$request->ip()),
                Limit::perHour(20)->by('app-verify-account:'.$account),
                Limit::perMinute(20)->by('app-verify-ip:'.$request->ip()),
            ];
        });
    }

    /**
     * Normalized email or DNI, so `Ana@x.com ` and `ana@x.com` share the same budget.
     */
    private function loginIdentifier(Request $request): string
    {
        $identifier = $request->filled('email') ? $request->input('email') : $request->input('dni');

        return Str::transliterate(Str::lower(trim((string) $identifier)));
    }
}
