<?php

namespace Tests\Feature;

use Tests\TestCase;

class NavigationStateTest extends TestCase
{
    public function test_course_navigation_returns_to_the_active_course_detail(): void
    {
        $response = $this
            ->withSession(['active_course_id' => 7])
            ->view('partials.sidebar', ['activeNav' => 'moodle']);

        $response->assertSee(
            'href="'.route('courses.show', ['courseId' => 7]).'"',
            false,
        );
    }

    public function test_course_navigation_targets_dashboard_courses_without_active_context(): void
    {
        $response = $this->view('partials.sidebar', ['activeNav' => 'dashboard']);

        $response->assertSee(
            'href="'.route('dashboard').'#courses"',
            false,
        );
    }

    public function test_legacy_course_page_redirects_to_dashboard(): void
    {
        $this->get(route('courses'))->assertRedirect(route('dashboard'));
        $this->get(route('user.courses'))->assertRedirect(route('dashboard'));
    }
}
