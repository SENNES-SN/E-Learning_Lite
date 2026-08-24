<?php

namespace Tests\Feature;

use App\Services\MoodleService;
use Mockery;
use Tests\TestCase;

class AchievementFlowTest extends TestCase
{
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

    protected function withStudentSession(): static
    {
        return $this->withSession([
            'logged_in' => true,
            'moodle_token' => 'student-token',
            'moodle_user' => ['id' => 21, 'name' => 'Mahasiswa Aktif', 'moodle_username' => 'student'],
        ]);
    }

    protected function courseContents(): array
    {
        return [[
            'name' => 'Topik 1',
            'modules' => [
                ['id' => 1, 'modname' => 'resource', 'name' => 'Materi 1'],
                ['id' => 2, 'modname' => 'assign', 'name' => 'Tugas 1'],
                ['id' => 3, 'modname' => 'quiz', 'name' => 'Kuis 1'],
                ['id' => 4, 'modname' => 'resource', 'name' => 'Materi 2'],
            ],
        ]];
    }

    protected function completionStatuses(int $completed): array
    {
        $statuses = [];
        for ($index = 1; $index <= 4; $index++) {
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
