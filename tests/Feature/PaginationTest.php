<?php

namespace Tests\Feature;

use App\Models\ExamTranscript;
use App\Models\Result;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The app is styled with Bootstrap 5, but the paginator ships with Tailwind markup by
 * default. These assert the Bootstrap views are in use so paginated lists stay styled.
 */
class PaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_results_pagination_renders_bootstrap_markup(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();

        // Page size is 15.
        Result::factory()->count(20)->create(['student_id' => $student->id]);

        $response = $this->actingAs($teacher)
            ->get(route('teacher.results.index'))
            ->assertOk();

        $response->assertSee('<ul class="pagination', false);
        $response->assertSee('page-item', false);
        $response->assertSee('page-link', false);

        // Tailwind utility classes from the default paginator view.
        $response->assertDontSee('relative inline-flex items-center', false);
    }

    public function test_the_second_page_of_teacher_results_loads(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();

        Result::factory()->count(20)->create(['student_id' => $student->id]);

        $this->actingAs($teacher)
            ->get(route('teacher.results.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('page-link', false);
    }

    public function test_filters_survive_pagination_links(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();

        Result::factory()->count(20)->create([
            'student_id' => $student->id,
            'subject' => 'Physics',
        ]);

        $this->actingAs($teacher)
            ->get(route('teacher.results.index', ['subject' => 'Physics']))
            ->assertOk()
            // withQueryString() keeps the filter on the page links.
            ->assertSee('subject=Physics', false);
    }

    public function test_other_paginated_lists_render_bootstrap_markup(): void
    {
        $teacher = User::factory()->teacher()->create();
        User::factory()->count(20)->student()->create();
        ExamTranscript::factory()->count(20)->create();

        foreach (['teacher.students.index', 'teacher.transcripts.index'] as $route) {
            $this->actingAs($teacher)
                ->get(route($route))
                ->assertOk()
                ->assertSee('<ul class="pagination', false);
        }
    }

    public function test_student_lists_render_bootstrap_markup(): void
    {
        $student = User::factory()->student()->create(['phone' => '01766666666']);

        Result::factory()->count(20)->create(['student_id' => $student->id]);
        ExamTranscript::factory()->count(15)->create(['student_id' => $student->id]);

        $this->actingAs($student)
            ->get(route('student.results.index'))
            ->assertOk()
            ->assertSee('<ul class="pagination', false);

        $this->actingAs($student)
            ->get(route('student.voice-exam'))
            ->assertOk()
            ->assertSee('<ul class="pagination', false);
    }
}
