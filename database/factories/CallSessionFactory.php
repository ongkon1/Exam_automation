<?php

namespace Database\Factories;

use App\Models\CallSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CallSession>
 */
class CallSessionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => User::factory()->student(),
            'call_id' => fake()->unique()->uuid().'@speaklar',
            'subject' => fake()->randomElement(['Mathematics', 'English', 'Physics']),
            'started_at' => now(),
        ];
    }
}
