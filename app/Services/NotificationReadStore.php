<?php

namespace App\Services;

use App\Models\NotificationRead;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class NotificationReadStore
{
    private ?bool $available = null;

    /**
     * @param  array<int, string>  $notificationKeys
     * @return array<int, string>
     */
    public function readKeysFor(int $moodleUserId, array $notificationKeys): array
    {
        $notificationKeys = array_values(array_unique(array_filter($notificationKeys)));
        if (! $this->isAvailable() || $moodleUserId <= 0 || $notificationKeys === []) {
            return [];
        }

        return NotificationRead::query()
            ->where('moodle_user_id', $moodleUserId)
            ->whereIn('notification_key', $notificationKeys)
            ->pluck('notification_key')
            ->all();
    }

    /**
     * @param  array<int, string>  $notificationKeys
     */
    public function rememberMany(int $moodleUserId, array $notificationKeys): void
    {
        $notificationKeys = array_values(array_unique(array_filter($notificationKeys)));
        if (! $this->isAvailable() || $moodleUserId <= 0 || $notificationKeys === []) {
            return;
        }

        $now = Carbon::now();
        $rows = array_map(fn (string $notificationKey): array => [
            'moodle_user_id' => $moodleUserId,
            'notification_key' => $notificationKey,
            'read_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ], $notificationKeys);

        NotificationRead::query()->upsert(
            $rows,
            ['moodle_user_id', 'notification_key'],
            ['read_at', 'updated_at'],
        );

        NotificationRead::query()
            ->where('moodle_user_id', $moodleUserId)
            ->where('read_at', '<', Carbon::now()->subDays(180))
            ->delete();
    }

    private function isAvailable(): bool
    {
        return $this->available ??= Schema::hasTable('notification_reads');
    }
}
