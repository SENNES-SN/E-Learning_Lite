<?php

namespace Tests\Unit;

use App\Http\Controllers\LoginController;
use App\Services\GamificationService;
use App\Services\MoodleService;
use PHPUnit\Framework\TestCase;

class DashboardDeadlineFilterTest extends TestCase
{
    public function test_completed_activities_are_removed_from_dashboard_deadlines(): void
    {
        $controller = new class($this->createMock(MoodleService::class), new GamificationService) extends LoginController
        {
            public function pendingDeadlineEvents(array $events, array $courses): array
            {
                return $this->withoutCompletedDeadlineEvents($events, $courses);
            }
        };

        $courses = [[
            'id' => 7,
            'progress' => [
                'statuses' => [
                    ['cmid' => 11, 'state' => 1],
                    ['cmid' => 12, 'state' => 0],
                ],
            ],
        ]];
        $events = [
            ['id' => 1, 'courseid' => 7, 'cmid' => 11, 'eventtype' => 'deadline'],
            ['id' => 2, 'courseid' => 7, 'cmid' => 12, 'eventtype' => 'quiz'],
            ['id' => 3, 'courseid' => 7, 'eventtype' => 'deadline'],
            ['id' => 4, 'courseid' => 7, 'cmid' => 11, 'eventtype' => 'materi'],
        ];

        $filtered = $controller->pendingDeadlineEvents($events, $courses);

        $this->assertSame([2, 3, 4], array_column($filtered, 'id'));
    }
}
