<?php

namespace Tests\Feature;

use App\Services\MoodleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class NotificationFlowTest extends TestCase
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

    public function test_graded_assignment_appears_in_task_notifications_with_grade_link(): void
    {
        $gradedAt = \Carbon\Carbon::create(2026, 8, 24, 2, 2, 0, config('app.timezone'))->timestamp;
        $service = $this->notificationService([
            'gradeitems' => [[
                'itemname' => 'Penerapan Tahapan Design Thinking',
                'itemmodule' => 'assign',
                'iteminstance' => 55,
                'cmid' => 11,
                'gradefordisplay' => '85,00',
                'graderaw' => 85,
                'gradedategraded' => $gradedAt,
                'timemodified' => $gradedAt + 86400,
                'hidden' => false,
            ]],
        ]);
        $this->app->instance(MoodleService::class, $service);

        $response = $this->withStudentSession()->get(route('notifications', ['filter' => 'task']));

        $response
            ->assertOk()
            ->assertSee('Semua')
            ->assertSee('Batas Waktu')
            ->assertSee('Nilai Tugas')
            ->assertSee('Penerapan Tahapan Design Thinking')
            ->assertDontSee('Penerapan Tahapan Design Thinking sudah dinilai')
            ->assertSee('24 Aug 2026, 02:02')
            ->assertDontSee('Nilai tugasmu sudah tersedia dan dapat dilihat pada Detail Nilai.')
            ->assertSee('Lihat Nilai')
            ->assertSee(route('grades', ['courseid' => 7, 'tab' => 'task']))
            ->assertSee('Baru');
    }

    public function test_ungraded_assignment_does_not_create_grade_notification(): void
    {
        $service = $this->notificationService([
            'gradeitems' => [[
                'itemname' => 'Tugas Belum Dinilai',
                'itemmodule' => 'assign',
                'iteminstance' => 55,
                'cmid' => 11,
                'gradefordisplay' => '-',
                'graderaw' => null,
                'gradedategraded' => 0,
                'hidden' => false,
            ]],
        ]);
        $this->app->instance(MoodleService::class, $service);

        $response = $this->withStudentSession()->get(route('notifications', ['filter' => 'task']));

        $response
            ->assertOk()
            ->assertDontSee('Tugas Belum Dinilai')
            ->assertDontSee('Lihat Nilai');
    }

    public function test_assignment_without_moodle_grading_date_does_not_create_grade_notification(): void
    {
        $service = $this->notificationService([
            'gradeitems' => [[
                'itemname' => 'Tugas Tanpa Tanggal Penilaian',
                'itemmodule' => 'assign',
                'iteminstance' => 55,
                'cmid' => 11,
                'graderaw' => 80,
                'gradedategraded' => 0,
                'timemodified' => time(),
                'hidden' => false,
            ]],
        ]);
        $this->app->instance(MoodleService::class, $service);

        $this->withStudentSession()
            ->get(route('notifications', ['filter' => 'task']))
            ->assertOk()
            ->assertDontSee('Tugas Tanpa Tanggal Penilaian')
            ->assertDontSee('Lihat Nilai');
    }

    public function test_graded_assignment_also_appears_in_all_notifications(): void
    {
        $service = $this->notificationService([
            'gradeitems' => [[
                'itemname' => 'Tugas Riset Pengguna',
                'itemmodule' => 'assign',
                'iteminstance' => 56,
                'cmid' => 12,
                'graderaw' => 90,
                'gradedategraded' => time() - 120,
                'hidden' => false,
            ]],
        ]);
        $this->app->instance(MoodleService::class, $service);

        $this->withStudentSession()
            ->get(route('notifications'))
            ->assertOk()
            ->assertSee('Tugas Riset Pengguna')
            ->assertDontSee('Tugas Riset Pengguna sudah dinilai')
            ->assertSee('Lihat Nilai');
    }

    public function test_notification_page_reuses_warmed_event_cache_without_waiting_for_moodle(): void
    {
        $courseCacheKey = 'notification_courses:v1:21';
        $cacheKey = 'notification_events:v1:21:'.sha1('7');
        Cache::put($courseCacheKey, [['id' => 7, 'fullname' => 'Design Thinking']], 600);
        Cache::put("illuminate:cache:flexible:created:{$courseCacheKey}", now()->timestamp, 600);
        Cache::put($cacheKey, [[
            'name' => 'Tugas dari Cache',
            'courseid' => 7,
            'cmid' => 11,
            'instance' => 55,
            'modulename' => 'assign',
            'eventtype' => 'assignment_graded',
            'timesort' => time() - 30,
            'source' => 'assignment-grade',
            'course' => [
                'id' => 7,
                'fullname' => 'Design Thinking',
            ],
        ]], 600);
        Cache::put("illuminate:cache:flexible:created:{$cacheKey}", now()->timestamp, 600);

        $service = Mockery::mock(MoodleService::class);
        $service->shouldNotReceive('getUserByUsername');
        $service->shouldNotReceive('getUserCourses');
        $service->shouldNotReceive('getCalendarActionEventsByCourses');
        $service->shouldNotReceive('getAssignments');
        $service->shouldNotReceive('getQuizzes');
        $service->shouldNotReceive('getCourseContents');
        $service->shouldNotReceive('getUserGrades');
        $this->app->instance(MoodleService::class, $service);

        $this->withStudentSession()
            ->get(route('notifications', ['filter' => 'task']))
            ->assertOk()
            ->assertSee('Tugas dari Cache')
            ->assertSee('Lihat Nilai');
    }

    public function test_graded_assignment_older_than_thirty_days_remains_available(): void
    {
        $service = $this->notificationService([
            'gradeitems' => [[
                'itemname' => 'Tugas Lama Sudah Dinilai',
                'itemmodule' => 'assign',
                'iteminstance' => 55,
                'cmid' => 11,
                'graderaw' => 87,
                'gradedategraded' => now()->subDays(45)->timestamp,
                'hidden' => false,
            ]],
        ]);
        $this->app->instance(MoodleService::class, $service);

        $this->withStudentSession()
            ->get(route('notifications', ['filter' => 'task']))
            ->assertOk()
            ->assertSee('Tugas Lama Sudah Dinilai')
            ->assertSee('Lihat Nilai');
    }

    public function test_grade_lookup_failure_is_not_shown_as_an_empty_success_state(): void
    {
        $service = Mockery::mock(MoodleService::class);
        $service->shouldReceive('getUserByUsername')->once()->with('student')->andReturn(['id' => 21]);
        $service->shouldReceive('getUserCourses')->once()->with(21)->andReturn([
            ['id' => 7, 'fullname' => 'Design Thinking'],
        ]);
        $service->shouldReceive('getCalendarActionEventsByCourses')->once()->with([7])->andReturn([]);
        $service->shouldReceive('getAssignments')->once()->with(7)->andReturn([]);
        $service->shouldReceive('getQuizzes')->once()->with(7)->andReturn([]);
        $service->shouldReceive('getCourseContents')->once()->with(7)->andReturn([]);
        $service->shouldReceive('getUserGrades')->once()->with(7, 21)->andThrow(new RuntimeException('Moodle gagal'));
        $this->app->instance(MoodleService::class, $service);

        $this->withStudentSession()
            ->get(route('notifications', ['filter' => 'task']))
            ->assertOk()
            ->assertSee('Sebagian nilai tugas belum dapat diperbarui. Silakan coba lagi beberapa saat.');
    }

    public function test_task_filter_only_contains_graded_assignment_notifications(): void
    {
        $service = $this->notificationService(
            ['gradeitems' => [[
                'itemname' => 'Tugas yang Dinilai',
                'itemmodule' => 'assign',
                'iteminstance' => 55,
                'cmid' => 11,
                'graderaw' => 88,
                'gradedategraded' => time() - 60,
                'hidden' => false,
            ]]],
            ['courses' => [['assignments' => [[
                'id' => 56,
                'cmid' => 12,
                'name' => 'Tugas dengan Deadline',
                'duedate' => time() + 86400,
            ]]]]],
            [[
                'name' => 'Pertemuan 2',
                'modules' => [[
                    'id' => 12,
                    'instance' => 56,
                    'modname' => 'assign',
                    'name' => 'Tugas Baru',
                    'added' => time() - 300,
                ]],
            ]],
        );
        $this->app->instance(MoodleService::class, $service);

        $this->withStudentSession()
            ->get(route('notifications', ['filter' => 'task']))
            ->assertOk()
            ->assertSee('Tugas yang Dinilai')
            ->assertDontSee('Tugas yang Dinilai sudah dinilai')
            ->assertDontSee('Tugas dengan Deadline')
            ->assertDontSee('Tugas Baru');
    }

    public function test_all_notifications_collapse_open_due_and_activity_events_for_the_same_task(): void
    {
        $dueAt = time() + 86400;
        $service = $this->notificationService(
            ['gradeitems' => []],
            ['courses' => [['assignments' => [[
                'id' => 56,
                'cmid' => 12,
                'name' => 'Test notif',
                'duedate' => $dueAt,
            ]]]]],
            [[
                'name' => 'Pertemuan 2',
                'modules' => [[
                    'id' => 12,
                    'instance' => 56,
                    'modname' => 'assign',
                    'name' => 'Test notif',
                    'added' => time() - 300,
                ]],
            ]],
            ['events' => [
                [
                    'id' => 901,
                    'name' => 'Test notif - Dibuka:',
                    'courseid' => 7,
                    'cmid' => 912,
                    'modulename' => 'assign',
                    'eventtype' => 'open',
                    'timestart' => time() - 300,
                    'timesort' => time() - 300,
                    'description' => '<p>Deskripsi event dibuka yang tidak perlu tampil.</p>',
                ],
                [
                    'id' => 902,
                    'name' => 'Test notif - Jatuh tempo:',
                    'courseid' => 7,
                    'instance' => 956,
                    'modulename' => 'assign',
                    'eventtype' => 'due',
                    'timestart' => $dueAt,
                    'timesort' => $dueAt,
                    'description' => '<p>Deskripsi event jatuh tempo yang tidak perlu tampil.</p>',
                ],
            ]],
        );
        $this->app->instance(MoodleService::class, $service);

        $this->withStudentSession()
            ->get(route('notifications'))
            ->assertOk()
            ->assertViewHas('events', fn(array $events): bool => count($events) === 1
                && ($events[0]['name'] ?? null) === 'Test notif'
                && (int) ($events[0]['timesort'] ?? 0) === $dueAt)
            ->assertDontSee('Deskripsi event dibuka yang tidak perlu tampil.')
            ->assertDontSee('Deskripsi event jatuh tempo yang tidak perlu tampil.');
    }

    public function test_all_notifications_collapse_indonesian_quiz_closing_events(): void
    {
        $closeAt = time() + 86400;
        $service = $this->notificationService(
            ['gradeitems' => []],
            [],
            [[
                'name' => 'Pertemuan 2',
                'modules' => [[
                    'id' => 22,
                    'instance' => 66,
                    'modname' => 'quiz',
                    'name' => 'Kuis Pemahaman Design Thinking',
                    'added' => time() - 300,
                ]],
            ]],
            ['events' => [[
                'id' => 903,
                'name' => 'Kuis Pemahaman Design Thinking - Tutup:',
                'courseid' => 7,
                'cmid' => 922,
                'modulename' => 'quiz',
                'eventtype' => 'close',
                'timestart' => $closeAt,
                'timesort' => $closeAt,
            ]]],
            ['quizzes' => [[
                'id' => 66,
                'coursemodule' => 22,
                'name' => 'Kuis Pemahaman Design Thinking',
                'timeclose' => $closeAt,
            ]]],
        );
        $this->app->instance(MoodleService::class, $service);

        $this->withStudentSession()
            ->get(route('notifications'))
            ->assertOk()
            ->assertViewHas('events', fn(array $events): bool => count($events) === 1
                && ($events[0]['name'] ?? null) === 'Kuis Pemahaman Design Thinking'
                && (int) ($events[0]['timesort'] ?? 0) === $closeAt)
            ->assertDontSee('Kuis Pemahaman Design Thinking - Tutup:');
    }

    public function test_unread_summary_is_available_without_opening_dashboard(): void
    {
        $service = $this->notificationService(
            ['gradeitems' => [[
                'itemname' => 'Tugas Refleksi',
                'itemmodule' => 'assign',
                'iteminstance' => 55,
                'cmid' => 11,
                'graderaw' => 90,
                'gradedategraded' => time() - 60,
                'hidden' => false,
            ]]],
            [],
            [],
            [],
            ['quizzes' => [[
                'id' => 66,
                'coursemodule' => 22,
                'name' => 'Kuis Mingguan',
                'timeclose' => time() + 86400,
            ]]],
        );
        $this->app->instance(MoodleService::class, $service);

        $this->withStudentSession()
            ->getJson(route('notifications.unread-summary'))
            ->assertOk()
            ->assertJsonPath('unread.all', 2)
            ->assertJsonPath('unread.deadline', 1)
            ->assertJsonPath('unread.task', 1);
    }

    public function test_notification_page_shows_unread_counts_for_each_matching_filter(): void
    {
        $service = $this->notificationService(
            ['gradeitems' => [[
                'itemname' => 'Tugas Refleksi',
                'itemmodule' => 'assign',
                'iteminstance' => 55,
                'cmid' => 11,
                'graderaw' => 90,
                'gradedategraded' => time() - 60,
                'hidden' => false,
            ]]],
            [],
            [],
            [],
            ['quizzes' => [[
                'id' => 66,
                'coursemodule' => 22,
                'name' => 'Kuis Mingguan',
                'timeclose' => time() + 86400,
            ]]],
        );
        $this->app->instance(MoodleService::class, $service);

        $this->withStudentSession()
            ->get(route('notifications'))
            ->assertOk()
            ->assertViewHas('unreadNotificationCounts', [
                'all' => 2,
                'deadline' => 1,
                'task' => 1,
            ])
            ->assertSee('Buka notifikasi, 2 belum dibaca')
            ->assertSee('Semua, 2 baru')
            ->assertSee('Batas Waktu, 1 baru')
            ->assertSee('Nilai Tugas, 1 baru');
    }

    public function test_read_notifications_stay_read_after_session_read_keys_are_lost(): void
    {
        $service = $this->notificationService([
            'gradeitems' => [[
                'itemname' => 'Tugas Persisten',
                'itemmodule' => 'assign',
                'iteminstance' => 55,
                'cmid' => 11,
                'graderaw' => 90,
                'gradedategraded' => time() - 60,
                'hidden' => false,
            ]],
        ]);
        $this->app->instance(MoodleService::class, $service);

        $this->withStudentSession()
            ->get(route('notifications'))
            ->assertOk();

        $this->assertDatabaseCount('notification_reads', 1);

        $this->withSession([
            'logged_in' => true,
            'username' => 'student',
            'moodle_token' => 'student-token',
            'moodle_user' => ['id' => 21, 'name' => 'Mahasiswa', 'moodle_username' => 'student'],
            'read_notification_events' => [],
        ])->getJson(route('notifications.unread-summary'))
            ->assertOk()
            ->assertJsonPath('unread.all', 0)
            ->assertJsonPath('unread.task', 0);
    }

    public function test_unread_summary_requires_an_active_student_session(): void
    {
        $this->getJson(route('notifications.unread-summary'))
            ->assertUnauthorized();
    }

    public function test_topbar_can_refresh_notification_badge_on_non_dashboard_pages(): void
    {
        $this->withStudentSession()
            ->get(route('profile'))
            ->assertOk()
            ->assertSee(route('notifications.unread-summary'))
            ->assertSee('data-notification-badge', false);
    }

    protected function notificationService(
        array $gradesData,
        array $assignmentsResponse = [],
        array $courseContents = [],
        array $calendarResponse = [],
        array $quizzesResponse = [],
    ): MoodleService
    {
        $service = Mockery::mock(MoodleService::class);
        $service->shouldReceive('getUserByUsername')->once()->with('student')->andReturn(['id' => 21]);
        $service->shouldReceive('getUserCourses')->once()->with(21)->andReturn([
            ['id' => 7, 'fullname' => 'Design Thinking'],
        ]);
        $service->shouldReceive('getCalendarActionEventsByCourses')->once()->with([7])->andReturn($calendarResponse);
        $service->shouldReceive('getAssignments')->once()->with(7)->andReturn($assignmentsResponse);
        $service->shouldReceive('getQuizzes')->once()->with(7)->andReturn($quizzesResponse);
        $service->shouldReceive('getCourseContents')->once()->with(7)->andReturn($courseContents);
        $service->shouldReceive('getUserGrades')->once()->with(7, 21)->andReturn($gradesData);

        return $service;
    }

    protected function withStudentSession(): static
    {
        return $this->withSession([
            'logged_in' => true,
            'username' => 'student',
            'moodle_token' => 'student-token',
            'moodle_user' => ['id' => 21, 'name' => 'Mahasiswa', 'moodle_username' => 'student'],
        ]);
    }
}
