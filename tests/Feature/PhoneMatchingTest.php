<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The voice provider reports the number it dialled in whatever notation it likes —
 * "008801890318278" for a student whose profile says "01890318278". Matching works on
 * the subscriber digits so the notation stops mattering.
 */
class PhoneMatchingTest extends TestCase
{
    use RefreshDatabase;

    public static function notations(): array
    {
        return [
            'international access prefix' => ['008801890318278'],
            'plus and country code' => ['+8801890318278'],
            'bare country code' => ['8801890318278'],
            'national with trunk zero' => ['01890318278'],
            'spaced and dashed' => ['+880 1890-318 278'],
            'bracketed' => ['00 (880) 1890318278'],
            'subscriber digits only' => ['1890318278'],
        ];
    }

    #[DataProvider('notations')]
    public function test_any_notation_resolves_to_the_same_student(string $reported): void
    {
        $student = User::factory()->student()->create(['phone' => '01890318278']);

        $this->assertTrue($student->is(PhoneNumber::findStudent($reported)));
    }

    #[DataProvider('notations')]
    public function test_any_notation_reduces_to_the_same_canonical_form(string $written): void
    {
        $this->assertSame('1890318278', PhoneNumber::canonical($written));
    }

    public function test_a_student_saved_in_international_form_is_still_found(): void
    {
        // Whichever way round it is stored, it is found either way round.
        $student = User::factory()->student()->create(['phone' => '008801890318278']);

        $this->assertTrue($student->is(PhoneNumber::findStudent('01890318278')));
        $this->assertTrue($student->is(PhoneNumber::findStudent('+8801890318278')));
    }

    public function test_a_different_number_that_merely_ends_the_same_way_is_not_matched(): void
    {
        User::factory()->student()->create(['phone' => '01890318278']);

        // Shares the trailing digits the shortlist is built from, but is not the same
        // line — the canonical forms differ, so it is rejected rather than accepted.
        $this->assertNull(PhoneNumber::findStudent('+1 234 890318278'));
        $this->assertNull(PhoneNumber::findStudent('01990318278'));
    }

    public function test_a_number_two_students_share_is_left_unmatched(): void
    {
        User::factory()->student()->create(['phone' => '01890318278']);
        User::factory()->student()->create(['phone' => '8801890318278']);

        $this->assertNull(PhoneNumber::findStudent('008801890318278'));
    }

    public function test_nothing_is_matched_on_too_few_digits(): void
    {
        User::factory()->student()->create(['phone' => '01890318278']);

        $this->assertNull(PhoneNumber::findStudent('318278'));
        $this->assertNull(PhoneNumber::findStudent(''));
        $this->assertNull(PhoneNumber::findStudent(null));
    }

    public function test_a_teacher_is_never_matched(): void
    {
        User::factory()->teacher()->create(['phone' => '01890318278']);

        $this->assertNull(PhoneNumber::findStudent('008801890318278'));
    }
}
