<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->string('employee_number');

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('used_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->boolean('is_used')->default(false);
            $table->timestamp('used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['employee_number', 'is_used']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
