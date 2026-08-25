<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GamificationActivityCompletion extends Model
{
    protected $fillable = [
        'moodle_user_id',
        'course_id',
        'module_id',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'moodle_user_id' => 'integer',
            'course_id' => 'integer',
            'module_id' => 'integer',
            'completed_at' => 'datetime',
        ];
    }
}
