<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // If the migration previously failed after creating the table, clean it up so reruns succeed.
        if (Schema::hasTable('commute_schedule_exceptions')) {
            Schema::drop('commute_schedule_exceptions');
        }

        Schema::create('commute_schedule_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commute_schedule_id')
                ->constrained('commute_schedules')
                ->cascadeOnDelete();
            // Window date D (22:00–06:00 belongs to D).
            $table->date('window_date');
            $table->boolean('is_skipped')->default(false);
            $table->time('time_override')->nullable();
            $table->text('address_override_raw')->nullable();
            $table->timestamps();

            // MySQL has a 64-char identifier limit; keep index name short.
            $table->unique(['commute_schedule_id', 'window_date'], 'cse_sched_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commute_schedule_exceptions');
    }
};
