<?php

namespace Database\Factories;

use App\Models\ExamTranscript;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamTranscript>
 */
class ExamTranscriptFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => User::factory()->student(),
            'phone' => fake()->unique()->numerify('01#########'),
            'subject' => fake()->randomElement(['Mathematics', 'English', 'Physics']),
            'transcript' => "Examiner: Define Newton's second law.\n"
                .'Student: Force equals mass times acceleration.',
            'external_id' => fake()->unique()->uuid(),
            'status' => ExamTranscript::STATUS_PENDING,
        ];
    }

    public function unmatched(): static
    {
        return $this->state(fn () => [
            'student_id' => null,
            'status' => ExamTranscript::STATUS_UNMATCHED,
        ]);
    }
}
