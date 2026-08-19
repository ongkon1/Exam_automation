<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyWebCallSecret;
use App\Models\ExamTranscript;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class WebCallTranscriptWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected const SECRET = 'test-webcall-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['webcall.webhook_secret' => self::SECRET]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function postCallback(array $payload, ?string $secret = self::SECRET)
    {
        return $this->withHeaders($secret === null ? [] : [VerifyWebCallSecret::HEADER => $secret])
            ->postJson(route('webhooks.webcall.transcript'), $payload);
    }

    public function test_the_callback_is_a_stateless_api_route(): void
    {
        // This is the URL configured on the provider's side, so pin it down.
        $this->assertSame('/api/webhooks/webcall/transcript', parse_url(
            route('webhooks.webcall.transcript'), PHP_URL_PATH
        ));

        $middleware = Route::getRoutes()->getByName('webhooks.webcall.transcript')->gatherMiddleware();

        // No session and no CSRF token: the provider posts server-to-server.
        $this->assertContains('api', $middleware);
        $this->assertNotContains('web', $middleware);
        $this->assertNotContains(ValidateCsrfToken::class, $middleware);
        $this->assertNotContains(StartSession::class, $middleware);
    }

    public function test_a_transcript_is_stored_and_matched_to_the_student_by_phone(): void
    {
        $student = User::factory()->student()->create(['phone' => '01766666666']);

        $this->postCallback([
            'phone' => '01766666666',
            'subject' => 'Physics',
            'transcript' => 'Examiner: State the first law. Student: An object stays at rest...',
            'call_id' => 'call-1',
        ])->assertStatus(202)->assertJson(['status' => 'accepted']);

        $this->assertDatabaseHas('exam_transcripts', [
            'student_id' => $student->id,
            'subject' => 'Physics',
            'external_id' => 'call-1',
        ]);
    }

    public function test_a_formatted_phone_number_still_matches(): void
    {
        $student = User::factory()->student()->create(['phone' => '01766666666']);

        $this->postCallback([
            'phone' => '+880 1766-666666',
            'subject' => 'English',
            'transcript' => 'Examiner: Introduce yourself. Student: My name is...',
        ])->assertStatus(202);

        $this->assertSame($student->id, ExamTranscript::first()->student_id);
    }

    public function test_an_unknown_phone_number_is_kept_as_unmatched(): void
    {
        User::factory()->student()->create(['phone' => '01766666666']);

        $this->postCallback([
            'phone' => '01999999999',
            'subject' => 'Biology',
            'transcript' => 'Examiner: Define osmosis.',
        ])->assertStatus(202)->assertJson(['status' => 'unmatched']);

        $transcript = ExamTranscript::first();

        $this->assertNull($transcript->student_id);
        $this->assertSame(ExamTranscript::STATUS_UNMATCHED, $transcript->status);
        $this->assertDatabaseCount('results', 0);
    }

    public function test_an_ambiguous_suffix_match_is_not_guessed(): void
    {
        // Two students whose numbers share the trailing digits used for fallback matching.
        User::factory()->student()->create(['phone' => '8801766666666']);
        User::factory()->student()->create(['phone' => '1766666666']);

        $this->postCallback([
            'phone' => '00801766666666',
            'subject' => 'Physics',
            'transcript' => 'Examiner: Hello.',
        ])->assertStatus(202)->assertJson(['status' => 'unmatched']);
    }

    public function test_a_repeated_callback_does_not_create_a_second_transcript(): void
    {
        User::factory()->student()->create(['phone' => '01766666666']);

        $payload = [
            'phone' => '01766666666',
            'subject' => 'Physics',
            'transcript' => 'Examiner: State the first law.',
            'call_id' => 'call-duplicate',
        ];

        $this->postCallback($payload)->assertStatus(202);
        $this->postCallback($payload)->assertOk()->assertJson(['status' => 'duplicate']);

        $this->assertDatabaseCount('exam_transcripts', 1);
    }

    public function test_alternative_provider_field_names_are_accepted(): void
    {
        $student = User::factory()->student()->create(['phone' => '01766666666']);

        $this->postCallback([
            'phone_number' => '01766666666',
            'subject' => 'Chemistry',
            'transcript_text' => 'Examiner: Define a mole.',
            'id' => 'call-alt',
        ])->assertStatus(202);

        $this->assertDatabaseHas('exam_transcripts', [
            'student_id' => $student->id,
            'subject' => 'Chemistry',
            'external_id' => 'call-alt',
        ]);
    }

    public function test_the_callback_is_rejected_without_the_shared_secret(): void
    {
        $this->postCallback(['phone' => '01766666666', 'subject' => 'Physics', 'transcript' => 'x'], null)
            ->assertUnauthorized();

        $this->postCallback(['phone' => '01766666666', 'subject' => 'Physics', 'transcript' => 'x'], 'wrong-secret')
            ->assertUnauthorized();

        $this->assertDatabaseCount('exam_transcripts', 0);
    }

    public function test_the_callback_is_unavailable_when_no_secret_is_configured(): void
    {
        config(['webcall.webhook_secret' => null]);

        $this->postCallback(['phone' => '01766666666', 'subject' => 'Physics', 'transcript' => 'x'])
            ->assertStatus(503);
    }

    public function test_the_payload_is_validated(): void
    {
        $this->postCallback(['subject' => 'Physics'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone', 'transcript']);
    }
}
