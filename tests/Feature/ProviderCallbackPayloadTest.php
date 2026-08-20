<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyWebCallSecret;
use App\Models\CallSession;
use App\Models\ExamTranscript;
use App\Models\TeacherSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Exercises the real payload shape the voice provider posts when a call ends:
 *
 *   {order_id, call_id, port, carrier, result, summary, transcript}
 *
 * Notably it carries no phone number and no subject, so the call id registered when
 * the student started the exam is the only thing tying it back to them.
 */
class ProviderCallbackPayloadTest extends TestCase
{
    use RefreshDatabase;

    protected const SECRET = 'test-webcall-secret';

    protected const CALL_ID = '85a764409b8911f1be2d7e0a46f1ba4d';

    protected User $student;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'webcall.webhook_secret' => self::SECRET,
            'services.openai.key' => 'test-key',
        ]);

        $teacher = User::factory()->teacher()->create();

        TeacherSetting::create([
            'user_id' => $teacher->id,
            'system_prompt' => 'You are an oral examiner.',
            'evaluation_prompt' => 'Score this spoken exam.',
        ]);

        $this->student = User::factory()->student()->create([
            'phone' => '01766666666',
            'created_by' => $teacher->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function payload(array $overrides = []): array
    {
        return [
            'order_id' => null,
            'call_id' => self::CALL_ID,
            'port' => '770111',
            'carrier' => '0',
            'result' => 'confirmed',
            'summary' => "### Call Summary\n\nThe student answered three questions on Newton's laws.",
            'transcript' => "assistant: প্রথম সূত্রটি বলুন।\nuser: An object stays at rest unless acted upon.",
        ] + $overrides;
    }

    protected function postCallback(array $payload)
    {
        return $this->withHeaders([VerifyWebCallSecret::HEADER => self::SECRET])
            ->postJson(route('webhooks.webcall.transcript'), $payload);
    }

    /**
     * Two OpenAI calls happen in order: the summary first, then the score.
     */
    protected function fakeOpenAiPair(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::sequence()
                ->push(['choices' => [['message' => [
                    'content' => 'The student answered three questions on Newton\'s laws.',
                ]]]])
                ->push(['choices' => [['message' => [
                    'content' => json_encode([
                        'marks_obtained' => 71, 'feedback' => 'Good recall of the first law.',
                    ]),
                ]]]]),
        ]);
    }

    public function test_the_provider_payload_is_accepted_and_stored(): void
    {
        $this->fakeOpenAiPair();

        CallSession::factory()->create([
            'student_id' => $this->student->id,
            'call_id' => self::CALL_ID,
            'subject' => 'Physics',
        ]);

        $this->postCallback($this->payload())
            ->assertStatus(202)
            ->assertJson(['status' => 'accepted']);

        $transcript = ExamTranscript::first();

        $this->assertSame(self::CALL_ID, $transcript->external_id);
        $this->assertSame($this->student->id, $transcript->student_id);
        $this->assertSame('Physics', $transcript->subject);
        // The stored summary is the one we generated, not the provider's.
        $this->assertSame("The student answered three questions on Newton's laws.", $transcript->summary);
        $this->assertSame('confirmed', $transcript->call_result);
        $this->assertStringContainsString('প্রথম সূত্রটি বলুন', $transcript->transcript);

        // ...and it went all the way through to a published result.
        $this->assertSame(ExamTranscript::STATUS_EVALUATED, $transcript->status);
        $this->assertSame('71.00', $transcript->result->marks_obtained);
        $this->assertSame('A', $transcript->result->grade);
        $this->assertSame('Physics', $transcript->result->subject);
        $this->assertSame('Good recall of the first law.', $transcript->result->ai_feedback);
    }

    public function test_the_payload_needs_no_phone_or_subject_when_the_call_id_is_known(): void
    {
        Http::fake();

        CallSession::factory()->create([
            'student_id' => $this->student->id,
            'call_id' => self::CALL_ID,
            'subject' => 'Chemistry',
        ]);

        $payload = $this->payload();

        $this->assertArrayNotHasKey('phone', $payload);
        $this->assertArrayNotHasKey('subject', $payload);

        $this->postCallback($payload)->assertStatus(202)->assertJson(['status' => 'accepted']);

        $this->assertSame('Chemistry', ExamTranscript::first()->subject);
    }

    public function test_an_unregistered_call_id_is_held_for_review_rather_than_dropped(): void
    {
        Http::fake();

        // No call_sessions row and no phone in the payload, so the provider lookup is
        // the only remaining route — and the faked lookup returns nothing usable.
        $this->postCallback($this->payload())
            ->assertStatus(202)
            ->assertJson(['status' => 'accepted']);

        $transcript = ExamTranscript::first();

        $this->assertSame(ExamTranscript::STATUS_UNMATCHED, $transcript->status);
        $this->assertSame(self::CALL_ID, $transcript->external_id);
        // The transcript is still kept so a teacher can reconcile it, but nothing was
        // summarised — that only happens once the test belongs to a student.
        $this->assertNotEmpty($transcript->transcript);
        $this->assertNull($transcript->summary);
        // No marks were invented for an unidentified student.
        $this->assertDatabaseCount('results', 0);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'openai.com'));
    }

    public function test_the_transcript_is_summarised_with_the_evaluation_prompt_as_system(): void
    {
        $this->fakeOpenAiPair();

        CallSession::factory()->create([
            'student_id' => $this->student->id,
            'call_id' => self::CALL_ID,
            'subject' => 'Physics',
        ]);

        $payload = $this->payload();
        $this->postCallback($payload);

        Http::assertSent(function ($request) use ($payload) {
            $messages = $request->data()['messages'];

            // The summarising call: evaluation prompt as system, transcript as the only input.
            return $messages[0]['content'] === 'Score this spoken exam.'
                && $messages[1]['content'] === $payload['transcript'];
        });
    }

    public function test_the_whole_callback_payload_is_sent_when_no_transcript_arrives(): void
    {
        Http::fake([
            // A blank transcript makes the job ask Speaklar for one; it has none either,
            // so the stored payload is the last resort.
            'app.speaklar.com/*' => Http::response(['body' => ['calls' => []]]),
            'api.openai.com/*' => Http::sequence()
                ->push(['choices' => [['message' => ['content' => 'Summary from payload.']]]])
                ->push(['choices' => [['message' => [
                    'content' => json_encode(['marks_obtained' => 40, 'feedback' => 'Limited detail.']),
                ]]]]),
        ]);

        CallSession::factory()->create([
            'student_id' => $this->student->id,
            'call_id' => self::CALL_ID,
            'subject' => 'Physics',
        ]);

        // Callback with no transcript at all.
        $this->postCallback([
            'call_id' => self::CALL_ID,
            'transcript' => null,
            'port' => 'PORT-MARKER-770111',
            'result' => 'confirmed',
        ]);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'openai.com')) {
                return false;
            }

            $content = $request->data()['messages'][1]['content'];

            return str_contains($content, 'No transcript was supplied')
                && str_contains($content, 'PORT-MARKER-770111');
        });

        $this->assertSame(ExamTranscript::STATUS_EVALUATED, ExamTranscript::first()->status);
    }

    public function test_a_transcript_is_preferred_over_the_payload(): void
    {
        $this->fakeOpenAiPair();

        CallSession::factory()->create([
            'student_id' => $this->student->id,
            'call_id' => self::CALL_ID,
            'subject' => 'Physics',
        ]);

        $this->postCallback($this->payload(['port' => 'PORT-MARKER-770111']));

        Http::assertSent(function ($request) {
            $content = $request->data()['messages'][1]['content'];

            return str_contains($content, 'প্রথম সূত্রটি বলুন')
                && ! str_contains($content, 'No transcript was supplied');
        });
    }

    public function test_the_provider_summary_is_never_sent_to_the_ai(): void
    {
        $this->fakeOpenAiPair();

        CallSession::factory()->create([
            'student_id' => $this->student->id,
            'call_id' => self::CALL_ID,
            'subject' => 'Physics',
        ]);

        $this->postCallback($this->payload(['summary' => 'PROVIDER-SUMMARY-MARKER']));

        Http::assertNotSent(fn ($request) => str_contains(
            json_encode($request->data()), 'PROVIDER-SUMMARY-MARKER'
        ));
    }

    public function test_the_provider_summary_is_not_persisted_anywhere_on_the_record(): void
    {
        $this->fakeOpenAiPair();

        CallSession::factory()->create([
            'student_id' => $this->student->id,
            'call_id' => self::CALL_ID,
            'subject' => 'Physics',
        ]);

        $this->postCallback($this->payload(['summary' => 'PROVIDER-SUMMARY-MARKER']));

        $transcript = ExamTranscript::first();

        $this->assertNotSame('PROVIDER-SUMMARY-MARKER', $transcript->summary);
        $this->assertStringNotContainsString('PROVIDER-SUMMARY-MARKER', json_encode($transcript->payload));
    }

    public function test_a_repeated_callback_for_the_same_call_id_is_ignored(): void
    {
        Http::fake();

        CallSession::factory()->create([
            'student_id' => $this->student->id,
            'call_id' => self::CALL_ID,
            'subject' => 'Physics',
        ]);

        $this->postCallback($this->payload())->assertStatus(202);
        $this->postCallback($this->payload())->assertOk()->assertJson(['status' => 'duplicate']);

        $this->assertDatabaseCount('exam_transcripts', 1);
    }

    public function test_the_teacher_can_read_the_summary_and_outcome(): void
    {
        Http::fake();

        $transcript = ExamTranscript::factory()->create([
            'external_id' => self::CALL_ID,
            'summary' => '### Call Summary
The student explained inertia clearly.',
            'call_result' => 'confirmed',
        ]);

        $this->actingAs(User::factory()->teacher()->create())
            ->get(route('teacher.transcripts.show', $transcript))
            ->assertOk()
            ->assertSee('Call Summary')
            ->assertSee('explained inertia clearly')
            ->assertSee('Confirmed')
            ->assertSee(self::CALL_ID);
    }
}
