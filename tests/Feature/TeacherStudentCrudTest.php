<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TeacherStudentCrudTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::factory()->teacher()->create();
    }

    public function test_teacher_can_see_the_student_list(): void
    {
        $student = User::factory()->student()->create(['name' => 'Arif Hossain']);

        $this->actingAs($this->teacher)
            ->get(route('teacher.students.index'))
            ->assertOk()
            ->assertSee($student->name);
    }

    public function test_teacher_can_search_students_by_roll_number(): void
    {
        $match = User::factory()->student()->create(['roll_number' => 'GEM-9001']);
        $other = User::factory()->student()->create(['roll_number' => 'GEM-9002']);

        $this->actingAs($this->teacher)
            ->get(route('teacher.students.index', ['search' => 'GEM-9001']))
            ->assertOk()
            ->assertSee($match->name)
            ->assertDontSee($other->name);
    }

    public function test_teacher_can_create_a_student(): void
    {
        $this->actingAs($this->teacher)
            ->post(route('teacher.students.store'), [
                'name' => 'Nusrat Jahan',
                'email' => 'nusrat@example.com',
                'password' => 'secret-password',
                'password_confirmation' => 'secret-password',
                'roll_number' => 'GEM-2001',
                'class_name' => 'Class 10',
            ])
            ->assertRedirect(route('teacher.students.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'nusrat@example.com',
            'role' => User::ROLE_STUDENT,
            'roll_number' => 'GEM-2001',
            'created_by' => $this->teacher->id,
        ]);
    }

    public function test_creating_a_student_rejects_a_duplicate_email(): void
    {
        User::factory()->student()->create(['email' => 'taken@example.com']);

        $this->actingAs($this->teacher)
            ->post(route('teacher.students.store'), [
                'name' => 'Duplicate',
                'email' => 'taken@example.com',
                'password' => 'secret-password',
                'password_confirmation' => 'secret-password',
            ])
            ->assertSessionHasErrors('email');

        $this->assertSame(1, User::where('email', 'taken@example.com')->count());
    }

    public function test_teacher_can_update_a_student_without_changing_the_password(): void
    {
        $student = User::factory()->student()->create(['name' => 'Old Name']);
        $originalPassword = $student->password;

        $this->actingAs($this->teacher)
            ->put(route('teacher.students.update', $student), [
                'name' => 'New Name',
                'email' => $student->email,
                'roll_number' => $student->roll_number,
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertRedirect(route('teacher.students.index'));

        $student->refresh();

        $this->assertSame('New Name', $student->name);
        $this->assertSame($originalPassword, $student->password);
    }

    public function test_teacher_can_change_a_student_password(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($this->teacher)
            ->put(route('teacher.students.update', $student), [
                'name' => $student->name,
                'email' => $student->email,
                'password' => 'brand-new-password',
                'password_confirmation' => 'brand-new-password',
            ]);

        $this->assertTrue(Hash::check('brand-new-password', $student->refresh()->password));
    }

    public function test_teacher_can_delete_a_student(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($this->teacher)
            ->delete(route('teacher.students.destroy', $student))
            ->assertRedirect(route('teacher.students.index'));

        $this->assertDatabaseMissing('users', ['id' => $student->id]);
    }

    public function test_the_student_routes_cannot_be_pointed_at_a_teacher_account(): void
    {
        $otherTeacher = User::factory()->teacher()->create();

        $this->actingAs($this->teacher)
            ->get(route('teacher.students.edit', $otherTeacher))
            ->assertNotFound();
    }
}
