<?php

namespace Tests\Feature;

use App\Models\Result;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::factory()->teacher()->create();
    }

    /**
     * Give a student a single result at a known percentage.
     */
    protected function studentScoring(float $percentage, array $attributes = []): User
    {
        $student = User::factory()->student()->create($attributes);

        Result::factory()->create([
            'student_id' => $student->id,
            'full_marks' => 100,
            'marks_obtained' => $percentage,
        ]);

        return $student;
    }

    public function test_the_dashboard_is_the_teachers_landing_page(): void
    {
        $this->actingAs($this->teacher)
            ->get('/')
            ->assertRedirect(route('teacher.dashboard'));
    }

    public function test_it_shows_the_student_count_and_average(): void
    {
        $this->studentScoring(90);
        $this->studentScoring(70);

        $this->actingAs($this->teacher)
            ->get(route('teacher.dashboard'))
            ->assertOk()
            ->assertSee('Total Students')
            ->assertSee('Average Result')
            // Two students, mean of 90 and 70.
            ->assertSee('>2<', false)
            ->assertSee('80%');
    }

    public function test_students_are_bucketed_into_performance_bands(): void
    {
        $this->studentScoring(95);   // Excellent
        $this->studentScoring(85);   // Excellent
        $this->studentScoring(65);   // Good
        $this->studentScoring(45);   // Average
        $this->studentScoring(20);   // Needs Improvement

        $response = $this->actingAs($this->teacher)
            ->get(route('teacher.dashboard'))
            ->assertOk();

        $bands = $response->viewData('bands');

        $this->assertSame(2, $bands[0]['count'], 'Excellent');
        $this->assertSame(1, $bands[1]['count'], 'Good');
        $this->assertSame(1, $bands[2]['count'], 'Average');
        $this->assertSame(1, $bands[3]['count'], 'Needs Improvement');
        $this->assertSame(5, $response->viewData('gradedCount'));
    }

    public function test_band_shares_add_up_to_one_hundred_percent(): void
    {
        $this->studentScoring(90);
        $this->studentScoring(50);

        $bands = $this->actingAs($this->teacher)
            ->get(route('teacher.dashboard'))
            ->viewData('bands');

        $this->assertEqualsWithDelta(100.0, array_sum(array_column($bands, 'share')), 0.5);
    }

    public function test_a_student_is_placed_by_their_average_not_their_latest_result(): void
    {
        $student = User::factory()->student()->create();

        // Mean of 90 and 30 is 60 — the "Good" band, not "Excellent" or "Needs Improvement".
        Result::factory()->create(['student_id' => $student->id, 'full_marks' => 100, 'marks_obtained' => 90]);
        Result::factory()->create(['student_id' => $student->id, 'full_marks' => 100, 'marks_obtained' => 30]);

        $bands = $this->actingAs($this->teacher)
            ->get(route('teacher.dashboard'))
            ->viewData('bands');

        $this->assertSame(0, $bands[0]['count']);
        $this->assertSame(1, $bands[1]['count']);
    }

    public function test_the_class_filter_narrows_the_distribution(): void
    {
        $this->studentScoring(95, ['class_name' => 'Class 10']);
        $this->studentScoring(30, ['class_name' => 'Class 12']);

        $response = $this->actingAs($this->teacher)
            ->get(route('teacher.dashboard', ['class' => 'Class 10']))
            ->assertOk();

        $this->assertSame(1, $response->viewData('gradedCount'));
        $this->assertSame(1, $response->viewData('bands')[0]['count']);
    }

    public function test_the_period_selector_is_validated(): void
    {
        $response = $this->actingAs($this->teacher)
            ->get(route('teacher.dashboard', ['period' => 999]))
            ->assertOk();

        // Falls back to the default rather than trusting the query string.
        $this->assertSame(7, $response->viewData('period'));
    }

    public function test_it_renders_with_no_data_at_all(): void
    {
        $this->actingAs($this->teacher)
            ->get(route('teacher.dashboard'))
            ->assertOk()
            ->assertSee('No results recorded yet')
            ->assertSee('No students yet');
    }

    public function test_recent_students_show_their_latest_result(): void
    {
        $student = User::factory()->student()->create(['name' => 'Nusrat Jahan', 'class_name' => 'Class 10']);

        Result::factory()->create(['student_id' => $student->id, 'full_marks' => 100, 'marks_obtained' => 40]);
        Result::factory()->create(['student_id' => $student->id, 'full_marks' => 100, 'marks_obtained' => 85]);

        $this->actingAs($this->teacher)
            ->get(route('teacher.dashboard'))
            ->assertOk()
            ->assertSee('Nusrat Jahan')
            ->assertSee('NJ')          // avatar initials
            ->assertSee('Class 10')
            ->assertSee('85%');        // the later result, not the earlier one
    }

    public function test_students_without_results_do_not_break_the_page(): void
    {
        User::factory()->student()->create(['name' => 'No Results Yet']);

        $this->actingAs($this->teacher)
            ->get(route('teacher.dashboard'))
            ->assertOk()
            ->assertSee('No Results Yet')
            ->assertSee('No results yet');
    }

    public function test_students_cannot_reach_the_teacher_dashboard(): void
    {
        $this->actingAs(User::factory()->student()->create())
            ->get(route('teacher.dashboard'))
            ->assertForbidden();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('teacher.dashboard'))->assertRedirect(route('login'));
    }
}
