<?php

namespace Tests\Feature;

use App\Jobs\EvaluateExamTranscript;
use App\Models\ExamTranscript;
use App\Models\Result;
use App\Models\TeacherSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EvaluateExamTranscriptTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;

    protected User $student;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.openai.key' => 'test-key',
            'services.openai.model' => 'gpt-4o-mini',
            'webcall.full_marks' => 100,
            'webcall.exam_name' => 'Voice Exam',
        ]);

        $this->teacher = User::factory()->teacher()->create();
        $this->student = User::factory()->student()->create([
            'phone' => '01766666666',
            'created_by' => $this->teacher->id,
        ]);

        TeacherSetting::create([
            'user_id' => $this->teacher->id,
            'system_prompt' => 'You are an oral examiner.',
            'evaluation_prompt' => 'Score this spoken exam.',
        ]);
    }

    protected function fakeOpenAi(mixed $content): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => is_string($content) ? $content : json_encode($content)]]],
            ]),
        ]);
    }

    protected function transcript(array $attributes = []): ExamTranscript
    {
        return ExamTranscript::factory()->create([
            'student_id' => $this->student->id,
            'subject' => 'Physics',
            ...$attributes,
        ]);
    }

    public function test_evaluation_creates_a_result_from_the_transcript(): void
    {
        $this->fakeOpenAi(['marks_obtained' => 78, 'feedback' => 'Clear answers, work on units.']);

        $transcript = $this->transcript();

        (new EvaluateExamTranscript($transcript))->handle(app(\App\Services\OpenAiEvaluator::class));

        $transcript->refresh();
        $result = $transcript->result;

        $this->assertSame(ExamTranscript::STATUS_EVALUATED, $transcript->status);
        $this->assertNotNull($transcript->evaluated_at);
        $this->assertNotNull($result);
        $this->assertSame('Physics', $result->subject);
        $this->assertSame('Voice Exam', $result->exam_name);
        $this->assertSame('78.00', $result->marks_obtained);
        $this->assertSame('A', $result->grade);
        $this->assertSame('Clear answers, work on units.', $result->ai_feedback);
        $this->assertSame($this->student->id, $result->student_id);
        $this->assertSame($this->teacher->id, $result->created_by);
    }

    public function test_the_transcript_and_teacher_prompts_are_sent_to_openai(): void
    {
        $this->fakeOpenAi(['marks_obtained' => 50, 'feedback' => 'Adequate.']);

        $transcript = $this->transcript(['transcript' => 'Examiner: Define inertia.']);

        (new EvaluateExamTranscript($transcript))->handle(app(\App\Services\OpenAiEvaluator::class));

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $body['messages'][0]['content'] === 'You are an oral examiner.'
                && str_contains($body['messages'][1]['content'], 'Score this spoken exam.')
                && str_contains($body['messages'][1]['content'], 'Examiner: Define inertia.')
                && str_contains($body['messages'][1]['content'], 'Physics')
                && $body['response_format']['type'] === 'json_object';
        });
    }

    public function test_a_score_outside_the_range_is_clamped(): void
    {
        $this->fakeOpenAi(['marks_obtained' => 250, 'feedback' => 'Outstanding.']);

        $transcript = $this->transcript();

        (new EvaluateExamTranscript($transcript))->handle(app(\App\Services\OpenAiEvaluator::class));

        $this->assertSame('100.00', $transcript->refresh()->result->marks_obtained);
    }

    public function test_a_malformed_ai_response_marks_the_transcript_failed(): void
    {
        $this->fakeOpenAi('this is not json');

        $transcript = $this->transcript();

        (new EvaluateExamTranscript($transcript))->handle(app(\App\Services\OpenAiEvaluator::class));

        $transcript->refresh();

        $this->assertSame(ExamTranscript::STATUS_FAILED, $transcript->status);
        $this->assertNotNull($transcript->failure_reason);
        $this->assertDatabaseCount('results', 0);
    }

    public function test_a_failed_api_call_marks_the_transcript_failed(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response(['error' => ['message' => 'Invalid API key.']], 401),
        ]);

        $transcript = $this->transcript();

        (new EvaluateExamTranscript($transcript))->handle(app(\App\Services\OpenAiEvaluator::class));

        $transcript->refresh();

        $this->assertSame(ExamTranscript::STATUS_FAILED, $transcript->status);
        $this->assertStringContainsString('Invalid API key.', $transcript->failure_reason);
        $this->assertDatabaseCount('results', 0);
    }

    public function test_evaluation_is_skipped_when_no_teacher_has_saved_prompts(): void
    {
        TeacherSetting::query()->delete();
        Http::fake();

        $transcript = $this->transcript();

        (new EvaluateExamTranscript($transcript))->handle(app(\App\Services\OpenAiEvaluator::class));

        $this->assertSame(ExamTranscript::STATUS_FAILED, $transcript->refresh()->status);
        Http::assertNothingSent();
    }

    public function test_an_already_evaluated_transcript_is_not_re_evaluated(): void
    {
        Http::fake();

        $transcript = $this->transcript(['status' => ExamTranscript::STATUS_EVALUATED]);

        (new EvaluateExamTranscript($transcript))->handle(app(\App\Services\OpenAiEvaluator::class));

        Http::assertNothingSent();
        $this->assertDatabaseCount('results', 0);
    }

    public function test_the_generated_result_is_visible_to_the_student(): void
    {
        $this->fakeOpenAi(['marks_obtained' => 64, 'feedback' => 'Good grasp of the basics.']);

        $transcript = $this->transcript();
        (new EvaluateExamTranscript($transcript))->handle(app(\App\Services\OpenAiEvaluator::class));

        $this->actingAs($this->student)
            ->get(route('student.results.show', $transcript->refresh()->result))
            ->assertOk()
            ->assertSee('Good grasp of the basics.')
            ->assertSee('Voice Exam');
    }

    public function test_the_end_to_end_callback_produces_a_result(): void
    {
        config(['webcall.webhook_secret' => 'test-secret']);
        $this->fakeOpenAi(['marks_obtained' => 88, 'feedback' => 'Excellent reasoning.']);

        $this->withHeaders(['X-Webhook-Secret' => 'test-secret'])
            ->postJson(route('webhooks.webcall.transcript'), [
                'phone' => '01766666666',
                'subject' => 'Chemistry',
                'transcript' => 'Examiner: Define a mole. Student: It is 6.022e23 particles.',
                'call_id' => 'call-e2e',
            ])->assertStatus(202);

        // dispatchAfterResponse runs when the application terminates, which the test
        // harness does after the response is returned.
        $transcript = ExamTranscript::where('external_id', 'call-e2e')->first();

        $this->assertSame(ExamTranscript::STATUS_EVALUATED, $transcript->status);
        $this->assertSame('Chemistry', $transcript->result->subject);
        $this->assertSame('88.00', $transcript->result->marks_obtained);
        $this->assertSame('A+', $transcript->result->grade);
    }

    public function test_transcripts_are_stored_per_subject_for_a_student(): void
    {
        $this->transcript(['subject' => 'Physics']);
        $this->transcript(['subject' => 'Chemistry']);

        $this->assertSame(
            ['Chemistry', 'Physics'],
            $this->student->examTranscripts()->orderBy('subject')->pluck('subject')->all()
        );
    }

    public function test_prompts_fall_back_to_another_teacher_when_the_students_teacher_has_none(): void
    {
        TeacherSetting::query()->delete();

        $otherTeacher = User::factory()->teacher()->create();
        TeacherSetting::create([
            'user_id' => $otherTeacher->id,
            'system_prompt' => 'Fallback examiner.',
            'evaluation_prompt' => 'Score it.',
        ]);

        $this->fakeOpenAi(['marks_obtained' => 55, 'feedback' => 'Fine.']);

        $transcript = $this->transcript();
        (new EvaluateExamTranscript($transcript))->handle(app(\App\Services\OpenAiEvaluator::class));

        $this->assertSame(ExamTranscript::STATUS_EVALUATED, $transcript->refresh()->status);
        $this->assertSame($otherTeacher->id, $transcript->result->created_by);
    }

    public function test_a_transcript_without_a_student_is_not_evaluated(): void
    {
        Http::fake();

        $transcript = ExamTranscript::factory()->unmatched()->create();

        (new EvaluateExamTranscript($transcript))->handle(app(\App\Services\OpenAiEvaluator::class));

        // A provider lookup is attempted (the transcript has a call id), but with no
        // student resolved it must never reach the evaluator.
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'openai.com'));
        $this->assertSame(ExamTranscript::STATUS_UNMATCHED, $transcript->refresh()->status);
        $this->assertDatabaseCount('results', 0);
    }

    public function test_result_creation_reuses_the_shared_grade_mapping(): void
    {
        $this->assertSame('A+', Result::gradeFor(88.0));
    }
}
