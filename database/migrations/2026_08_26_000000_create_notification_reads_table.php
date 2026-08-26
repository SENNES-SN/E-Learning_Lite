<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_reads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('moodle_user_id');
            $table->char('notification_key', 40);
            $table->timestamp('read_at');
            $table->timestamps();

            $table->unique(
                ['moodle_user_id', 'notification_key'],
                'notification_reads_user_key_unique'
            );
            $table->index(['moodle_user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_reads');
    }
};
