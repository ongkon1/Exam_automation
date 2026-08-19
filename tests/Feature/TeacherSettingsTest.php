<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TeacherSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_creates_an_empty_settings_row_on_first_visit(): void
    {
        $teacher = User::factory()->teacher()->create();

        $this->actingAs($teacher)
            ->get(route('teacher.settings.edit'))
            ->assertOk()
            ->assertSee('System prompt')
            ->assertSee('Evaluation prompt');

        $this->assertDatabaseHas('teacher_settings', ['user_id' => $teacher->id]);
    }

    public function test_teacher_can_save_both_prompts(): void
    {
        $teacher = User::factory()->teacher()->create();

        $this->actingAs($teacher)
            ->put(route('teacher.settings.update'), [
                'name' => $teacher->name,
                'email' => $teacher->email,
                'system_prompt' => 'You are a strict examiner.',
                'evaluation_prompt' => 'Give three improvement tips.',
            ])
            ->assertRedirect(route('teacher.settings.edit'));

        $this->assertDatabaseHas('teacher_settings', [
            'user_id' => $teacher->id,
            'system_prompt' => 'You are a strict examiner.',
            'evaluation_prompt' => 'Give three improvement tips.',
        ]);
    }

    public function test_teacher_can_update_their_own_profile_and_password(): void
    {
        $teacher = User::factory()->teacher()->create();

        $this->actingAs($teacher)
            ->put(route('teacher.settings.update'), [
                'name' => 'Updated Teacher',
                'email' => 'updated-teacher@example.com',
                'phone' => '01700000000',
                'password' => 'a-new-password',
                'password_confirmation' => 'a-new-password',
            ]);

        $teacher->refresh();

        $this->assertSame('Updated Teacher', $teacher->name);
        $this->assertSame('updated-teacher@example.com', $teacher->email);
        $this->assertTrue(Hash::check('a-new-password', $teacher->password));
    }

    public function test_email_must_stay_unique_across_users(): void
    {
        $teacher = User::factory()->teacher()->create();
        User::factory()->student()->create(['email' => 'someone@example.com']);

        $this->actingAs($teacher)
            ->put(route('teacher.settings.update'), [
                'name' => $teacher->name,
                'email' => 'someone@example.com',
            ])
            ->assertSessionHasErrors('email');
    }
}
