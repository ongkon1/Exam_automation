<?php

namespace Tests\Feature;

use App\Models\Result;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherResultCrudTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;

    protected User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::factory()->teacher()->create();
        $this->student = User::factory()->student()->create();
    }

    public function test_teacher_can_record_a_result_and_the_grade_is_derived(): void
    {
        $this->actingAs($this->teacher)
            ->post(route('teacher.results.store'), [
                'student_id' => $this->student->id,
                'exam_name' => 'Midterm 2026',
                'subject' => 'Mathematics',
                'exam_date' => '2026-03-01',
                'full_marks' => 100,
                'marks_obtained' => 72,
            ])
            ->assertRedirect(route('teacher.results.index'));

        $this->assertDatabaseHas('results', [
            'student_id' => $this->student->id,
            'subject' => 'Mathematics',
            'grade' => 'A',
            'created_by' => $this->teacher->id,
        ]);
    }

    public function test_marks_obtained_cannot_exceed_full_marks(): void
    {
        $this->actingAs($this->teacher)
            ->post(route('teacher.results.store'), [
                'student_id' => $this->student->id,
                'exam_name' => 'Midterm 2026',
                'subject' => 'Physics',
                'full_marks' => 50,
                'marks_obtained' => 80,
            ])
            ->assertSessionHasErrors('marks_obtained');

        $this->assertDatabaseCount('results', 0);
    }

    public function test_a_result_cannot_be_assigned_to_a_teacher(): void
    {
        $otherTeacher = User::factory()->teacher()->create();

        $this->actingAs($this->teacher)
            ->post(route('teacher.results.store'), [
                'student_id' => $otherTeacher->id,
                'exam_name' => 'Midterm 2026',
                'subject' => 'Physics',
                'full_marks' => 100,
                'marks_obtained' => 55,
            ])
            ->assertSessionHasErrors('student_id');
    }

    public function test_teacher_can_update_a_result_and_the_grade_is_recalculated(): void
    {
        $result = Result::factory()->create([
            'student_id' => $this->student->id,
            'full_marks' => 100,
            'marks_obtained' => 90,
            'grade' => 'A+',
        ]);

        $this->actingAs($this->teacher)
            ->put(route('teacher.results.update', $result), [
                'student_id' => $this->student->id,
                'exam_name' => $result->exam_name,
                'subject' => $result->subject,
                'full_marks' => 100,
                'marks_obtained' => 35,
            ])
            ->assertRedirect(route('teacher.results.index'));

        $this->assertSame('F', $result->refresh()->grade);
    }

    public function test_teacher_can_delete_a_result(): void
    {
        $result = Result::factory()->create(['student_id' => $this->student->id]);

        $this->actingAs($this->teacher)
            ->delete(route('teacher.results.destroy', $result))
            ->assertRedirect(route('teacher.results.index'));

        $this->assertDatabaseMissing('results', ['id' => $result->id]);
    }

    public function test_results_can_be_filtered_by_student(): void
    {
        $otherStudent = User::factory()->student()->create();

        // Exam names are used here because subjects and student names both appear in the filter dropdowns.
        Result::factory()->create(['student_id' => $this->student->id, 'exam_name' => 'Kept Exam']);
        Result::factory()->create(['student_id' => $otherStudent->id, 'exam_name' => 'Filtered Out Exam']);

        $this->actingAs($this->teacher)
            ->get(route('teacher.results.index', ['student_id' => $this->student->id]))
            ->assertOk()
            ->assertSee('Kept Exam')
            ->assertDontSee('Filtered Out Exam');
    }
}
