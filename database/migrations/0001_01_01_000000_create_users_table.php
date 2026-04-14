<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            return;
        }

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('employee_number', 20)->unique();
            $table->string('phone', 20)->nullable();
            $table->string('password');
            $table->unsignedSmallInteger('role')->default(UserRole::Employee->value);
            $table->boolean('is_active')->default(true);
            $table->string('telegram_id')->nullable()->unique();
            $table->timestamp('do_not_disturb_until')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
