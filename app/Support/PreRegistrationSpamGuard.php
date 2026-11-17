<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

/**
 * Cheap bot filter for the public pre-registration form, without captchas or
 * third-party services:
 *
 * - a honeypot field that people never see and bots tend to fill in;
 * - an encrypted timestamp issued with the form, because bots post instantly.
 *
 * Submissions that look automated get the normal success response but nothing
 * is stored or emailed, so the bot learns nothing.
 */
final class PreRegistrationSpamGuard
{
    public const HONEYPOT_FIELD = 'website';

    public const TOKEN_FIELD = 'form_token';

    public const MIN_SECONDS_TO_FILL = 3;

    public static function issueToken(): string
    {
        return Crypt::encryptString((string) now()->timestamp);
    }

    /**
     * Unix time the form was rendered, or null when the token is missing or forged.
     */
    public static function tokenIssuedAt(mixed $token): ?int
    {
        if (! is_string($token) || $token === '') {
            return null;
        }

        try {
            $timestamp = Crypt::decryptString($token);
        } catch (DecryptException) {
            return null;
        }

        return ctype_digit($timestamp) ? (int) $timestamp : null;
    }

    public static function looksAutomated(Request $request): bool
    {
        if (filled($request->input(self::HONEYPOT_FIELD))) {
            return true;
        }

        $issuedAt = self::tokenIssuedAt($request->input(self::TOKEN_FIELD));

        return $issuedAt === null || now()->timestamp - $issuedAt < self::MIN_SECONDS_TO_FILL;
    }
}
