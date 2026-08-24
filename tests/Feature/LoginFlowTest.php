<?php

namespace Tests\Feature;

use App\Services\MoodleService;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class LoginFlowTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_login_page_uses_the_student_facing_design(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('E-Learning Lite')
            ->assertSee('Masuk untuk melanjutkan pembelajaran')
            ->assertSee('Masukkan Username')
            ->assertSee('Masukkan Password')
            ->assertSee('class="login-submit" type="submit" data-loading-button', false);
    }

    public function test_invalid_credentials_show_a_friendly_message(): void
    {
        $service = Mockery::mock(MoodleService::class);
        $service->shouldReceive('fetchToken')
            ->once()
            ->andThrow(new RuntimeException('Invalid login, please try again'));

        $this->app->instance(MoodleService::class, $service);

        $this->from('/login')
            ->post('/login', [
                'username' => 'student',
                'password' => 'wrong-password',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors([
                'username' => 'Username atau password salah.',
            ]);
    }

    public function test_service_failure_shows_a_friendly_message(): void
    {
        $service = Mockery::mock(MoodleService::class);
        $service->shouldReceive('fetchToken')
            ->once()
            ->andThrow(new RuntimeException('Connection timed out'));

        $this->app->instance(MoodleService::class, $service);

        $this->from('/login')
            ->post('/login', [
                'username' => 'student',
                'password' => 'secret',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors([
                'username' => 'Layanan pembelajaran sedang mengalami gangguan. Silakan coba lagi.',
            ]);
    }

    public function test_valid_credentials_redirect_to_the_dashboard(): void
    {
        $service = Mockery::mock(MoodleService::class);
        $service->shouldReceive('fetchToken')
            ->once()
            ->andReturn(['token' => 'student-token']);
        $service->shouldReceive('getUserByUsername')
            ->once()
            ->with('student')
            ->andReturn([
                'id' => 21,
                'fullname' => 'Mahasiswa Uji',
                'email' => 'student@example.test',
            ]);

        $this->app->instance(MoodleService::class, $service);

        $this->post('/login', [
            'username' => 'student',
            'password' => 'secret',
        ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('logged_in', true)
            ->assertSessionHas('username', 'student')
            ->assertSessionHas('moodle_user.name', 'Mahasiswa Uji');
    }
}
