<?php

namespace App\Services;

class GamificationService
{
    public const POINTS_PER_ACTIVITY = 10;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function badgeCatalog(): array
    {
        return [
            [
                'threshold' => 20,
                'slug' => 'knowledge',
                'name' => 'Knowledge Seeker',
                'description' => 'Capai 20% progres pembelajaran untuk mendapatkan badge ini!',
            ],
            [
                'threshold' => 50,
                'slug' => 'goal',
                'name' => 'Goal Getter',
                'description' => 'Capai 50% progres pembelajaran untuk mendapatkan badge ini!',
            ],
            [
                'threshold' => 100,
                'slug' => 'perfection',
                'name' => 'Perfection',
                'description' => 'Capai 100% progres pembelajaran untuk mendapatkan badge ini!',
            ],
            [
                'threshold' => null,
                'slug' => 'pointbreaker',
                'name' => 'Pointbreaker',
                'description' => 'Raih peringkat pertama di leaderboard mata kuliah untuk mendapatkan badge ini!',
                'rank_required' => 1,
            ],
            [
                'threshold' => null,
                'slug' => 'runner-up',
                'name' => 'Runner Up',
                'description' => 'Raih peringkat kedua di leaderboard mata kuliah untuk mendapatkan badge ini!',
                'rank_required' => 2,
            ],
            [
                'threshold' => null,
                'slug' => 'third-place',
                'name' => 'Third Place',
                'description' => 'Raih peringkat ketiga di leaderboard mata kuliah untuk mendapatkan badge ini!',
                'rank_required' => 3,
            ],
        ];
    }

    /**
     * @param  array<int, mixed>  $statuses
     * @return array{points: int, progress: array{completed: int, total: int, percent: int}, badges: array<int, array<string, mixed>>}
     */
    public function achievementSummary(array $statuses, ?int $rank = null): array
    {
        $progress = $this->summary($statuses);
        $completedStatuses = array_values(array_filter(
            $statuses,
            fn ($status): bool => is_array($status) && $this->statusIsComplete($status),
        ));
        usort($completedStatuses, function (array $left, array $right): int {
            $leftTime = (int) ($left['timecompleted'] ?? $left['completiontime'] ?? 0);
            $rightTime = (int) ($right['timecompleted'] ?? $right['completiontime'] ?? 0);

            return ($leftTime ?: PHP_INT_MAX) <=> ($rightTime ?: PHP_INT_MAX);
        });

        $badges = array_map(function (array $badge) use ($progress, $completedStatuses, $rank): array {
            $isLeaderboardBadge = isset($badge['rank_required']);
            $earned = $isLeaderboardBadge
                ? $rank === (int) $badge['rank_required']
                : $progress['total'] > 0 && $progress['percent'] >= (int) $badge['threshold'];
            $earnedAt = null;

            if ($earned && $progress['total'] > 0) {
                $requiredCompleted = $isLeaderboardBadge
                    ? count($completedStatuses)
                    : max(1, (int) ceil($progress['total'] * ((int) $badge['threshold'] / 100)));
                $earnedStatus = $completedStatuses[max(0, $requiredCompleted - 1)] ?? null;
                $earnedAt = is_array($earnedStatus)
                    ? (int) ($earnedStatus['timecompleted'] ?? $earnedStatus['completiontime'] ?? 0)
                    : null;
            }

            return array_merge($badge, [
                'earned' => $earned,
                'earned_at' => $earnedAt ?: null,
            ]);
        }, $this->badgeCatalog());

        return [
            'points' => $progress['completed'] * self::POINTS_PER_ACTIVITY,
            'progress' => $progress,
            'badges' => $badges,
        ];
    }

    /**
     * @param  array<int, mixed>  $beforeStatuses
     * @param  array<int, mixed>  $afterStatuses
     * @return array<string, mixed>
     */
    public function materialCompletionFeedback(array $beforeStatuses, array $afterStatuses, int $moduleId): array
    {
        return $this->activityCompletionFeedback($beforeStatuses, $afterStatuses, $moduleId);
    }

    /**
     * @param  array<int, mixed>  $beforeStatuses
     * @param  array<int, mixed>  $afterStatuses
     * @return array<string, mixed>
     */
    public function activityCompletionFeedback(array $beforeStatuses, array $afterStatuses, int $moduleId): array
    {
        $before = $this->summary($beforeStatuses);
        $after = $this->summary($afterStatuses);
        $newlyCompleted = ! $this->moduleIsComplete($beforeStatuses, $moduleId)
            && $this->moduleIsComplete($afterStatuses, $moduleId);

        return [
            'points_awarded' => $newlyCompleted ? self::POINTS_PER_ACTIVITY : 0,
            'total_points' => $after['completed'] * self::POINTS_PER_ACTIVITY,
            'progress' => $after,
            'badge' => $newlyCompleted ? $this->newlyEarnedBadge($before['percent'], $after['percent']) : null,
        ];
    }

    /**
     * @param  array<int, mixed>  $statuses
     * @return array{completed: int, total: int, percent: int}
     */
    public function summary(array $statuses): array
    {
        $validStatuses = array_values(array_filter($statuses, 'is_array'));
        $total = count($validStatuses);
        $completed = count(array_filter($validStatuses, fn (array $status): bool => $this->statusIsComplete($status)));

        return [
            'completed' => $completed,
            'total' => $total,
            'percent' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
        ];
    }

    /**
     * @param  array<int, mixed>  $statuses
     */
    public function moduleIsComplete(array $statuses, int $moduleId): bool
    {
        foreach ($statuses as $status) {
            if (! is_array($status)) {
                continue;
            }

            $statusModuleId = (int) ($status['cmid'] ?? $status['coursemoduleid'] ?? 0);
            if ($statusModuleId === $moduleId) {
                return $this->statusIsComplete($status);
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function newlyEarnedBadge(int $beforePercent, int $afterPercent): ?array
    {
        $badges = array_filter(
            $this->badgeCatalog(),
            fn (array $badge): bool => is_numeric($badge['threshold']),
        );

        $earned = null;
        foreach ($badges as $badge) {
            if ($beforePercent < $badge['threshold'] && $afterPercent >= $badge['threshold']) {
                $earned = $badge;
            }
        }

        return $earned;
    }

    protected function statusIsComplete(array $status): bool
    {
        $state = (int) ($status['state'] ?? $status['completionstate'] ?? 0);

        return in_array($state, [1, 2, 3], true)
            || (isset($status['completed']) && (bool) $status['completed']);
    }
}
