<?php

namespace Tests\Feature;

use App\Services\MoodleService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class MaterialCompletionFlowTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_material_module_uses_the_student_facing_reader_design(): void
    {
        $service = Mockery::mock(MoodleService::class);
        $service->shouldReceive('getCourse')
            ->once()
            ->with(7)
            ->andReturn(['id' => 7, 'fullname' => 'Pengantar UI/UX Design']);
        $service->shouldReceive('getCourseContents')
            ->once()
            ->with(7)
            ->andReturn([
                [
                    'name' => '08 Juni - 13 Juni',
                    'modules' => [[
                        'id' => 11,
                        'instance' => 71,
                        'modname' => 'resource',
                        'name' => 'Pengenalan UI/UX Design',
                        'contents' => [[
                            'filename' => 'Pengenalan_UIUX_Design.pdf',
                            'fileurl' => 'https://elearning.example.test/pluginfile.php/11/material.pdf',
                            'filesize' => 2569011,
                        ]],
                    ]],
                ],
            ]);
        $service->shouldReceive('getResources')
            ->once()
            ->with(7)
            ->andReturn(['resources' => [[
                'id' => 71,
                'coursemodule' => 11,
                'intro' => '<p>Konsep dasar antarmuka dan pengalaman pengguna.</p><script>alert("xss")</script>',
            ]]]);
        $service->shouldReceive('getActivityCompletionStatus')
            ->once()
            ->with(7, 21)
            ->andReturn(['statuses' => [['cmid' => 11, 'state' => 0]]]);

        $this->app->instance(MoodleService::class, $service);

        $response = $this
            ->withSession([
                'logged_in' => true,
                'moodle_token' => 'student-token',
                'moodle_user' => ['id' => 21, 'name' => 'Mahasiswa'],
            ])
            ->get(route('courses.modules.show', ['courseId' => 7, 'moduleId' => 11]));

        $response
            ->assertOk()
            ->assertSee('Pengenalan UI/UX Design')
            ->assertSee('Belum Diselesaikan')
            ->assertSee('Konsep dasar antarmuka dan pengalaman pengguna.')
            ->assertDontSee('alert(&quot;xss&quot;)', false)
            ->assertSee('Pengenalan_UIUX_Design.pdf')
            ->assertSee('Baca Materi')
            ->assertSee(route('moodle.file.download'), false)
            ->assertSee(route('courses.modules.material.complete', ['courseId' => 7, 'moduleId' => 11]), false)
            ->assertSee('data-loading-button data-loading-tone="dark"', false)
            ->assertDontSee('material-reader-button-loader', false)
            ->assertDontSee("completeForm?.addEventListener('submit'", false)
            ->assertDontSee('Memperbarui status')
            ->assertDontSee('Buka di Moodle');
    }

    public function test_material_attachment_is_returned_as_a_download(): void
    {
        config(['moodle.base_url' => 'https://elearning.example.test']);
        Http::fake([
            'https://elearning.example.test/*' => Http::response('%PDF-file-content', 200, [
                'Content-Type' => 'application/pdf',
            ]),
        ]);

        $response = $this
            ->withSession(['logged_in' => true])
            ->get(route('moodle.file.download', [
                'url' => 'https://elearning.example.test/pluginfile.php/11/material.pdf?token=student-token',
                'filename' => 'Pengenalan_UIUX_Design.pdf',
            ]));

        $response
            ->assertOk()
            ->assertDownload('Pengenalan_UIUX_Design.pdf')
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertSame('%PDF-file-content', $response->streamedContent());
    }

    public function test_student_can_complete_material_and_receive_feedback(): void
    {
        $before = [
            ['cmid' => 11, 'state' => 0],
            ['cmid' => 12, 'state' => 0],
            ['cmid' => 13, 'state' => 0],
        ];
        $after = [
            ['cmid' => 11, 'state' => 1],
            ['cmid' => 12, 'state' => 0],
            ['cmid' => 13, 'state' => 0],
        ];

        $service = Mockery::mock(MoodleService::class);
        $service->shouldReceive('getCourseContents')
            ->once()
            ->with(7)
            ->andReturn([
                ['modules' => [['id' => 11, 'modname' => 'resource', 'name' => 'Materi 1']]],
            ]);
        $service->shouldReceive('getActivityCompletionStatus')
            ->twice()
            ->with(7, 21)
            ->andReturn(['statuses' => $before], ['statuses' => $after]);
        $service->shouldReceive('markActivityComplete')
            ->once()
            ->with(11)
            ->andReturn(['status' => true]);

        $this->app->instance(MoodleService::class, $service);

        $response = $this
            ->withSession([
                'logged_in' => true,
                'moodle_user' => ['id' => 21, 'name' => 'Mahasiswa'],
            ])
            ->post(route('courses.modules.material.complete', ['courseId' => 7, 'moduleId' => 11]));

        $response
            ->assertRedirect(route('courses.modules.show', ['courseId' => 7, 'moduleId' => 11]))
            ->assertSessionHas('material_completion_feedback.points_awarded', 10)
            ->assertSessionHas('material_completion_feedback.badge.name', 'Knowledge Seeker');
    }

    public function test_resource_without_moodle_completion_is_completed_locally(): void
    {
        Cache::forget('local_activity_completions:v1:31:9');

        $service = Mockery::mock(MoodleService::class);
        $service->shouldReceive('getCourseContents')
            ->once()
            ->with(9)
            ->andReturn([
                [
                    'modules' => [
                        ['id' => 41, 'modname' => 'resource', 'name' => 'Materi 1'],
                        ['id' => 42, 'modname' => 'assign', 'name' => 'Tugas 1'],
                        ['id' => 43, 'modname' => 'quiz', 'name' => 'Kuis 1'],
                        ['id' => 44, 'modname' => 'resource', 'name' => 'Materi 2'],
                        ['id' => 45, 'modname' => 'resource', 'name' => 'Materi 3'],
                    ],
                ],
            ]);
        $service->shouldReceive('getActivityCompletionStatus')
            ->once()
            ->with(9, 31)
            ->andReturn(['statuses' => []]);
        $service->shouldNotReceive('markActivityComplete');

        $this->app->instance(MoodleService::class, $service);

        $response = $this
            ->withSession([
                'logged_in' => true,
                'moodle_user' => ['id' => 31, 'name' => 'Mahasiswa'],
            ])
            ->post(route('courses.modules.material.complete', ['courseId' => 9, 'moduleId' => 41]));

        $response
            ->assertRedirect(route('courses.modules.show', ['courseId' => 9, 'moduleId' => 41]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('material_completion_feedback.points_awarded', 10)
            ->assertSessionHas('material_completion_feedback.badge.name', 'Knowledge Seeker');

        $savedCompletions = Cache::get('local_activity_completions:v1:31:9', []);
        $this->assertArrayHasKey(41, $savedCompletions);
        $this->assertIsInt($savedCompletions[41]);
    }

    public function test_failed_moodle_completion_update_uses_local_completion_without_an_error(): void
    {
        Cache::forget('local_activity_completions:v1:32:10');

        $statuses = [
            ['cmid' => 51, 'state' => 0],
            ['cmid' => 52, 'state' => 0],
            ['cmid' => 53, 'state' => 0],
            ['cmid' => 54, 'state' => 0],
            ['cmid' => 55, 'state' => 0],
        ];

        $service = Mockery::mock(MoodleService::class);
        $service->shouldReceive('getCourseContents')
            ->once()
            ->with(10)
            ->andReturn([
                ['modules' => [['id' => 51, 'modname' => 'resource', 'name' => 'Materi 1']]],
            ]);
        $service->shouldReceive('getActivityCompletionStatus')
            ->once()
            ->with(10, 32)
            ->andReturn(['statuses' => $statuses]);
        $service->shouldReceive('markActivityComplete')
            ->once()
            ->with(51)
            ->andThrow(new \RuntimeException('Activity completion is not enabled.'));

        $this->app->instance(MoodleService::class, $service);

        $response = $this
            ->withSession([
                'logged_in' => true,
                'moodle_user' => ['id' => 32, 'name' => 'Mahasiswa'],
            ])
            ->post(route('courses.modules.material.complete', ['courseId' => 10, 'moduleId' => 51]));

        $response
            ->assertRedirect(route('courses.modules.show', ['courseId' => 10, 'moduleId' => 51]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('material_completion_feedback.points_awarded', 10)
            ->assertSessionHas('material_completion_feedback.badge.name', 'Knowledge Seeker');

        $this->assertArrayHasKey(51, Cache::get('local_activity_completions:v1:32:10', []));
    }
}
