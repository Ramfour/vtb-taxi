<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('temp_requests', function (Blueprint $table) {
            $table->boolean('is_planned')->default(false)->after('date_time');
            $table->date('window_date')->nullable()->after('is_planned');
        });
    }

    public function down(): void
    {
        Schema::table('temp_requests', function (Blueprint $table) {
            $table->dropColumn(['is_planned', 'window_date']);
        });
    }
};

