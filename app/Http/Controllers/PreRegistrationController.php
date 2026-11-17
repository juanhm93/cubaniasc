<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePublicPreRegistrationRequest;
use App\Mail\PreRegistrationConfirmationMail;
use App\Models\PreRegistration;
use App\Notifications\NewPreRegistrationNotification;
use App\Support\PreRegistrationRecipients;
use App\Support\PreRegistrationSpamGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
            'formToken' => PreRegistrationSpamGuard::issueToken(),
        ]);
    }

    /**
     * Bots, and emails that already pre-registered, get the same success response as a
     * real submission but nothing is stored or emailed: the form never reveals who is
     * registered and never becomes a way to flood the staff or third parties with mail.
     */
    public function store(StorePublicPreRegistrationRequest $request): RedirectResponse
    {
        $data = $request->preRegistrationData();

        if (PreRegistrationSpamGuard::looksAutomated($request)) {
            Log::info('Pre-registration discarded as automated.', ['ip' => $request->ip()]);
        } elseif (! PreRegistration::withTrashed()->where('email', $data['email'])->exists()) {
            $preRegistration = PreRegistration::query()->create($data);

            $this->dispatchPreRegistrationMail($preRegistration);
        }

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
