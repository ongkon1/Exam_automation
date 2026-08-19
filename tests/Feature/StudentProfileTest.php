<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentProfileTest extends TestCase
{
    use RefreshDatabase;

    protected User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->student()->create([
            'roll_number' => 'GEM-3001',
            'class_name' => 'Class 10',
        ]);
    }

    public function test_student_can_open_their_profile_edit_form(): void
    {
        $this->actingAs($this->student)
            ->get(route('student.profile.edit'))
            ->assertOk()
            ->assertSee('Edit My Profile')
            ->assertSee($this->student->email);
    }

    public function test_student_can_update_their_own_details(): void
    {
        $this->actingAs($this->student)
            ->put(route('student.profile.update'), [
                'name' => 'Updated Name',
                'email' => 'updated-student@example.com',
                'phone' => '01799999999',
                'date_of_birth' => '2008-05-04',
                'address' => 'Dhaka, Bangladesh',
            ])
            ->assertRedirect(route('student.profile'))
            ->assertSessionHas('success');

        $this->student->refresh();

        $this->assertSame('Updated Name', $this->student->name);
        $this->assertSame('updated-student@example.com', $this->student->email);
        $this->assertSame('01799999999', $this->student->phone);
        $this->assertSame('2008-05-04', $this->student->date_of_birth->toDateString());
        $this->assertSame('Dhaka, Bangladesh', $this->student->address);
    }

    public function test_student_cannot_change_their_roll_number_or_class(): void
    {
        $this->actingAs($this->student)
            ->put(route('student.profile.update'), [
                'name' => $this->student->name,
                'email' => $this->student->email,
                'roll_number' => 'GEM-HACKED',
                'class_name' => 'Class 12',
            ])
            ->assertRedirect(route('student.profile'));

        $this->student->refresh();

        $this->assertSame('GEM-3001', $this->student->roll_number);
        $this->assertSame('Class 10', $this->student->class_name);
    }

    public function test_student_cannot_promote_themselves_to_a_teacher(): void
    {
        $this->actingAs($this->student)
            ->put(route('student.profile.update'), [
                'name' => $this->student->name,
                'email' => $this->student->email,
                'role' => User::ROLE_TEACHER,
            ]);

        $this->assertTrue($this->student->refresh()->isStudent());
    }

    public function test_student_can_change_their_password(): void
    {
        $this->actingAs($this->student)
            ->put(route('student.profile.update'), [
                'name' => $this->student->name,
                'email' => $this->student->email,
                'password' => 'my-new-password',
                'password_confirmation' => 'my-new-password',
            ]);

        $this->assertTrue(Hash::check('my-new-password', $this->student->refresh()->password));
    }

    public function test_leaving_the_password_blank_keeps_the_current_one(): void
    {
        $original = $this->student->password;

        $this->actingAs($this->student)
            ->put(route('student.profile.update'), [
                'name' => 'Still Me',
                'email' => $this->student->email,
                'password' => '',
                'password_confirmation' => '',
            ]);

        $this->assertSame($original, $this->student->refresh()->password);
    }

    public function test_email_must_stay_unique(): void
    {
        User::factory()->student()->create(['email' => 'taken@example.com']);

        $this->actingAs($this->student)
            ->put(route('student.profile.update'), [
                'name' => $this->student->name,
                'email' => 'taken@example.com',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_a_teacher_cannot_use_the_student_profile_routes(): void
    {
        $teacher = User::factory()->teacher()->create();

        $this->actingAs($teacher)->get(route('student.profile.edit'))->assertForbidden();
        $this->actingAs($teacher)->put(route('student.profile.update'), [
            'name' => 'X',
            'email' => 'x@example.com',
        ])->assertForbidden();
    }

    public function test_guests_cannot_reach_the_profile_edit_form(): void
    {
        $this->get(route('student.profile.edit'))->assertRedirect(route('login'));
    }
}
