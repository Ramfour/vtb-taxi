<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('commute_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            // Bitmask: 1<<0=Mon ... 1<<6=Sun.
            $table->unsignedSmallInteger('days_mask')->default(0);
            $table->time('default_time');
            $table->text('default_address_raw')->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commute_schedules');
    }
};

