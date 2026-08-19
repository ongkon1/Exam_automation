<?php

namespace Database\Seeders;

use App\Models\Result;
use App\Models\TeacherSetting;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $teacher = User::factory()->teacher()->create([
            'name' => 'Rahima Khatun',
            'email' => 'teacher@example.com',
            'phone' => '01711111111',
        ]);

        TeacherSetting::create([
            'user_id' => $teacher->id,
            'system_prompt' => 'You are an experienced government exam examiner. Write concise, encouraging '
                .'feedback in plain language that a secondary school student can act on.',
            'evaluation_prompt' => 'Review the exam result below. Summarise the performance in one sentence, '
                .'then list three specific, practical suggestions the student can follow to improve in this subject.',
        ]);

        $subjects = ['Mathematics', 'English', 'Physics', 'Chemistry'];
        $examName = 'Midterm 2026';

        collect([
            ['Arif Hossain', 'GEM-1001', 'Class 10'],
            ['Nusrat Jahan', 'GEM-1002', 'Class 10'],
            ['Tanvir Ahmed', 'GEM-1003', 'Class 10'],
            ['Sadia Islam', 'GEM-1004', 'Class 11'],
            ['Mehedi Hasan', 'GEM-1005', 'Class 11'],
            ['Farhana Akter', 'GEM-1006', 'Class 12'],
        ])->each(function (array $data, int $index) use ($teacher, $subjects, $examName) {
            [$name, $roll, $class] = $data;

            $student = User::factory()->student()->create([
                'name' => $name,
                'email' => 'student'.($index + 1).'@example.com',
                'roll_number' => $roll,
                'class_name' => $class,
                'created_by' => $teacher->id,
            ]);

            foreach ($subjects as $subject) {
                $fullMarks = 100;
                $marksObtained = fake()->randomFloat(2, 32, 98);

                Result::create([
                    'student_id' => $student->id,
                    'exam_name' => $examName,
                    'subject' => $subject,
                    'exam_date' => now()->subMonths(2)->toDateString(),
                    'full_marks' => $fullMarks,
                    'marks_obtained' => $marksObtained,
                    'grade' => Result::gradeFor(round($marksObtained / $fullMarks * 100, 2)),
                    'remarks' => null,
                    'created_by' => $teacher->id,
                ]);
            }
        });
    }
}
