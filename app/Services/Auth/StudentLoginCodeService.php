<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Mail\StudentLoginCodeMail;
use App\Models\Student;
use App\Models\StudentLoginCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * One-time codes the mobile app uses instead of a password.
 */
final class StudentLoginCodeService
{
    public const CODE_LENGTH = 6;

    public const EXPIRES_IN_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    /**
     * A new code is not issued while the previous one is younger than this,
     * so requesting codes in a loop does not reset the attempt counter.
     */
    public const RESEND_COOLDOWN_SECONDS = 60;

    /**
     * Wrong guesses allowed per student across all their codes in the window.
     */
    public const MAX_FAILED_ATTEMPTS_PER_WINDOW = 10;

    public const FAILED_ATTEMPTS_WINDOW_MINUTES = 60;

    /**
     * Invalidates any pending code, stores a new one and emails it to the student.
     * Does nothing while the latest pending code is still within the cooldown.
     */
    public function issue(Student $student): void
    {
        if ($this->hasRecentPendingCode($student)) {
            return;
        }

        $code = $this->generateCode();

        DB::transaction(function () use ($student, $code): void {
            $student->loginCodes()->pending()->update(['consumed_at' => now()]);

            $student->loginCodes()->create([
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(self::EXPIRES_IN_MINUTES),
            ]);
        });

        Mail::to($student->email)->send(
            new StudentLoginCodeMail($student, $code, self::EXPIRES_IN_MINUTES),
        );
    }

    /**
     * Checks the latest pending code. A correct code is consumed; each wrong
     * attempt counts, and the code is burned after MAX_ATTEMPTS failures.
     * Too many failures across codes lock the student out for the window.
     */
    public function verify(?Student $student, string $code): bool
    {
        if ($student === null) {
            // Same hashing cost as a real check, so timing does not reveal who is enrolled.
            Hash::make($code);

            return false;
        }

        return DB::transaction(function () use ($student, $code): bool {
            if ($this->failedAttemptsInWindow($student) >= self::MAX_FAILED_ATTEMPTS_PER_WINDOW) {
                return false;
            }

            /** @var StudentLoginCode|null $loginCode */
            $loginCode = $student->loginCodes()
                ->pending()
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($loginCode === null) {
                return false;
            }

            if (Hash::check($code, $loginCode->code_hash)) {
                $loginCode->consume();

                return true;
            }

            $loginCode->increment('attempts');

            if ($loginCode->attempts >= self::MAX_ATTEMPTS) {
                $loginCode->consume();
            }

            return false;
        });
    }

    private function hasRecentPendingCode(Student $student): bool
    {
        return $student->loginCodes()
            ->pending()
            ->where('created_at', '>', now()->subSeconds(self::RESEND_COOLDOWN_SECONDS))
            ->exists();
    }

    private function failedAttemptsInWindow(Student $student): int
    {
        return (int) $student->loginCodes()
            ->where('created_at', '>=', now()->subMinutes(self::FAILED_ATTEMPTS_WINDOW_MINUTES))
            ->sum('attempts');
    }

    private function generateCode(): string
    {
        return str_pad(
            (string) random_int(0, (10 ** self::CODE_LENGTH) - 1),
            self::CODE_LENGTH,
            '0',
            STR_PAD_LEFT,
        );
    }
}
