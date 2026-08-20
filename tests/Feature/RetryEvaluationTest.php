<?php

namespace Tests\Feature;

use App\Models\ExamTranscript;
use App\Models\TeacherSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * `failure_reason` records what happened last time, so fixing the underlying cause does
 * not clear it. These cover the retry that does.
 */
class RetryEvaluationTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;

    protected User $student;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.openai.key' => 'test-key']);

        $this->teacher = User::factory()->teacher()->create();

        TeacherSetting::create([
            'user_id' => $this->teacher->id,
            'system_prompt' => 'You are an oral examiner.',
            'evaluation_prompt' => 'Score this spoken exam.',
        ]);

        $this->student = User::factory()->student()->create(['created_by' => $this->teacher->id]);
    }

    protected function fakeOpenAi(): void
    {
        Http::fake(['api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'marks_obtained' => 68, 'feedback' => 'Solid answers.',
            ])]]],
        ])]);
    }

    protected function failedTranscript(): ExamTranscript
    {
        return ExamTranscript::factory()->create([
            'student_id' => $this->student->id,
            'status' => ExamTranscript::STATUS_FAILED,
            'failure_reason' => 'OPENAI_API_KEY is not set in your .env file.',
        ]);
    }

    public function test_the_failure_reason_is_not_surfaced_on_the_page(): void
    {
        $transcript = $this->failedTranscript();

        $this->actingAs($this->teacher)
            ->get(route('teacher.transcripts.show', $transcript))
            ->assertOk()
            ->assertDontSee('OPENAI_API_KEY is not set')
            ->assertDontSee('Evaluation failed')
            // The teacher can still re-run it.
            ->assertSee('Retry evaluation');

        // Still recorded, for the retry command and for diagnosis.
        $this->assertNotNull($transcript->fresh()->failure_reason);
    }

    public function test_a_teacher_can_retry_a_failed_evaluation(): void
    {
        $this->fakeOpenAi();
        $transcript = $this->failedTranscript();

        $this->actingAs($this->teacher)
            ->post(route('teacher.transcripts.retry', $transcript))
            ->assertRedirect()
            ->assertSessionHas('success');

        $transcript->refresh();

        $this->assertSame(ExamTranscript::STATUS_EVALUATED, $transcript->status);
        $this->assertNull($transcript->failure_reason);
        $this->assertSame('68.00', $transcript->result->marks_obtained);
    }

    public function test_a_retry_that_fails_again_records_the_new_reason(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'Rate limited.']], 429)]);

        $transcript = $this->failedTranscript();

        $this->actingAs($this->teacher)
            ->post(route('teacher.transcripts.retry', $transcript))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertStringContainsString('Rate limited.', $transcript->refresh()->failure_reason);
    }

    public function test_the_retry_button_is_hidden_once_evaluated(): void
    {
        $this->fakeOpenAi();
        $transcript = $this->failedTranscript();

        $this->actingAs($this->teacher)->post(route('teacher.transcripts.retry', $transcript));

        $this->actingAs($this->teacher)
            ->get(route('teacher.transcripts.show', $transcript->refresh()))
            ->assertOk()
            ->assertDontSee('Retry evaluation');
    }

    public function test_students_cannot_trigger_a_retry(): void
    {
        Http::fake();
        $transcript = $this->failedTranscript();

        $this->actingAs(User::factory()->student()->create())
            ->post(route('teacher.transcripts.retry', $transcript))
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_the_console_command_retries_the_backlog(): void
    {
        $this->fakeOpenAi();
        $this->failedTranscript();
        $this->failedTranscript();

        $this->artisan('voice-exam:retry')
            ->expectsOutputToContain('Retrying 2 transcript(s)')
            ->assertSuccessful();

        $this->assertSame(0, ExamTranscript::where('status', ExamTranscript::STATUS_FAILED)->count());
        $this->assertSame(2, ExamTranscript::where('status', ExamTranscript::STATUS_EVALUATED)->count());
    }

    public function test_the_command_says_so_when_there_is_nothing_to_retry(): void
    {
        Http::fake();

        $this->artisan('voice-exam:retry')
            ->expectsOutputToContain('No transcripts with status [failed]')
            ->assertSuccessful();

        Http::assertNothingSent();
    }
}
