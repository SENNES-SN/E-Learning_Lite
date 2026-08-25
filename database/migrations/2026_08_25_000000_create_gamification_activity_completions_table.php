<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gamification_activity_completions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('moodle_user_id');
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('module_id');
            $table->timestamp('completed_at');
            $table->timestamps();

            $table->unique(
                ['moodle_user_id', 'course_id', 'module_id'],
                'gamification_completions_user_course_module_unique'
            );
            $table->index(['course_id', 'moodle_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gamification_activity_completions');
    }
};
