<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('employee_number')->unique();
            $table->unsignedBigInteger('telegram_id')->nullable()->unique();
            $table->string('full_name');
            $table->string('email')->nullable();
            $table->string('phone', 20);
            $table->text('default_address')->nullable();
            $table->string('password')->nullable();
            $table->unsignedSmallInteger('role')->default(UserRole::Employee->value);
            $table->boolean('is_active')->default(true);
            $table->timestamp('do_not_disturb_until')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index('role');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
