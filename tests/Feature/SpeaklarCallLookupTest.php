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
 * The browser never learns the provider's call id, so the transcript callback arrives
 * for an unknown call. These cover the recovery path: look the call up on Speaklar,
 * read the student's number off the CDR, and pair it with the session they opened.
 */
class SpeaklarCallLookupTest extends TestCase
{
    use RefreshDatabase;

    protected const SECRET = 'test-webcall-secret';

    protected const CALL_ID = '454ffa209b9d11f1831ebf8e1fb071d7';

    /** The number the student called from, as Speaklar reports it in cdr.dst. */
    protected const DST = '008801890318278';

    protected User $student;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'webcall.webhook_secret' => self::SECRET,
            'webcall.session_window_minutes' => 360,
            'services.speaklar.url' => 'https://app.speaklar.com',
            'services.speaklar.token' => 'test-speaklar-token',
            'services.openai.key' => 'test-openai-key',
        ]);

        $teacher = User::factory()->teacher()->create();

        TeacherSetting::create([
            'user_id' => $teacher->id,
            'system_prompt' => 'You are an oral examiner.',
            'evaluation_prompt' => 'Score this spoken exam.',
        ]);

        $this->student = User::factory()->student()->create([
            'phone' => '01890318278',
            'created_by' => $teacher->id,
        ]);
    }

    /**
     * Mirrors the real status response, including the {http_status, body} wrapper.
     */
    protected function fakeSpeaklar(array $overrides = []): void
    {
        $call = array_merge([
            'type' => 'ai_bulk_call',
            'call_id' => self::CALL_ID,
            'uuid' => self::CALL_ID,
            'phone_number' => '770115',
            'extension' => '770115',
            'carrier' => '0',
            'status' => 'success',
            'transcript' => "assistant: আসসালামু আলাইকুম\nuser: Wa Alaikum Salam.",
            'cdr' => [
                'src' => '770115',
                'dst' => self::DST,
                'duration' => 67,
                'billsec' => 67,
                'disposition' => 'ANSWERED',
            ],
            'ai_data' => ['port' => '770115', 'status' => 'success', 'duration' => 66],
        ], $overrides);

        Http::fake([
            'app.speaklar.com/*' => Http::response([
                'http_status' => 200,
                'body' => ['ok' => true, 'type' => 'ai_bulk_call', 'calls' => [$call]],
            ]),
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'marks_obtained' => 60, 'feedback' => 'Answered three of five correctly.',
                ])]]],
            ]),
        ]);
    }

    protected function postCallback(array $overrides = [])
    {
        return $this->withHeaders([VerifyWebCallSecret::HEADER => self::SECRET])
            ->postJson(route('webhooks.webcall.transcript'), array_merge([
                'order_id' => null,
                'call_id' => self::CALL_ID,
                'port' => '770115',
                'carrier' => '0',
                'result' => 'confirmed',
                'summary' => '### Call Summary',
                'transcript' => "assistant: আসসালামু আলাইকুম\nuser: Wa Alaikum Salam.",
            ], $overrides));
    }

    public function test_an_unknown_call_is_looked_up_and_matched_to_the_student(): void
    {
        $this->fakeSpeaklar();

        CallSession::create([
            'student_id' => $this->student->id,
            'phone' => $this->student->phone,
            'subject' => 'English',
            'started_at' => now()->subMinutes(5),
        ]);

        $this->postCallback()->assertStatus(202)->assertJson(['status' => 'accepted']);

        $transcript = ExamTranscript::first();

        $this->assertSame($this->student->id, $transcript->student_id);
        $this->assertSame('English', $transcript->subject);
        $this->assertSame(ExamTranscript::STATUS_EVALUATED, $transcript->status);
        $this->assertSame('60.00', $transcript->result->marks_obtained);
        $this->assertSame('English', $transcript->result->subject);
    }

    public function test_the_lookup_is_sent_with_the_bearer_token_and_call_id(): void
    {
        $this->fakeSpeaklar();

        CallSession::create([
            'student_id' => $this->student->id,
            'subject' => 'English',
            'started_at' => now()->subMinute(),
        ]);

        $this->postCallback();

        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'https://app.speaklar.com/api/ai-bulk-calls/status')
                && str_contains($request->url(), 'call_id='.self::CALL_ID)
                && $request->hasHeader('Authorization', 'Bearer test-speaklar-token');
        });
    }

    public function test_the_session_is_consumed_so_a_later_call_cannot_reuse_it(): void
    {
        $this->fakeSpeaklar();

        $session = CallSession::create([
            'student_id' => $this->student->id,
            'subject' => 'English',
            'started_at' => now()->subMinutes(2),
        ]);

        $this->postCallback();

        $session->refresh();

        $this->assertNotNull($session->matched_at);
        $this->assertSame(self::CALL_ID, $session->call_id);
        $this->assertNull(CallSession::openFor($this->student));
    }

    public function test_the_internal_channel_number_is_not_treated_as_the_student(): void
    {
        // src/port/extension are all 770115 — a student row carrying that must not match.
        User::factory()->student()->create(['phone' => '770115']);

        $this->fakeSpeaklar();

        CallSession::create([
            'student_id' => $this->student->id,
            'subject' => 'English',
            'started_at' => now()->subMinute(),
        ]);

        $this->postCallback();

        $this->assertSame($this->student->id, ExamTranscript::first()->student_id);
    }

    public function test_a_call_with_no_matching_student_is_left_unmatched(): void
    {
        $this->fakeSpeaklar(['cdr' => ['src' => '770115', 'dst' => '008809999999999', 'billsec' => 10]]);

        $this->postCallback();

        $transcript = ExamTranscript::first();

        $this->assertNull($transcript->student_id);
        $this->assertSame(ExamTranscript::STATUS_UNMATCHED, $transcript->status);
        // The content is still kept for the teacher to reconcile.
        $this->assertNotEmpty($transcript->transcript);
    }

    public function test_a_matched_student_needs_no_session_because_results_are_student_wise(): void
    {
        $this->fakeSpeaklar();

        $this->postCallback();

        $transcript = ExamTranscript::first();

        $this->assertSame($this->student->id, $transcript->student_id);
        $this->assertSame(ExamTranscript::STATUS_EVALUATED, $transcript->status);
        // No subject was captured anywhere, so the configured label is used.
        $this->assertSame(config('webcall.subject'), $transcript->result->subject);
    }

    public function test_a_stale_session_outside_the_window_is_not_consumed(): void
    {
        $this->fakeSpeaklar();

        $stale = CallSession::create([
            'student_id' => $this->student->id,
            'subject' => 'English',
            'started_at' => now()->subMinutes(400),
        ]);

        $this->postCallback();

        // The student still matches on their phone number, so the exam is scored...
        $this->assertSame(ExamTranscript::STATUS_EVALUATED, ExamTranscript::first()->status);
        // ...but a session from hours ago is neither claimed nor used.
        $this->assertNull($stale->refresh()->matched_at);
        $this->assertSame(config('webcall.subject'), ExamTranscript::first()->result->subject);
    }

    public function test_a_failed_lookup_leaves_the_transcript_unmatched(): void
    {
        Http::fake(['app.speaklar.com/*' => Http::response(['message' => 'Unauthorized'], 401)]);

        CallSession::create([
            'student_id' => $this->student->id,
            'subject' => 'English',
            'started_at' => now()->subMinute(),
        ]);

        $this->postCallback();

        $this->assertSame(ExamTranscript::STATUS_UNMATCHED, ExamTranscript::first()->status);
    }

    public function test_no_lookup_happens_when_the_callback_already_identifies_the_student(): void
    {
        $this->fakeSpeaklar();

        CallSession::factory()->create([
            'student_id' => $this->student->id,
            'call_id' => self::CALL_ID,
            'subject' => 'Physics',
        ]);

        $this->postCallback()->assertJson(['status' => 'accepted']);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'speaklar.com'));
        $this->assertSame('Physics', ExamTranscript::first()->subject);
    }

    public function test_students_open_a_session_without_a_call_id(): void
    {
        $this->actingAs($this->student)
            ->postJson(route('student.voice-exam.sessions.store'), ['subject' => 'Biology'])
            ->assertCreated()
            ->assertJson(['status' => 'registered']);

        $session = CallSession::first();

        $this->assertSame($this->student->id, $session->student_id);
        $this->assertSame('Biology', $session->subject);
        $this->assertSame('01890318278', $session->phone);
        $this->assertNull($session->call_id);
    }

    public function test_reopening_within_two_minutes_updates_the_same_session(): void
    {
        foreach (['Biology', 'Physics'] as $subject) {
            $this->actingAs($this->student)
                ->postJson(route('student.voice-exam.sessions.store'), ['subject' => $subject])
                ->assertCreated();
        }

        $this->assertDatabaseCount('call_sessions', 1);
        $this->assertSame('Physics', CallSession::first()->subject);
    }

    public function test_a_student_can_end_their_session_without_a_call_id(): void
    {
        CallSession::create([
            'student_id' => $this->student->id,
            'subject' => 'English',
            'started_at' => now()->subMinute(),
        ]);

        $this->actingAs($this->student)
            ->postJson(route('student.voice-exam.sessions.end'), [])
            ->assertOk();

        $this->assertNotNull(CallSession::first()->ended_at);
    }
}
