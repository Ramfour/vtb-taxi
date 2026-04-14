<?php

use App\Enums\RequestStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('temp_requests')) {
            return;
        }

        Schema::create('temp_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('full_name');
            $table->string('phone', 20);
            $table->text('address_raw');
            $table->text('address_norm')->nullable();
            $table->decimal('lat', 10, 8)->nullable();
            $table->decimal('lon', 11, 8)->nullable();
            $table->dateTime('date_time');
            $table->unsignedSmallInteger('status')->default(RequestStatus::Pending->value);

            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('manager_comment')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
            $table->index('status');
            $table->index('date_time');
            $table->index(['status', 'date_time']);
            $table->unique(['user_id', 'date_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temp_requests');
    }
};
