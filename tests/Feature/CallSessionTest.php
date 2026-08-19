<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyWebCallSecret;
use App\Models\CallSession;
use App\Models\ExamTranscript;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CallSessionTest extends TestCase
{
    use RefreshDatabase;

    protected const SECRET = 'test-webcall-secret';

    protected User $student;

    protected function setUp(): void
    {
        parent::setUp();

        config(['webcall.webhook_secret' => self::SECRET]);

        // An unattributed callback triggers a Speaklar lookup; keep it off the network.
        Http::fake();

        $this->student = User::factory()->student()->create(['phone' => '01766666666']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function postCallback(array $payload)
    {
        return $this->withHeaders([VerifyWebCallSecret::HEADER => self::SECRET])
            ->postJson(route('webhooks.webcall.transcript'), $payload);
    }

    public function test_a_student_can_register_the_call_id_they_are_about_to_use(): void
    {
        $this->actingAs($this->student)
            ->postJson(route('student.voice-exam.sessions.store'), [
                'call_id' => 'abc123@speaklar',
                'subject' => 'Physics',
            ])
            ->assertCreated()
            ->assertJson(['status' => 'registered']);

        $this->assertDatabaseHas('call_sessions', [
            'student_id' => $this->student->id,
            'call_id' => 'abc123@speaklar',
            'subject' => 'Physics',
        ]);

        $this->assertNotNull(CallSession::first()->started_at);
    }

    public function test_registering_the_same_call_id_twice_updates_rather_than_duplicates(): void
    {
        foreach (['Physics', 'Chemistry'] as $subject) {
            $this->actingAs($this->student)
                ->postJson(route('student.voice-exam.sessions.store'), [
                    'call_id' => 'abc123@speaklar',
                    'subject' => $subject,
                ])->assertCreated();
        }

        $this->assertDatabaseCount('call_sessions', 1);
        $this->assertSame('Chemistry', CallSession::first()->subject);
    }

    public function test_a_student_cannot_claim_another_students_call_id(): void
    {
        $other = User::factory()->student()->create();
        CallSession::factory()->create(['student_id' => $other->id, 'call_id' => 'taken@speaklar']);

        $this->actingAs($this->student)
            ->postJson(route('student.voice-exam.sessions.store'), [
                'call_id' => 'taken@speaklar',
                'subject' => 'Physics',
            ])
            ->assertStatus(409);

        $this->assertSame($other->id, CallSession::first()->student_id);
    }

    public function test_a_student_can_mark_their_call_ended(): void
    {
        CallSession::factory()->create([
            'student_id' => $this->student->id,
            'call_id' => 'abc123@speaklar',
            'ended_at' => null,
        ]);

        $this->actingAs($this->student)
            ->postJson(route('student.voice-exam.sessions.end'), ['call_id' => 'abc123@speaklar'])
            ->assertOk();

        $this->assertNotNull(CallSession::first()->ended_at);
    }

    public function test_ending_someone_elses_call_is_not_found(): void
    {
        CallSession::factory()->create(['call_id' => 'someone-else@speaklar']);

        $this->actingAs($this->student)
            ->postJson(route('student.voice-exam.sessions.end'), ['call_id' => 'someone-else@speaklar'])
            ->assertNotFound();
    }

    public function test_the_session_routes_reject_teachers(): void
    {
        $this->actingAs(User::factory()->teacher()->create())
            ->postJson(route('student.voice-exam.sessions.store'), [
                'call_id' => 'x@speaklar',
                'subject' => 'Physics',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('call_sessions', 0);
    }

    public function test_the_session_routes_reject_guests(): void
    {
        $this->postJson(route('student.voice-exam.sessions.store'), [
            'call_id' => 'x@speaklar',
            'subject' => 'Physics',
        ])->assertUnauthorized();

        $this->assertDatabaseCount('call_sessions', 0);
    }

    public function test_the_callback_resolves_the_student_and_subject_from_the_call_id(): void
    {
        CallSession::factory()->create([
            'student_id' => $this->student->id,
            'call_id' => 'abc123@speaklar',
            'subject' => 'Chemistry',
        ]);

        // No phone and no subject in the payload — the call id carries both.
        $this->postCallback([
            'call_id' => 'abc123@speaklar',
            'transcript' => 'Examiner: Define a mole. Student: 6.022e23 particles.',
        ])->assertStatus(202)->assertJson(['status' => 'accepted']);

        $transcript = ExamTranscript::first();

        $this->assertSame($this->student->id, $transcript->student_id);
        $this->assertSame('Chemistry', $transcript->subject);

        // It was attributed; evaluation then runs after the response (and fails here,
        // because this test seeds no teacher prompts).
        $this->assertNotSame(ExamTranscript::STATUS_UNMATCHED, $transcript->status);
    }

    public function test_the_call_id_wins_over_a_mismatched_phone_number(): void
    {
        $other = User::factory()->student()->create(['phone' => '01999999999']);

        CallSession::factory()->create([
            'student_id' => $this->student->id,
            'call_id' => 'abc123@speaklar',
            'subject' => 'Physics',
        ]);

        $this->postCallback([
            'call_id' => 'abc123@speaklar',
            'phone' => '01999999999',
            'subject' => 'Biology',
            'transcript' => 'Examiner: Hello.',
        ])->assertStatus(202);

        $transcript = ExamTranscript::first();

        $this->assertSame($this->student->id, $transcript->student_id);
        $this->assertNotSame($other->id, $transcript->student_id);
        $this->assertSame('Physics', $transcript->subject);
    }

    public function test_an_unregistered_call_id_still_falls_back_to_the_phone_number(): void
    {
        $this->postCallback([
            'call_id' => 'never-registered@speaklar',
            'phone' => '01766666666',
            'subject' => 'English',
            'transcript' => 'Examiner: Introduce yourself.',
        ])->assertStatus(202)->assertJson(['status' => 'accepted']);

        $transcript = ExamTranscript::first();

        $this->assertSame($this->student->id, $transcript->student_id);
        $this->assertSame('English', $transcript->subject);
    }

    public function test_a_call_id_with_no_session_and_no_phone_is_unmatched(): void
    {
        // Accepted for a provider lookup first; the lookup finds nothing, so it settles
        // as unmatched rather than being rejected outright.
        $this->postCallback([
            'call_id' => 'never-registered@speaklar',
            'transcript' => 'Examiner: Hello.',
        ])->assertStatus(202)->assertJson(['status' => 'pending']);

        $this->assertSame(ExamTranscript::STATUS_UNMATCHED, ExamTranscript::first()->status);
        $this->assertDatabaseCount('results', 0);
    }

    public function test_a_callback_without_a_call_id_still_requires_phone_and_subject(): void
    {
        $this->postCallback(['transcript' => 'Examiner: Hello.'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone', 'subject']);
    }

    public function test_the_voice_exam_page_exposes_the_session_endpoints(): void
    {
        $this->actingAs($this->student)
            ->get(route('student.voice-exam'))
            ->assertOk()
            ->assertSee('data-session-url="'.route('student.voice-exam.sessions.store').'"', false)
            ->assertSee('data-session-end-url="'.route('student.voice-exam.sessions.end').'"', false)
            // Voice exams are recorded per student, so there is no subject picker.
            ->assertDontSee('id="webcall-subject"', false);
    }
}
