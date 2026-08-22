<?php

namespace App\Support;

use App\Models\User;

class PhoneNumber
{
    /**
     * The country the exam calls are placed in. Its dialling code is stripped when a
     * number is reduced to its canonical form.
     */
    public const COUNTRY_CODE = '880';

    /**
     * How many trailing digits are used to shortlist students in SQL. The shortlist is
     * then confirmed in PHP by comparing canonical forms, so this only has to be short
     * enough to catch every notation and long enough not to shortlist half the table.
     */
    public const SUFFIX_LENGTH = 9;

    /**
     * Reduce a phone number to digits only, for storage.
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
     * Reduce a phone number to the subscriber digits, so the same line written in any
     * notation compares equal:
     *
     *   008801890318278  →  1890318278
     *   +880 1890-318278 →  1890318278
     *   8801890318278    →  1890318278
     *   01890318278      →  1890318278
     *
     * The international access prefix is dropped first, then the country code, then the
     * national trunk zero. Bangladeshi mobile numbers begin 01, so after the trunk zero
     * goes there is no leading zero left to confuse anything.
     */
    public static function canonical(?string $phone): ?string
    {
        $digits = self::normalize($phone);

        if ($digits === null) {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, self::COUNTRY_CODE)) {
            $digits = substr($digits, strlen(self::COUNTRY_CODE));
        }

        $digits = ltrim($digits, '0');

        return $digits === '' ? null : $digits;
    }

    /**
     * Resolve a phone number to exactly one student.
     *
     * Returns null when nothing matches, or when more than one student shares the
     * number — the caller stores those transcripts as unmatched rather than guessing.
     */
    public static function findStudent(?string $phone): ?User
    {
        $canonical = self::canonical($phone);

        if ($canonical === null) {
            return null;
        }

        // Too short to identify anyone; matching on it would be a guess.
        if (strlen($canonical) < self::SUFFIX_LENGTH) {
            return null;
        }

        // SQL narrows the field on the trailing digits, which every notation shares...
        $suffix = substr($canonical, -self::SUFFIX_LENGTH);

        $matches = User::students()
            ->where('phone', 'like', "%{$suffix}")
            ->limit(10)
            ->get()
            // ...then the canonical forms are compared in full, so a shortlisted number
            // that merely ends the same way is dropped rather than accepted.
            ->filter(fn (User $student) => self::canonical($student->phone) === $canonical);

        return $matches->count() === 1 ? $matches->first() : null;
    }
}
