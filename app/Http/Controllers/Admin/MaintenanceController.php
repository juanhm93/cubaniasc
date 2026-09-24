<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

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

    public function clearApplicationCache(): RedirectResponse
    {
        Artisan::call('optimize:clear');

        return back()
            ->with('success', 'Application caches cleared.')
            ->with('maintenance_output', Artisan::output());
    }

    public function runMigrations(): RedirectResponse
    {
        Artisan::call('migrate', ['--force' => true]);

        return back()
            ->with('success', 'Database migrations finished.')
            ->with('maintenance_output', Artisan::output());
    }
}
