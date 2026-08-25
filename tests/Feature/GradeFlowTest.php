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
                        'iteminstance' => 31,
                        'itemname' => 'Tugas 1',
                        'graderaw' => 80,
                        'grademax' => 100,
                    ],
                    [
                        'id' => 2,
                        'itemtype' => 'mod',
                        'itemmodule' => 'assign',
                        'iteminstance' => 32,
                        'itemname' => 'Tugas Belum Dikumpulkan',
                        'graderaw' => null,
                        'grademax' => 100,
                        'gradedatesubmitted' => $assignmentDate + 1800,
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
                        'itemtype' => 'mod',
                        'itemmodule' => 'assign',
                        'iteminstance' => 33,
                        'itemname' => 'Tugas Menunggu Penilaian',
                        'graderaw' => null,
                        'grademax' => 100,
                        'gradedatesubmitted' => $assignmentDate + 3600,
                    ],
                    [
                        'id' => 5,
                        'itemtype' => 'mod',
                        'itemmodule' => 'quiz',
                        'itemname' => 'Quiz Menunggu Penilaian',
                        'graderaw' => null,
                        'grademax' => 100,
                        'gradedatesubmitted' => $quizDate + 3600,
                    ],
                    [
                        'id' => 6,
                        'itemtype' => 'mod',
                        'itemmodule' => 'quiz',
                        'itemname' => 'Quiz Belum Dikerjakan',
                        'graderaw' => null,
                        'grademax' => 100,
                    ],
                    [
                        'id' => 7,
                        'itemtype' => 'course',
                        'itemname' => 'Total mata kuliah',
                        'graderaw' => 85,
                    ],
                ],
            ]],
        ]);
        $service->shouldReceive('getAssignmentSubmissionStatus')->once()->with(31)->andReturn([
            'lastattempt' => [
                'canedit' => false,
                'submission' => [
                    'status' => 'submitted',
                    'timemodified' => $assignmentDate,
                ],
            ],
        ]);
        $service->shouldReceive('getAssignmentSubmissionStatus')->once()->with(32)->andReturn([
            'lastattempt' => [
                'canedit' => true,
                'submission' => [
                    'status' => 'draft',
                    'timemodified' => $assignmentDate + 1800,
                ],
            ],
        ]);
        $service->shouldReceive('getAssignmentSubmissionStatus')->once()->with(33)->andReturn([
            'lastattempt' => [
                'canedit' => false,
                'submission' => [
                    'status' => 'submitted',
                    'timemodified' => $assignmentDate + 3600,
                ],
            ],
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
            ->assertSee('Tugas Menunggu Penilaian')
            ->assertSee('Quiz 1')
            ->assertSee('Quiz Menunggu Penilaian')
            ->assertSee('01/01/2026')
            ->assertSee('01/02/2026')
            ->assertSee('Sudah Dinilai')
            ->assertSee('Belum Dinilai')
            ->assertSee('data-grade-tab="quizzes"', false)
            ->assertDontSee('Tugas Belum Dikumpulkan')
            ->assertDontSee('Quiz Belum Dikerjakan')
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
