<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PowerinAiDemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_landing_page_is_public(): void
    {
        $this->get('/powerinai-demo')
            ->assertOk()
            ->assertSee('PowerinAI Voice Assistant');
    }

    public function test_it_renders_the_supplied_logo_artwork(): void
    {
        $this->get(route('powerinai-demo'))
            ->assertOk()
            ->assertSee('asset/img/powerinai-logo.jpg', false)
            ->assertSee('PowerinAI.com', false)
            ->assertSee('Hire AI Employee', false)
            // The header is the artwork itself, not type set in HTML.
            ->assertDontSee('pa-wordmark', false);
    }

    public function test_the_logo_files_exist(): void
    {
        $this->assertFileExists(public_path('asset/img/powerinai-logo.jpg'));
        $this->assertFileExists(public_path('asset/img/powerinai-mark-light.svg'));
    }

    public function test_the_avatar_uses_the_light_mark_so_it_reads_on_the_dark_tile(): void
    {
        $this->get(route('powerinai-demo'))
            ->assertOk()
            ->assertSee('powerinai-mark-light.svg', false);
    }

    public function test_it_renders_the_assistant_console(): void
    {
        $this->get(route('powerinai-demo'))
            ->assertOk()
            ->assertSee('Online')
            ->assertSee('Tap to start speaking')
            ->assertSee('Listening for your request')
            ->assertSee('Tap to speak')
            ->assertSee('id="pa-mic"', false);
    }

    public function test_it_loads_its_own_stylesheet_and_not_the_app_theme(): void
    {
        // The landing page is standalone: no sidebar, no navbar, no app chrome.
        $this->get(route('powerinai-demo'))
            ->assertOk()
            ->assertSee('asset/css/powerinai-demo.css', false)
            ->assertDontSee('asset/css/theme.css', false)
            ->assertDontSee('Government Exam Monitoring');
    }

    public function test_signed_in_users_are_not_redirected_away(): void
    {
        // It sits outside the guest-only group, so it stays reachable when logged in.
        $this->actingAs(User::factory()->teacher()->create())
            ->get(route('powerinai-demo'))
            ->assertOk();

        $this->actingAs(User::factory()->student()->create())
            ->get(route('powerinai-demo'))
            ->assertOk();
    }
}
