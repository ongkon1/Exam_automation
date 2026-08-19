<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyWebCallSecret;
use App\Models\User;
use App\Models\WebhookRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Everything reaching the callback endpoint is recorded before the secret is checked,
 * so a rejected call is distinguishable from a provider that never called.
 */
class WebhookLogTest extends TestCase
{
    use RefreshDatabase;

    protected const SECRET = 'test-webcall-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['webcall.webhook_secret' => self::SECRET]);
        Http::fake();
    }

    public function test_a_rejected_call_is_still_logged(): void
    {
        $this->withHeaders(['X-Webhook-Secret' => 'wrong-secret'])
            ->postJson(route('webhooks.webcall.transcript'), ['transcript' => 'hello'])
            ->assertUnauthorized();

        $entry = WebhookRequest::sole();

        $this->assertSame(401, $entry->status_code);
        $this->assertStringContainsString('hello', $entry->body);
        $this->assertStringContainsString('wrong or missing', $entry->outcome());
    }

    public function test_a_call_with_no_secret_header_at_all_is_logged(): void
    {
        $this->postJson(route('webhooks.webcall.transcript'), ['transcript' => 'hello'])
            ->assertUnauthorized();

        $entry = WebhookRequest::sole();

        $this->assertSame(401, $entry->status_code);
        $this->assertArrayNotHasKey('x-webhook-secret', array_change_key_case($entry->headers));
    }

    public function test_a_call_rejected_by_validation_is_logged(): void
    {
        $this->withHeaders([VerifyWebCallSecret::HEADER => self::SECRET])
            ->postJson(route('webhooks.webcall.transcript'), ['transcript' => ['nonsense']])
            ->assertStatus(422);

        $entry = WebhookRequest::sole();

        $this->assertSame(422, $entry->status_code);
        $this->assertStringContainsString('nonsense', $entry->body);
    }

    public function test_an_accepted_call_is_logged_with_its_response(): void
    {
        $this->withHeaders([VerifyWebCallSecret::HEADER => self::SECRET])
            ->postJson(route('webhooks.webcall.transcript'), [
                'call_id' => 'logged-call-1',
                'transcript' => 'Examiner: Hello.',
            ])->assertStatus(202);

        $entry = WebhookRequest::sole();

        $this->assertSame(202, $entry->status_code);
        $this->assertStringContainsString('transcript_id', $entry->response);
        $this->assertTrue($entry->wasAccepted());
    }

    public function test_the_secret_header_value_is_masked(): void
    {
        $this->withHeaders([VerifyWebCallSecret::HEADER => self::SECRET])
            ->postJson(route('webhooks.webcall.transcript'), ['call_id' => 'masked-call-1']);

        $headers = array_change_key_case(WebhookRequest::sole()->headers);

        $this->assertStringNotContainsString(self::SECRET, $headers['x-webhook-secret']);
        // Enough is kept to confirm the header arrived and roughly what was sent.
        $this->assertStringContainsString('chars', $headers['x-webhook-secret']);
    }

    public function test_a_call_to_the_wrong_webhook_path_is_logged(): void
    {
        $this->postJson('/api/webhooks/something-else', ['transcript' => 'hello'])
            ->assertNotFound()
            ->assertJson(['status' => 'unknown_endpoint']);

        $entry = WebhookRequest::sole();

        $this->assertSame(404, $entry->status_code);
        $this->assertStringContainsString('something-else', $entry->path);
    }

    public function test_a_get_to_the_callback_url_is_logged(): void
    {
        $this->getJson('/api/webhooks/webcall/transcript')->assertNotFound();

        $this->assertSame('GET', WebhookRequest::sole()->method);
    }

    public function test_a_teacher_can_read_the_log(): void
    {
        $this->withHeaders(['X-Webhook-Secret' => 'wrong'])
            ->postJson(route('webhooks.webcall.transcript'), ['transcript' => 'body-marker']);

        $entry = WebhookRequest::sole();

        $this->actingAs(User::factory()->teacher()->create())
            ->get(route('teacher.webhooks.index'))
            ->assertOk()
            ->assertSee('401')
            ->assertSee(route('webhooks.webcall.transcript'));

        $this->actingAs(User::factory()->teacher()->create())
            ->get(route('teacher.webhooks.show', $entry))
            ->assertOk()
            ->assertSee('body-marker');
    }

    public function test_the_log_warns_when_nothing_has_arrived(): void
    {
        $this->actingAs(User::factory()->teacher()->create())
            ->get(route('teacher.webhooks.index'))
            ->assertOk()
            ->assertSee('Nothing has reached this endpoint yet');
    }

    public function test_the_log_shows_the_endpoint_as_open_when_no_secret_is_set(): void
    {
        config(['webcall.webhook_secret' => null]);

        $this->actingAs(User::factory()->teacher()->create())
            ->get(route('teacher.webhooks.index'))
            ->assertOk()
            ->assertSee('Open')
            ->assertSee('no header or key required');
    }

    public function test_the_log_shows_the_endpoint_as_protected_when_a_secret_is_set(): void
    {
        $this->actingAs(User::factory()->teacher()->create())
            ->get(route('teacher.webhooks.index'))
            ->assertOk()
            ->assertSee('Protected');
    }

    public function test_an_open_endpoint_still_logs_every_call(): void
    {
        config(['webcall.webhook_secret' => null]);

        $this->postJson(route('webhooks.webcall.transcript'), [
            'call_id' => 'open-call-1',
            'transcript' => 'open-marker',
        ])->assertStatus(202);

        $entry = WebhookRequest::sole();

        $this->assertSame(202, $entry->status_code);
        $this->assertStringContainsString('open-marker', $entry->body);
    }

    public function test_a_teacher_can_clear_the_log(): void
    {
        WebhookRequest::create(['method' => 'POST', 'path' => '/api/webhooks/webcall/transcript']);

        $this->actingAs(User::factory()->teacher()->create())
            ->delete(route('teacher.webhooks.destroy'))
            ->assertRedirect(route('teacher.webhooks.index'));

        $this->assertDatabaseCount('webhook_requests', 0);
    }

    public function test_students_cannot_read_the_log(): void
    {
        $this->actingAs(User::factory()->student()->create())
            ->get(route('teacher.webhooks.index'))
            ->assertForbidden();
    }
}
