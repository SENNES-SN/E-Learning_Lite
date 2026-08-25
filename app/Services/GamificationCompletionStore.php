<?php

namespace App\Services;

use App\Models\GamificationActivityCompletion;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class GamificationCompletionStore
{
    private ?bool $available = null;

    /**
     * @return array<int, int>
     */
    public function completionsFor(int $moodleUserId, int $courseId): array
    {
        if (! $this->isAvailable() || $moodleUserId <= 0 || $courseId <= 0) {
            return [];
        }

        return GamificationActivityCompletion::query()
            ->where('moodle_user_id', $moodleUserId)
            ->where('course_id', $courseId)
            ->get(['module_id', 'completed_at'])
            ->mapWithKeys(fn (GamificationActivityCompletion $completion): array => [
                $completion->module_id => $completion->completed_at->timestamp,
            ])
            ->all();
    }

    public function remember(
        int $moodleUserId,
        int $courseId,
        int $moduleId,
        ?int $completedAt = null
    ): void {
        if (! $this->isAvailable() || $moodleUserId <= 0 || $courseId <= 0 || $moduleId <= 0) {
            return;
        }

        GamificationActivityCompletion::query()->firstOrCreate(
            [
                'moodle_user_id' => $moodleUserId,
                'course_id' => $courseId,
                'module_id' => $moduleId,
            ],
            [
                'completed_at' => Carbon::createFromTimestamp($completedAt ?: time()),
            ],
        );
    }

    /**
     * @param  array<int, mixed>  $statuses
     */
    public function rememberCompletedStatuses(int $moodleUserId, int $courseId, array $statuses): void
    {
        foreach ($statuses as $status) {
            if (! is_array($status) || ! $this->statusIsComplete($status)) {
                continue;
            }

            $moduleId = (int) ($status['cmid'] ?? $status['coursemoduleid'] ?? 0);
            $completedAt = (int) ($status['timecompleted'] ?? $status['completiontime'] ?? 0);
            $this->remember($moodleUserId, $courseId, $moduleId, $completedAt ?: null);
        }
    }

    private function isAvailable(): bool
    {
        return $this->available ??= Schema::hasTable('gamification_activity_completions');
    }

    private function statusIsComplete(array $status): bool
    {
        $state = (int) ($status['state'] ?? $status['completionstate'] ?? 0);

        return in_array($state, [1, 2, 3], true)
            || (isset($status['completed']) && (bool) $status['completed']);
    }
}
