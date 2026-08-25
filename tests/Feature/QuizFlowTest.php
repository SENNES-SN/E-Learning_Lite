<?php

namespace Tests\Feature;

use App\Services\MoodleService;
use Mockery;
use Tests\TestCase;

class QuizFlowTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_quiz_module_uses_the_student_facing_design(): void
    {
        $service = Mockery::mock(MoodleService::class);
        $service->shouldReceive('getCourse')->once()->with(7)->andReturn(['id' => 7, 'fullname' => 'Pengantar UI/UX Design']);
        $service->shouldReceive('getCourseContents')->once()->with(7)->andReturn($this->courseContents());
        $service->shouldReceive('getActivityCompletionStatus')->once()->with(7, 21)->andReturn([
            'statuses' => [['cmid' => 11, 'state' => 0]],
        ]);
        $service->shouldReceive('getEnrolledUsers')->once()->with(7)->andReturn($this->studentUsers());
        $service->shouldReceive('getQuizzes')->once()->with(7)->andReturn(['quizzes' => [$this->quizData(time() + 86400)]]);
        $service->shouldReceive('getQuizUserAttempts')->once()->with(55, 21)->andReturn(['attempts' => []]);

        $this->app->instance(MoodleService::class, $service);

        $response = $this->withStudentSession()->get(route('courses.modules.show', [
            'courseId' => 7,
            'moduleId' => 11,
        ]));

        $response
            ->assertOk()
            ->assertDontSee('quiz-access-icon', false)
            ->assertSee('Quiz 1')
            ->assertSee('Belum Dikerjakan')
            ->assertSee('Jumlah Soal')
            ->assertSee('Kerjakan Kuis')
            ->assertDontSee('Buka di Moodle');
    }

    public function test_expired_quiz_cannot_be_started(): void
    {
        $deadline = time() - 60;
        $service = Mockery::mock(MoodleService::class);
        $service->shouldReceive('getCourseContents')->once()->with(7)->andReturn($this->courseContents());
        $service->shouldReceive('getQuizzes')->once()->with(7)->andReturn(['quizzes' => [$this->quizData($deadline)]]);
        $service->shouldNotReceive('startQuizAttempt');

        $this->app->instance(MoodleService::class, $service);

        $response = $this->withStudentSession()->post(route('courses.modules.quiz.start', [
            'courseId' => 7,
            'moduleId' => 11,
        ]));

        $response
            ->assertSessionHasErrors('quiz')
            ->assertSessionHas('quiz_access_expired', true)
            ->assertSessionHas('quiz_expired_at', $deadline);
    }

    public function test_quiz_attempt_displays_questions_and_navigation(): void
    {
        $service = Mockery::mock(MoodleService::class);
        $service->shouldReceive('getCourseContents')->once()->with(7)->andReturn($this->courseContents());
        $service->shouldReceive('getQuizzes')->once()->with(7)->andReturn(['quizzes' => [$this->quizData(time() + 86400)]]);
        $service->shouldReceive('getQuizAttemptData')->once()->with(71, 0)->andReturn($this->attemptData());
        $service->shouldReceive('getQuizAttemptSummary')->once()->with(71)->andReturn($this->attemptSummary());

        $this->app->instance(MoodleService::class, $service);

        $response = $this->withStudentSession()->get(route('courses.modules.quiz.attempt', [
            'courseId' => 7,
            'moduleId' => 11,
            'attemptId' => 71,
        ]));

        $response
            ->assertOk()
            ->assertSee('Waktu Tersisa')
            ->assertSee('Pertanyaan kuis pertama')
            ->assertSee('Daftar Soal')
            ->assertDontSee('data-question-answered="1"', false)
            ->assertDontSee('quiz-navigation-legend', false)
            ->assertDontSee('quiz-page-actions is-last-question', false)
            ->assertSee('Selesai');

        $this->assertSame(1, substr_count($response->getContent(), '>Selesai</button>'));
    }

    public function test_finishing_quiz_updates_progress_and_awards_points_and_badge(): void
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
        $service->shouldReceive('getQuizzes')->once()->with(7)->andReturn(['quizzes' => [$this->quizData(time() + 86400)]]);
        $service->shouldReceive('getQuizAttemptData')->once()->with(71, 0)->andReturn($this->attemptData());
        $service->shouldReceive('getActivityCompletionStatus')->twice()->with(7, 21)->andReturn(
            ['statuses' => $before],
            ['statuses' => $after],
        );
        $service->shouldReceive('processQuizAttempt')->once()->with(71, [
            ['name' => 'q71:1_answer', 'value' => '0'],
        ], true)->andReturn(['state' => 'finished']);
        $service->shouldReceive('getQuizAttemptReview')->once()->with(71)->andReturn(['grade' => 80]);
        $service->shouldNotReceive('markActivityComplete');

        $this->app->instance(MoodleService::class, $service);

        $response = $this->withStudentSession()->post(route('courses.modules.quiz.submit', [
            'courseId' => 7,
            'moduleId' => 11,
            'attemptId' => 71,
        ]), [
            'quiz_payload' => json_encode([
                ['name' => 'q71:1_answer', 'value' => '0'],
            ], JSON_THROW_ON_ERROR),
            'finishattempt' => 1,
            'page' => 0,
        ]);

        $response
            ->assertRedirect(route('courses.modules.show', ['courseId' => 7, 'moduleId' => 11]))
            ->assertSessionHas('quiz_completion_feedback.points_awarded', 10)
            ->assertSessionHas('quiz_completion_feedback.score', 80.0)
            ->assertSessionHas('quiz_completion_feedback.badge.name', 'Goal Getter');
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
                'modname' => 'quiz',
                'name' => 'Quiz 1',
            ]],
        ]];
    }

    protected function quizData(int $deadline): array
    {
        return [
            'id' => 55,
            'coursemodule' => 11,
            'name' => 'Quiz 1',
            'intro' => '<p>Kerjakan kuis berikut untuk menguji pemahaman kamu.</p>',
            'timeopen' => time() - 3600,
            'timeclose' => $deadline,
            'timelimit' => 900,
            'grade' => 100,
            'sumgrades' => 10,
            'questioncount' => 10,
        ];
    }

    protected function attemptData(): array
    {
        return [
            'attempt' => [
                'id' => 71,
                'state' => 'inprogress',
                'timestart' => time() - 60,
            ],
            'questions' => [[
                'slot' => 1,
                'number' => 1,
                'page' => 0,
                'state' => 'todo',
                'html' => '<div class="que"><div class="qtext">Pertanyaan kuis pertama</div><div class="answer"><div><input type="radio" name="q71:1_answer" value="0"> Jawaban A</div></div></div>',
            ]],
        ];
    }

    protected function attemptSummary(): array
    {
        return [
            'questions' => [
                ['slot' => 1, 'number' => 1, 'page' => 0, 'state' => 'complete', 'status' => 'Answer saved'],
                ['slot' => 2, 'number' => 2, 'page' => 1, 'state' => 'todo'],
            ],
        ];
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
