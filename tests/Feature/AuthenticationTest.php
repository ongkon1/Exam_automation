<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get('/login')->assertOk()->assertSee('Sign in to continue');
    }

    public function test_teacher_is_redirected_to_the_students_list_after_login(): void
    {
        $teacher = User::factory()->teacher()->create();

        $this->post('/login', [
            'email' => $teacher->email,
            'password' => 'password',
        ])->assertRedirect(route('teacher.students.index'));

        $this->assertAuthenticatedAs($teacher);
    }

    public function test_student_is_redirected_to_their_dashboard_after_login(): void
    {
        $student = User::factory()->student()->create();

        $this->post('/login', [
            'email' => $student->email,
            'password' => 'password',
        ])->assertRedirect(route('student.dashboard'));

        $this->assertAuthenticatedAs($student);
    }

    public function test_login_fails_with_an_incorrect_password(): void
    {
        $user = User::factory()->student()->create();

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertRedirect('/login')->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_users_can_log_out(): void
    {
        $user = User::factory()->student()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }
}
