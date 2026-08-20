<?php

namespace Tests\Feature;

use App\Models\ExamTranscript;
use App\Models\Result;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoiceExamPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_sees_the_voice_exam_page_with_their_own_transcripts(): void
    {
        $student = User::factory()->student()->create(['phone' => '01766666666']);
        $other = User::factory()->student()->create(['phone' => '01755555555']);

        ExamTranscript::factory()->create(['student_id' => $student->id, 'subject' => 'Physics']);
        ExamTranscript::factory()->create(['student_id' => $other->id, 'subject' => 'Astronomy']);

        $this->actingAs($student)
            ->get(route('student.voice-exam'))
            ->assertOk()
            ->assertSee('Start an Exam')
            ->assertSee('Physics')
            ->assertDontSee('Astronomy');
    }

    public function test_the_widget_container_carries_the_profile_values_it_autofills(): void
    {
        $student = User::factory()->student()->create([
            'name' => 'Arif Hossain',
            'email' => 'arif@example.com',
            'phone' => '01766666666',
        ]);

        $this->actingAs($student)
            ->get(route('student.voice-exam'))
            ->assertOk()
            ->assertSee('id="webcall-widget"', false)
            ->assertSee('data-name="Arif Hossain"', false)
            ->assertSee('data-phone="01766666666"', false)
            ->assertSee('data-email="arif@example.com"', false)
            ->assertSee('data-website="', false);
    }

    public function test_the_start_an_exam_section_has_no_subject_picker(): void
    {
        $student = User::factory()->student()->create(['phone' => '01766666666']);

        // Results are recorded per student; the examiner asks about subjects in-call.
        $this->actingAs($student)
            ->get(route('student.voice-exam'))
            ->assertOk()
            ->assertDontSee('id="webcall-subject"', false)
            ->assertDontSee('data-subject-select', false);
    }

    public function test_the_start_an_exam_card_is_an_accent_panel_the_widget_blends_into(): void
    {
        $student = User::factory()->student()->create(['phone' => '01766666666']);

        $this->actingAs($student)
            ->get(route('student.voice-exam'))
            ->assertOk()
            ->assertSee('voice-exam-card', false)
            // Built from the shared theme tokens, not hard-coded colours.
            ->assertSee('var(--pia-accent-2)', false)
            // The widget drops its own background so the two read as one panel.
            ->assertSee('background: transparent !important', false);
    }

    public function test_the_widget_scripts_are_loaded_on_the_voice_exam_page_only(): void
    {
        $student = User::factory()->student()->create(['phone' => '01766666666']);

        $this->actingAs($student)
            ->get(route('student.voice-exam'))
            ->assertOk()
            ->assertSee('webcall-bd%201.js', false)
            ->assertSee('voice-exam-embed.js', false);

        // The floating widget must not follow the student around the rest of the panel.
        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertDontSee('webcall-bd%201.js', false);
    }

    public function test_the_website_value_is_a_host_the_widget_will_accept(): void
    {
        config(['app.url' => 'http://127.0.0.1:8000']);
        $student = User::factory()->student()->create(['phone' => '01766666666']);

        $this->actingAs($student)
            ->get(route('student.voice-exam'))
            ->assertOk()
            // No scheme and no port — the widget's URL regex rejects both.
            ->assertSee('data-website="127.0.0.1"', false);
    }

    public function test_a_student_without_a_phone_number_is_prompted_to_add_one(): void
    {
        $student = User::factory()->student()->create(['phone' => null]);

        $this->actingAs($student)
            ->get(route('student.voice-exam'))
            ->assertOk()
            ->assertSee('add one first')
            // Nothing to mount into, so the loading placeholder is not rendered either.
            ->assertDontSee('id="webcall-placeholder"', false);
    }

    public function test_pages_load_the_shared_light_theme(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('data-bs-theme="light"', false)
            ->assertSee('asset/css/theme.css', false);
    }

    public function test_the_login_screen_loads_the_shared_light_theme(): void
    {
        // It has its own <head> rather than extending the layout, so it is checked separately.
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('data-bs-theme="light"', false)
            ->assertSee('asset/css/theme.css', false);
    }

    public function test_teachers_cannot_open_the_student_voice_exam_page(): void
    {
        $this->actingAs(User::factory()->teacher()->create())
            ->get(route('student.voice-exam'))
            ->assertForbidden();
    }

    public function test_teacher_sees_all_transcripts_and_the_unmatched_warning(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create(['name' => 'Arif Hossain']);

        // Rows are identified by student and phone; the list shows no subject column.
        ExamTranscript::factory()->create(['student_id' => $student->id, 'phone' => '01711111111']);
        ExamTranscript::factory()->unmatched()->create(['phone' => '01722222222']);

        $this->actingAs($teacher)
            ->get(route('teacher.transcripts.index'))
            ->assertOk()
            ->assertSee('Arif Hossain')
            ->assertSee('01711111111')
            ->assertSee('01722222222')
            ->assertSee('could not be matched');
    }

    public function test_teacher_can_filter_transcripts_by_status(): void
    {
        $teacher = User::factory()->teacher()->create();
        ExamTranscript::factory()->create(['phone' => '01711111111']);
        ExamTranscript::factory()->unmatched()->create(['phone' => '01722222222']);

        $this->actingAs($teacher)
            ->get(route('teacher.transcripts.index', ['status' => ExamTranscript::STATUS_UNMATCHED]))
            ->assertOk()
            ->assertSee('01722222222')
            ->assertDontSee('01711111111');
    }

    public function test_the_list_shows_no_subject_or_status_column(): void
    {
        $teacher = User::factory()->teacher()->create();
        ExamTranscript::factory()->create(['subject' => 'Astronomy']);

        $this->actingAs($teacher)
            ->get(route('teacher.transcripts.index'))
            ->assertOk()
            ->assertDontSee('<th>Subject</th>', false)
            ->assertDontSee('Astronomy');
    }

    public function test_the_raw_transcript_is_hidden_on_the_result_page(): void
    {
        $teacher = User::factory()->teacher()->create();
        $transcript = ExamTranscript::factory()->create([
            'transcript' => 'Examiner: Define velocity. Student: Speed with direction.',
        ]);

        $this->actingAs($teacher)
            ->get(route('teacher.transcripts.show', $transcript))
            ->assertOk()
            ->assertSee('Voice Test Result')
            ->assertDontSee('Speed with direction.');

        // Hidden from the screen only — the transcript is still stored and still fed to the AI.
        $this->assertStringContainsString('Speed with direction.', $transcript->fresh()->transcript);
    }

    public function test_the_result_page_still_shows_the_score_and_feedback(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create(['name' => 'Nusrat Jahan']);

        $result = Result::factory()->create([
            'student_id' => $student->id,
            'full_marks' => 100,
            'marks_obtained' => 72,
            'grade' => 'A',
            'ai_feedback' => 'Clear answers, work on units.',
        ]);

        $transcript = ExamTranscript::factory()->create([
            'student_id' => $student->id,
            'result_id' => $result->id,
            'status' => ExamTranscript::STATUS_EVALUATED,
            'summary' => 'The student covered five questions.',
        ]);

        $this->actingAs($teacher)
            ->get(route('teacher.transcripts.show', $transcript))
            ->assertOk()
            ->assertSee('Nusrat Jahan')
            ->assertSee('Grade A')
            ->assertSee('72.00 / 100')
            ->assertSee('Clear answers, work on units.')
            ->assertSee('The student covered five questions.')
            ->assertSee($transcript->external_id);
    }

    public function test_students_cannot_reach_the_teacher_transcript_pages(): void
    {
        $transcript = ExamTranscript::factory()->create();

        $this->actingAs(User::factory()->student()->create())
            ->get(route('teacher.transcripts.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->student()->create())
            ->get(route('teacher.transcripts.show', $transcript))
            ->assertForbidden();
    }
}
