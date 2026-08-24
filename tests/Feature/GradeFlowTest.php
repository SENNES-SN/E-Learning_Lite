<?php

namespace Tests\Feature;

use App\Services\MoodleService;
use Carbon\Carbon;
use Mockery;
use Tests\TestCase;

class GradeFlowTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_student_can_switch_between_assignment_and_quiz_grades(): void
    {
        $assignmentDate = Carbon::create(2026, 1, 1, 8, 0, 0, 'Asia/Jakarta')->timestamp;
        $quizDate = Carbon::create(2026, 2, 1, 8, 0, 0, 'Asia/Jakarta')->timestamp;
        $service = Mockery::mock(MoodleService::class);
        $service->shouldReceive('getCourse')->once()->with(7)->andReturn([
            'id' => 7,
            'fullname' => 'Pengantar UI/UX Design',
        ]);
        $service->shouldReceive('getUserGrades')->once()->with(7, 21)->andReturn([
            'usergrades' => [[
                'userid' => 21,
                'gradeitems' => [
                    [
                        'id' => 1,
                        'itemtype' => 'mod',
                        'itemmodule' => 'assign',
                        'itemname' => 'Tugas 1',
                        'graderaw' => 80,
                        'grademax' => 100,
                        'gradedatesubmitted' => $assignmentDate,
                    ],
                    [
                        'id' => 2,
                        'itemtype' => 'mod',
                        'itemmodule' => 'assign',
                        'itemname' => 'Tugas 2',
                        'graderaw' => null,
                        'grademax' => 100,
                    ],
                    [
                        'id' => 3,
                        'itemtype' => 'mod',
                        'itemmodule' => 'quiz',
                        'itemname' => 'Quiz 1',
                        'gradeformatted' => '90.00',
                        'grademax' => 100,
                        'gradedatesubmitted' => $quizDate,
                    ],
                    [
                        'id' => 4,
                        'itemtype' => 'course',
                        'itemname' => 'Total mata kuliah',
                        'graderaw' => 85,
                    ],
                ],
            ]],
        ]);

        $this->app->instance(MoodleService::class, $service);

        $response = $this->withStudentSession()->get(route('grades', [
            'courseid' => 7,
            'tab' => 'quiz',
        ]));

        $response
            ->assertOk()
            ->assertSessionHas('active_course_id', 7)
            ->assertSee('Pengantar UI/UX Design')
            ->assertSee('Detail Nilai')
            ->assertSee('Tugas 1')
            ->assertSee('Tugas 2')
            ->assertSee('Quiz 1')
            ->assertSee('01/01/2026')
            ->assertSee('01/02/2026')
            ->assertSee('Dinilai')
            ->assertSee('Belum Dinilai')
            ->assertSee('data-grade-tab="quizzes"', false)
            ->assertDontSee('Total mata kuliah');
    }

    protected function withStudentSession(): static
    {
        return $this->withSession([
            'logged_in' => true,
            'moodle_token' => 'student-token',
            'moodle_user' => [
                'id' => 21,
                'name' => 'Mahasiswa Aktif',
                'moodle_username' => 'student',
            ],
        ]);
    }
}
