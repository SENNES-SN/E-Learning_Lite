<?php

namespace Tests\Feature;

use App\Services\MoodleService;
use Mockery;
use Tests\TestCase;

class MoodleApiTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_fetches_a_moodle_token(): void
    {
        $service = Mockery::mock(MoodleService::class);
        $service->shouldReceive('fetchToken')
            ->once()
            ->with('demo', 'secret', 'mobile_app')
            ->andReturn(['token' => 'abc123']);

        $this->app->instance(MoodleService::class, $service);

        $response = $this->postJson('/api/moodle/token', [
            'username' => 'demo',
            'password' => 'secret',
            'service' => 'mobile_app',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'token' => 'abc123',
                ],
            ]);
    }

    public function test_it_returns_user_courses(): void
    {
        $service = Mockery::mock(MoodleService::class);
        $service->shouldReceive('getUserCourses')
            ->once()
            ->with(12)
            ->andReturn([
                ['id' => 5, 'fullname' => 'Laravel Dasar'],
            ]);

        $this->app->instance(MoodleService::class, $service);

        $response = $this->getJson('/api/moodle/users/12/courses');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    ['id' => 5, 'fullname' => 'Laravel Dasar'],
                ],
            ]);
    }

}
