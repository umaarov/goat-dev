<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // epoch seconds; web sessions issued before it are no longer valid (works with any session driver)
    public function up(): void
    {
        if (Schema::hasColumn('users', 'sessions_invalid_before')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('sessions_invalid_before')->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'sessions_invalid_before')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('sessions_invalid_before');
            });
        }
    }
};
