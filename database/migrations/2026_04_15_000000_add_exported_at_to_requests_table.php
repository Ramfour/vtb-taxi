<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('requests')) {
            return;
        }

        Schema::table('requests', function (Blueprint $table) {
            if (! Schema::hasColumn('requests', 'exported_at')) {
                $table->timestamp('exported_at')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('requests')) {
            return;
        }

        Schema::table('requests', function (Blueprint $table) {
            if (Schema::hasColumn('requests', 'exported_at')) {
                $table->dropColumn('exported_at');
            }
        });
    }
};

