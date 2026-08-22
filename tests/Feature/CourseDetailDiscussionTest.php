<?php

namespace Tests\Feature;

use App\Services\MoodleService;
use Mockery;
use Tests\TestCase;

class CourseDetailDiscussionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_announcement_is_displayed_as_discussion_without_completion_status(): void
    {
        $response = $this->withSession([
            'logged_in' => true,
            'active_course_id' => 7,
        ])->view('course_detail', [
            'courseId' => 7,
            'course' => ['id' => 7, 'fullname' => 'Pengantar UI/UX Design'],
            'contents' => [[
                'name' => 'Topik 1',
                'modules' => [[
                    'id' => 10,
                    'modname' => 'forum',
                    'name' => 'Announcements',
                ]],
            ]],
            'contentError' => null,
            'canMonitorCourse' => false,
            'courseProgress' => [
                'percent' => 0,
                'statuses' => [['cmid' => 10, 'state' => 0]],
            ],
        ]);

        $response
            ->assertSee('Announcements')
            ->assertSee('data-lucide="messages-square"', false)
            ->assertDontSee('Belum Diselesaikan')
            ->assertDontSee('Belum Dikumpulkan');
    }

    public function test_announcement_opens_the_final_discussion_page_instead_of_the_legacy_activity_page(): void
    {
        $service = Mockery::mock(MoodleService::class);
        $service->shouldReceive('getCourse')->once()->with(7)->andReturn([
            'id' => 7,
            'fullname' => 'Pengantar UI/UX Design',
        ]);
        $service->shouldReceive('getCourseContents')->once()->with(7)->andReturn([[
            'name' => 'Umum',
            'modules' => [[
                'id' => 10,
                'modname' => 'forum',
                'name' => 'Announcements',
                'description' => 'Silakan gunakan ruang ini untuk berdiskusi.',
            ]],
        ]]);

        $this->app->instance(MoodleService::class, $service);

        $response = $this->withSession([
            'logged_in' => true,
            'moodle_token' => 'student-token',
            'moodle_user' => ['id' => 21, 'name' => 'Mahasiswa'],
        ])->get(route('courses.modules.show', ['courseId' => 7, 'moduleId' => 10]));

        $response
            ->assertOk()
            ->assertSee('discussion-detail-page', false)
            ->assertSee('Forum Diskusi')
            ->assertSee('Silakan gunakan ruang ini untuk berdiskusi.')
            ->assertDontSee('Tipe aktivitas')
            ->assertDontSee('Konten Materi')
            ->assertDontSee('Lampiran');
    }
}
