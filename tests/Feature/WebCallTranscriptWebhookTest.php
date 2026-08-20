<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyWebCallSecret;
use App\Models\ExamTranscript;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The callback contract: a call id and nothing else. Everything that decides whose
 * result it is comes from looking that id up on Speaklar.
 */
class WebCallTranscriptWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected const SECRET = 'test-webcall-secret';

    protected const CALL_ID = '85a764409b8911f1be2d7e0a46f1ba4d';

    protected function setUp(): void
    {
        parent::setUp();

        config(['webcall.webhook_secret' => self::SECRET]);
        Http::fake();
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

    public function test_a_call_id_on_its_own_is_a_valid_callback(): void
    {
        $this->postCallback(['call_id' => self::CALL_ID])
            ->assertStatus(202)
            ->assertJson(['status' => 'accepted']);

        $this->assertDatabaseHas('exam_transcripts', ['external_id' => self::CALL_ID]);
    }

    public function test_a_callback_with_no_call_id_can_never_be_attributed(): void
    {
        $this->postCallback(['transcript' => 'Examiner: Hello.', 'summary' => 'x'])
            ->assertStatus(202)
            ->assertJson(['status' => 'unmatched']);

        // Kept for review, but there is nothing to look the call up by.
        $this->assertSame(ExamTranscript::STATUS_UNMATCHED, ExamTranscript::sole()->status);
        $this->assertDatabaseCount('results', 0);
    }

    public function test_a_malformed_field_is_rejected(): void
    {
        $this->postCallback(['call_id' => self::CALL_ID, 'transcript' => ['not', 'a', 'string']])
            ->assertStatus(422)
            ->assertJsonValidationErrors('transcript');

        $this->assertDatabaseCount('exam_transcripts', 0);
    }

    public function test_optional_fields_are_stored_when_provided(): void
    {
        $this->postCallback([
            'call_id' => self::CALL_ID,
            'transcript' => 'Examiner: State the first law.',
            'summary' => '### Call Summary',
            'result' => 'confirmed',
        ])->assertStatus(202);

        $transcript = ExamTranscript::sole();

        $this->assertSame('Examiner: State the first law.', $transcript->transcript);
        $this->assertSame('confirmed', $transcript->call_result);

        // The provider's summary is discarded; ours is written from the transcript later.
        $this->assertNull($transcript->summary);
    }

    public function test_a_phone_number_in_the_payload_never_decides_the_student(): void
    {
        // The spoofing case: an open endpoint must not mint marks from a posted number.
        $victim = User::factory()->student()->create(['phone' => '01766666666']);

        $this->postCallback([
            'call_id' => self::CALL_ID,
            'phone' => '01766666666',
            'subject' => 'Physics',
            'transcript' => 'Fabricated answers.',
        ])->assertStatus(202);

        $transcript = ExamTranscript::sole();

        $this->assertNull($transcript->student_id);
        $this->assertSame(ExamTranscript::STATUS_UNMATCHED, $transcript->status);
        $this->assertDatabaseCount('results', 0);
        $this->assertSame(0, $victim->results()->count());
    }

    public function test_a_repeated_callback_does_not_create_a_second_transcript(): void
    {
        $payload = ['call_id' => self::CALL_ID, 'transcript' => 'Examiner: Hello.'];

        $this->postCallback($payload)->assertStatus(202);
        $this->postCallback($payload)->assertOk()->assertJson(['status' => 'duplicate']);

        $this->assertDatabaseCount('exam_transcripts', 1);
    }

    public function test_alternative_call_id_field_names_are_accepted(): void
    {
        foreach (['id', 'uuid'] as $index => $field) {
            $this->postCallback([$field => 'call-'.$index])->assertStatus(202);

            $this->assertDatabaseHas('exam_transcripts', ['external_id' => 'call-'.$index]);
        }
    }

    public function test_the_callback_is_rejected_without_the_shared_secret(): void
    {
        $this->postCallback(['call_id' => self::CALL_ID], null)->assertUnauthorized();
        $this->postCallback(['call_id' => self::CALL_ID], 'wrong-secret')->assertUnauthorized();

        $this->assertDatabaseCount('exam_transcripts', 0);
    }

    public function test_the_callback_is_open_when_no_secret_is_configured(): void
    {
        config(['webcall.webhook_secret' => null]);

        // No header sent at all — the endpoint accepts it.
        $this->postJson(route('webhooks.webcall.transcript'), ['call_id' => self::CALL_ID])
            ->assertStatus(202)
            ->assertJson(['status' => 'accepted']);

        $this->assertDatabaseHas('exam_transcripts', ['external_id' => self::CALL_ID]);
    }
}
