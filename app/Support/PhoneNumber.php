<?php

namespace App\Support;

use App\Models\User;

class PhoneNumber
{
    /**
     * How many trailing digits must agree for a fallback match. A voice provider may
     * report "+8801766666666" for a number a teacher entered as "01766666666".
     */
    public const SUFFIX_LENGTH = 9;

    /**
     * Reduce a phone number to digits only so stored and inbound numbers compare equally.
     */
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return $digits === '' ? null : $digits;
    }

    /**
     * Resolve a phone number to exactly one student.
     *
     * Returns null when nothing matches, or when a suffix match is ambiguous — the
     * caller stores those transcripts as unmatched rather than guessing.
     */
    public static function findStudent(?string $phone): ?User
    {
        $normalized = self::normalize($phone);

        if ($normalized === null) {
            return null;
        }

        $exact = User::students()->where('phone', $normalized)->first();

        if ($exact) {
            return $exact;
        }

        if (strlen($normalized) < self::SUFFIX_LENGTH) {
            return null;
        }

        $suffix = substr($normalized, -self::SUFFIX_LENGTH);
        $candidates = User::students()->where('phone', 'like', "%{$suffix}")->limit(2)->get();

        return $candidates->count() === 1 ? $candidates->first() : null;
    }
}
