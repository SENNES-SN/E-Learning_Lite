<?php

namespace Tests\Feature;

use App\Models\GamificationActivityCompletion;
use App\Services\MoodleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AchievementFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_student_can_view_points_badges_and_course_leaderboard(): void
    {
        $service = Mockery::mock(MoodleService::class);
        $service->shouldReceive('getCourse')->once()->with(7)->andReturn([
            'id' => 7,
            'fullname' => 'Pengantar UI/UX Design',
        ]);
        $service->shouldReceive('getCourseContents')->once()->with(7)->andReturn($this->courseContents());
        $service->shouldReceive('getActivityCompletionStatus')->once()->with(7, 21)->andReturn([
            'statuses' => $this->completionStatuses(2),
        ]);
        $service->shouldReceive('getEnrolledUsers')->once()->with(7)->andReturn($this->students());
        $service->shouldReceive('getActivityCompletionStatus')->once()->with(7, 22)->andReturn([
            'statuses' => $this->completionStatuses(3),
        ]);
        $service->shouldReceive('getActivityCompletionStatus')->once()->with(7, 23)->andReturn([
            'statuses' => $this->completionStatuses(1),
        ]);

        $this->app->instance(MoodleService::class, $service);

        $response = $this->withStudentSession()->get(route('courses.achievements', ['courseId' => 7]));

        $response
            ->assertOk()
            ->assertSee('Pencapaian Saya')
            ->assertSee('Poin Saya')
            ->assertSee('20')
            ->assertSee('# 2')
            ->assertSee('Knowledge Seeker')
            ->assertSee('Goal Getter')
            ->assertSee('Pointbreaker')
            ->assertSee('Runner Up')
            ->assertSee('Third Place')
            ->assertSee(\Carbon\Carbon::createFromTimestamp(1778547720)->locale('id')->translatedFormat('d F Y'))
            ->assertSee('Semua Badge')
            ->assertSee('all-badges-layer', false)
            ->assertSee('Mahasiswa 2')
            ->assertSee('Anda')
            ->assertDontSee('Buka di Moodle');
    }

    public function test_leaderboard_failure_keeps_the_students_own_achievement_visible(): void
    {
        $service = Mockery::mock(MoodleService::class);
        $service->shouldReceive('getCourse')->once()->with(7)->andReturn([
            'id' => 7,
            'fullname' => 'Pengantar UI/UX Design',
        ]);
        $service->shouldReceive('getCourseContents')->once()->with(7)->andReturn($this->courseContents());
        $service->shouldReceive('getActivityCompletionStatus')->once()->with(7, 21)->andReturn([
            'statuses' => $this->completionStatuses(2),
        ]);
        $service->shouldReceive('getEnrolledUsers')->once()->with(7)->andThrow(new \RuntimeException('service unavailable'));

        $this->app->instance(MoodleService::class, $service);

        $response = $this->withStudentSession()->get(route('courses.achievements', ['courseId' => 7]));

        $response
            ->assertOk()
            ->assertSee('Pencapaian Saya')
            ->assertSee('20')
            ->assertSee('Leaderboard belum dapat dimuat sepenuhnya.')
            ->assertSee('Anda');
    }

    public function test_leaderboard_reads_another_students_local_points_when_moodle_completion_is_unavailable(): void
    {
        foreach (range(1, 10) as $moduleId) {
            GamificationActivityCompletion::query()->create([
                'moodle_user_id' => 21,
                'course_id' => 7,
                'module_id' => $moduleId,
                'completed_at' => now()->subMinutes(11 - $moduleId),
            ]);
        }

        $service = Mockery::mock(MoodleService::class);
        $service->shouldReceive('getCourse')->once()->with(7)->andReturn([
            'id' => 7,
            'fullname' => 'Pengantar UI/UX Design',
        ]);
        $service->shouldReceive('getCourseContents')->once()->with(7)->andReturn($this->courseContents(10));
        $service->shouldReceive('getActivityCompletionStatus')->once()->with(7, 22)->andReturn([
            'statuses' => $this->completionStatuses(2, 10),
        ]);
        $service->shouldReceive('getEnrolledUsers')->once()->with(7)->andReturn([
            ['id' => 21, 'fullname' => 'Mahasiswa Seratus Poin', 'roles' => [['shortname' => 'student']]],
            ['id' => 22, 'fullname' => 'Mahasiswa Aktif', 'roles' => [['shortname' => 'student']]],
        ]);
        $service->shouldReceive('getActivityCompletionStatus')->once()->with(7, 21)
            ->andThrow(new \RuntimeException('Tidak diizinkan melihat completion mahasiswa lain.'));

        $this->app->instance(MoodleService::class, $service);

        $response = $this->withSession([
            'logged_in' => true,
            'moodle_token' => 'student-token-2',
            'moodle_user' => ['id' => 22, 'name' => 'Mahasiswa Aktif', 'moodle_username' => 'student-2'],
        ])->get(route('courses.achievements', ['courseId' => 7]));

        $response
            ->assertOk()
            ->assertSeeInOrder(['Mahasiswa Seratus Poin', '100'])
            ->assertSee('Anda');

        $this->assertDatabaseCount('gamification_activity_completions', 12);
    }

    protected function withStudentSession(): static
    {
        return $this->withSession([
            'logged_in' => true,
            'moodle_token' => 'student-token',
            'moodle_user' => ['id' => 21, 'name' => 'Mahasiswa Aktif', 'moodle_username' => 'student'],
        ]);
    }

    protected function courseContents(int $total = 4): array
    {
        $modules = [];
        foreach (range(1, $total) as $moduleId) {
            $modules[] = [
                'id' => $moduleId,
                'modname' => $moduleId % 3 === 1 ? 'resource' : ($moduleId % 3 === 2 ? 'assign' : 'quiz'),
                'name' => 'Aktivitas '.$moduleId,
            ];
        }

        return [[
            'name' => 'Topik 1',
            'modules' => $modules,
        ]];
    }

    protected function completionStatuses(int $completed, int $total = 4): array
    {
        $statuses = [];
        for ($index = 1; $index <= $total; $index++) {
            $statuses[] = [
                'cmid' => $index,
                'state' => $index <= $completed ? 1 : 0,
                'timecompleted' => $index <= $completed ? 1778547600 + ($index * 60) : 0,
            ];
        }

        return $statuses;
    }

    protected function students(): array
    {
        return [
            ['id' => 21, 'fullname' => 'Mahasiswa Aktif', 'roles' => [['shortname' => 'student']]],
            ['id' => 22, 'fullname' => 'Mahasiswa 2', 'roles' => [['shortname' => 'student']]],
            ['id' => 23, 'fullname' => 'Mahasiswa 3', 'roles' => [['shortname' => 'student']]],
        ];
    }
}
