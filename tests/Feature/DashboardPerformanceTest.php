<?php

namespace Tests\Feature;

use App\Http\Controllers\LoginController;
use App\Services\GamificationService;
use App\Services\MoodleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class DashboardPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    protected function tearDown(): void
    {
        Cache::flush();
        Mockery::close();

        parent::tearDown();
    }

    public function test_repeated_dashboard_requests_reuse_course_and_progress_data(): void
    {
        $notificationCacheKey = 'notification_events:v1:21:'.sha1('7');
        Cache::put($notificationCacheKey, [], 600);
        Cache::put("illuminate:cache:flexible:created:{$notificationCacheKey}", now()->timestamp, 600);

        $service = Mockery::mock(MoodleService::class);
        $service->shouldReceive('getUserByUsername')
            ->once()
            ->with('student')
            ->andReturn(['id' => 21]);
        $service->shouldReceive('getUserCourses')
            ->once()
            ->with(21)
            ->andReturn([['id' => 7, 'fullname' => 'Pengujian E-Learning Lite']]);
        $service->shouldReceive('getActivityCompletionStatus')
            ->once()
            ->with(7, 21)
            ->andReturn(['statuses' => [
                ['cmid' => 11, 'state' => 1, 'timecompleted' => 1778547600],
                ['cmid' => 12, 'state' => 0],
            ]]);
        $service->shouldNotReceive('getAssignments');
        $service->shouldNotReceive('getQuizzes');
        $service->shouldNotReceive('getRecentlyAccessedItems');
        $service->shouldNotReceive('getCalendarActionEventsByCourses');
        $service->shouldNotReceive('getCourseContents');
        $service->shouldNotReceive('getUserGrades');

        $this->app->instance(MoodleService::class, $service);

        $browser = $this->withSession($this->studentSession());
        $firstResponse = $browser->get(route('dashboard'));
        $secondResponse = $browser->get(route('dashboard'));

        foreach ([$firstResponse, $secondResponse] as $response) {
            $response
                ->assertOk()
                ->assertSee('Pengujian E-Learning Lite')
                ->assertSee('Progres pembelajaran 50 persen')
                ->assertSee('Buka Kelas');
        }

        $this->assertDatabaseCount('gamification_activity_completions', 1);
    }

    public function test_dashboard_cache_invalidation_keeps_course_list_until_enrolment_changes(): void
    {
        $controller = new class(Mockery::mock(MoodleService::class), app(GamificationService::class)) extends LoginController
        {
            public function invalidateDashboardForTest(int $courseId, bool $coursesChanged = false): void
            {
                $this->invalidateCurrentUserDashboardCache($courseId, $coursesChanged);
            }
        };

        $this->withSession($this->studentSession());

        $courseCacheKey = 'notification_courses:v1:21';
        $progressCacheKey = 'dashboard_course_progress:v1:21:7';
        $notificationCacheKey = 'notification_events:v1:21:'.sha1('7');
        $referenceCacheKey = 'notification_events_reference:v1:21';

        Cache::put($courseCacheKey, [['id' => 7]], 600);
        Cache::put($progressCacheKey, ['percent' => 50], 600);
        Cache::put($notificationCacheKey, [['id' => 1]], 600);
        Cache::put("illuminate:cache:flexible:created:{$courseCacheKey}", now()->timestamp, 600);
        Cache::put("illuminate:cache:flexible:created:{$progressCacheKey}", now()->timestamp, 600);
        Cache::put("illuminate:cache:flexible:created:{$notificationCacheKey}", now()->timestamp, 600);
        Cache::forever($referenceCacheKey, $notificationCacheKey);

        $controller->invalidateDashboardForTest(7);

        $this->assertTrue(Cache::has($courseCacheKey));
        $this->assertFalse(Cache::has($progressCacheKey));
        $this->assertFalse(Cache::has("illuminate:cache:flexible:created:{$progressCacheKey}"));
        $this->assertFalse(Cache::has($notificationCacheKey));
        $this->assertFalse(Cache::has("illuminate:cache:flexible:created:{$notificationCacheKey}"));
        $this->assertFalse(Cache::has($referenceCacheKey));

        $controller->invalidateDashboardForTest(7, true);

        $this->assertFalse(Cache::has($courseCacheKey));
        $this->assertFalse(Cache::has("illuminate:cache:flexible:created:{$courseCacheKey}"));
    }

    private function studentSession(): array
    {
        return [
            'logged_in' => true,
            'username' => 'student',
            'moodle_token' => 'student-token',
            'moodle_user' => [
                'id' => 21,
                'name' => 'Mahasiswa',
                'moodle_username' => 'student',
            ],
        ];
    }
}
