<?php

namespace Tests\Unit;

use App\Services\GamificationService;
use PHPUnit\Framework\TestCase;

class GamificationServiceTest extends TestCase
{
    public function test_material_completion_awards_points_and_a_new_badge_once(): void
    {
        $service = new GamificationService;
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

        $feedback = $service->materialCompletionFeedback($before, $after, 11);

        $this->assertSame(10, $feedback['points_awarded']);
        $this->assertSame(10, $feedback['total_points']);
        $this->assertSame(33, $feedback['progress']['percent']);
        $this->assertSame('Knowledge Seeker', $feedback['badge']['name']);
    }

    public function test_completed_material_does_not_award_duplicate_points(): void
    {
        $service = new GamificationService;
        $statuses = [
            ['cmid' => 11, 'state' => 1],
            ['cmid' => 12, 'state' => 0],
        ];

        $feedback = $service->materialCompletionFeedback($statuses, $statuses, 11);

        $this->assertSame(0, $feedback['points_awarded']);
        $this->assertNull($feedback['badge']);
    }

    public function test_achievement_summary_calculates_points_progress_badges_and_rank_badge(): void
    {
        $service = new GamificationService;
        $statuses = [
            ['cmid' => 1, 'state' => 1, 'timecompleted' => 100],
            ['cmid' => 2, 'state' => 1, 'timecompleted' => 200],
            ['cmid' => 3, 'state' => 0],
            ['cmid' => 4, 'state' => 0],
        ];

        $summary = $service->achievementSummary($statuses, 1);
        $badges = collect($summary['badges'])->keyBy('slug');

        $this->assertSame(20, $summary['points']);
        $this->assertSame(50, $summary['progress']['percent']);
        $this->assertTrue($badges['knowledge']['earned']);
        $this->assertTrue($badges['goal']['earned']);
        $this->assertFalse($badges['perfection']['earned']);
        $this->assertTrue($badges['pointbreaker']['earned']);
        $this->assertFalse($badges['runner-up']['earned']);
        $this->assertFalse($badges['third-place']['earned']);
        $this->assertSame(200, $badges['goal']['earned_at']);
        $this->assertSame(200, $badges['pointbreaker']['earned_at']);
    }

    public function test_second_place_badge_uses_latest_completion_date(): void
    {
        $service = new GamificationService;
        $summary = $service->achievementSummary([
            ['cmid' => 1, 'state' => 1, 'timecompleted' => 100],
            ['cmid' => 2, 'state' => 1, 'timecompleted' => 200],
            ['cmid' => 3, 'state' => 0, 'timecompleted' => 0],
        ], 2);
        $badges = collect($summary['badges'])->keyBy('slug');

        $this->assertTrue($badges['runner-up']['earned']);
        $this->assertSame(200, $badges['runner-up']['earned_at']);
        $this->assertFalse($badges['pointbreaker']['earned']);
        $this->assertFalse($badges['third-place']['earned']);
    }
}
