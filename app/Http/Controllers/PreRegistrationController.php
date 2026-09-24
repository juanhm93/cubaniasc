<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePublicPreRegistrationRequest;
use App\Mail\PreRegistrationConfirmationMail;
use App\Models\PreRegistration;
use App\Notifications\NewPreRegistrationNotification;
use App\Support\PreRegistrationRecipients;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

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
        $preRegistration = PreRegistration::query()->create($request->validated());

        $this->dispatchPreRegistrationMail($preRegistration);

        return redirect()
            ->route('pre-registration.create')
            ->with('status', 'pre-registration-created');
    }

    /**
     * Alert the staff and confirm to the applicant.
     *
     * Everything here runs synchronously because the production host has no
     * queue worker. Recipients are notified one by one so a single failure
     * cannot cost the rest their notification, and the notification writes its
     * database row before attempting mail (see its via method).
     *
     * A saved pre-registration is worth more than a delivered email, so every
     * failure is reported and swallowed instead of breaking the public form.
     */
    private function dispatchPreRegistrationMail(PreRegistration $preRegistration): void
    {
        $notification = new NewPreRegistrationNotification($preRegistration);

        foreach (PreRegistrationRecipients::staff() as $recipient) {
            $this->attempt(fn () => $recipient->notify($notification));
        }

        $this->attempt(fn () => Mail::to($preRegistration->email)
            ->send(new PreRegistrationConfirmationMail($preRegistration)));
    }

    private function attempt(callable $stage): void
    {
        try {
            $stage();
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
