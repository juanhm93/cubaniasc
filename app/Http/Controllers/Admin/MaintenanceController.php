<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RunMaintenanceActionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class MaintenanceController extends Controller
{
    /**
     * Plain Blade page (no Inertia/React) so migrations can be run even when
     * the SPA is broken by missing tables.
     */
    public function show(): View
    {
        Artisan::call('migrate:status');

        return view('maintenance', [
            'migrationStatus' => Artisan::output(),
        ]);
    }

    public function clearApplicationCache(RunMaintenanceActionRequest $request): RedirectResponse
    {
        return $this->runArtisan($request, 'optimize:clear', [], 'Application caches cleared.');
    }

    public function runMigrations(RunMaintenanceActionRequest $request): RedirectResponse
    {
        return $this->runArtisan($request, 'migrate', ['--force' => true], 'Database migrations finished.');
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function runArtisan(RunMaintenanceActionRequest $request, string $command, array $parameters, string $successMessage): RedirectResponse
    {
        $context = ['command' => $command, 'user_id' => $request->user()->id, 'ip' => $request->ip()];

        Log::notice('Owner maintenance command started.', $context);

        try {
            Artisan::call($command, $parameters);
        } catch (Throwable $exception) {
            report($exception);
            Log::error('Owner maintenance command failed.', $context);

            return back()->withErrors(['maintenance' => 'El comando falló. Revisa el log del servidor.']);
        }

        return back()
            ->with('success', $successMessage)
            ->with('maintenance_output', Artisan::output());
    }
}
