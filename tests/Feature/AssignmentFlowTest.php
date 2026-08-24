<?php

namespace Tests\Feature;

use App\Services\MoodleService;
use Mockery;
use Tests\TestCase;

class AssignmentFlowTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_assignment_module_uses_the_student_facing_design(): void
    {
        $assignment = $this->assignmentData(time() + 86400);
        $service = Mockery::mock(MoodleService::class);
        $service->shouldReceive('getCourse')->once()->with(7)->andReturn(['id' => 7, 'fullname' => 'Pengantar UI/UX Design']);
        $service->shouldReceive('getCourseContents')->once()->with(7)->andReturn($this->courseContents());
        $service->shouldReceive('getActivityCompletionStatus')->once()->with(7, 21)->andReturn([
            'statuses' => [['cmid' => 11, 'state' => 0]],
        ]);
        $service->shouldReceive('getAssignments')->once()->with(7)->andReturn([
            'courses' => [['assignments' => [$assignment]]],
        ]);

        $service->shouldReceive('getAssignmentSubmissionStatus')
            ->twice()
            ->with(55)
            ->andReturn($this->draftStatus());

        $service->shouldReceive('getUserGrades')->once()->with(7, 21)->andReturn([]);

        $this->app->instance(MoodleService::class, $service);

        $response = $this->withStudentSession()->get(route('courses.modules.show', [
            'courseId' => 7,
            'moduleId' => 11,
        ]));

        $response
            ->assertOk()
            ->assertSee('Tugas 1')
            ->assertSee('Belum Dikumpulkan')
            ->assertSee('Deskripsi Tugas')
            ->assertSee('Panduan_Tugas1.pdf')
            ->assertSee('Kerjakan Tugas')
            ->assertDontSee('Buka di Moodle');
    }

    public function test_expired_assignment_cannot_be_saved_as_draft(): void
    {
        $service = Mockery::mock(MoodleService::class);
        $service->shouldReceive('getCourseContents')->once()->with(7)->andReturn($this->courseContents());
        $service->shouldReceive('getAssignments')->once()->with(7)->andReturn([
            'courses' => [['assignments' => [$this->assignmentData(time() - 60)]]],
        ]);
        $service->shouldReceive('getAssignmentSubmissionStatus')->once()->with(55)->andReturn($this->draftStatus());
        $service->shouldNotReceive('saveAssignmentSubmission');

        $this->app->instance(MoodleService::class, $service);

        $response = $this->withStudentSession()->post(route('courses.modules.assignment.submit', [
            'courseId' => 7,
            'moduleId' => 11,
            'mode' => 'work',
        ]), ['answer' => 'Jawaban tugas']);

        $response
            ->assertSessionHasErrors('answer')
            ->assertSessionHas('assignment_deadline_expired', true);
    }

    public function test_final_submission_awards_points_and_badge_from_completion_progress(): void
    {
        $before = [
            ['cmid' => 10, 'state' => 1],
            ['cmid' => 11, 'state' => 0],
            ['cmid' => 12, 'state' => 0],
        ];
        $after = [
            ['cmid' => 10, 'state' => 1],
            ['cmid' => 11, 'state' => 1],
            ['cmid' => 12, 'state' => 0],
        ];

        $service = Mockery::mock(MoodleService::class);
        $service->shouldReceive('getCourseContents')->once()->with(7)->andReturn($this->courseContents());
        $service->shouldReceive('getAssignments')->once()->with(7)->andReturn([
            'courses' => [['assignments' => [$this->assignmentData(time() + 86400)]]],
        ]);
        $service->shouldReceive('getAssignmentSubmissionStatus')
            ->twice()
            ->with(55)
            ->andReturn($this->draftStatus(), $this->submittedStatus());
        $service->shouldReceive('getActivityCompletionStatus')
            ->twice()
            ->with(7, 21)
            ->andReturn(['statuses' => $before], ['statuses' => $after]);
        $service->shouldReceive('submitAssignmentForGrading')->once()->with(55, false)->andReturn([]);
        $service->shouldNotReceive('markActivityComplete');

        $this->app->instance(MoodleService::class, $service);

        $response = $this->withStudentSession()->post(route('courses.modules.assignment.final-submit', [
            'courseId' => 7,
            'moduleId' => 11,
            'mode' => 'confirm',
        ]));

        $response
            ->assertRedirect(route('courses.modules.show', ['courseId' => 7, 'moduleId' => 11]) . '?mode=confirm')
            ->assertSessionHas('assignment_completion_feedback.points_awarded', 10)
            ->assertSessionHas('assignment_completion_feedback.badge.name', 'Goal Getter');
    }

    protected function withStudentSession(): static
    {
        return $this->withSession([
            'logged_in' => true,
            'moodle_token' => 'student-token',
            'moodle_user' => ['id' => 21, 'name' => 'Mahasiswa', 'moodle_username' => 'student'],
        ]);
    }

    protected function courseContents(): array
    {
        return [[
            'name' => '5 Juni - 11 Juni',
            'modules' => [[
                'id' => 11,
                'instance' => 55,
                'modname' => 'assign',
                'name' => 'Tugas 1',
                'contents' => [[
                    'filename' => 'Panduan_Tugas1.pdf',
                    'fileurl' => 'https://elearning.example.test/pluginfile.php/11/panduan.pdf',
                    'filesize' => 2569011,
                ]],
            ]],
        ]];
    }

    protected function assignmentData(int $deadline): array
    {
        return [
            'id' => 55,
            'cmid' => 11,
            'name' => 'Tugas 1',
            'intro' => 'Kerjakan tugas sesuai instruksi yang diberikan.',
            'allowsubmissionsfromdate' => time() - 3600,
            'duedate' => $deadline,
            'cutoffdate' => $deadline,
            'configs' => [
                ['plugin' => 'file', 'name' => 'enabled', 'value' => '1'],
                ['plugin' => 'file', 'name' => 'maxfilesubmissions', 'value' => '1'],
                ['plugin' => 'file', 'name' => 'maxsubmissionsizebytes', 'value' => (string) (10 * 1024 * 1024)],
                ['plugin' => 'file', 'name' => 'filetypeslist', 'value' => '.pdf,.docx'],
                ['plugin' => 'onlinetext', 'name' => 'enabled', 'value' => '1'],
            ],
        ];
    }

    protected function draftStatus(): array
    {
        return [
            'lastattempt' => [
                'canedit' => true,
                'submission' => [
                    'status' => 'draft',
                    'plugins' => [[
                        'type' => 'file',
                        'fileareas' => [[
                            'files' => [[
                                'filename' => 'Jawaban_Tugas1.pdf',
                                'filesize' => 2450000,
                                'fileurl' => 'https://elearning.example.test/pluginfile.php/21/jawaban.pdf',
                            ]],
                        ]],
                    ]],
                ],
            ],
        ];
    }

    protected function submittedStatus(): array
    {
        $status = $this->draftStatus();
        $status['lastattempt']['canedit'] = false;
        $status['lastattempt']['submission']['status'] = 'submitted';

        return $status;
    }

    protected function studentUsers(): array
    {
        return [[
            'id' => 21,
            'username' => 'student',
            'roles' => [['shortname' => 'student']],
        ]];
    }
}
