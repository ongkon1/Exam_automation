<?php

namespace Tests\Feature;

use App\Models\Result;
use App\Models\TeacherSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EvaluationTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;

    protected Result $result;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.openai.key' => 'test-key', 'services.openai.model' => 'gpt-4o-mini']);

        $this->teacher = User::factory()->teacher()->create();
        $this->result = Result::factory()->create([
            'student_id' => User::factory()->student(),
        ]);
    }

    protected function withPrompts(): TeacherSetting
    {
        return TeacherSetting::create([
            'user_id' => $this->teacher->id,
            'system_prompt' => 'You are an examiner.',
            'evaluation_prompt' => 'Give three tips.',
        ]);
    }

    public function test_evaluation_stores_the_feedback_returned_by_openai(): void
    {
        $this->withPrompts();

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Solid work. Practise algebra daily.']],
                ],
            ]),
        ]);

        $this->actingAs($this->teacher)
            ->from(route('teacher.results.index'))
            ->post(route('teacher.results.evaluate', $this->result))
            ->assertRedirect(route('teacher.results.index'))
            ->assertSessionHas('success');

        $this->result->refresh();

        $this->assertSame('Solid work. Practise algebra daily.', $this->result->ai_feedback);
        $this->assertNotNull($this->result->evaluated_at);
    }

    public function test_the_teachers_prompts_are_sent_to_openai(): void
    {
        $this->withPrompts();

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'Feedback.']]],
            ]),
        ]);

        $this->actingAs($this->teacher)->post(route('teacher.results.evaluate', $this->result));

        Http::assertSent(function ($request) {
            $messages = $request->data()['messages'];

            return $request->url() === 'https://api.openai.com/v1/chat/completions'
                && $messages[0]['role'] === 'system'
                && $messages[0]['content'] === 'You are an examiner.'
                && str_contains($messages[1]['content'], 'Give three tips.')
                && str_contains($messages[1]['content'], $this->result->subject);
        });
    }

    public function test_evaluation_is_blocked_until_both_prompts_are_saved(): void
    {
        TeacherSetting::create([
            'user_id' => $this->teacher->id,
            'system_prompt' => 'Only half filled in.',
        ]);

        Http::fake();

        $this->actingAs($this->teacher)
            ->from(route('teacher.results.index'))
            ->post(route('teacher.results.evaluate', $this->result))
            ->assertRedirect(route('teacher.results.index'))
            ->assertSessionHas('error');

        Http::assertNothingSent();
        $this->assertNull($this->result->refresh()->ai_feedback);
    }

    public function test_a_failed_api_call_leaves_the_result_untouched(): void
    {
        $this->withPrompts();

        Http::fake([
            'api.openai.com/*' => Http::response(['error' => ['message' => 'Invalid API key.']], 401),
        ]);

        $this->actingAs($this->teacher)
            ->from(route('teacher.results.index'))
            ->post(route('teacher.results.evaluate', $this->result))
            ->assertRedirect(route('teacher.results.index'))
            ->assertSessionHas('error');

        $this->result->refresh();

        $this->assertNull($this->result->ai_feedback);
        $this->assertNull($this->result->evaluated_at);
    }

    public function test_students_cannot_trigger_an_evaluation(): void
    {
        $this->withPrompts();
        Http::fake();

        $this->actingAs(User::factory()->student()->create())
            ->post(route('teacher.results.evaluate', $this->result))
            ->assertForbidden();

        Http::assertNothingSent();
    }
}
