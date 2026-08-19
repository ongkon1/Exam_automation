<?php

namespace Tests\Feature;

use App\Models\Result;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentAccessTest extends TestCase
{
    use RefreshDatabase;

    protected User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->student()->create();
    }

    public function test_student_can_see_their_own_dashboard(): void
    {
        Result::factory()->create([
            'student_id' => $this->student->id,
            'exam_name' => 'My Own Exam',
        ]);

        $this->actingAs($this->student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('My Own Exam');
    }

    public function test_student_only_sees_their_own_results(): void
    {
        $otherStudent = User::factory()->student()->create();

        Result::factory()->create(['student_id' => $this->student->id, 'exam_name' => 'Mine']);
        Result::factory()->create(['student_id' => $otherStudent->id, 'exam_name' => 'Not Mine']);

        $this->actingAs($this->student)
            ->get(route('student.results.index'))
            ->assertOk()
            ->assertSee('Mine')
            ->assertDontSee('Not Mine');
    }

    public function test_student_cannot_open_another_students_result(): void
    {
        $otherResult = Result::factory()->create([
            'student_id' => User::factory()->student(),
        ]);

        $this->actingAs($this->student)
            ->get(route('student.results.show', $otherResult))
            ->assertForbidden();
    }

    public function test_student_can_open_their_own_result(): void
    {
        $result = Result::factory()->create(['student_id' => $this->student->id]);

        $this->actingAs($this->student)
            ->get(route('student.results.show', $result))
            ->assertOk();
    }

    public function test_student_cannot_reach_teacher_routes(): void
    {
        $this->actingAs($this->student)
            ->get(route('teacher.students.index'))
            ->assertForbidden();

        $this->actingAs($this->student)
            ->get(route('teacher.results.index'))
            ->assertForbidden();

        $this->actingAs($this->student)
            ->get(route('teacher.settings.edit'))
            ->assertForbidden();
    }

    public function test_teacher_cannot_reach_student_routes(): void
    {
        $teacher = User::factory()->teacher()->create();

        $this->actingAs($teacher)
            ->get(route('student.dashboard'))
            ->assertForbidden();
    }

    public function test_guests_cannot_reach_protected_routes(): void
    {
        $this->get(route('teacher.students.index'))->assertRedirect(route('login'));
        $this->get(route('student.dashboard'))->assertRedirect(route('login'));
    }
}
