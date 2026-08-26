<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationRead extends Model
{
    protected $fillable = [
        'moodle_user_id',
        'notification_key',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'moodle_user_id' => 'integer',
            'read_at' => 'datetime',
        ];
    }
}
