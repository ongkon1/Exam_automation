<?php

namespace Database\Factories;

use App\Models\Result;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Result>
 */
class ResultFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fullMarks = 100;
        $marksObtained = fake()->randomFloat(2, 25, 100);

        return [
            'student_id' => User::factory()->student(),
            'exam_name' => fake()->randomElement(['First Term 2026', 'Midterm 2026', 'Final Term 2026']),
            'subject' => fake()->randomElement(['Mathematics', 'English', 'Physics', 'Chemistry', 'Biology']),
            'exam_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'full_marks' => $fullMarks,
            'marks_obtained' => $marksObtained,
            'grade' => Result::gradeFor(round($marksObtained / $fullMarks * 100, 2)),
            'remarks' => fake()->optional()->sentence(),
        ];
    }
}
