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
     * Invalidates any pending code, stores a new one and emails it to the student.
     */
    public function issue(Student $student): void
    {
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
     */
    public function verify(Student $student, string $code): bool
    {
        return DB::transaction(function () use ($student, $code): bool {
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
