<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePublicPreRegistrationRequest;
use App\Models\PreRegistration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PreRegistrationController extends Controller
{
    /**
     * Public pre-registration form (no authentication).
     */
    public function create(Request $request): Response
    {
        return Inertia::render('pre-registration', [
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(StorePublicPreRegistrationRequest $request): RedirectResponse
    {
        PreRegistration::query()->create($request->validated());

        return redirect()
            ->route('pre-registration.create')
            ->with('status', 'pre-registration-created');
    }
}
