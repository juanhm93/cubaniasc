<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;

class MaintenanceController extends Controller
{
    public function clearApplicationCache(): RedirectResponse
    {
        Artisan::call('optimize:clear');

        return back()->with('success', 'Application caches cleared.');
    }

    public function runMigrations(): RedirectResponse
    {
        Artisan::call('migrate', ['--force' => true]);

        return back()->with('success', 'Database migrations finished.');
    }
}
